<?php use App\Core\App; use App\Core\View; ?>
<div class="card" style="max-width:800px"><h2>エラーが発生しました</h2><p>処理を完了できませんでした。時間をおいて再度お試しください。</p>
<?php if (!empty($debug) && isset($error) && $error instanceof \Throwable): ?><pre style="white-space:pre-wrap;font-size:12px;background:#f3f4f6;padding:8px"><?= View::e($error->getMessage()) ?>
<?= View::e($error->getFile()) ?>:<?= (int)$error->getLine() ?>
<?= View::e($error->getTraceAsString()) ?></pre><?php endif; ?>
<a class="btn sec" href="<?= View::e(App::url('/projects')) ?>">案件一覧へ</a></div>
