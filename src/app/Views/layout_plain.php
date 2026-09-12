<?php
use App\Core\App;
use App\Core\Session;
use App\Core\View;

/** @var string $content */
$flashes = Session::pullFlash();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= View::e($title ?? '') ?> | カナメ見積システム</title>
<link rel="stylesheet" href="<?= View::e(App::url('/assets/app.css')) ?>">
</head>
<body class="plain">
<main class="page narrow">
  <?php foreach ($flashes as $f): ?>
    <div class="flash <?= View::e($f['kind']) ?>"><?= View::e($f['message']) ?></div>
  <?php endforeach; ?>
  <?= $content ?>
</main>
</body>
</html>
