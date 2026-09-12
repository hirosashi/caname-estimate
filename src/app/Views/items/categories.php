<?php
use App\Core\App;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\View;

/** @var array<int,array<string,mixed>> $rows */
$can = Auth::can('items');
$ro = $can ? '' : ' readonly disabled';
?>
<form method="post" action="<?= View::e(App::url('/categories/save')) ?>">
<?= Csrf::field() ?>
<div class="card" style="max-width:800px">
  <h2>カテゴリ（並び順に表示。空欄の新規行は無視されます）</h2>
  <table class="grid">
    <thead><tr><th style="width:50px">順</th><th>カテゴリ名</th><th class="num">品目数</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $i => $r): ?>
      <tr><td class="num"><?= $i + 1 ?></td>
        <td><input type="hidden" name="id[<?= $i ?>]" value="<?= (int)$r['id'] ?>"><input type="text" name="name[<?= $i ?>]" value="<?= View::e($r['name']) ?>"<?= $ro ?>></td>
        <td class="num"><?= (int)$r['item_count'] ?></td>
        <td><a href="<?= View::e(App::url('/items?category_id=' . (int)$r['id'])) ?>">品目を見る</a></td></tr>
    <?php endforeach; ?>
    <?php $n = count($rows); for ($i = $n; $i < $n + 3; $i++): ?>
      <tr><td class="num"><?= $i + 1 ?></td><td><input type="hidden" name="id[<?= $i ?>]" value="0"><input type="text" name="name[<?= $i ?>]" placeholder="新しいカテゴリ"<?= $ro ?>></td><td></td><td></td></tr>
    <?php endfor; ?>
    </tbody>
  </table>
  <?php if ($can): ?><div class="right" style="margin-top:10px"><button class="btn">保存</button></div><?php endif; ?>
</div>
</form>
