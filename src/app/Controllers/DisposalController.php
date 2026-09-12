<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Auth;
use App\Core\Validator;
use App\Core\View;
use App\Services\Estimate;

/** 処分費目安（Excel「処分費目安」シートの計算） */
final class DisposalController extends Base
{
    public static function index(): void
    {
        Auth::requireLogin();
        $slate = Validator::toNum(App::param('slate', '91.3')) ?? 0.0;
        $gutter = Validator::toNum(App::param('gutter', '56')) ?? 0.0;
        $carry = Validator::toNum(App::param('carry', '15000')) ?? 15000.0;

        $calc = static function (float $qty, float $perM3, float $price): array {
            $m3 = $qty / $perM3;
            $cost = $m3 * $price;
            $trips = Estimate::roundUp($m3 / 8, 0);
            return ['m3' => $m3, 'cost' => $cost, 'trips_raw' => $m3 / 8, 'trips' => $trips, 'pickup' => $trips * 50000];
        };
        View::render('disposal/index', [
            'title' => '処分費目安',
            'slate' => $slate,
            'gutter' => $gutter,
            'carry' => $carry,
            's' => $calc($slate, 31, 30000),
            'g' => $calc($gutter, 25, 12000),
            'project' => self::currentProject(false) ?: null,
        ]);
    }
}
