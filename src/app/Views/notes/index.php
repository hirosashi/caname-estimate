<?php
use App\Core\App;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\View;

/** @var array<string,mixed> $project @var array<int,array<string,mixed>> $notes */
$pid = (int)$project['id'];
$can = Auth::can('estimate');
$ro = $can ? '' : ' readonly disabled';
$count = max(count($notes) + 5, 30);
?>
<div class="card">
  <h2>工事全般特記事項（見積書の最終ページに印字）</h2>
  <?php if ($can): ?>
  <form method="post" action="<?= View::e(App::url('/notes/import')) ?>" class="inline"><?= Csrf::field() ?><input type="hidden" name="project_id" value="<?= $pid ?>">
    <button class="btn sec sm" onclick="return confirm('現在の特記事項をひな形で置き換えます。よろしいですか？')">ひな形から読み込み直す</button></form>
  <a class="muted" href="<?= View::e(App::url('/note-templates')) ?>">ひな形を編集</a>
  <?php endif; ?>
</div>
<form method="post" action="<?= View::e(App::url('/notes/save')) ?>">
<?= Csrf::field() ?><input type="hidden" name="project_id" value="<?= $pid ?>">
<div class="card">
  <table class="grid" style="max-width:1000px">
    <thead><tr><th style="width:40px">No</th><th>内容（空欄行は削除されます）</th></tr></thead>
    <tbody>
    <?php for ($i = 0; $i < $count; $i++): ?>
      <tr><td class="num"><?= $i + 1 ?></td><td><input type="text" name="body[]" value="<?= View::e($notes[$i]['body'] ?? '') ?>" maxlength="500"<?= $ro ?>></td></tr>
    <?php endfor; ?>
    </tbody>
  </table>
  <?php if ($can): ?><div class="sticky-actions"><button class="btn">保存</button></div><?php endif; ?>
</div>
</form>
