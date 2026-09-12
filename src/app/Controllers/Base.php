<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Db;
use App\Core\Session;

abstract class Base
{
    /**
     * 画面で扱う案件を決める（?project_id= 優先、なければセッションの最後の案件）。
     * @return array<string, mixed>
     */
    protected static function currentProject(bool $redirectIfNone = true): array
    {
        $id = App::intParam('project_id', 0);
        if ($id <= 0) {
            $id = (int)(Session::get('project_id') ?? 0);
        }
        $p = $id > 0 ? Db::row('SELECT * FROM projects WHERE id = ?', [$id]) : null;
        if ($p === null) {
            if ($redirectIfNone) {
                Session::flash('warn', '案件を選んでください。');
                App::redirect('/projects');
            }
            return [];
        }
        Session::set('project_id', (int)$p['id']);
        return $p;
    }

    protected static function back(string $path, int $projectId, string $extra = ''): never
    {
        App::redirect($path . '?project_id=' . $projectId . $extra);
    }

    /** @return array<int, string> */
    protected static function optionList(string $key): array
    {
        $rows = Db::rows('SELECT value FROM option_lists WHERE list_key = ? ORDER BY sort_no, id', [$key]);
        return array_map(static fn(array $r): string => (string)$r['value'], $rows);
    }

    /** @return array<string, mixed> */
    protected static function postArray(string $key): array
    {
        $v = $_POST[$key] ?? [];
        return is_array($v) ? $v : [];
    }
}
