<?php
use App\Core\App;
use App\Core\Csrf;
use App\Core\View;

/** @var array<string,mixed> $item @var array<int,array<string,mixed>> $categories */
$cats = [];
foreach ($categories as $c) {
    $cats[(int)$c['id']] = $c['name'];
}
$v = static fn(string $f): string => View::e($item[$f] === null ? '' : (is_numeric($item[$f]) ? (string)(float)$item[$f] : (string)$item[$f]));
?>
<form method="post" action="<?= View::e(App::url('/items/save')) ?>">
<?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
<div class="card" style="max-width:900px">
  <h2><?= View::e($title) ?></h2>
  <div class="form-grid">
    <div><label>コード *</label><input type="text" name="code" required value="<?= View::e($item['code']) ?>"></div>
    <div><label>カテゴリ</label><?= View::select('category_id', $cats, $item['category_id'], '', '（なし）') ?></div>
    <div><label>並び順（0=末尾）</label><input type="number" name="sort_no" class="num" value="<?= (int)$item['sort_no'] ?>"></div>
    <div class="wide"><label>項目 *</label><input type="text" name="name" required value="<?= View::e($item['name']) ?>"></div>
    <div class="wide"><label>材質</label><input type="text" name="material" value="<?= View::e($item['material']) ?>"></div>
    <div><label>長（m）</label><input type="number" step="any" class="num" name="length" value="<?= $v('length') ?>"></div>
    <div><label>巾（m）</label><input type="number" step="any" class="num" name="width" value="<?= $v('width') ?>"></div>
    <div><label>厚</label><input type="number" step="any" class="num" name="thickness" value="<?= $v('thickness') ?>"></div>
    <div><label>実数の単位（㎡, m, 式 …）</label><input type="text" name="area_unit" value="<?= View::e($item['area_unit']) ?>"></div>
    <div><label>材料の単位（坪, 枚, 本 …）</label><input type="text" name="use_unit" value="<?= View::e($item['use_unit']) ?>"></div>
    <div><label>材料単価（材料単位あたり）</label><input type="number" step="any" class="num" name="material_price" value="<?= $v('material_price') ?>"></div>
    <div><label>歩掛（実数1あたり人工）</label><input type="number" step="any" class="num" name="labor_rate" value="<?= $v('labor_rate') ?>"></div>
    <div><label>人工単価</label><input type="number" step="any" class="num" name="labor_unit_price" value="<?= $v('labor_unit_price') ?>"></div>
    <div class="wide"><label>備考</label><input type="text" name="note" value="<?= View::e($item['note']) ?>"></div>
    <div><label><input type="checkbox" name="is_active" value="1" <?= (int)$item['is_active'] === 1 ? 'checked' : '' ?>> 有効（明細書の検索対象）</label></div>
  </div>
  <div class="right" style="margin-top:10px"><a class="btn sec" href="<?= View::e(App::url('/items')) ?>">戻る</a> <button class="btn">保存</button></div>
</div>
</form>
