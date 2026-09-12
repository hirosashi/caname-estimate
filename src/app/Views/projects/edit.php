<?php
use App\Core\App;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\View;

/** @var array<string,mixed> $p @var array<string,array<int,string>> $options @var array<string,string> $statuses */
$can = Auth::can('projects');
$ro = $can ? '' : ' readonly disabled';
$id = (int)($p['id'] ?? 0);
$txt = static function (string $f, string $label, string $extra = '') use ($p, $ro): string {
    return '<div' . $extra . '><label>' . View::e($label) . '</label><input type="text" name="' . $f . '" value="' . View::e($p[$f] ?? '') . '"' . $ro . '></div>';
};
$date = static function (string $f, string $label) use ($p, $ro): string {
    return '<div><label>' . View::e($label) . '</label><input type="date" name="' . $f . '" value="' . View::e($p[$f] ?? '') . '"' . $ro . '></div>';
};
$num = static function (string $f, string $label, string $step = 'any') use ($p, $ro): string {
    $v = $p[$f] ?? null;
    return '<div><label>' . View::e($label) . '</label><input type="number" step="' . $step . '" class="num" name="' . $f . '" value="' . View::e($v === null ? '' : (string)(float)$v) . '"' . $ro . '></div>';
};
/** 選択肢＋自由入力（datalist） */
$pick = static function (string $f, string $label, string $listKey) use ($p, $options, $ro): string {
    $opts = $options[$listKey] ?? [];
    $h = '<div><label>' . View::e($label) . '</label><input type="text" name="' . $f . '" list="dl_' . $f . '" value="' . View::e($p[$f] ?? '') . '"' . $ro . '>';
    $h .= '<datalist id="dl_' . $f . '">';
    foreach ($opts as $o) {
        $h .= '<option value="' . View::e($o) . '">';
    }
    return $h . '</datalist></div>';
};
$party = static function (string $prefix, string $label) use ($txt): string {
    return '<div class="card"><h2>' . View::e($label) . '</h2><div class="form-grid">'
        . $txt($prefix . '_name', '名称', ' class="wide"') . $txt($prefix . '_staff', '担当者') . $txt($prefix . '_zip', '郵便番号')
        . $txt($prefix . '_address', '住所', ' class="wide"') . $txt($prefix . '_tel', 'TEL') . $txt($prefix . '_fax', 'FAX')
        . '</div></div>';
};
?>
<form method="post" action="<?= View::e(App::url('/projects/save')) ?>">
<?= Csrf::field() ?><input type="hidden" name="id" value="<?= $id ?>">
<div class="card">
  <h2>案件</h2>
  <div class="form-grid">
    <?= $txt('code', '案件No（工事番号）') ?>
    <div class="wide"><label>件名（工事名）*</label><input type="text" name="name" required value="<?= View::e($p['name'] ?? '') ?>"<?= $ro ?>></div>
    <div><label>状態</label><?= View::select('status', $statuses, $p['status'] ?? 'draft', $ro) ?></div>
    <?= $txt('staff_name', '担当者') ?>
    <?= $txt('customer_name', '顧客名（見積書 宛名）', ' class="wide"') ?>
    <?= $date('estimate_date', '見積日') ?>
    <?= $date('budget_date', '実行予算 作成日') ?>
    <?= $date('contract_date', '契約日') ?>
    <?= $date('period_from', '工期 自') ?>
    <?= $date('period_to', '工期 至') ?>
    <?= $date('start_date', '着工日') ?>
    <?= $pick('work_type', '工事種別', 'work_type') ?>
    <?= $pick('info_source', '情報入手先', 'info_source') ?>
    <?= $pick('repeat_kind', '新規／リピート', 'repeat_kind') ?>
  </div>
</div>

<div class="card">
  <h2>現場</h2>
  <div class="form-grid">
    <?= $txt('site_name', '現場名', ' class="wide"') ?><?= $txt('site_zip', '郵便番号') ?><?= $txt('site_address', '現場住所', ' class="wide"') ?><?= $txt('site_tel', 'TEL') ?><?= $txt('site_fax', 'FAX') ?>
  </div>
</div>

<?= $party('orderer', '発注者（注文者）') ?>
<?= $party('designer', '設計事務所') ?>
<?= $party('contractor', '元請（施工会社）') ?>

<div class="card">
  <h2>屋根仕様</h2>
  <div class="form-grid">
    <?= $pick('roof_spec', '仕様', 'roof_spec') ?><?= $pick('roof_material', '材質', 'roof_material') ?><?= $pick('roof_thickness', '厚み', 'roof_thickness') ?>
    <?= $pick('roof_product', '商品名', 'roof_product') ?><?= $txt('roof_color', '色') ?><?= $num('roof_area', '屋根面積（㎡）') ?>
  </div>
</div>

<div class="card">
  <h2>支払条件・経理メモ</h2>
  <div class="form-grid">
    <?= $pick('pay_close', '締日', 'pay_close') ?><?= $pick('pay_day', '支払日', 'pay_day') ?>
    <?= $num('pay_cash_pct', '現金 %') ?><?= $num('pay_bill_pct', '手形 %') ?><?= $num('pay_site_days', '手形サイト（日）', '1') ?>
    <?= $date('payment_due_date', '入金予定日') ?>
    <?= $pick('pay_terms_text', '支払条件（文言）', 'pay_terms') ?>
    <?= $pick('note_receipt', '受領書', 'receipt') ?><?= $pick('note_billing', '請求書', 'billing') ?><?= $pick('note_accounting', '経理', 'accounting') ?>
    <div class="wide"><label>備考</label><textarea name="remarks" rows="3"<?= $ro ?>><?= View::e($p['remarks'] ?? '') ?></textarea></div>
  </div>
</div>

<?php if ($can): ?>
<div class="card right">
  <a class="btn sec" href="<?= View::e(App::url('/projects')) ?>">一覧へ</a>
  <button class="btn"><?= $id > 0 ? '保存' : '作成して明細書へ' ?></button>
</div>
<?php endif; ?>
</form>
