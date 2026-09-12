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

final class ExtraWorkController extends Base
{
    public const MAX_LINES = 30;

    public static function index(): void
    {
        Auth::requireLogin();
        $p = self::currentProject();
        $no = max(1, min(4, App::intParam('no', 1)));
        $works = [];
        for ($i = 1; $i <= 4; $i++) {
            $works[$i] = self::work((int)$p['id'], $i);
        }
        $lines = Db::rows('SELECT * FROM extra_work_lines WHERE extra_work_id = ? ORDER BY sort_no, id', [(int)$works[$no]['id']]);
        $total = 0.0;
        foreach ($lines as &$l) {
            $l['amount'] = ($l['quantity'] === null || $l['unit_price'] === null) ? null : (float)$l['quantity'] * (float)$l['unit_price'];
            $total += (float)($l['amount'] ?? 0);
        }
        unset($l);
        $totals = [];
        foreach ($works as $i => $w) {
            $totals[$i] = (float)(Db::value('SELECT COALESCE(SUM(COALESCE(quantity,0)*COALESCE(unit_price,0)),0) FROM extra_work_lines WHERE extra_work_id = ?', [(int)$w['id']]) ?? 0);
        }
        View::render('extra/index', [
            'title' => 'その他工事',
            'project' => $p,
            'no' => $no,
            'works' => $works,
            'work' => $works[$no],
            'lines' => $lines,
            'total' => $total,
            'totals' => $totals,
            'maxLines' => self::MAX_LINES,
        ]);
    }

    public static function save(): void
    {
        Auth::requireLogin();
        Csrf::verify();
        Auth::requireCan('estimate', '/extra');
        $p = self::currentProject();
        $no = max(1, min(4, App::intParam('no', 1)));
        $w = self::work((int)$p['id'], $no);
        $title = Validator::toStr($_POST['title'] ?? null);
        $rows = self::postArray('rows');
        Db::tx(static function () use ($w, $title, $rows, $p): void {
            Db::exec('UPDATE extra_works SET title = ? WHERE id = ?', [$title === null ? null : mb_substr($title, 0, 100), (int)$w['id']]);
            Db::exec('DELETE FROM extra_work_lines WHERE extra_work_id = ?', [(int)$w['id']]);
            $sort = 0;
            foreach ($rows as $r) {
                if (!is_array($r)) {
                    continue;
                }
                $name = Validator::toStr($r['name'] ?? null);
                $qty = Validator::toNum($r['quantity'] ?? null);
                $price = Validator::toNum($r['unit_price'] ?? null);
                $unit = Validator::toStr($r['unit'] ?? null);
                if ($name === null && $qty === null && $price === null) {
                    continue;
                }
                $sort++;
                if ($sort > self::MAX_LINES) {
                    break;
                }
                Db::exec('INSERT INTO extra_work_lines (extra_work_id, sort_no, name, quantity, unit, unit_price) VALUES (?,?,?,?,?,?)', [(int)$w['id'], $sort, $name === null ? null : mb_substr($name, 0, 200), $qty, $unit === null ? null : mb_substr($unit, 0, 20), $price]);
            }
            Db::exec('UPDATE projects SET updated_at = ? WHERE id = ?', [Clock::nowStr(), (int)$p['id']]);
        });
        OperationLog::write('update', 'estimate', (string)$w['id'], "その他工事{$no} 保存");
        Session::flash('ok', '保存しました。');
        self::back('/extra', (int)$p['id'], '&no=' . $no);
    }

    /** @return array<string, mixed> */
    private static function work(int $projectId, int $no): array
    {
        $w = Db::row('SELECT * FROM extra_works WHERE project_id = ? AND no = ?', [$projectId, $no]);
        if ($w === null) {
            Db::exec('INSERT INTO extra_works (project_id, no, title) VALUES (?,?,?)', [$projectId, $no, 'その他工事' . $no]);
            $w = Db::row('SELECT * FROM extra_works WHERE id = ?', [Db::lastId()]) ?? [];
        }
        return $w;
    }
}
