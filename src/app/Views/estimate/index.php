<?php
use App\Core\App;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\View;

/** @var array<string,mixed> $project @var array<string,mixed> $est @var int $open @var array<int,array<string,mixed>> $categories @var int $maxLines */
$pid = (int)$project['id'];
$can = Auth::can('estimate');
$ro = $can ? '' : ' readonly disabled';
$cur = null;
foreach ($est['sections'] as $s) {
    if ((int)$s['id'] === $open) {
        $cur = $s;
    }
}
$fmt = static fn(mixed $v, int $d = 0): string => $v === null ? '' : View::num((float)$v, $d);
$lineNo = [];
if ($cur !== null) {
    foreach ($cur['lines'] as $i => $l) {
        $lineNo[(int)$l['id']] = $i + 1;
    }
}
?>
<div class="card">
  <form method="post" action="<?= View::e(App::url('/estimate/settings')) ?>" class="row">
    <?= Csrf::field() ?><input type="hidden" name="project_id" value="<?= $pid ?>">
    <div><label>一律人工単価（空欄なら品目の人工単価）</label><input type="number" step="1" class="num" name="uniform_labor_price" value="<?= View::e($project['uniform_labor_price'] === null ? '' : (string)(float)$project['uniform_labor_price']) ?>" style="width:140px"<?= $ro ?>></div>
    <div><label>一律掛率（原価÷掛率＝見積単価）</label><input type="number" step="0.01" min="0.01" max="1" class="num" name="uniform_rate" value="<?= View::e((string)(float)$project['uniform_rate']) ?>" style="width:100px"<?= $ro ?>></div>
    <?php if ($can): ?><div><button class="btn sec">設定を保存</button></div><?php endif; ?>
    <div style="margin-left:auto" class="right">
      <div class="muted">材料代合計 <?= View::yen($est['material_total']) ?>　手間代合計 <?= View::yen($est['labor_total']) ?>（<?= $fmt($est['labor_count_total'], 2) ?> 人工）</div>
      <div class="big">見積小計 <?= View::yen($est['quote_total']) ?></div>
    </div>
  </form>
</div>

<div class="card">
  <h2>工事項目（<?= count($est['sections']) ?>/15）</h2>
  <div class="section-nav">
    <?php foreach ($est['sections'] as $s): ?>
      <a href="<?= View::e(App::url('/estimate?project_id=' . $pid . '&section=' . (int)$s['id'])) ?>" class="<?= (int)$s['id'] === $open ? 'on' : '' ?>"><?= (int)$s['sort_no'] ?>. <?= View::e($s['name']) ?> <span class="muted"><?= View::yen($s['quote_total']) ?></span></a>
    <?php endforeach; ?>
  </div>
  <?php if ($can && count($est['sections']) < 15): ?>
  <form method="post" action="<?= View::e(App::url('/estimate/section/add')) ?>" class="row">
    <?= Csrf::field() ?><input type="hidden" name="project_id" value="<?= $pid ?>">
    <div><input type="text" name="name" placeholder="新しい工事項目名（例 屋根工事）" style="width:280px"></div>
    <div><button class="btn sm">工事項目を追加</button></div>
  </form>
  <?php endif; ?>
</div>

<?php if ($cur !== null): ?>
<div class="card">
  <form method="post" action="<?= View::e(App::url('/estimate/section/save')) ?>" class="row">
    <?= Csrf::field() ?><input type="hidden" name="project_id" value="<?= $pid ?>"><input type="hidden" name="section_id" value="<?= (int)$cur['id'] ?>">
    <div><label>工事項目名</label><input type="text" name="name" value="<?= View::e($cur['name']) ?>" style="width:320px"<?= $ro ?>></div>
    <div><label>ロス率（例 0.07）</label><input type="number" step="0.01" min="0" max="1" name="loss_rate" class="num" value="<?= View::e((string)(float)$cur['loss_rate']) ?>" style="width:90px"<?= $ro ?>></div>
    <?php if ($can): ?>
      <div><button class="btn sec sm">項目を保存</button></div>
      <div><button class="btn sec sm" formaction="<?= View::e(App::url('/estimate/section/move')) ?>" name="dir" value="up">↑</button>
           <button class="btn sec sm" formaction="<?= View::e(App::url('/estimate/section/move')) ?>" name="dir" value="down">↓</button></div>
      <div><button class="btn sec sm" formaction="<?= View::e(App::url('/estimate/lines/refresh')) ?>" onclick="return confirm('この工事項目の行を単価マスタの現在値で更新します。よろしいですか？')">単価マスタ再取得</button></div>
      <div style="margin-left:auto"><button class="btn warn sm" formaction="<?= View::e(App::url('/estimate/section/delete')) ?>" onclick="return confirm('工事項目「<?= View::e($cur['name']) ?>」と明細行を削除します。よろしいですか？')">この工事項目を削除</button></div>
    <?php endif; ?>
  </form>
