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

final class ProjectController extends Base
{
    public const STATUS = [
        'draft' => '作成中',
        'submitted' => '提出済',
        'ordered' => '受注',
        'lost' => '失注',
        'done' => '完了',
    ];

    private const TEXT_FIELDS = [
        'code' => 30, 'name' => 200, 'staff_name' => 50, 'customer_name' => 200,
        'site_name' => 200, 'site_zip' => 10, 'site_address' => 200, 'site_tel' => 30, 'site_fax' => 30,
        'designer_name' => 200, 'designer_staff' => 50, 'designer_zip' => 10, 'designer_address' => 200, 'designer_tel' => 30, 'designer_fax' => 30,
        'contractor_name' => 200, 'contractor_staff' => 50, 'contractor_zip' => 10, 'contractor_address' => 200, 'contractor_tel' => 30, 'contractor_fax' => 30,
        'orderer_name' => 200, 'orderer_staff' => 50, 'orderer_zip' => 10, 'orderer_address' => 200, 'orderer_tel' => 30, 'orderer_fax' => 30,
        'pay_close' => 20, 'pay_day' => 20, 'pay_terms_text' => 200,
        'roof_spec' => 100, 'roof_material' => 100, 'roof_thickness' => 20, 'roof_product' => 100, 'roof_color' => 100,
        'work_type' => 100, 'info_source' => 100, 'repeat_kind' => 20,
        'note_receipt' => 100, 'note_billing' => 100, 'note_accounting' => 100, 'remarks' => 2000,
    ];
    private const DATE_FIELDS = ['budget_date', 'contract_date', 'estimate_date', 'period_from', 'period_to', 'start_date', 'payment_due_date'];
    private const NUM_FIELDS = ['pay_cash_pct', 'pay_bill_pct', 'pay_site_days', 'roof_area'];

