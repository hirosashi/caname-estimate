<?php use App\Core\App; use App\Core\View; ?>
<div class="card" style="max-width:520px"><h2>ページが見つかりません</h2><p class="muted"><?= View::e($path ?? '') ?></p><a class="btn sec" href="<?= View::e(App::url('/projects')) ?>">案件一覧へ</a></div>
