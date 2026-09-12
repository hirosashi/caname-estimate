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

final class UserController extends Base
{
    public static function index(): void
    {
        Auth::requireLogin();
        Auth::requireCan('users', '/');
        View::render('users/index', [
            'title' => 'ユーザー管理',
            'rows' => Db::rows('SELECT id, login_id, name, role, is_active, must_change_pw, last_login_at FROM users ORDER BY id'),
            'roles' => Auth::ROLE_LABELS,
        ]);
    }

    public static function save(): void
    {
        Auth::requireLogin();
        Csrf::verify();
        Auth::requireCan('users', '/');
        $id = App::intParam('id', 0);
        $v = new Validator($_POST);
        $v->required('login_id', 'ログインID')->maxLength('login_id', 50, 'ログインID')->required('name', '氏名')->maxLength('name', 50, '氏名');
        if ($v->fails()) {
            Session::flash('error', implode(' ', $v->errors()));
            App::redirect('/users');
        }
        $role = App::param('role', 'viewer');
        if (!isset(Auth::ROLE_LABELS[$role])) {
            $role = 'viewer';
        }
        $pw = (string)($_POST['password'] ?? '');
        $loginId = App::param('login_id');
        if (Db::row('SELECT id FROM users WHERE login_id = ? AND id <> ?', [$loginId, $id]) !== null) {
            Session::flash('error', 'そのログインIDは既に使われています。');
            App::redirect('/users');
        }
        $now = Clock::nowStr();
        $active = isset($_POST['is_active']) ? 1 : 0;
        if ($id > 0) {
            Db::exec('UPDATE users SET login_id = ?, name = ?, role = ?, is_active = ?, updated_at = ? WHERE id = ?', [$loginId, App::param('name'), $role, $active, $now, $id]);
            if ($pw !== '') {
                if (mb_strlen($pw) < 8) {
                    Session::flash('error', 'パスワードは8文字以上にしてください。');
                    App::redirect('/users');
                }
                Db::exec('UPDATE users SET password_hash = ?, must_change_pw = 1 WHERE id = ?', [password_hash($pw, PASSWORD_DEFAULT), $id]);
            }
            OperationLog::write('update', 'users', (string)$id, 'ユーザー更新: ' . $loginId);
        } else {
            if (mb_strlen($pw) < 8) {
                Session::flash('error', '初期パスワード（8文字以上）を入力してください。');
                App::redirect('/users');
            }
            Db::exec('INSERT INTO users (login_id, name, password_hash, role, is_active, must_change_pw, created_at, updated_at) VALUES (?,?,?,?,?,1,?,?)', [$loginId, App::param('name'), password_hash($pw, PASSWORD_DEFAULT), $role, $active, $now, $now]);
            OperationLog::write('create', 'users', (string)Db::lastId(), 'ユーザー追加: ' . $loginId);
        }
        Session::flash('ok', 'ユーザーを保存しました。');
        App::redirect('/users');
    }
}
