<?php
use App\Core\View;

/** @var array<string,mixed> $project @var array<string,mixed> $est @var array<string,mixed> $sum @var array<string,mixed> $company @var array<int,array<string,mixed>> $notes @var bool $withCost */
$perPage = 25;
$tax = $sum['quote_total'] * (float)$project['tax_rate'];
$dim = static fn(mixed $v): string => $v === null ? '' : View::dec($v, 3);
$pageNo = 1;
$totalPages = 1;
foreach ($est['sections'] as $s) {
    $totalPages += max(1, (int)ceil(max(1, count($s['print_rows'])) / $perPage));
}
if ($notes !== []) {
    $totalPages++;
}
?>
<div class="sheet cover">
  <div class="center"><h1 class="doc">御見積書</h1></div>
  <div class="right"><?= View::e(View::d($project['estimate_date'])) ?>　No. <?= View::e($project['code']) ?></div>
  <div class="to"><?= View::e($project['customer_name'] ?? $project['orderer_name'] ?? '') ?>　御中</div>
  <p>下記のとおり御見積り申し上げます。</p>
  <div class="center"><div class="amount">御見積金額　<?= View::yen($sum['quote_total'] + $tax) ?>－</div><div>（税抜 <?= View::yen($sum['quote_total']) ?>　消費税 <?= View::yen($tax) ?>）</div></div>
  <table class="meta">
    <tr><th>工事名称</th><td><?= View::e($project['name']) ?></td></tr>
    <tr><th>工事場所</th><td><?= View::e($project['site_address'] ?? $project['site_name'] ?? '') ?></td></tr>
    <tr><th>工　　期</th><td><?= View::e(View::d($project['period_from'])) ?><?= $project['period_from'] !== null || $project['period_to'] !== null ? ' 〜 ' : '' ?><?= View::e(View::d($project['period_to'])) ?></td></tr>
    <tr><th>支払条件</th><td><?= View::e($project['pay_terms_text'] ?? '') ?></td></tr>
    <tr><th>有効期限</th><td>見積日より30日</td></tr>
  </table>

  <table class="det">
    <thead><tr><th style="width:8mm">No</th><th>工事項目</th><th style="width:14mm">単位</th><th style="width:14mm">数量</th><th style="width:34mm">金額</th><th style="width:30mm">備考</th></tr></thead>
    <tbody>
    <?php foreach ($est['sections'] as $s): ?>
      <tr><td class="c"><?= (int)$s['sort_no'] ?></td><td><?= View::e($s['name']) ?></td><td class="c">式</td><td class="num">1</td><td class="num"><?= View::num($s['quote_total']) ?></td><td></td></tr>
    <?php endforeach; ?>
    <tr class="sub"><td></td><td>小計</td><td></td><td></td><td class="num"><?= View::num($sum['quote_subtotal']) ?></td><td></td></tr>
    <tr><td></td><td>一般管理費</td><td class="c">式</td><td class="num">1</td><td class="num"><?= View::num($sum['general_admin']) ?></td><td><?= View::pct($project['general_admin_rate'], 1) ?></td></tr>
    <tr><td></td><td>現場管理費</td><td class="c">式</td><td class="num">1</td><td class="num"><?= View::num($sum['site_admin']) ?></td><td><?= View::pct($project['site_admin_rate'], 1) ?></td></tr>
    <tr class="total"><td></td><td>合計（税抜）</td><td></td><td></td><td class="num"><?= View::num($sum['quote_total']) ?></td><td></td></tr>
    <tr><td></td><td>消費税（<?= View::pct($project['tax_rate'], 0) ?>）</td><td></td><td></td><td class="num"><?= View::num($tax) ?></td><td></td></tr>
    <tr class="total"><td></td><td>税込合計</td><td></td><td></td><td class="num"><?= View::num($sum['quote_total'] + $tax) ?></td><td></td></tr>
    </tbody>
  </table>
  <div class="company">
    <div class="name"><?= View::e($company['name'] ?? '') ?></div>
    <div>〒<?= View::e($company['zip'] ?? '') ?> <?= View::e($company['address'] ?? '') ?></div>
    <div>TEL <?= View::e($company['tel'] ?? '') ?>　FAX <?= View::e($company['fax'] ?? '') ?></div>
    <div>担当：<?= View::e($project['staff_name'] ?? '') ?></div>
  </div>
  <div class="pageno"><?= $pageNo ?> / <?= $totalPages ?></div>
