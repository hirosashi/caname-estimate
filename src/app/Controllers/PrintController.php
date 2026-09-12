<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Auth;
use App\Core\Db;
use App\Core\View;
use App\Services\Estimate;

final class PrintController extends Base
{
    /** 見積書（印刷用）: 表紙 + 総括 + 工事項目ごとの内訳 + 特記事項 */
    public static function estimate(): void
    {
        Auth::requireLogin();
        $p = self::currentProject();
        $est = Estimate::calc($p);
        $sum = Estimate::summary($p, $est);
        $company = App::config()['company'] ?? [];
        View::render('print/estimate', [
            'title' => '見積書',
            'project' => $p,
            'est' => $est,
            'sum' => $sum,
            'company' => $company,
            'notes' => Db::rows('SELECT body FROM project_notes WHERE project_id = ? ORDER BY sort_no, id', [(int)$p['id']]),
            'withCost' => App::param('cost') === '1',
        ], 'layout_print');
    }

    public static function budget(): void
    {
        Auth::requireLogin();
        $p = self::currentProject();
        $est = Estimate::calc($p);
        View::render('print/budget', [
            'title' => '経理実行予算',
            'project' => $p,
            'est' => $est,
            'sum' => Estimate::summary($p, $est),
        ] + BudgetController::data($p), 'layout_print');
    }
}