</div>

<form method="post" action="<?= View::e(App::url('/estimate/lines/save')) ?>" id="lines-form" data-loss="<?= (float)$cur['loss_rate'] ?>" data-labor="<?= $project['uniform_labor_price'] === null ? '' : (float)$project['uniform_labor_price'] ?>" data-rate="<?= (float)$project['uniform_rate'] ?>">
<?= Csrf::field() ?><input type="hidden" name="project_id" value="<?= $pid ?>"><input type="hidden" name="section_id" value="<?= (int)$cur['id'] ?>">
<div class="card" style="overflow-x:auto">
  <table class="grid" id="lines">
    <thead>
      <tr>
        <th rowspan="2">No</th><th rowspan="2">コード</th><th rowspan="2">項目</th><th rowspan="2">材質</th>
        <th>長</th><th>巾</th><th>厚</th><th rowspan="2">実数</th><th rowspan="2">単位</th>
        <th>材料数</th><th>材料単価</th><th rowspan="2">材料代</th>
        <th>歩掛</th><th>人工数</th><th>人工単価</th><th rowspan="2">手間代</th>
        <th rowspan="2">原単価</th><th rowspan="2" title="この行を見積書の行にする">見積行</th><th rowspan="2" title="見積行にしない行を、どのNoの行に合算するか">合算先<br>No</th>
        <th rowspan="2">合算<br>原単価</th><th>掛率</th><th rowspan="2">見積単価</th><th rowspan="2">見積金額</th><th rowspan="2">見積書 表示名 / 備考</th>
      </tr>
      <tr><th colspan="3" class="muted">m</th><th class="muted">切上</th><th class="muted">円</th><th class="muted">/実数</th><th class="muted">実数×歩掛</th><th class="muted">円</th><th class="muted">空欄=一律</th></tr>
    </thead>
    <tbody>
    <?php for ($i = 0; $i < $maxLines; $i++): $l = $cur['lines'][$i] ?? null; $n = $i + 1; $k = "rows[$i]"; ?>
      <tr class="<?= $l !== null && (int)$l['is_quote'] === 1 ? 'quote' : '' ?>" data-i="<?= $i ?>">
        <td class="num"><?= $n ?><input type="hidden" name="<?= $k ?>[id]" value="<?= $l === null ? 0 : (int)$l['id'] ?>"></td>
        <td><input type="text" class="s code" name="<?= $k ?>[item_code]" value="<?= View::e($l['item_code'] ?? '') ?>" list="" autocomplete="off"<?= $ro ?>>
            <?php if ($can): ?><button type="button" class="btn sec sm pick" title="単価マスタから選ぶ">…</button><?php endif; ?></td>
        <td><input type="text" class="m name" name="<?= $k ?>[name]" value="<?= View::e($l['name'] ?? '') ?>" style="width:190px"<?= $ro ?>></td>
        <td><input type="text" class="s material" name="<?= $k ?>[material]" value="<?= View::e($l['material'] ?? '') ?>" style="width:110px"<?= $ro ?>></td>
        <td><input type="number" step="any" class="xs num length" name="<?= $k ?>[length]" value="<?= View::e(($l['length'] ?? null) === null ? '' : (string)(float)$l['length']) ?>"<?= $ro ?>></td>
        <td><input type="number" step="any" class="xs num width" name="<?= $k ?>[width]" value="<?= View::e(($l['width'] ?? null) === null ? '' : (string)(float)$l['width']) ?>"<?= $ro ?>></td>
        <td><input type="number" step="any" class="xs num thickness" name="<?= $k ?>[thickness]" value="<?= View::e(($l['thickness'] ?? null) === null ? '' : (string)(float)$l['thickness']) ?>"<?= $ro ?>></td>
        <td><input type="number" step="any" class="s num quantity" name="<?= $k ?>[quantity]" value="<?= View::e(($l['quantity'] ?? null) === null ? '' : (string)(float)$l['quantity']) ?>"<?= $ro ?>></td>
        <td><input type="text" class="xs area_unit" name="<?= $k ?>[area_unit]" value="<?= View::e($l['area_unit'] ?? '') ?>" style="width:40px"<?= $ro ?>></td>
        <td class="num calc mat_count"><?= $fmt($l['mat_count'] ?? null) ?></td>
        <td><input type="number" step="any" class="s num material_price" name="<?= $k ?>[material_price]" value="<?= View::e(($l['material_price'] ?? null) === null ? '' : (string)(float)$l['material_price']) ?>"<?= $ro ?>></td>
        <td class="num calc mat_cost"><?= $fmt($l['mat_cost'] ?? null) ?></td>
        <td><input type="number" step="any" class="xs num labor_rate" name="<?= $k ?>[labor_rate]" value="<?= View::e(($l['labor_rate'] ?? null) === null ? '' : (string)(float)$l['labor_rate']) ?>"<?= $ro ?>></td>
        <td class="num calc labor_count"><?= $fmt($l['labor_count'] ?? null, 2) ?></td>
        <td><input type="number" step="any" class="s num labor_unit_price" name="<?= $k ?>[labor_unit_price]" value="<?= View::e(($l['labor_unit_price'] ?? null) === null ? '' : (string)(float)$l['labor_unit_price']) ?>"<?= $ro ?>><?php if ($project['uniform_labor_price'] !== null): ?><div class="muted" style="font-size:10px">一律 <?= $fmt($project['uniform_labor_price']) ?></div><?php endif; ?></td>
        <td class="num calc labor_cost"><?= $fmt($l['labor_cost'] ?? null) ?></td>
        <td class="num calc unit_cost"><?= $fmt($l['unit_cost'] ?? null, 1) ?></td>
        <td class="center"><input type="checkbox" class="is_quote" name="<?= $k ?>[is_quote]" value="1" <?= $l !== null && (int)$l['is_quote'] === 1 ? 'checked' : '' ?><?= $ro ?>></td>
        <td><input type="number" class="xs num merge_no" name="<?= $k ?>[merge_no]" min="1" max="<?= $maxLines ?>" value="<?= $l !== null && $l['merge_into_line_id'] !== null && isset($lineNo[(int)$l['merge_into_line_id']]) ? $lineNo[(int)$l['merge_into_line_id']] : '' ?>" style="width:44px"<?= $ro ?>></td>
        <td class="num calc merged_unit_cost"><?= $fmt($l['merged_unit_cost'] ?? null, 1) ?></td>
        <td><input type="number" step="0.01" min="0.01" max="1" class="xs num rate_override" name="<?= $k ?>[rate_override]" value="<?= View::e($l === null || $l['rate_override'] === null ? '' : (string)(float)$l['rate_override']) ?>" placeholder="<?= (float)$project['uniform_rate'] ?>" style="width:50px"<?= $ro ?>></td>
        <td class="num calc quote_price"><?= $fmt($l['quote_price'] ?? null) ?></td>
        <td class="num calc quote_amount"><?= $fmt($l['quote_amount'] ?? null) ?></td>
        <td><input type="text" name="<?= $k ?>[print_name]" value="<?= View::e($l['print_name'] ?? '') ?>" placeholder="表示名（空欄=項目名）" style="width:150px"<?= $ro ?>>
            <input type="text" name="<?= $k ?>[print_material]" value="<?= View::e($l['print_material'] ?? '') ?>" placeholder="材質表示" style="width:90px"<?= $ro ?>>
            <input type="text" name="<?= $k ?>[remarks]" value="<?= View::e($l['remarks'] ?? '') ?>" placeholder="備考" style="width:120px"<?= $ro ?>></td>
      </tr>
    <?php endfor; ?>
    </tbody>
    <tfoot>
      <tr class="sub">
        <td colspan="11" class="right">工事項目 計</td>
        <td class="num" id="t_mat"><?= $fmt($cur['material_total']) ?></td>
        <td></td><td class="num" id="t_labor_count"><?= $fmt($cur['labor_count_total'], 2) ?></td><td></td>
        <td class="num" id="t_labor"><?= $fmt($cur['labor_total']) ?></td>
        <td colspan="6"></td>
        <td class="num" id="t_quote"><?= $fmt($cur['quote_total']) ?></td><td></td>
      </tr>
    </tfoot>
  </table>
  <p class="muted">表示中の計算値はブラウザ上の目安です。「明細を保存」でサーバー側で確定計算されます。コード入力またはマスタ選択で、項目名・寸法・単価が単価マスタから転記されます（以降は案件内で独立した値になります）。</p>
  <?php if ($can): ?>
  <div class="sticky-actions"><button class="btn">明細を保存</button></div>
  <?php endif; ?>
</div>
</form>

<div id="picker">
  <div class="box">
    <div class="head">
      <select id="pk-cat"><option value="0">全カテゴリ</option><?php foreach ($categories as $c): ?><option value="<?= (int)$c['id'] ?>"><?= View::e($c['name']) ?></option><?php endforeach; ?></select>
      <input type="text" id="pk-q" placeholder="コード・項目・材質で検索">
      <button type="button" class="btn sec sm" id="pk-close">閉じる</button>
    </div>
    <div class="list"><table class="grid"><thead><tr><th>コード</th><th>カテゴリ</th><th>項目</th><th>材質</th><th>長</th><th>巾</th><th>単位</th><th>材料単価</th><th>歩掛</th><th>人工単価</th></tr></thead><tbody id="pk-body"></tbody></table></div>
  </div>
</div>
<script>window.CANAME_SEARCH_URL = <?= json_encode(App::url('/items/search'), JSON_UNESCAPED_SLASHES) ?>;</script>
<?php else: ?>
<div class="card"><p>工事項目がありません。上のフォームから追加してください。</p></div>
<?php endif; ?>