    public static function index(): void
    {
        Auth::requireLogin();
        $q = App::param('q');
        $status = App::param('status');
        $sql = 'SELECT p.*, (SELECT COUNT(*) FROM project_sections s WHERE s.project_id = p.id) AS section_count FROM projects p WHERE 1=1';
        $params = [];
        if ($q !== '') {
            $sql .= ' AND (p.name LIKE ? OR p.code LIKE ? OR p.customer_name LIKE ? OR p.site_name LIKE ?)';
            $like = '%' . $q . '%';
            array_push($params, $like, $like, $like, $like);
        }
        if ($status !== '' && isset(self::STATUS[$status])) {
            $sql .= ' AND p.status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY p.updated_at DESC, p.id DESC LIMIT 200';
        $rows = Db::rows($sql, $params);
        foreach ($rows as &$r) {
            $r['quote_total'] = Estimate::calc($r)['quote_total'];
        }
        unset($r);
        View::render('projects/index', [
            'title' => '案件一覧',
            'rows' => $rows,
            'q' => $q,
            'status' => $status,
            'statuses' => self::STATUS,
            'current' => self::currentProject(false),
        ]);
    }

    public static function create(): void
    {
        Auth::requireLogin();
        Auth::requireCan('projects', '/projects');
        View::render('projects/edit', [
            'title' => '案件の新規作成',
            'p' => self::defaults(),
            'options' => self::options(),
            'statuses' => self::STATUS,
            'project' => null,
        ]);
    }

    public static function edit(): void
    {
        Auth::requireLogin();
        $p = self::currentProject();
        View::render('projects/edit', [
            'title' => '案件情報',
            'p' => $p,
            'options' => self::options(),
            'statuses' => self::STATUS,
            'project' => $p,
        ]);
    }

    public static function save(): void
    {
        Auth::requireLogin();
        Csrf::verify();
        Auth::requireCan('projects', '/projects');
        $id = App::intParam('id', 0);
        $v = new Validator($_POST);
        $v->required('name', '件名')->maxLength('name', 200, '件名');
        foreach (self::DATE_FIELDS as $f) {
            $v->date($f, $f);
        }
        if ($v->fails()) {
            Session::flash('error', implode(' ', $v->errors()));
            App::redirect($id > 0 ? '/projects/edit?project_id=' . $id : '/projects/new');
        }
        $data = [];
        foreach (self::TEXT_FIELDS as $f => $max) {
            $s = Validator::toStr($_POST[$f] ?? null);
            $data[$f] = $s === null ? null : mb_substr($s, 0, $max);
        }
        foreach (self::DATE_FIELDS as $f) {
            $data[$f] = Validator::toDate($_POST[$f] ?? null);
        }
        foreach (self::NUM_FIELDS as $f) {
            $data[$f] = Validator::toNum($_POST[$f] ?? null);
        }
        $status = App::param('status', 'draft');
        $data['status'] = isset(self::STATUS[$status]) ? $status : 'draft';
        $data['updated_at'] = Clock::nowStr();

        if ($id > 0) {
            $sets = implode(', ', array_map(static fn(string $k): string => "$k = :$k", array_keys($data)));
            $data['id'] = $id;
            Db::exec("UPDATE projects SET $sets WHERE id = :id", $data);
            OperationLog::write('update', 'projects', (string)$id, '案件更新: ' . $data['name']);
            Session::flash('ok', '案件情報を保存しました。');
        } else {
            $data['created_at'] = $data['updated_at'];
            $data['created_by'] = (int)(Auth::user()['id'] ?? 0);
            $cols = implode(', ', array_keys($data));
            $vals = ':' . implode(', :', array_keys($data));
            Db::exec("INSERT INTO projects ($cols) VALUES ($vals)", $data);
            $id = Db::lastId();
            for ($no = 1; $no <= 4; $no++) {
                Db::exec('INSERT INTO extra_works (project_id, no, title) VALUES (?,?,?)', [$id, $no, 'その他工事' . $no]);
            }
            Db::exec('INSERT INTO project_notes (project_id, sort_no, body) SELECT ?, sort_no, body FROM note_templates ORDER BY sort_no', [$id]);
            OperationLog::write('create', 'projects', (string)$id, '案件作成: ' . $data['name']);
            Session::flash('ok', '案件を作成しました。続けて明細書を入力してください。');
            Session::set('project_id', $id);
            App::redirect('/estimate?project_id=' . $id);
        }
        App::redirect('/projects/edit?project_id=' . $id);
    }

    public static function copy(): void
    {
        Auth::requireLogin();
        Csrf::verify();
        Auth::requireCan('projects', '/projects');
        $src = self::currentProject();
        $newId = Db::tx(static function () use ($src): int {
            $cols = array_keys($src);
            $cols = array_values(array_filter($cols, static fn(string $c): bool => !in_array($c, ['id', 'created_at', 'updated_at', 'created_by'], true)));
            $data = [];
            foreach ($cols as $c) {
                $data[$c] = $src[$c];
            }
            $data['name'] = mb_substr((string)$src['name'] . '（コピー）', 0, 200);
            $data['status'] = 'draft';
            $data['created_at'] = $data['updated_at'] = Clock::nowStr();
            $data['created_by'] = (int)(Auth::user()['id'] ?? 0);
            $keys = array_keys($data);
            Db::exec('INSERT INTO projects (' . implode(', ', $keys) . ') VALUES (:' . implode(', :', $keys) . ')', $data);
            $newId = Db::lastId();
            $srcId = (int)$src['id'];
            $sections = Db::rows('SELECT * FROM project_sections WHERE project_id = ? ORDER BY sort_no, id', [$srcId]);
            foreach ($sections as $s) {
                Db::exec('INSERT INTO project_sections (project_id, sort_no, name, loss_rate) VALUES (?,?,?,?)', [$newId, $s['sort_no'], $s['name'], $s['loss_rate']]);
                $nsid = Db::lastId();
                $lines = Db::rows('SELECT * FROM project_lines WHERE section_id = ? ORDER BY sort_no, id', [(int)$s['id']]);
                $map = [];
                foreach ($lines as $l) {
                    $old = (int)$l['id'];
                    unset($l['id']);
                    $l['section_id'] = $nsid;
                    $l['merge_into_line_id'] = null;
                    $k = array_keys($l);
                    Db::exec('INSERT INTO project_lines (' . implode(', ', $k) . ') VALUES (:' . implode(', :', $k) . ')', $l);
                    $map[$old] = Db::lastId();
                }
                foreach ($lines as $l) {
                    if ($l['merge_into_line_id'] !== null && isset($map[(int)$l['merge_into_line_id']])) {
                        Db::exec('UPDATE project_lines SET merge_into_line_id = ? WHERE id = ?', [$map[(int)$l['merge_into_line_id']], $map[(int)$l['id']]]);
                    }
                }
            }
            $works = Db::rows('SELECT * FROM extra_works WHERE project_id = ?', [$srcId]);
            foreach ($works as $w) {
                Db::exec('INSERT INTO extra_works (project_id, no, title) VALUES (?,?,?)', [$newId, $w['no'], $w['title']]);
                $nwid = Db::lastId();
                Db::exec('INSERT INTO extra_work_lines (extra_work_id, sort_no, name, quantity, unit, unit_price) SELECT ?, sort_no, name, quantity, unit, unit_price FROM extra_work_lines WHERE extra_work_id = ?', [$nwid, (int)$w['id']]);
            }
            Db::exec('INSERT INTO project_notes (project_id, sort_no, body) SELECT ?, sort_no, body FROM project_notes WHERE project_id = ?', [$newId, $srcId]);
            Db::exec('INSERT INTO project_budgets (project_id, budget_item_id, sales_amount, work_amount) SELECT ?, budget_item_id, sales_amount, work_amount FROM project_budgets WHERE project_id = ?', [$newId, $srcId]);
            return $newId;
        });
        OperationLog::write('copy', 'projects', (string)$newId, '案件コピー 元ID=' . $src['id']);
        Session::flash('ok', '案件をコピーしました。');
        Session::set('project_id', $newId);
        App::redirect('/projects/edit?project_id=' . $newId);
    }

    public static function delete(): void
    {
        Auth::requireLogin();
        Csrf::verify();
        Auth::requireCan('settings', '/projects');
        $p = self::currentProject();
        Db::exec('DELETE FROM projects WHERE id = ?', [(int)$p['id']]);
        OperationLog::write('delete', 'projects', (string)$p['id'], '案件削除: ' . $p['name']);
        Session::remove('project_id');
        Session::flash('ok', '案件を削除しました。');
        App::redirect('/projects');
    }

    /** @return array<string, mixed> */
    private static function defaults(): array
    {
        $d = ['status' => 'draft', 'estimate_date' => Clock::today(), 'budget_date' => Clock::today()];
        foreach (array_keys(self::TEXT_FIELDS) as $f) {
            $d[$f] ??= null;
        }
        foreach ([...self::DATE_FIELDS, ...self::NUM_FIELDS] as $f) {
            $d[$f] ??= null;
        }
        return $d;
    }

    /** @return array<string, array<int, string>> */
    private static function options(): array
    {
        $keys = ['pay_close', 'pay_day', 'pay_cash_pct', 'pay_bill_pct', 'pay_site_days', 'pay_terms', 'roof_spec', 'roof_material', 'roof_thickness', 'roof_product', 'info_source', 'repeat_kind', 'work_type', 'receipt', 'billing', 'accounting'];
        $out = [];
        foreach ($keys as $k) {
            $out[$k] = self::optionList($k);
        }
        return $out;
    }
}
