<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Auth;
use App\Core\Clock;
use App\Core\Csrf;
use App\Core\Db;
use App\Core\OperationLog;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;
use App\Services\Estimate;

final class EstimateController extends Base
{
    public const MAX_SECTIONS = 15;
    public const MAX_LINES = 27;

    public static function index(): void
    {
        Auth::requireLogin();
        $p = self::currentProject();
        $est = Estimate::calc($p);
        $open = App::intParam('section', 0);
        if ($open === 0 && $est['sections'] !== []) {
            $open = (int)$est['sections'][0]['id'];
        }
        View::render('estimate/index', [
            'wide' => true,
            'title' => '明細書',
            'project' => $p,
            'est' => $est,
            'open' => $open,
            'categories' => Db::rows('SELECT id, name FROM categories ORDER BY sort_no, id'),
            'maxLines' => self::MAX_LINES,
        ]);
    }

    public static function saveSettings(): void
    {
        Auth::requireLogin();
        Csrf::verify();
        Auth::requireCan('estimate', '/estimate');
        $p = self::currentProject();
        $labor = Validator::toNum($_POST['uniform_labor_price'] ?? null);
        $rate = Validator::toNum($_POST['uniform_rate'] ?? null);
        if ($rate === null || $rate <= 0 || $rate > 1) {
            Session::flash('error', '一律掛率は 0 より大きく 1 以下で入力してください（例 0.8）。');
            self::back('/estimate', (int)$p['id']);
        }
        Db::exec('UPDATE projects SET uniform_labor_price = ?, uniform_rate = ?, updated_at = ? WHERE id = ?', [$labor, $rate, Clock::nowStr(), (int)$p['id']]);
        OperationLog::write('update', 'estimate', (string)$p['id'], "設定 一律人工={$labor} 掛率={$rate}");
        Session::flash('ok', '設定を保存しました。');
        self::back('/estimate', (int)$p['id']);
    }

    public static function addSection(): void
    {
        Auth::requireLogin();
        Csrf::verify();
        Auth::requireCan('estimate', '/estimate');
        $p = self::currentProject();
        $cnt = (int)Db::value('SELECT COUNT(*) FROM project_sections WHERE project_id = ?', [(int)$p['id']]);
        if ($cnt >= self::MAX_SECTIONS) {
            Session::flash('error', '工事項目は ' . self::MAX_SECTIONS . ' 件までです。');
            self::back('/estimate', (int)$p['id']);
        }
        $name = Validator::toStr($_POST['name'] ?? null) ?? '【新規工事項目】';
        Db::exec('INSERT INTO project_sections (project_id, sort_no, name, loss_rate) VALUES (?,?,?,?)', [(int)$p['id'], $cnt + 1, mb_substr($name, 0, 100), 0.07]);
        $sid = Db::lastId();
        OperationLog::write('create', 'estimate', (string)$sid, '工事項目追加: ' . $name);
        self::back('/estimate', (int)$p['id'], '&section=' . $sid);
    }

    public static function saveSection(): void
    {
        Auth::requireLogin();
        Csrf::verify();
        Auth::requireCan('estimate', '/estimate');
        $p = self::currentProject();
        $s = self::section($p);
        $name = Validator::toStr($_POST['name'] ?? null) ?? '';
        $loss = Validator::toNum($_POST['loss_rate'] ?? null);
        if ($name === '' || $loss === null || $loss < 0 || $loss > 1) {
            Session::flash('error', '工事項目名とロス率（0〜1、例 0.07）を入力してください。');
            self::back('/estimate', (int)$p['id'], '&section=' . $s['id']);
        }
        Db::exec('UPDATE project_sections SET name = ?, loss_rate = ? WHERE id = ?', [mb_substr($name, 0, 100), $loss, (int)$s['id']]);
        self::touch((int)$p['id']);
        OperationLog::write('update', 'estimate', (string)$s['id'], '工事項目更新: ' . $name);
        Session::flash('ok', '工事項目を保存しました。');
        self::back('/estimate', (int)$p['id'], '&section=' . $s['id']);
    }

    public static function deleteSection(): void
    {
        Auth::requireLogin();
        Csrf::verify();
        Auth::requireCan('estimate', '/estimate');
        $p = self::currentProject();
        $s = self::section($p);
        Db::exec('DELETE FROM project_sections WHERE id = ?', [(int)$s['id']]);
        self::renumber((int)$p['id']);
        OperationLog::write('delete', 'estimate', (string)$s['id'], '工事項目削除: ' . $s['name']);
        Session::flash('ok', '工事項目を削除しました。');
        self::back('/estimate', (int)$p['id']);
    }

