#!/usr/bin/env bash
# src/ を さくら共通開発サイトへ反映（config.local.php・ログは除外）。SAKURA_SSH_* は secret から渡す。
# サーバ側に ~/.caname_auth.htaccess（Basic認証の設定）があれば .htaccess の末尾に追記して維持する。
set -euo pipefail
cd "$(dirname "$0")"
KEY=${SAKURA_SSH_KEY_FILE:-$HOME/.ssh/sakura_key}
DEST=${CANAME_DEV_DIR:-/home/jyunbi/www/caname}
H="$SAKURA_SSH_USER@$SAKURA_SSH_HOST"
tar czf /tmp/caname_src.tgz -C src --exclude='config/config.local.php' --exclude='storage/logs/*.log' .
scp -q -i "$KEY" -o StrictHostKeyChecking=no /tmp/caname_src.tgz "$H:/tmp/caname_src.tgz"
ssh -i "$KEY" -o StrictHostKeyChecking=no "$H" "mkdir -p $DEST && tar xzf /tmp/caname_src.tgz -C $DEST && rm -f /tmp/caname_src.tgz && chmod 707 $DEST/storage/logs ; test -f ~/.caname_auth.htaccess && cat ~/.caname_auth.htaccess >> $DEST/.htaccess; echo deployed"
rm -f /tmp/caname_src.tgz
