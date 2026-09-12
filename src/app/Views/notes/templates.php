<?php
use App\Core\App;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\View;

/** @var array<int,array<string,mixed>> $notes */
$can = Auth::can('items');
$ro = $can ? '' : ' readonly disabled';
$count = max(count($notes) + 5, 30);
?>
<form method="post" action="<?= View::e(App::url('/note-templates/save')) ?>">
<?= Csrf::field() ?>
<div class="card">
  <h2>特記事項ひな形（新規案件の作成時に案件へコピーされます）</h2>
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
