<?php
use App\Core\App;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\View;

/** @var array<string,mixed> $project */
$pid = (int)$project['id'];
$can = Auth::can('budget');
$ro = $can ? '' : ' readonly disabled';
$amt = static fn(string $f): string => View::e($project[$f] === null ? '' : (string)(float)$project[$f]);
$rate = static fn(string $f): string => View::e((string)(float)$project[$f]);
$vars = get_defined_vars();
?>
<form method="post" action="<?= View::e(App::url('/budget/save')) ?>">
<?= Csrf::field() ?><input type="hidden" name="project_id" value="<?= $pid ?>">
<div class="card">
  <h2>契約情報</h2>
  <div class="form-grid">
    <div><label>実行予算 作成日</label><input type="date" name="budget_date" value="<?= View::e($project['budget_date'] ?? '') ?>"<?= $ro ?>></div>
    <div><label>契約日</label><input type="date" name="contract_date" value="<?= View::e($project['contract_date'] ?? '') ?>"<?= $ro ?>></div>
    <div><label>見積金額（税抜）</label><input type="number" step="1" class="num" name="estimate_amount" value="<?= $amt('estimate_amount') ?>"<?= $ro ?>></div>
    <div><label>契約金額（税抜）</label><input type="number" step="1" class="num" name="contract_amount" value="<?= $amt('contract_amount') ?>"<?= $ro ?>></div>
    <div><label>追加工事1</label><input type="number" step="1" class="num" name="extra1_amount" value="<?= $amt('extra1_amount') ?>"<?= $ro ?>></div>
    <div><label>追加工事2</label><input type="number" step="1" class="num" name="extra2_amount" value="<?= $amt('extra2_amount') ?>"<?= $ro ?>></div>
    <div><label>消費税率</label><input type="number" step="0.01" class="num" name="tax_rate" value="<?= $rate('tax_rate') ?>"<?= $ro ?>></div>
    <div><label>労災保険料率</label><input type="number" step="0.0001" class="num" name="insurance_rate" value="<?= $rate('insurance_rate') ?>"<?= $ro ?>></div>
    <div><label>工事監理費率</label><input type="number" step="0.0001" class="num" name="supervision_rate" value="<?= $rate('supervision_rate') ?>"<?= $ro ?>></div>
    <div><label>事業所経費率</label><input type="number" step="0.0001" class="num" name="budget_office_rate" value="<?= $rate('budget_office_rate') ?>"<?= $ro ?>></div>
  </div>
  <table class="grid kv" style="margin-top:10px">
    <tr><th>契約額 合計（契約＋追加1＋追加2）</th><td><?= View::yen($contract_sum) ?></td><th>消費税</th><td><?= View::yen($tax) ?></td><th>税込契約額</th><td><?= View::yen($with_tax) ?></td></tr>
  </table>
</div>
<div class="card">
  <h2>原価予算</h2>
  <?= View::partial('budget/_table', $vars + ['editable' => $can]) ?>
  <?php if ($can): ?><div class="sticky-actions"><a class="btn sec" target="_blank" href="<?= View::e(App::url('/print/budget?project_id=' . $pid)) ?>">印刷</a> <button class="btn">保存して再計算</button></div><?php endif; ?>
</div>
</form>