</div>

<?php foreach ($est['sections'] as $s): ?>
  <?php $rows = $s['print_rows']; $chunks = $rows === [] ? [[]] : array_chunk($rows, $perPage); ?>
  <?php foreach ($chunks as $ci => $chunk): $pageNo++; ?>
  <div class="sheet">
    <div class="page-title"><span><?= (int)$s['sort_no'] ?>. <?= View::e($s['name']) ?></span><span><?= View::e($project['name']) ?></span></div>
    <table class="det">
      <thead><tr><th style="width:7mm">No</th><th>名称</th><th style="width:44mm">材質</th><th style="width:11mm">長</th><th style="width:11mm">巾</th><th style="width:11mm">厚</th><th style="width:10mm">単位</th><th style="width:14mm">数量</th><th style="width:18mm">単価</th><th style="width:24mm">金額</th><th style="width:32mm">備考</th><?php if ($withCost): ?><th style="width:16mm">原単価</th><?php endif; ?></tr></thead>
      <tbody>
      <?php foreach ($chunk as $i => $r): ?>
        <tr>
          <td class="c"><?= $ci * $perPage + $i + 1 ?></td>
          <td><?= View::e($r['name']) ?></td><td><?= View::e($r['material']) ?></td>
          <td class="num"><?= $dim($r['length']) ?></td><td class="num"><?= $dim($r['width']) ?></td><td class="num"><?= $dim($r['thickness']) ?></td>
          <td class="c"><?= View::e($r['unit']) ?></td>
          <td class="num"><?= $r['quantity'] === null ? '' : View::dec($r['quantity'], 2) ?></td>
          <td class="num"><?= $r['unit_price'] === null ? '' : View::num($r['unit_price']) ?></td>
          <td class="num"><?= $r['amount'] === null ? '' : View::num($r['amount']) ?></td>
          <td><?= View::e($r['remarks']) ?></td>
          <?php if ($withCost): ?><td class="num"><?php foreach ($s['lines'] as $l) { if ((int)$l['id'] === (int)$r['line_id']) { echo $l['merged_unit_cost'] === null ? '' : View::num($l['merged_unit_cost']); } } ?></td><?php endif; ?>
        </tr>
      <?php endforeach; ?>
      <?php for ($i = count($chunk); $i < $perPage; $i++): ?>
        <tr><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><?php if ($withCost): ?><td></td><?php endif; ?></tr>
      <?php endfor; ?>
      <?php if ($ci === count($chunks) - 1): ?>
        <tr class="total"><td></td><td colspan="8">小計</td><td class="num"><?= View::num($s['quote_total']) ?></td><td></td><?php if ($withCost): ?><td class="num"><?= View::num($s['material_total'] + $s['labor_total']) ?></td><?php endif; ?></tr>
      <?php endif; ?>
      </tbody>
    </table>
    <div class="pageno"><?= $pageNo ?> / <?= $totalPages ?></div>
  </div>
  <?php endforeach; ?>
<?php endforeach; ?>

<?php if ($notes !== []): $pageNo++; ?>
<div class="sheet notes">
  <div class="page-title"><span>工事全般特記事項</span><span><?= View::e($project['name']) ?></span></div>
  <ol><?php foreach ($notes as $n): ?><li><?= View::e($n['body']) ?></li><?php endforeach; ?></ol>
  <div class="pageno"><?= $pageNo ?> / <?= $totalPages ?></div>
</div>
<?php endif; ?>