    public static function moveSection(): void
    {
        Auth::requireLogin();
        Csrf::verify();
        Auth::requireCan('estimate', '/estimate');
        $p = self::currentProject();
        $s = self::section($p);
        $dir = App::param('dir') === 'up' ? -1 : 1;
        $all = Db::rows('SELECT id FROM project_sections WHERE project_id = ? ORDER BY sort_no, id', [(int)$p['id']]);
        $ids = array_map(static fn(array $r): int => (int)$r['id'], $all);
        $i = array_search((int)$s['id'], $ids, true);
        if ($i !== false && isset($ids[$i + $dir])) {
            [$ids[$i], $ids[$i + $dir]] = [$ids[$i + $dir], $ids[$i]];
            foreach ($ids as $n => $id) {
                Db::exec('UPDATE project_sections SET sort_no = ? WHERE id = ?', [$n + 1, $id]);
            }
        }
        self::back('/estimate', (int)$p['id'], '&section=' . $s['id']);
    }

    /** 行の一括保存（1工事項目分） */
    public static function saveLines(): void
    {
        Auth::requireLogin();
        Csrf::verify();
        Auth::requireCan('estimate', '/estimate');
        $p = self::currentProject();
        $s = self::section($p);
        $sid = (int)$s['id'];
        $rows = self::postArray('rows');

        Db::tx(static function () use ($rows, $sid, $p): void {
            $existing = Db::rows('SELECT * FROM project_lines WHERE section_id = ? ORDER BY sort_no, id', [$sid]);
            $byId = [];
            foreach ($existing as $e) {
                $byId[(int)$e['id']] = $e;
            }
            $keep = [];
            $mergeReq = []; // 新ID => 合算先の行番号(1始まり)
            $noToId = [];
            $sort = 0;
            foreach ($rows as $r) {
                if (!is_array($r)) {
                    continue;
                }
                $code = Validator::toStr($r['item_code'] ?? null);
                $name = Validator::toStr($r['name'] ?? null);
                $qty = Validator::toNum($r['quantity'] ?? null);
                $printName = Validator::toStr($r['print_name'] ?? null);
                $remarks = Validator::toStr($r['remarks'] ?? null);
                if ($code === null && $name === null && $qty === null && $printName === null && $remarks === null) {
                    continue;
                }
                $sort++;
                if ($sort > self::MAX_LINES) {
                    break;
                }
                $lineId = (int)($r['id'] ?? 0);
                $old = $byId[$lineId] ?? null;
                $item = $code === null ? null : Db::row('SELECT * FROM items WHERE code = ? AND is_active = 1', [$code]);
                $codeChanged = $old === null || (string)($old['item_code'] ?? '') !== (string)($code ?? '');

                $data = [
                    'section_id' => $sid,
                    'sort_no' => $sort,
                    'item_id' => $item === null ? ($codeChanged ? null : $old['item_id']) : (int)$item['id'],
                    'item_code' => $code,
                    'quantity' => $qty,
                    'is_quote' => (isset($r['is_quote']) && (string)$r['is_quote'] === '1') ? 1 : 0,
                    'merge_into_line_id' => null,
                    'rate_override' => Validator::toNum($r['rate_override'] ?? null),
                    'print_name' => $printName === null ? null : mb_substr($printName, 0, 200),
                    'print_material' => Validator::toStr($r['print_material'] ?? null),
                    'remarks' => $remarks === null ? null : mb_substr($remarks, 0, 200),
                ];
                if ($item !== null && $codeChanged) {
                    // マスタから単価等を写す（以後はスナップショット）
                    $data += [
                        'name' => $item['name'], 'material' => $item['material'],
                        'length' => $item['length'], 'width' => $item['width'], 'thickness' => $item['thickness'],
                        'area_unit' => $item['area_unit'], 'use_unit' => $item['use_unit'],
                        'material_price' => $item['material_price'], 'labor_rate' => $item['labor_rate'], 'labor_unit_price' => $item['labor_unit_price'],
                    ];
                } elseif ($old !== null && !$codeChanged) {
                    $data += [
                        'name' => $name ?? $old['name'], 'material' => Validator::toStr($r['material'] ?? null) ?? $old['material'],
                        'length' => Validator::toNum($r['length'] ?? null) ?? $old['length'], 'width' => Validator::toNum($r['width'] ?? null) ?? $old['width'],
                        'thickness' => Validator::toNum($r['thickness'] ?? null), 'area_unit' => Validator::toStr($r['area_unit'] ?? null) ?? $old['area_unit'],
                        'use_unit' => $old['use_unit'],
                        'material_price' => Validator::toNum($r['material_price'] ?? null), 'labor_rate' => Validator::toNum($r['labor_rate'] ?? null),
                        'labor_unit_price' => Validator::toNum($r['labor_unit_price'] ?? null),
                    ];
                } else {
                    // 手入力行（マスタ外）
                    $data += [
                        'name' => $name, 'material' => Validator::toStr($r['material'] ?? null),
                        'length' => Validator::toNum($r['length'] ?? null), 'width' => Validator::toNum($r['width'] ?? null),
                        'thickness' => Validator::toNum($r['thickness'] ?? null), 'area_unit' => Validator::toStr($r['area_unit'] ?? null), 'use_unit' => null,
                        'material_price' => Validator::toNum($r['material_price'] ?? null), 'labor_rate' => Validator::toNum($r['labor_rate'] ?? null),
                        'labor_unit_price' => Validator::toNum($r['labor_unit_price'] ?? null),
                    ];
                }
                if ($old !== null) {
                    $sets = implode(', ', array_map(static fn(string $k): string => "$k = :$k", array_keys($data)));
                    $data['id'] = $lineId;
                    Db::exec("UPDATE project_lines SET $sets WHERE id = :id AND section_id = :section_id2", $data + ['section_id2' => $sid]);
                    $newId = $lineId;
                } else {
                    $k = array_keys($data);
                    Db::exec('INSERT INTO project_lines (' . implode(', ', $k) . ') VALUES (:' . implode(', :', $k) . ')', $data);
                    $newId = Db::lastId();
                }
                $keep[] = $newId;
                $noToId[$sort] = $newId;
                $mergeNo = (int)($r['merge_no'] ?? 0);
                if ($data['is_quote'] === 0 && $mergeNo > 0) {
                    $mergeReq[$newId] = $mergeNo;
                }
            }
            // 削除された行
            $delete = array_diff(array_keys($byId), $keep);
            if ($delete !== []) {
                $in = implode(',', array_fill(0, count($delete), '?'));
                Db::exec("UPDATE project_lines SET merge_into_line_id = NULL WHERE merge_into_line_id IN ($in)", array_values($delete));
                Db::exec("DELETE FROM project_lines WHERE section_id = ? AND id IN ($in)", [$sid, ...array_values($delete)]);
            }
            foreach ($mergeReq as $id => $no) {
                if (isset($noToId[$no]) && $noToId[$no] !== $id) {
                    Db::exec('UPDATE project_lines SET merge_into_line_id = ? WHERE id = ?', [$noToId[$no], $id]);
                }
            }
            Db::exec('UPDATE projects SET updated_at = ? WHERE id = ?', [Clock::nowStr(), (int)$p['id']]);
        });
        OperationLog::write('update', 'estimate', (string)$sid, '明細行保存: ' . $s['name']);
        Session::flash('ok', '明細を保存しました。');
        self::back('/estimate', (int)$p['id'], '&section=' . $sid);
    }

