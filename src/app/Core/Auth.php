<?php
declare(strict_types=1);

namespace App\Core;

final class Auth
{
    /** 区分ごとに編集できる役割。roles: admin / sales(営業) / accounting(経理) / viewer */
    public const EDIT_PERMISSIONS = [
        'projects' => ['admin', 'sales', 'accounting'],
        'estimate' => ['admin', 'sales'],
        'budget'   => ['admin', 'sales', 'accounting'],
        'items'    => ['admin', 'sales'],
        'settings' => ['admin'],
        'users'    => ['admin'],
    ];

    public const ROLE_LABELS = [
        'admin' => '管理者',
        'sales' => '営業担当',
        'accounting' => '経理担当',
        'viewer' => '閲覧のみ',
    ];

    private const MAX_ATTEMPTS = 5;
    private const LOCK_MINUTES = 15;

    /** @return array<string, mixed>|null */
    public static function user(): ?array
    {
        $id = Session::get('user_id');
        if (!is_int($id)) {
            return null;
        }
        static $cache = null;
        if ($cache === null || ($cache['id'] ?? null) !== $id) {
            $cache = Db::row('SELECT id, login_id, name, role, must_change_pw, is_active FROM users WHERE id = ?', [$id]);
        }
        return $cache;
    }

    public static function requireLogin(): void
    {
        $u = self::user();
        if ($u === null || (int)$u['is_active'] !== 1) {
            Session::set('after_login', App::currentPath());
            App::redirect('/login');
        }
        if ((int)$u['must_change_pw'] === 1 && App::currentPath() !== '/password') {
            App::redirect('/password');
        }
    }

    public static function can(string $area): bool
    {
        $u = self::user();
        if ($u === null) {
            return false;
        }
        $roles = self::EDIT_PERMISSIONS[$area] ?? [];
        return in_array((string)$u['role'], $roles, true);
    }

    public static function requireCan(string $area, string $back = '/'): void
    {
        if (!self::can($area)) {
            Session::flash('warn', 'この操作をする権限がありません。');
            App::redirect($back);
        }
    }

    /** @return string|null エラーメッセージ（成功時 null） */
    public static function attempt(string $loginId, string $password): ?string
    {
        $la = Db::row('SELECT attempts, locked_until FROM login_attempts WHERE login_id = ?', [$loginId]);
        if ($la !== null && $la['locked_until'] !== null && (string)$la['locked_until'] > Clock::nowStr()) {
            return 'ログインが一時的にロックされています。しばらく待ってからやり直してください。';
        }
        $u = Db::row('SELECT * FROM users WHERE login_id = ? AND is_active = 1', [$loginId]);
        if ($u === null || !password_verify($password, (string)$u['password_hash'])) {
            $attempts = ($la === null ? 0 : (int)$la['attempts']) + 1;
            $locked = null;
            if ($attempts >= self::MAX_ATTEMPTS) {
                $locked = Clock::now()->modify('+' . self::LOCK_MINUTES . ' minutes')->format('Y-m-d H:i:s');
                $attempts = 0;
            }
            Db::exec(
                'INSERT INTO login_attempts (login_id, attempts, locked_until, updated_at) VALUES (?,?,?,?)
                 ON DUPLICATE KEY UPDATE attempts = VALUES(attempts), locked_until = VALUES(locked_until), updated_at = VALUES(updated_at)',
                [$loginId, $attempts, $locked, Clock::nowStr()]
            );
            return 'IDまたはパスワードが違います。';
        }
        Db::exec('DELETE FROM login_attempts WHERE login_id = ?', [$loginId]);
        Session::regenerate();
        Session::set('user_id', (int)$u['id']);
        Db::exec('UPDATE users SET last_login_at = ? WHERE id = ?', [Clock::nowStr(), (int)$u['id']]);
        return null;
    }

    public static function logout(): void
    {
        Session::destroy();
    }
}
