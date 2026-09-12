<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Clock;
use App\Core\Csrf;
use App\Core\Db;
use App\Core\OperationLog;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;

/** 経理実行予算（材料費・外注費・経費の営業予算／工事予算） */
final class BudgetController extends Base
{
    public const GROUPS = ['material' => '材料費', 'subcontract' => '外注費', 'expense' => '経費'];

    public static function index(): void
    {
        Auth::requireLogin();
        $p = self::currentProject();
        View::render('budget/index', ['title' => '経理実行予算', 'project' => $p] + self::data($p));
    }

    public static function save(): void
    {
        Auth::requireLogin();
        Csrf::verify();
        Auth::requireCan('budget', '/budget');
        $p = self::currentProject();
        $sales = self::postArray('sales');
        $work = self::postArray('work');
        $hdr = [];
        foreach (['estimate_amount', 'contract_amount', 'extra1_amount', 'extra2_amount'] as $f) {
            $hdr[$f] = Validator::toNum($_POST[$f] ?? null);
        }
        foreach (['insurance_rate', 'supervision_rate', 'budget_office_rate', 'tax_rate'] as $f) {
            $v = Validator::toNum($_POST[$f] ?? null);
            if ($v === null || $v < 0 || $v > 1) {
                Session::flash('error', '率は 0〜1 の小数で入力してください（例 0.5% → 0.005）。');
                self::back('/budget', (int)$p['id']);
            }
            $hdr[$f] = $v;
        }
        $hdr['budget_date'] = Validator::toDate($_POST['budget_date'] ?? null);
        $hdr['contract_date'] = Validator::toDate($_POST['contract_date'] ?? null);
        Db::tx(static function () use ($p, $sales, $work, $hdr): void {
            $hdr['updated_at'] = Clock::nowStr();
            $sets = implode(', ', array_map(static fn(string $k): string => "$k = :$k", array_keys($hdr)));
            Db::exec("UPDATE projects SET $sets WHERE id = :id", $hdr + ['id' => (int)$p['id']]);
            $items = Db::rows('SELECT id FROM budget_items');
            foreach ($items as $it) {
                $id = (int)$it['id'];
                $s = Validator::toNum($sales[$id] ?? null);
                $w = Validator::toNum($work[$id] ?? null);
                if ($s === null && $w === null) {
                    Db::exec('DELETE FROM project_budgets WHERE project_id = ? AND budget_item_id = ?', [(int)$p['id'], $id]);
                    continue;
                }
                Db::exec(
                    'INSERT INTO project_budgets (project_id, budget_item_id, sales_amount, work_amount) VALUES (?,?,?,?)
                     ON DUPLICATE KEY UPDATE sales_amount = VALUES(sales_amount), work_amount = VALUES(work_amount)',
                    [(int)$p['id'], $id, $s, $w]
                );
            }
        });
        OperationLog::write('update', 'budget', (string)$p['id'], '経理実行予算 保存');
        Session::flash('ok', '保存しました。');
        self::back('/budget', (int)$p['id']);
    }

    /**
     * 経理実行予算の計算結果。
     * @param array<string, mixed> $p
     * @return array<string, mixed>
     */
    public static function data(array $p): array
    {
        $items = Db::rows(
            'SELECT b.*, pb.sales_amount, pb.work_amount FROM budget_items b
             LEFT JOIN project_budgets pb ON pb.budget_item_id = b.id AND pb.project_id = ?
             WHERE b.is_active = 1 ORDER BY b.group_key, b.sort_no, b.id',
            [(int)$p['id']]
        );
        $groups = ['material' => [], 'subcontract' => [], 'expense' => []];
        $sub = ['material' => [0.0, 0.0], 'subcontract' => [0.0, 0.0], 'expense' => [0.0, 0.0]];
        foreach ($items as $it) {
            $g = (string)$it['group_key'];
            $groups[$g][] = $it;
            $sub[$g][0] += (float)($it['sales_amount'] ?? 0);
            $sub[$g][1] += (float)($it['work_amount'] ?? 0);
        }
        $contract = (float)($p['contract_amount'] ?? 0) + (float)($p['extra1_amount'] ?? 0) + (float)($p['extra2_amount'] ?? 0);
        $tax = $contract * (float)$p['tax_rate'];
        $withTax = $contract + $tax;
        $insurance = $withTax * (float)$p['insurance_rate'];
        $supervision = $contract * (float)$p['supervision_rate'];
        $office = $contract * (float)$p['budget_office_rate'];
        $direct = [];
        $mq = [];
        foreach ([0, 1] as $i) {
            $direct[$i] = $sub['material'][$i] + $sub['subcontract'][$i] + $sub['expense'][$i] + $insurance + $supervision + $office;
            $mq[$i] = $contract - $direct[$i];
        }
        return [
            'groups' => $groups,
            'groupLabels' => self::GROUPS,
            'sub' => $sub,
            'contract_sum' => $contract,
            'tax' => $tax,
            'with_tax' => $withTax,
            'insurance' => $insurance,
            'supervision' => $supervision,
            'office' => $office,
            'direct' => $direct,
            'mq' => $mq,
            'mq_rate' => $contract > 0 ? [$mq[0] / $contract, $mq[1] / $contract] : [null, null],
        ];
    }
}
