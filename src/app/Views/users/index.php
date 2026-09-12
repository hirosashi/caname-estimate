<?php
use App\Core\App;
use App\Core\Csrf;
use App\Core\View;

/** @var array<int,array<string,mixed>> $rows @var array<string,string> $roles */
?>
<div class="card" style="max-width:1000px">
  <h2>ユーザー一覧</h2>
  <table class="grid">
    <thead><tr><th>ID</th><th>ログインID</th><th>氏名</th><th>権限</th><th>有効</th><th>新パスワード（変更時のみ）</th><th>最終ログイン</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <?php $fid = 'u' . (int)$r['id']; ?>
        <td class="num"><?= (int)$r['id'] ?></td>
        <td><input type="text" name="login_id" form="<?= $fid ?>" value="<?= View::e($r['login_id']) ?>" required class="s"></td>
        <td><input type="text" name="name" form="<?= $fid ?>" value="<?= View::e($r['name']) ?>" required class="m"></td>
        <td><?= View::select('role', $roles, $r['role'], 'form="' . $fid . '"') ?></td>
        <td class="center"><input type="checkbox" name="is_active" form="<?= $fid ?>" value="1" <?= (int)$r['is_active'] === 1 ? 'checked' : '' ?>></td>
        <td><input type="password" name="password" form="<?= $fid ?>" autocomplete="new-password" placeholder="8文字以上" class="m"><?= (int)$r['must_change_pw'] === 1 ? '<div class="muted">初回変更待ち</div>' : '' ?></td>
        <td class="muted"><?= View::e(View::dt($r['last_login_at'])) ?></td>
        <td><button class="btn sec sm" form="<?= $fid ?>">保存</button></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php foreach ($rows as $r): ?>
    <form method="post" action="<?= View::e(App::url('/users/save')) ?>" id="u<?= (int)$r['id'] ?>"><?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"></form>
  <?php endforeach; ?>
</div>
<div class="card" style="max-width:1000px">
  <h2>ユーザー追加</h2>
  <form method="post" action="<?= View::e(App::url('/users/save')) ?>" class="row">
    <?= Csrf::field() ?><input type="hidden" name="id" value="0">
    <div><label>ログインID</label><input type="text" name="login_id" required></div>
    <div><label>氏名</label><input type="text" name="name" required></div>
    <div><label>権限</label><?= View::select('role', $roles, 'sales') ?></div>
    <div><label>初期パスワード（8文字以上）</label><input type="password" name="password" required minlength="8" autocomplete="new-password"></div>
    <div><label><input type="checkbox" name="is_active" value="1" checked> 有効</label></div>
    <div><button class="btn">追加</button></div>
  </form>
  <p class="muted">権限: 管理者=全操作／営業担当=案件・明細・予算・マスタ／経理担当=案件・予算／閲覧のみ=参照のみ</p>
</div>
