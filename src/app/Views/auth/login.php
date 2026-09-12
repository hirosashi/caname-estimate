<?php
use App\Core\App;
use App\Core\Csrf;
use App\Core\View;
?>
<div class="card">
  <h2>カナメ見積・実行予算システム</h2>
  <form method="post" action="<?= View::e(App::url('/login')) ?>">
    <?= Csrf::field() ?>
    <div class="form-grid" style="grid-template-columns:1fr">
      <div><label>ログインID</label><input type="text" name="login_id" required autofocus autocomplete="username"></div>
      <div><label>パスワード</label><input type="password" name="password" required autocomplete="current-password"></div>
      <div><button class="btn" style="width:100%">ログイン</button></div>
    </div>
  </form>
</div>
