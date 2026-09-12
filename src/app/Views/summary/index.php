<?php
use App\Core\App;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\View;

/** @var array<string,mixed> $project @var array<string,mixed> $est @var array<string,mixed> $sum @var array<int,array<string,mixed>> $banks */
$pid = (int)$project['id'];
$can = Auth::can('budget');
$ro = $can ? '' : ' readonly disabled';
$rate = static fn(string $f): string => View::e((string)(float)$project[$f]);
$amt = static fn(string $f): string => View::e($project[$f] === null ? '' : (string)(float)$project[$f]);
?>
<form method="post" action="<?= View::e(App::url('/summary/save')) ?>">
<?= Csrf::field() ?><input type="hidden" name="project_id" value="<?= $pid ?>">
<div class="row" style="align-items:flex-start">
<div class="card" style="flex:1;min-width:480px">
  <h2>原価（明細書・その他工事から自動集計）</h2>
  <table class="grid kv" style="width:100%">
    <tr><th>材料代 合計</th><td><?= View::yen($est['material_total']) ?></td><th style="width:auto" class="muted">明細書</th></tr>
    <tr><th>手間代 合計</th><td><?= View::yen($est['labor_total']) ?></td><td class="muted"><?= View::num($est['labor_count_total'], 2) ?> 人工</td></tr>
    <?php for ($i = 1; $i <= 3; $i++): ?>
    <tr><th>その他工事<?= $i ?></th><td><?= View::yen($sum['extras'][$i]) ?></td><td class="muted"><a href="<?= View::e(App::url('/extra?project_id=' . $pid . '&no=' . $i)) ?>">編集</a></td></tr>
    <?php endfor; ?>
    <tr><th>処分費</th><td><input type="number" step="1" class="num" name="disposal_amount" value="<?= $amt('disposal_amount') ?>" style="width:140px"<?= $ro ?>></td><td class="muted"><a href="<?= View::e(App::url('/disposal?project_id=' . $pid)) ?>">処分費目安</a></td></tr>
    <tr><th>振込手数料（契約予定額 ×）</th><td><?= View::yen($sum['fee']) ?></td><td><input type="number" step="0.0001" class="num" name="fee_rate" value="<?= $rate('fee_rate') ?>" style="width:90px"<?= $ro ?>>
        <?php if ($banks !== []): ?><select onchange="this.previousElementSibling.value=this.value" style="margin-left:4px"<?= $ro ?>><option value="">銀行から選ぶ</option><?php foreach ($banks as $b): ?><option value="<?= View::e($b['extra']) ?>"><?= View::e($b['value']) ?> (<?= View::e($b['extra']) ?>)</option><?php endforeach; ?></select><?php endif; ?></td></tr>
    <tr><th>予備費（契約予定額 ×）</th><td><?= View::yen($sum['reserve']) ?></td><td><input type="number" step="0.0001" class="num" name="reserve_rate" value="<?= $rate('reserve_rate') ?>" style="width:90px"<?= $ro ?>></td></tr>
    <tr><th>現場経費</th><td><input type="number" step="1" class="num" name="site_expense_amount" value="<?= $amt('site_expense_amount') ?>" style="width:140px"<?= $ro ?>></td><td></td></tr>
    <tr class="sub"><td>小計</td><td><?= View::yen($sum['cost_subtotal']) ?></td><td></td></tr>
    <tr><th>設計費（契約予定額 ×）</th><td><?= View::yen($sum['design']) ?></td><td><input type="number" step="0.0001" class="num" name="design_rate" value="<?= $rate('design_rate') ?>" style="width:90px"<?= $ro ?>></td></tr>
    <tr><th>事務費（契約予定額 ×）</th><td><?= View::yen($sum['office']) ?></td><td><input type="number" step="0.0001" class="num" name="office_rate" value="<?= $rate('office_rate') ?>" style="width:90px"<?= $ro ?>></td></tr>
    <tr class="sub"><td>直接原価 合計</td><td class="big"><?= View::yen($sum['direct_cost']) ?></td><td></td></tr>
  </table>
</div>

<div style="flex:1;min-width:420px">
  <div class="card">
    <h2>見積金額（明細書の見積単価から）</h2>
    <table class="grid kv" style="width:100%">
      <?php foreach ($est['sections'] as $s): ?>
        <tr><th><?= (int)$s['sort_no'] ?>. <?= View::e($s['name']) ?></th><td><?= View::yen($s['quote_total']) ?></td></tr>
      <?php endforeach; ?>
      <tr class="sub"><td>見積 小計</td><td><?= View::yen($sum['quote_subtotal']) ?></td></tr>
      <tr><th>一般管理費 <input type="number" step="0.0001" class="num" name="general_admin_rate" value="<?= $rate('general_admin_rate') ?>" style="width:80px"<?= $ro ?>></th><td><?= View::yen($sum['general_admin']) ?></td></tr>
      <tr><th>現場管理費 <input type="number" step="0.0001" class="num" name="site_admin_rate" value="<?= $rate('site_admin_rate') ?>" style="width:80px"<?= $ro ?>></th><td><?= View::yen($sum['site_admin']) ?></td></tr>
      <tr class="sub"><td>見積 合計（税抜）</td><td class="big"><?= View::yen($sum['quote_total']) ?></td></tr>
    </table>
  </div>
  <div class="card">
    <h2>利益シミュレーション（直接原価 ÷ (1−利益率)、1万円切上）</h2>
    <table class="grid kv" style="width:100%">
      <?php foreach (['20', '15', '10', '5'] as $k): ?>
        <tr><th>利益 <?= $k ?>% 確保 必要売上</th><td><?= View::yen($sum['net'][$k]) ?></td></tr>
      <?php endforeach; ?>
      <tr><th>目安見積額（直接原価 ÷ 0.55）</th><td><?= View::yen($sum['expected_quote']) ?></td></tr>
      <tr><th>契約予定額</th><td><input type="number" step="1" class="num" name="planned_contract_amount" value="<?= $amt('planned_contract_amount') ?>" style="width:150px"<?= $ro ?>></td></tr>
      <tr><th>MQ（契約予定額 − 直接原価）</th><td><?= $sum['mq_amount'] === null ? '-' : View::yen($sum['mq_amount']) ?></td></tr>
      <tr><th>MQ率</th><td><?= $sum['mq_rate'] === null ? '-' : View::pct($sum['mq_rate'], 1) ?></td></tr>
      <tr><th>契約予定額 ÷ 目安見積額</th><td><?= $sum['expected_ratio'] === null ? '-' : View::pct($sum['expected_ratio'], 1) ?></td></tr>
    </table>
  </div>
  <?php if ($can): ?><div class="right"><button class="btn">保存して再計算</button></div><?php endif; ?>
</div>
</div>
</form>
