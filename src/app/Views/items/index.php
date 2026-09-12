<?php
use App\Core\App;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\View;

/** @var array<int,array<string,mixed>> $rows @var array<int,array<string,mixed>> $categories */
$can = Auth::can('items');
$cats = [];
foreach ($categories as $c) {
    $cats[(int)$c['id']] = $c['name'];
}
$d = static fn(mixed $v): string => $v === null ? '' : View::dec($v, 4);
?>
<div class="card">
  <form method="get" action="<?= View::e(App::url('/items')) ?>" class="row">
    <div><label>カテゴリ</label><?= View::select('category_id', $cats, $category_id, '', '全カテゴリ') ?></div>
    <div><label>検索</label><input type="text" name="q" value="<?= View::e($q) ?>" placeholder="コード・項目・材質" style="width:240px"></div>
    <div><button class="btn sec">検索</button></div>
    <?php if ($can): ?><div style="margin-left:auto"><a class="btn" href="<?= View::e(App::url('/items/edit?category_id=' . $category_id)) ?>">＋ 品目追加</a></div><?php endif; ?>
  </form>
</div>
<div class="card" style="overflow-x:auto">
  <p class="muted"><?= count($rows) ?> 件表示（最大500件）</p>
  <table class="grid">
    <thead><tr><th>コード</th><th>カテゴリ</th><th>項目</th><th>材質</th><th>長</th><th>巾</th><th>厚</th><th>実数単位</th><th>材料単位</th><th class="num">材料単価</th><th class="num">歩掛</th><th class="num">人工単価</th><th>備考</th><th>有効</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= View::e($r['code']) ?></td><td><?= View::e($r['category_name']) ?></td><td><?= View::e($r['name']) ?></td><td><?= View::e($r['material']) ?></td>
        <td class="num"><?= $d($r['length']) ?></td><td class="num"><?= $d($r['width']) ?></td><td class="num"><?= $d($r['thickness']) ?></td>
        <td><?= View::e($r['area_unit']) ?></td><td><?= View::e($r['use_unit']) ?></td>
        <td class="num"><?= $r['material_price'] === null ? '' : View::num($r['material_price']) ?></td>
        <td class="num"><?= $d($r['labor_rate']) ?></td>
        <td class="num"><?= $r['labor_unit_price'] === null ? '' : View::num($r['labor_unit_price']) ?></td>
        <td class="muted"><?= View::e($r['note']) ?></td>
        <td class="center"><?= (int)$r['is_active'] === 1 ? '○' : '×' ?></td>
        <td style="white-space:nowrap">
          <?php if ($can): ?>
            <a class="btn sec sm" href="<?= View::e(App::url('/items/edit?id=' . (int)$r['id'])) ?>">編集</a>
            <form method="post" action="<?= View::e(App::url('/items/delete')) ?>" class="inline"><?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn warn sm" onclick="return confirm('「<?= View::e($r['code']) ?>」を削除します（明細で使用中なら無効化）。よろしいですか？')">削除</button></form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
