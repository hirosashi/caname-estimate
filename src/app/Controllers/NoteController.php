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

final class NoteController extends Base
{
    public static function index(): void
    {
        Auth::requireLogin();
        $p = self::currentProject();
        View::render('notes/index', [
            'title' => '工事全般特記事項',
            'project' => $p,
            'notes' => Db::rows('SELECT * FROM project_notes WHERE project_id = ? ORDER BY sort_no, id', [(int)$p['id']]),
        ]);
    }

    public static function save(): void
    {
        Auth::requireLogin();
        Csrf::verify();
        Auth::requireCan('estimate', '/notes');
        $p = self::currentProject();
        $bodies = self::postArray('body');
        Db::tx(static function () use ($p, $bodies): void {
            Db::exec('DELETE FROM project_notes WHERE project_id = ?', [(int)$p['id']]);
            $n = 0;
            foreach ($bodies as $b) {
                $s = Validator::toStr($b);
                if ($s === null) {
                    continue;
                }
                $n++;
                Db::exec('INSERT INTO project_notes (project_id, sort_no, body) VALUES (?,?,?)', [(int)$p['id'], $n, mb_substr($s, 0, 500)]);
            }
            Db::exec('UPDATE projects SET updated_at = ? WHERE id = ?', [Clock::nowStr(), (int)$p['id']]);
        });
        OperationLog::write('update', 'estimate', (string)$p['id'], '特記事項保存');
        Session::flash('ok', '特記事項を保存しました。');
        self::back('/notes', (int)$p['id']);
    }

    public static function importTemplates(): void
    {
        Auth::requireLogin();
        Csrf::verify();
        Auth::requireCan('estimate', '/notes');
        $p = self::currentProject();
        Db::tx(static function () use ($p): void {
            Db::exec('DELETE FROM project_notes WHERE project_id = ?', [(int)$p['id']]);
            Db::exec('INSERT INTO project_notes (project_id, sort_no, body) SELECT ?, sort_no, body FROM note_templates ORDER BY sort_no', [(int)$p['id']]);
        });
        OperationLog::write('update', 'estimate', (string)$p['id'], '特記事項をひな形から読み込み');
        Session::flash('ok', 'ひな形から読み込みました。');
        self::back('/notes', (int)$p['id']);
    }

    public static function templates(): void
    {
        Auth::requireLogin();
        View::render('notes/templates', [
            'title' => '特記事項ひな形',
            'notes' => Db::rows('SELECT * FROM note_templates ORDER BY sort_no, id'),
        ]);
    }

    public static function saveTemplates(): void
    {
        Auth::requireLogin();
        Csrf::verify();
        Auth::requireCan('items', '/note-templates');
        $bodies = self::postArray('body');
        Db::tx(static function () use ($bodies): void {
            Db::exec('DELETE FROM note_templates');
            $n = 0;
            foreach ($bodies as $b) {
                $s = Validator::toStr($b);
                if ($s === null) {
                    continue;
                }
                $n++;
                Db::exec('INSERT INTO note_templates (sort_no, body) VALUES (?,?)', [$n, mb_substr($s, 0, 500)]);
            }
        });
        OperationLog::write('update', 'items', '-', '特記事項ひな形保存');
        Session::flash('ok', 'ひな形を保存しました。');
        App::redirect('/note-templates');
    }
}
