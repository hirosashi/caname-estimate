<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Db;

/**
 * 明細書（Excel）の計算をそのまま再現する。
 *
 *  材料数   = ROUNDUP(実数 × (1 + ロス率) ÷ (長 × 巾), 0)
 *  材料代   = 材料数 × 材料単価
 *  人工数   = 実数 × 歩掛
 *  人工単価 = 一律人工設定 があればそれ、なければ品目の人工単価
 *  手間代   = ROUNDUP(人工数 × 人工単価, 0)
 *  原単価   = (材料代 + 手間代) ÷ 実数
 *  合算原単価 = 同じ見積行にまとめた行の原単価の合計
 *  掛率     = 行別掛率 があればそれ、なければ一律掛率
 *  見積単価 = ROUNDUP(合算原単価 ÷ 掛率, -2)   … 100円単位の切上げ
 *  見積金額 = 実数 × 見積単価
 */
final class Estimate
{
    public static function roundUp(float $v, int $digits): float
    {
        $m = 10 ** $digits;
        $x = $v * $m;
        $r = abs($x - round($x)) < 1e-9 ? round($x) : ($x >= 0 ? ceil($x) : floor($x));
        return $r / $m;
    }

    /** @return array<string, mixed> */
    public static function project(int $projectId): array
    {
        $p = Db::row('SELECT * FROM projects WHERE id = ?', [$projectId]);
        if ($p === null) {
            throw new \RuntimeException('案件がありません');
        }
        return $p;
    }

    /**
     * 案件全体を計算して返す。
     * @param array<string, mixed> $project
     * @return array{sections: array<int, array<string, mixed>>, material_total: float, labor_count_total: float, labor_total: float, quote_total: float}
     */
    public static function calc(array $project): array
    {
        $pid = (int)$project['id'];
        $uniformLabor = $project['uniform_labor_price'] === null ? null : (float)$project['uniform_labor_price'];
        $uniformRate = (float)$project['uniform_rate'];

        $sections = Db::rows('SELECT * FROM project_sections WHERE project_id = ? ORDER BY sort_no, id', [$pid]);
        $lines = Db::rows(
            'SELECT l.* FROM project_lines l JOIN project_sections s ON s.id = l.section_id WHERE s.project_id = ? ORDER BY l.section_id, l.sort_no, l.id',
            [$pid]
        );
        $bySection = [];
        foreach ($lines as $l) {
            $bySection[(int)$l['section_id']][] = $l;
        }

        $materialTotal = 0.0;
        $laborCountTotal = 0.0;
        $laborTotal = 0.0;
        $quoteTotal = 0.0;
        $out = [];
        foreach ($sections as $s) {
            $sec = self::calcSection($s, $bySection[(int)$s['id']] ?? [], $uniformLabor, $uniformRate);
            $materialTotal += $sec['material_total'];
            $laborCountTotal += $sec['labor_count_total'];
            $laborTotal += $sec['labor_total'];
            $quoteTotal += $sec['quote_total'];
            $out[] = $sec;
        }
        return [
            'sections' => $out,
            'material_total' => $materialTotal,
            'labor_count_total' => $laborCountTotal,
            'labor_total' => $laborTotal,
            'quote_total' => $quoteTotal,
        ];
    }

