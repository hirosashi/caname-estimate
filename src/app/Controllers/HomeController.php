<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Auth;

final class HomeController extends Base
{
    public static function index(): void
    {
        Auth::requireLogin();
        App::redirect('/projects');
    }
}
