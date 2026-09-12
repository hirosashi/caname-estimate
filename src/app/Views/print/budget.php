<?php
use App\Core\View;

/** @var array<string,mixed> $project @var array<string,mixed> $est @var array<string,mixed> $sum */
$vars = get_defined_vars();
$kv = static fn(string $th, string $td): string => '<tr><th>' . View::e($th) . '</th><td>' . $td . '</td></tr>';
?>
<style>
.hdr{display:flex;gap:6mm}.hdr table{border-collapse:collapse;flex:1;font-size:9.5pt}.hdr th{text-align:left;width:26mm;border-bottom:1px solid #999;padding:1mm 0;font-weight:normal;vertical-align:top}.hdr td{border-bottom:1px solid #999;padding:1mm 0}
.grid{border-collapse:collapse;width:100%;font-size:9.5pt;margin-top:3mm}.grid th,.grid td{border:1px solid #000;padding:1mm 1.5mm}.grid .num,.grid td.right{text-align:right}.grid tr.sub td{font-weight:bold;background:#f0f0f0}
</style>
<div class="sheet">
  <div class="page-title"><span>経理実行予算書</span><span>作成日 <?= View::e(View::d($project['budget_date'])) ?>　契約日 <?= View::e(View::d($project['contract_date'])) ?></span></div>
  <div class="hdr">
    <table>
      <?= $kv('件名', View::e($project['name'])) ?>
      <?= $kv('工事番号', View::e($project['code'])) ?>
      <?= $kv('担当者', View::e($project['staff_name'])) ?>
      <?= $kv('工事場所', View::e($project['site_name']) . '<br>' . View::e($project['site_address']) . ' TEL ' . View::e($project['site_tel'])) ?>
      <?= $kv('発注者', View::e($project['orderer_name']) . ' ' . View::e($project['orderer_staff'])) ?>
      <?= $kv('設計事務所', View::e($project['designer_name'])) ?>
      <?= $kv('元請', View::e($project['contractor_name'])) ?>
      <?= $kv('工期', View::e(View::d($project['period_from'])) . ' 〜 ' . View::e(View::d($project['period_to'])) . '　着工 ' . View::e(View::d($project['start_date']))) ?>
    </table>
    <table>
      <?= $kv('支払条件', View::e($project['pay_close']) . '締 ' . View::e($project['pay_day']) . '払　現金' . View::e(View::dec($project['pay_cash_pct'])) . '% 手形' . View::e(View::dec($project['pay_bill_pct'])) . '% サイト' . View::e(View::dec($project['pay_site_days'])) . '日') ?>
      <?= $kv('屋根仕様', View::e($project['roof_spec']) . ' / ' . View::e($project['roof_material']) . ' / ' . View::e($project['roof_thickness']) . ' / ' . View::e($project['roof_product']) . ' / ' . View::e($project['roof_color']) . '　' . View::e(View::dec($project['roof_area'])) . '㎡') ?>
      <?= $kv('工事種別', View::e($project['work_type'])) ?>
      <?= $kv('情報源', View::e($project['info_source']) . '（' . View::e($project['repeat_kind']) . '）') ?>
      <?= $kv('見積金額', View::yen($project['estimate_amount'])) ?>
      <?= $kv('契約金額', View::yen($project['contract_amount']) . '　追加1 ' . View::yen($project['extra1_amount']) . '　追加2 ' . View::yen($project['extra2_amount'])) ?>
      <?= $kv('消費税/税込', View::yen($tax) . ' / ' . View::yen($with_tax)) ?>
      <?= $kv('備考', View::e($project['note_receipt']) . ' ' . View::e($project['note_billing']) . ' ' . View::e($project['note_accounting']) . ' 入金予定 ' . View::e(View::d($project['payment_due_date']))) ?>
    </table>
  </div>
  <?= View::partial('budget/_table', $vars + ['editable' => false]) ?>
  <p style="font-size:9pt">参考：明細書 材料代 <?= View::yen($est['material_total']) ?>　手間代 <?= View::yen($est['labor_total']) ?>　営業実行予算 直接原価 <?= View::yen($sum['direct_cost']) ?></p>
</div>
