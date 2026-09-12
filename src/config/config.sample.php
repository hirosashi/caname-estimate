<?php
// 本番/開発サーバ用は config.php にコピーして値を設定する（Git管理外）
return [
    'db' => ['host' => 'localhost', 'name' => 'DB名', 'user' => 'ユーザー', 'pass' => 'パスワード'],
    'base_path' => '/caname',   // 公開URLのサブディレクトリ（ルート直下なら ''）
    'debug' => false,
    'company' => ['name' => '株式会社カナメ', 'zip' => '321-0932', 'address' => '栃木県宇都宮市平出工業団地38-52', 'tel' => '028-663-6300', 'fax' => '028-660-3858'],
];
