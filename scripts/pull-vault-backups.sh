#!/usr/bin/env bash
#
# Pulls the sealed vault backups from the server to this computer. The files are encrypted to your public key, so
# copying them around is safe; only your private key opens them.
#
#   scripts/pull-vault-backups.sh
#
# Settings (environment variables, all optional):
#   LORE_SSH   user@host of the server       (default: deployer@5.78.112.252)
#   LORE_PATH  backup folder on the server   (default: /var/www/lore/storage/app/vault-backups)
#   LORE_DEST  where to keep them here       (default: ~/lore-vault-backups)
#
# Files are only ever added: the server prunes old backups, but copies you already pulled are kept here.
#
# Run it daily, for example:
#
#   cron (crontab -e):
#     30 4 * * * /path/to/lore/scripts/pull-vault-backups.sh >> ~/lore-vault-backups/pull.log 2>&1
#
#   macOS launchd (~/Library/LaunchAgents/ug.verbeek.lore-vault-pull.plist, then `launchctl load` it):
#     <?xml version="1.0" encoding="UTF-8"?>
#     <plist version="1.0"><dict>
#       <key>Label</key><string>ug.verbeek.lore-vault-pull</string>
#       <key>ProgramArguments</key><array><string>/path/to/lore/scripts/pull-vault-backups.sh</string></array>
#       <key>StartCalendarInterval</key><dict><key>Hour</key><integer>4</integer><key>Minute</key><integer>30</integer></dict>
#     </dict></plist>
#
#   systemd user timer (Linux): a oneshot service running this script plus a timer with OnCalendar=*-*-* 04:30:00.

set -euo pipefail

SSH_TARGET="${LORE_SSH:-deployer@5.78.112.252}"
REMOTE_PATH="${LORE_PATH:-/var/www/lore/storage/app/vault-backups}"
DEST="${LORE_DEST:-$HOME/lore-vault-backups}"

mkdir -p "$DEST"
chmod 700 "$DEST"

rsync -a --ignore-existing --include='vault-*.sealed' --exclude='*' \
    -e 'ssh -o BatchMode=yes' \
    "$SSH_TARGET:$REMOTE_PATH/" "$DEST/"

echo "$(date -Is) pulled vault backups into $DEST ($(ls "$DEST"/vault-*.sealed 2>/dev/null | wc -l) files)"