    /** 単価マスタの現在値で行を更新し直す */
    public static function refreshPrices(): void
    {
        Auth::requireLogin();
        Csrf::verify();
        Auth::requireCan('estimate', '/estimate');
        $p = self::currentProject();
        $s = self::section($p);
        $n = Db::exec(
            'UPDATE project_lines l JOIN items i ON i.id = l.item_id
             SET l.name = i.name, l.material = i.material, l.length = i.length, l.width = i.width, l.thickness = i.thickness,
                 l.area_unit = i.area_unit, l.use_unit = i.use_unit, l.material_price = i.material_price, l.labor_rate = i.labor_rate, l.labor_unit_price = i.labor_unit_price
             WHERE l.section_id = ?',
            [(int)$s['id']]
        );
        self::touch((int)$p['id']);
        OperationLog::write('update', 'estimate', (string)$s['id'], "単価マスタ再取得 {$n}行");
        Session::flash('ok', "単価マスタの現在値で {$n} 行を更新しました。");
        self::back('/estimate', (int)$p['id'], '&section=' . $s['id']);
    }

    /** @param array<string, mixed> $p @return array<string, mixed> */
    private static function section(array $p): array
    {
        $sid = App::intParam('section_id', 0);
        $s = Db::row('SELECT * FROM project_sections WHERE id = ? AND project_id = ?', [$sid, (int)$p['id']]);
        if ($s === null) {
            Session::flash('error', '工事項目が見つかりません。');
            self::back('/estimate', (int)$p['id']);
        }
        return $s;
    }

    private static function renumber(int $projectId): void
    {
        $all = Db::rows('SELECT id FROM project_sections WHERE project_id = ? ORDER BY sort_no, id', [$projectId]);
        foreach ($all as $n => $r) {
            Db::exec('UPDATE project_sections SET sort_no = ? WHERE id = ?', [$n + 1, (int)$r['id']]);
        }
        self::touch($projectId);
    }

    private static function touch(int $projectId): void
    {
        Db::exec('UPDATE projects SET updated_at = ? WHERE id = ?', [Clock::nowStr(), $projectId]);
    }
}
