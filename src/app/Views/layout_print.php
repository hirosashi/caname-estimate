<?php
use App\Core\App;
use App\Core\View;

/** @var string $content */
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<title><?= View::e($title ?? '') ?> <?= View::e($project['name'] ?? '') ?></title>
<link rel="stylesheet" href="<?= View::e(App::url('/assets/print.css')) ?>">
</head>
<body class="print<?= !empty($landscape) ? ' land' : '' ?>">
<div class="noprint toolbar">
  <button onclick="window.print()">印刷 / PDF保存</button>
  <a href="<?= View::e(App::url('/estimate?project_id=' . (int)($project['id'] ?? 0))) ?>">明細書へ戻る</a>
  <?php if (isset($withCost)): ?>
    <?php if ($withCost): ?>
      <a href="<?= View::e(App::url('/print?project_id=' . (int)$project['id'])) ?>">原価を隠す</a>
    <?php else: ?>
      <a href="<?= View::e(App::url('/print?project_id=' . (int)$project['id'] . '&cost=1')) ?>">原価を表示（社内用）</a>
    <?php endif; ?>
  <?php endif; ?>
</div>
<?= $content ?>
</body>
</html>
