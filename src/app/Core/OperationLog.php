<?php
declare(strict_types=1);

namespace App\Core;

final class OperationLog
{
    public static function write(string $action, string $area, string $targetId, string $message): void
    {
        $u = Auth::user();
        Db::exec(
            'INSERT INTO operation_logs (user_id, action, area, target_id, message, created_at) VALUES (?,?,?,?,?,?)',
            [$u === null ? null : (int)$u['id'], $action, $area, $targetId, mb_substr($message, 0, 500), Clock::nowStr()]
        );
    }
}
