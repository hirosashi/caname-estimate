<?php
use App\Core\App;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\View;

/** @var array<int,array<string,mixed>> $rows @var array<string,string> $statuses */
$can = Auth::can('projects');
?>
<div class="card">
  <form method="get" action="<?= View::e(App::url('/projects')) ?>" class="row">
    <div><label>検索</label><input type="text" name="q" value="<?= View::e($q) ?>" placeholder="案件名・番号・顧客・現場" style="width:260px"></div>
    <div><label>状態</label><?= View::select('status', $statuses, $status, '', 'すべて') ?></div>
    <div><button class="btn sec">絞り込み</button></div>
    <?php if ($can): ?><div style="margin-left:auto"><a class="btn" href="<?= View::e(App::url('/projects/new')) ?>">＋ 新規案件</a></div><?php endif; ?>
  </form>
</div>
<div class="card">
  <table class="grid">
    <thead><tr><th>案件No</th><th>案件名</th><th>顧客</th><th>現場</th><th>担当</th><th>状態</th><th>見積日</th><th>工事項目</th><th class="num">見積小計（税抜・管理費前）</th><th>更新</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $id = (int)$r['id']; ?>
      <tr>
        <td><?= View::e($r['code']) ?></td>
        <td><a href="<?= View::e(App::url('/estimate?project_id=' . $id)) ?>"><?= View::e($r['name']) ?></a></td>
        <td><?= View::e($r['customer_name']) ?></td>
        <td><?= View::e($r['site_name']) ?></td>
        <td><?= View::e($r['staff_name']) ?></td>
        <td><?= View::e($statuses[$r['status']] ?? $r['status']) ?></td>
        <td><?= View::e(View::d($r['estimate_date'])) ?></td>
        <td class="num"><?= (int)$r['section_count'] ?></td>
        <td class="num"><?= View::yen($r['quote_total']) ?></td>
        <td class="muted"><?= View::e(View::dt($r['updated_at'])) ?></td>
        <td style="white-space:nowrap">
          <a class="btn sec sm" href="<?= View::e(App::url('/projects/edit?project_id=' . $id)) ?>">案件情報</a>
          <a class="btn sec sm" href="<?= View::e(App::url('/estimate?project_id=' . $id)) ?>">明細書</a>
          <a class="btn sec sm" href="<?= View::e(App::url('/print?project_id=' . $id)) ?>" target="_blank">見積書</a>
          <?php if ($can): ?>
          <form method="post" action="<?= View::e(App::url('/projects/copy')) ?>" class="inline"><?= Csrf::field() ?><input type="hidden" name="project_id" value="<?= $id ?>"><button class="btn sec sm" onclick="return confirm('この案件を複製しますか？')">複製</button></form>
          <form method="post" action="<?= View::e(App::url('/projects/delete')) ?>" class="inline"><?= Csrf::field() ?><input type="hidden" name="project_id" value="<?= $id ?>"><button class="btn warn sm" onclick="return confirm('案件「<?= View::e($r['name']) ?>」を削除します。明細・予算もすべて削除されます。よろしいですか？')">削除</button></form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if ($rows === []): ?><tr><td colspan="11" class="muted">案件がありません。</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
