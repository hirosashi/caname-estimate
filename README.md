# カナメ 見積・実行予算システム（PHP + MySQL）

Excel「見積実行予算作成ツール」をWeb化したもの。素のPHP 8.1 + PDO、Composer不使用。

## 構成
- `src/index.php` ルート定義・エントリ / `src/app/{Core,Controllers,Services,Views}`
- `db/schema.sql` スキーマ正本 / `db/seed_real.sql` Excel由来マスタ（品目2,533・カテゴリ39・特記事項27・選択肢・サンプル案件）
- `tools/build_seed.py` Excel(`tools/source/見積実行予算作成ツール*.xlsx`) → seed_real.sql 生成（openpyxl）
- `tests/calc_test.php` 明細書計算のExcel突合 / `tests/smoke_post.py` 保存系ルートの往復検査
- `docs/excel_analysis.md` 元Excelの解析メモ

## ローカル起動
```bash
cp src/config/config.sample.php src/config/config.local.php   # DB接続を編集
mysql caname_dev < db/schema.sql && mysql caname_dev < db/seed_real.sql
php -S 127.0.0.1:8088 -t src dev_router.php
```
初期管理者 `admin`（パスワードは seed 生成時の `CANAME_ADMIN_PW`。初回ログイン後に変更）。

## 検査
```bash
find src tests tools -name '*.php' -print0 | xargs -0 -n1 php -l | grep -v '^No syntax errors'
php tests/calc_test.php
python3 tests/smoke_post.py 2   # サーバ起動後
```

## 共通開発サイト（さくら）への反映
- URL: https://jyunbi.sakura.ne.jp/caname （配置先 `/home/jyunbi/www/caname/`、`config/config.php` はサーバ側のみ・Git管理外）
- 反映: `SAKURA_SSH_HOST/USER` と鍵 `~/.ssh/sakura_key` を用意して `./deploy.sh`
- 反映後の確認: `CANAME_BASE=https://jyunbi.sakura.ne.jp/caname python3 tests/smoke_post.py 1`
- スキーマ変更は `db/schema.sql` に ALTER/CREATE を追記し、ローカル → 開発サイトの順に適用（DROP禁止）

### 開発サイトの Basic 認証

開発サイトはサーバ側の `~/.caname_htpasswd` と `~/.caname_auth.htaccess` で Basic 認証をかけている（Git 管理外）。`deploy.sh` は展開後にこの設定を `.htaccess` 末尾へ追記するので、反映しても認証は外れない。
