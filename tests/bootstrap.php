<?php
declare(strict_types=1);
mb_internal_encoding('UTF-8');
spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'App\\')) return;
    $file = dirname(__DIR__) . '/src/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) require $file;
});
\App\Core\Clock::init();
