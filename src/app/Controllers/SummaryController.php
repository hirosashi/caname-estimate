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
use App\Services\Estimate;

final class SummaryController extends Base
{
    private const RATES = ['general_admin_rate', 'site_admin_rate', 'fee_rate', 'reserve_rate', 'design_rate', 'office_rate'];
    private const AMOUNTS = ['disposal_amount', 'site_expense_amount', 'planned_contract_amount'];

    public static function index(): void
    {
        Auth::requireLogin();
        $p = self::currentProject();
        $est = Estimate::calc($p);
        View::render('summary/index', [
            'title' => '営業実行予算',
            'project' => $p,
            'est' => $est,
            'sum' => Estimate::summary($p, $est),
            'banks' => Db::rows("SELECT value, extra FROM option_lists WHERE list_key = 'bank' ORDER BY sort_no"),
        ]);
    }

    public static function save(): void
    {
        Auth::requireLogin();
        Csrf::verify();
        Auth::requireCan('budget', '/summary');
        $p = self::currentProject();
        $data = [];
        foreach (self::RATES as $f) {
            $v = Validator::toNum($_POST[$f] ?? null);
            if ($v === null || $v < 0 || $v > 1) {
                Session::flash('error', '率は 0〜1 の小数で入力してください（例 8% → 0.08）。');
                self::back('/summary', (int)$p['id']);
            }
            $data[$f] = $v;
        }
        foreach (self::AMOUNTS as $f) {
            $data[$f] = Validator::toNum($_POST[$f] ?? null);
        }
        $data['updated_at'] = Clock::nowStr();
        $sets = implode(', ', array_map(static fn(string $k): string => "$k = :$k", array_keys($data)));
        $data['id'] = (int)$p['id'];
        Db::exec("UPDATE projects SET $sets WHERE id = :id", $data);
        OperationLog::write('update', 'budget', (string)$p['id'], '営業実行予算 設定保存');
        Session::flash('ok', '保存しました。');
        self::back('/summary', (int)$p['id']);
    }
}
