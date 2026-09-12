<?php
use App\Core\App;
use App\Core\View;

/** @var float $slate @var float $gutter @var float $carry @var array<string,float> $s @var array<string,float> $g */
$total = $s['cost'] + $g['cost'] + $s['pickup'] + $g['pickup'] + $carry;
?>
<div class="card" style="max-width:900px">
  <h2>処分費目安（Excel「処分費目安」シート）</h2>
  <form method="get" action="<?= View::e(App::url('/disposal')) ?>" class="row">
    <?php if (isset($project) && is_array($project) && $project !== []): ?><input type="hidden" name="project_id" value="<?= (int)$project['id'] ?>"><?php endif; ?>
    <div><label>スレート面積（㎡）</label><input type="number" step="any" class="num" name="slate" value="<?= View::e((string)$slate) ?>"></div>
    <div><label>雨樋 長さ（m）</label><input type="number" step="any" class="num" name="gutter" value="<?= View::e((string)$gutter) ?>"></div>
    <div><label>運搬費（円）</label><input type="number" step="1" class="num" name="carry" value="<?= View::e((string)$carry) ?>"></div>
    <div><button class="btn sec">計算</button></div>
  </form>
  <table class="grid" style="margin-top:12px">
    <thead><tr><th>品目</th><th class="num">数量</th><th>換算</th><th class="num">立米（m³）</th><th class="num">処分単価/m³</th><th class="num">処分費</th><th class="num">引取回数（8m³/回 切上）</th><th class="num">引取費（5万円/回）</th></tr></thead>
    <tbody>
      <tr><td>スレート</td><td class="num"><?= View::dec($slate) ?> ㎡</td><td>31㎡ = 1m³</td><td class="num"><?= View::num($s['m3'], 2) ?></td><td class="num">30,000</td><td class="num"><?= View::num($s['cost']) ?></td><td class="num"><?= View::num($s['trips']) ?>（<?= View::num($s['trips_raw'], 2) ?>）</td><td class="num"><?= View::num($s['pickup']) ?></td></tr>
      <tr><td>雨樋</td><td class="num"><?= View::dec($gutter) ?> m</td><td>25m = 1m³</td><td class="num"><?= View::num($g['m3'], 2) ?></td><td class="num">12,000</td><td class="num"><?= View::num($g['cost']) ?></td><td class="num"><?= View::num($g['trips']) ?>（<?= View::num($g['trips_raw'], 2) ?>）</td><td class="num"><?= View::num($g['pickup']) ?></td></tr>
      <tr><td>運搬費</td><td colspan="6"></td><td class="num"><?= View::num($carry) ?></td></tr>
    </tbody>
    <tfoot><tr class="sub"><td colspan="7" class="right">処分費 目安 合計</td><td class="num"><?= View::yen($total) ?></td></tr></tfoot>
  </table>
  <p class="muted">この金額を営業実行予算の「処分費」に入力してください。</p>
</div>
