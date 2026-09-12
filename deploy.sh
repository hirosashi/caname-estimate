#!/usr/bin/env bash
# src/ を さくら共通開発サイトへ反映（config.local.php・ログは除外）。SAKURA_SSH_* は secret から渡す。
set -euo pipefail
cd "$(dirname "$0")"
KEY=${SAKURA_SSH_KEY_FILE:-$HOME/.ssh/sakura_key}
DEST=${KANAME_DEV_DIR:-/home/jyunbi/www/caname}
H="$SAKURA_SSH_USER@$SAKURA_SSH_HOST"
tar czf /tmp/kaname_src.tgz -C src --exclude='config/config.local.php' --exclude='storage/logs/*.log' .
scp -q -i "$KEY" -o StrictHostKeyChecking=no /tmp/kaname_src.tgz "$H:/tmp/kaname_src.tgz"
ssh -i "$KEY" -o StrictHostKeyChecking=no "$H" "mkdir -p $DEST && tar xzf /tmp/kaname_src.tgz -C $DEST && rm -f /tmp/kaname_src.tgz && chmod 707 $DEST/storage/logs && echo deployed"
rm -f /tmp/kaname_src.tgz
