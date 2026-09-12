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
use App\Core\View;

final class AuthController extends Base
{
    public static function loginForm(): void
    {
        if (Auth::user() !== null) {
            App::redirect('/');
        }
        View::render('auth/login', ['title' => 'ログイン'], 'layout_plain');
    }

    public static function login(): void
    {
        Csrf::verify();
        $loginId = App::param('login_id');
        $password = (string)($_POST['password'] ?? '');
        if ($loginId === '' || $password === '') {
            Session::flash('error', 'IDとパスワードを入力してください。');
            App::redirect('/login');
        }
        $err = Auth::attempt($loginId, $password);
        if ($err !== null) {
            Session::flash('error', $err);
            App::redirect('/login');
        }
        OperationLog::write('login', 'auth', $loginId, 'ログイン');
        $after = (string)(Session::get('after_login') ?? '/');
        Session::remove('after_login');
        App::redirect($after === '/login' ? '/' : $after);
    }

    public static function logout(): void
    {
        Csrf::verify();
        Auth::logout();
        App::redirect('/login');
    }

    public static function passwordForm(): void
    {
        Auth::requireLogin();
        View::render('auth/password', ['title' => 'パスワード変更']);
    }

    public static function password(): void
    {
        Auth::requireLogin();
        Csrf::verify();
        $u = Auth::user();
        if ($u === null) {
            App::redirect('/login');
        }
        $cur = (string)($_POST['current'] ?? '');
        $new = (string)($_POST['new'] ?? '');
        $new2 = (string)($_POST['new2'] ?? '');
        $full = Db::row('SELECT password_hash FROM users WHERE id = ?', [(int)$u['id']]);
        if ($full === null || !password_verify($cur, (string)$full['password_hash'])) {
            Session::flash('error', '現在のパスワードが違います。');
            App::redirect('/password');
        }
        if (mb_strlen($new) < 8 || $new !== $new2) {
            Session::flash('error', '新しいパスワードは8文字以上で、確認用と一致させてください。');
            App::redirect('/password');
        }
        Db::exec('UPDATE users SET password_hash = ?, must_change_pw = 0, updated_at = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), Clock::nowStr(), (int)$u['id']]);
        OperationLog::write('update', 'users', (string)$u['id'], 'パスワード変更');
        Session::flash('ok', 'パスワードを変更しました。');
        App::redirect('/');
    }
}