    /**
     * @param array<string, mixed> $section
     * @param array<int, array<string, mixed>> $lines
     * @return array<string, mixed>
     */
    public static function calcSection(array $section, array $lines, ?float $uniformLabor, float $uniformRate): array
    {
        $loss = (float)$section['loss_rate'];
        $calc = [];
        foreach ($lines as $l) {
            $qty = $l['quantity'] === null ? null : (float)$l['quantity'];
            $len = (float)($l['length'] ?? 0);
            $wid = (float)($l['width'] ?? 0);
            $area = ($len > 0 ? $len : 1.0) * ($wid > 0 ? $wid : 1.0);
            $matPrice = (float)($l['material_price'] ?? 0);
            $laborRate = (float)($l['labor_rate'] ?? 0);
            $laborPrice = $uniformLabor ?? (float)($l['labor_unit_price'] ?? 0);

            $matCount = $qty === null ? null : self::roundUp($qty * (1 + $loss) / $area, 0);
            $matCost = $matCount === null ? null : $matCount * $matPrice;
            $laborCount = $qty === null ? null : $qty * $laborRate;
            $laborCost = $laborCount === null ? null : self::roundUp($laborCount * $laborPrice, 0);
            $unitCost = ($qty === null || $qty == 0.0) ? null : (($matCost ?? 0) + ($laborCost ?? 0)) / $qty;

            $l['mat_count'] = $matCount;
            $l['mat_cost'] = $matCost;
            $l['labor_count'] = $laborCount;
            $l['labor_price'] = $laborPrice;
            $l['labor_cost'] = $laborCost;
            $l['unit_cost'] = $unitCost;
            $l['rate'] = $l['rate_override'] === null || (float)$l['rate_override'] <= 0 ? $uniformRate : (float)$l['rate_override'];
            $calc[(int)$l['id']] = $l;
        }

        // 合算: 見積行ごとに原単価を集める
        $merged = [];
        foreach ($calc as $id => $l) {
            $target = null;
            if ((int)$l['is_quote'] === 1) {
                $target = $id;
            } elseif ($l['merge_into_line_id'] !== null && isset($calc[(int)$l['merge_into_line_id']]) && (int)$calc[(int)$l['merge_into_line_id']]['is_quote'] === 1) {
                $target = (int)$l['merge_into_line_id'];
            }
            $calc[$id]['merge_target'] = $target;
            if ($target !== null && $l['unit_cost'] !== null) {
                $merged[$target] = ($merged[$target] ?? 0.0) + $l['unit_cost'];
            }
        }

        $matTotal = 0.0;
        $laborCountTotal = 0.0;
        $laborTotal = 0.0;
        $quoteTotal = 0.0;
        $printRows = [];
        foreach ($calc as $id => $l) {
            $matTotal += (float)($l['mat_cost'] ?? 0);
            $laborCountTotal += (float)($l['labor_count'] ?? 0);
            $laborTotal += (float)($l['labor_cost'] ?? 0);
            $calc[$id]['merged_unit_cost'] = null;
            $calc[$id]['quote_price'] = null;
            $calc[$id]['quote_amount'] = null;
            if ((int)$l['is_quote'] !== 1) {
                continue;
            }
            $mu = $merged[$id] ?? null;
            $rate = (float)$l['rate'];
            $qp = ($mu === null || $rate <= 0) ? null : self::roundUp($mu / $rate, -2);
            $qty = $l['quantity'] === null ? null : (float)$l['quantity'];
            $amount = ($qp === null || $qty === null) ? null : $qp * $qty;
            $calc[$id]['merged_unit_cost'] = $mu;
            $calc[$id]['quote_price'] = $qp;
            $calc[$id]['quote_amount'] = $amount;
            $quoteTotal += (float)($amount ?? 0);

            $name = $l['print_name'] ?? $l['name'];
            $printRows[] = [
                'line_id' => $id,
                'name' => $name,
                'material' => $l['print_material'] ?? $l['material'],
                'length' => $l['length'],
                'width' => $l['width'],
                'thickness' => $l['thickness'],
                'unit' => $l['area_unit'],
                'quantity' => $qty,
                'unit_price' => $qp,
                'amount' => $amount,
                'remarks' => $l['remarks'],
            ];
        }

        $section['lines'] = array_values($calc);
        $section['print_rows'] = $printRows;
        $section['material_total'] = $matTotal;
        $section['labor_count_total'] = $laborCountTotal;
        $section['labor_total'] = $laborTotal;
        $section['quote_total'] = $quoteTotal;
        return $section;
    }

    /**
     * 営業実行予算（サマリー）の計算。
     * @param array<string, mixed> $project
     * @param array{sections: array<int, array<string, mixed>>, material_total: float, labor_count_total: float, labor_total: float, quote_total: float} $est
     * @return array<string, mixed>
     */
    public static function summary(array $project, array $est): array
    {
        $pid = (int)$project['id'];
        $extras = [];
        for ($no = 1; $no <= 4; $no++) {
            $extras[$no] = (float)(Db::value(
                'SELECT COALESCE(SUM(COALESCE(l.quantity,0) * COALESCE(l.unit_price,0)),0) FROM extra_work_lines l JOIN extra_works w ON w.id = l.extra_work_id WHERE w.project_id = ? AND w.no = ?',
                [$pid, $no]
            ) ?? 0);
        }
        $planned = $project['planned_contract_amount'] === null ? null : (float)$project['planned_contract_amount'];
        $base = $planned ?? 0.0;
        $fee = $base * (float)$project['fee_rate'];
        $reserve = $base * (float)$project['reserve_rate'];
        $disposal = (float)($project['disposal_amount'] ?? 0);
        $siteExp = (float)($project['site_expense_amount'] ?? 0);
        $subtotal = $est['material_total'] + $est['labor_total'] + $extras[1] + $extras[2] + $extras[3] + $disposal + $fee + $reserve + $siteExp;
        $design = $base * (float)$project['design_rate'];
        $office = $base * (float)$project['office_rate'];
        $direct = $subtotal + $design + $office;

        $quoteSub = $est['quote_total'];
        $general = $quoteSub * (float)$project['general_admin_rate'];
        $site = $quoteSub * (float)$project['site_admin_rate'];
        $quoteTotal = $quoteSub + $general + $site;

        return [
            'extras' => $extras,
            'fee' => $fee,
            'reserve' => $reserve,
            'disposal' => $disposal,
            'site_expense' => $siteExp,
            'cost_subtotal' => $subtotal,
            'design' => $design,
            'office' => $office,
            'direct_cost' => $direct,
            'net' => [
                '20' => self::roundUp($direct / 0.8, -4),
                '15' => self::roundUp($direct / 0.85, -4),
                '10' => self::roundUp($direct / 0.9, -4),
                '5' => self::roundUp($direct / 0.95, -4),
            ],
            'expected_quote' => self::roundUp($direct / 0.55, -4),
            'planned' => $planned,
            'mq_amount' => $planned === null ? null : $planned - $direct,
            'mq_rate' => ($planned === null || $planned == 0.0) ? null : ($planned - $direct) / $planned,
            'expected_ratio' => ($planned === null || self::roundUp($direct / 0.55, -4) == 0.0) ? null : $planned / self::roundUp($direct / 0.55, -4),
            'quote_subtotal' => $quoteSub,
            'general_admin' => $general,
            'site_admin' => $site,
            'quote_total' => $quoteTotal,
        ];
    }
}
