<?php
use App\Core\View;

/**
 * 経理実行予算の本体テーブル（編集画面・印刷で共用）
 * @var array<string,mixed> $project @var array<string,array<int,array<string,mixed>>> $groups @var array<string,string> $groupLabels
 * @var array<string,array<int,float>> $sub @var array<int,float> $direct @var array<int,float> $mq @var array<int,float|null> $mq_rate
 * @var bool $editable
 */
$editable = $editable ?? false;
$cell = static function (string $kind, int $id, mixed $v) use ($editable): string {
    if ($editable) {
        return '<input type="number" step="1" class="num" name="' . $kind . '[' . $id . ']" value="' . View::e($v === null ? '' : (string)(float)$v) . '">';
    }
    return $v === null ? '' : View::num($v);
};
?>
<table class="grid" style="max-width:1000px">
  <thead><tr><th style="width:120px">区分</th><th>項目</th><th class="num" style="width:160px">営業予算</th><th class="num" style="width:160px">工務予算</th></tr></thead>
  <tbody>
  <?php foreach ($groups as $g => $items): ?>
    <?php foreach ($items as $i => $it): ?>
      <tr>
        <?php if ($i === 0): ?><td rowspan="<?= count($items) ?>" style="background:#f3f4f6;font-weight:bold"><?= View::e($groupLabels[$g]) ?></td><?php endif; ?>
        <td><?= View::e($it['name']) ?></td>
        <td class="num"><?= $cell('sales', (int)$it['id'], $it['sales_amount']) ?></td>
        <td class="num"><?= $cell('work', (int)$it['id'], $it['work_amount']) ?></td>
      </tr>
    <?php endforeach; ?>
    <tr class="sub"><td colspan="2" class="right"><?= View::e($groupLabels[$g]) ?> 計</td><td class="num"><?= View::num($sub[$g][0]) ?></td><td class="num"><?= View::num($sub[$g][1]) ?></td></tr>
  <?php endforeach; ?>
    <tr><td colspan="2" class="right">労災保険料（税込契約額 × <?= View::pct($project['insurance_rate'], 2) ?>）</td><td class="num"><?= View::num($insurance) ?></td><td class="num"><?= View::num($insurance) ?></td></tr>
    <tr><td colspan="2" class="right">工事監理費（契約額 × <?= View::pct($project['supervision_rate'], 1) ?>）</td><td class="num"><?= View::num($supervision) ?></td><td class="num"><?= View::num($supervision) ?></td></tr>
    <tr><td colspan="2" class="right">事業所経費（契約額 × <?= View::pct($project['budget_office_rate'], 1) ?>）</td><td class="num"><?= View::num($office) ?></td><td class="num"><?= View::num($office) ?></td></tr>
    <tr class="sub"><td colspan="2" class="right">直接原価 合計</td><td class="num"><?= View::num($direct[0]) ?></td><td class="num"><?= View::num($direct[1]) ?></td></tr>
    <tr class="sub"><td colspan="2" class="right">MQ（契約額 − 直接原価）</td><td class="num"><?= View::num($mq[0]) ?></td><td class="num"><?= View::num($mq[1]) ?></td></tr>
    <tr class="sub"><td colspan="2" class="right">MQ率</td><td class="num"><?= $mq_rate[0] === null ? '-' : View::pct($mq_rate[0], 1) ?></td><td class="num"><?= $mq_rate[1] === null ? '-' : View::pct($mq_rate[1], 1) ?></td></tr>
  </tbody>
</table>
