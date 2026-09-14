<?php
use App\Core\App;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\View;

/** @var string $content */
$user = Auth::user();
$flashes = Session::pullFlash();
$pid = isset($project) && is_array($project) && isset($project['id']) ? (int)$project['id'] : (int)(Session::get('project_id') ?? 0);
$path = App::currentPath();
$projTabs = [
    '/projects/edit' => '案件情報',
    '/estimate' => '明細書',
    '/summary' => '営業実行予算',
    '/extra' => 'その他工事',
    '/notes' => '特記事項',
    '/budget' => '経理実行予算',
    '/print' => '見積書印刷',
];
$masterTabs = [
    '/items' => '単価マスタ',
    '/categories' => 'カテゴリ',
    '/note-templates' => '特記ひな形',
    '/disposal' => '処分費目安',
];
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= View::e($title ?? '') ?> | カナメ見積システム</title>
<link rel="stylesheet" href="<?= View::e(App::url('/assets/app.css')) ?>">
</head>
<body>
<header class="top">
  <a class="brand" href="<?= View::e(App::url('/projects')) ?>">カナメ見積・実行予算</a>
  <nav class="gnav">
    <a href="<?= View::e(App::url('/projects')) ?>" class="<?= $path === '/projects' ? 'on' : '' ?>">案件一覧</a>
    <?php foreach ($masterTabs as $href => $label): ?>
      <a href="<?= View::e(App::url($href)) ?>" class="<?= str_starts_with($path, $href) ? 'on' : '' ?>"><?= View::e($label) ?></a>
    <?php endforeach; ?>
    <?php if (Auth::can('users')): ?>
      <a href="<?= View::e(App::url('/users')) ?>" class="<?= $path === '/users' ? 'on' : '' ?>">ユーザー</a>
    <?php endif; ?>
  </nav>
  <?php if ($user !== null): ?>
  <div class="me">
    <?= View::e($user['name']) ?>（<?= View::e(Auth::ROLE_LABELS[$user['role']] ?? $user['role']) ?>）
    <a href="<?= View::e(App::url('/password')) ?>">PW変更</a>
    <form method="post" action="<?= View::e(App::url('/logout')) ?>" class="inline"><?= Csrf::field() ?><button class="link">ログアウト</button></form>
  </div>
  <?php endif; ?>
</header>
<?php if ($pid > 0 && isset($project) && is_array($project) && $project !== []): ?>
<div class="projbar">
  <span class="pname"><?= View::e($project['code'] ?? '') ?> <?= View::e($project['name']) ?></span>
  <nav class="tabs">
    <?php foreach ($projTabs as $href => $label): ?>
      <a href="<?= View::e(App::url($href . '?project_id=' . $pid)) ?>" class="<?= $path === $href ? 'on' : '' ?>"><?= View::e($label) ?></a>
    <?php endforeach; ?>
  </nav>
</div>
<?php endif; ?>
<main class="page<?= !empty($wide) ? ' wide' : '' ?>">
  <?php foreach ($flashes as $f): ?>
    <div class="flash <?= View::e($f['kind']) ?>"><?= View::e($f['message']) ?></div>
  <?php endforeach; ?>
  <?= $content ?>
</main>
<script src="<?= View::e(App::url('/assets/app.js')) ?>"></script>
</body>
</html>
