<?php
use App\Core\App;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\View;

/** @var array<string,mixed> $project @var int $no @var array<int,array<string,mixed>> $works @var array<string,mixed> $work @var array<int,array<string,mixed>> $lines @var float $total @var array<int,float> $totals @var int $maxLines */
$pid = (int)$project['id'];
$can = Auth::can('estimate');
$ro = $can ? '' : ' readonly disabled';
?>
<div class="card">
  <div class="section-nav">
    <?php foreach ($works as $i => $w): ?>
      <a href="<?= View::e(App::url('/extra?project_id=' . $pid . '&no=' . $i)) ?>" class="<?= $i === $no ? 'on' : '' ?>">その他工事<?= $i ?>：<?= View::e($w['title'] ?? '') ?> <span class="muted"><?= View::yen($totals[$i]) ?></span></a>
    <?php endforeach; ?>
  </div>
  <p class="muted">その他工事1〜3 は営業実行予算の原価に集計されます。4 は予備（集計対象外）です。</p>
</div>
<form method="post" action="<?= View::e(App::url('/extra/save')) ?>" id="extra-form">
<?= Csrf::field() ?><input type="hidden" name="project_id" value="<?= $pid ?>"><input type="hidden" name="no" value="<?= $no ?>">
<div class="card">
  <div class="row"><div><label>工事名</label><input type="text" name="title" value="<?= View::e($work['title'] ?? '') ?>" style="width:360px"<?= $ro ?>></div></div>
  <table class="grid" style="margin-top:10px;max-width:900px">
    <thead><tr><th>No</th><th>名称</th><th>数量</th><th>単位</th><th>単価</th><th>金額</th></tr></thead>
    <tbody>
    <?php for ($i = 0; $i < $maxLines; $i++): $l = $lines[$i] ?? null; $k = "rows[$i]"; ?>
      <tr>
        <td class="num"><?= $i + 1 ?></td>
        <td><input type="text" name="<?= $k ?>[name]" value="<?= View::e($l['name'] ?? '') ?>"<?= $ro ?>></td>
        <td><input type="number" step="any" class="s num qty" name="<?= $k ?>[quantity]" value="<?= View::e($l === null || $l['quantity'] === null ? '' : (string)(float)$l['quantity']) ?>"<?= $ro ?>></td>
        <td><input type="text" class="xs" name="<?= $k ?>[unit]" value="<?= View::e($l['unit'] ?? '') ?>"<?= $ro ?>></td>
        <td><input type="number" step="any" class="m num price" name="<?= $k ?>[unit_price]" value="<?= View::e($l === null || $l['unit_price'] === null ? '' : (string)(float)$l['unit_price']) ?>"<?= $ro ?>></td>
        <td class="num calc amt"><?= $l === null || $l['amount'] === null ? '' : View::num($l['amount']) ?></td>
      </tr>
    <?php endfor; ?>
    </tbody>
    <tfoot><tr class="sub"><td colspan="5" class="right">合計</td><td class="num" id="x_total"><?= View::num($total) ?></td></tr></tfoot>
  </table>
  <?php if ($can): ?><div class="sticky-actions"><button class="btn">保存</button></div><?php endif; ?>
</div>
</form>
<script>
(function(){
  var f=document.getElementById('extra-form');
  f.addEventListener('input',function(){
    var t=0;f.querySelectorAll('tbody tr').forEach(function(tr){
      var q=parseFloat(tr.querySelector('.qty').value),p=parseFloat(tr.querySelector('.price').value);
      var a=(isNaN(q)||isNaN(p))?null:q*p; if(a!==null)t+=a;
      tr.querySelector('.amt').textContent=a===null?'':a.toLocaleString('ja-JP',{maximumFractionDigits:0});
    });
    document.getElementById('x_total').textContent=t.toLocaleString('ja-JP',{maximumFractionDigits:0});
  });
})();
</script>
