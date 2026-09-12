<?php
declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\BudgetController;
use App\Controllers\DisposalController;
use App\Controllers\EstimateController;
use App\Controllers\ExtraWorkController;
use App\Controllers\HomeController;
use App\Controllers\ItemController;
use App\Controllers\NoteController;
use App\Controllers\PrintController;
use App\Controllers\ProjectController;
use App\Controllers\SummaryController;
use App\Controllers\UserController;
use App\Core\App;
use App\Core\Clock;
use App\Core\Router;
use App\Core\Session;
use App\Core\View;

mb_internal_encoding('UTF-8');
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/storage/logs/php_error.log');

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $file = __DIR__ . '/app/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

Clock::init();
Session::start();

$router = new Router();

$router->get('/login', [AuthController::class, 'loginForm']);
$router->post('/login', [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout']);
$router->get('/password', [AuthController::class, 'passwordForm']);
$router->post('/password', [AuthController::class, 'password']);

$router->get('/', [HomeController::class, 'index']);

$router->get('/projects', [ProjectController::class, 'index']);
$router->get('/projects/new', [ProjectController::class, 'create']);
$router->get('/projects/edit', [ProjectController::class, 'edit']);
$router->post('/projects/save', [ProjectController::class, 'save']);
$router->post('/projects/copy', [ProjectController::class, 'copy']);
$router->post('/projects/delete', [ProjectController::class, 'delete']);

$router->get('/estimate', [EstimateController::class, 'index']);
$router->post('/estimate/settings', [EstimateController::class, 'saveSettings']);
$router->post('/estimate/section/add', [EstimateController::class, 'addSection']);
$router->post('/estimate/section/save', [EstimateController::class, 'saveSection']);
$router->post('/estimate/section/delete', [EstimateController::class, 'deleteSection']);
$router->post('/estimate/section/move', [EstimateController::class, 'moveSection']);
$router->post('/estimate/lines/save', [EstimateController::class, 'saveLines']);
$router->post('/estimate/lines/refresh', [EstimateController::class, 'refreshPrices']);

$router->get('/summary', [SummaryController::class, 'index']);
$router->post('/summary/save', [SummaryController::class, 'save']);

$router->get('/extra', [ExtraWorkController::class, 'index']);
$router->post('/extra/save', [ExtraWorkController::class, 'save']);

$router->get('/notes', [NoteController::class, 'index']);
$router->post('/notes/save', [NoteController::class, 'save']);
$router->post('/notes/import', [NoteController::class, 'importTemplates']);
$router->get('/note-templates', [NoteController::class, 'templates']);
$router->post('/note-templates/save', [NoteController::class, 'saveTemplates']);

$router->get('/budget', [BudgetController::class, 'index']);
$router->post('/budget/save', [BudgetController::class, 'save']);

$router->get('/print', [PrintController::class, 'estimate']);
$router->get('/print/budget', [PrintController::class, 'budget']);

$router->get('/items', [ItemController::class, 'index']);
$router->get('/items/search', [ItemController::class, 'search']);
$router->get('/items/edit', [ItemController::class, 'edit']);
$router->post('/items/save', [ItemController::class, 'save']);
$router->post('/items/delete', [ItemController::class, 'delete']);
$router->get('/categories', [ItemController::class, 'categories']);
$router->post('/categories/save', [ItemController::class, 'saveCategories']);

$router->get('/disposal', [DisposalController::class, 'index']);

$router->get('/users', [UserController::class, 'index']);
$router->post('/users/save', [UserController::class, 'save']);

try {
    $router->dispatch();
} catch (\Throwable $e) {
    error_log($e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    View::render('errors/500', ['debug' => (bool)(App::config()['debug'] ?? false), 'error' => $e]);
}
