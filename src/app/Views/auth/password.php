<?php
use App\Core\App;
use App\Core\Csrf;
use App\Core\View;
?>
<div class="card" style="max-width:480px">
  <h2>パスワード変更</h2>
  <form method="post" action="<?= View::e(App::url('/password')) ?>">
    <?= Csrf::field() ?>
    <div class="form-grid" style="grid-template-columns:1fr">
      <div><label>現在のパスワード</label><input type="password" name="current" required autocomplete="current-password"></div>
      <div><label>新しいパスワード（8文字以上）</label><input type="password" name="new" required minlength="8" autocomplete="new-password"></div>
      <div><label>新しいパスワード（確認）</label><input type="password" name="new2" required minlength="8" autocomplete="new-password"></div>
      <div><button class="btn">変更する</button></div>
    </div>
  </form>
</div>
