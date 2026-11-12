#!/usr/bin/env bash
# Installs or uninstalls the user systemd timer of the auto-deploy (E7, PD-50).
#
#   deploy/install-auto-deploy.sh            # install and start the timer
#   deploy/install-auto-deploy.sh uninstall  # stop it and remove the units
#
# The units get the absolute path of this copy of the repository, so no path is committed. No sudo: they go to
# ~/.config/systemd/user. AUTO_DEPLOY_INTERVAL (systemd time span, 5min by default) sets the interval at install.

set -euo pipefail

readonly NAME=jolo-market-deploy
readonly UNIT_DIR="${XDG_CONFIG_HOME:-$HOME/.config}/systemd/user"

repo_dir=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
interval=${AUTO_DEPLOY_INTERVAL:-5min}

# Same reading as deploy/auto-deploy.sh: the last assignment wins, surrounding spaces and quotes go.
auto_deploy_enabled() {
    local line
    line=$(grep -E '^[[:space:]]*(export[[:space:]]+)?AUTO_DEPLOY[[:space:]]*=' "$repo_dir/.env" 2>/dev/null | tail -n 1) || return 1
    line=${line#*=}
    line=$(printf '%s' "$line" | sed -E "s/^[[:space:]]+//; s/[[:space:]]+\$//; s/^[\"']//; s/[\"']\$//")
    [[ $line == true ]]
}

install_units() {
    mkdir -p "$UNIT_DIR"

    cat >"$UNIT_DIR/$NAME.service" <<EOF
[Unit]
Description=jolo-market: auto-deploy from origin/main
Documentation=file://$repo_dir/README.md

[Service]
Type=oneshot
ExecStart="$repo_dir/deploy/auto-deploy.sh"
EOF

    cat >"$UNIT_DIR/$NAME.timer" <<EOF
[Unit]
Description=jolo-market: check every $interval whether to deploy

[Timer]
OnBootSec=2min
OnUnitInactiveSec=$interval
RandomizedDelaySec=30s

[Install]
WantedBy=timers.target
EOF

    systemctl --user daemon-reload
    systemctl --user enable --now "$NAME.timer"
    echo "Installed $NAME.timer (every $interval) for $repo_dir."

    if [[ $(loginctl show-user "$USER" --property=Linger --value 2>/dev/null) != yes ]]; then
        echo "Warning: without lingering the timer only runs while you are logged in."
        echo "         Turn it on with: loginctl enable-linger $USER"
    fi
    if ! auto_deploy_enabled; then
        echo "Warning: AUTO_DEPLOY is not true in .env; the timer will run without deploying anything."
    fi
}

uninstall_units() {
    systemctl --user disable --now "$NAME.timer" 2>/dev/null || true
    systemctl --user stop "$NAME.service" 2>/dev/null || true
    rm -f "$UNIT_DIR/$NAME.service" "$UNIT_DIR/$NAME.timer"
    systemctl --user daemon-reload
    echo "Uninstalled $NAME.timer."
}

case ${1:-install} in
    install) install_units ;;
    uninstall) uninstall_units ;;
    *) echo "Usage: $0 [install|uninstall]" >&2; exit 2 ;;
esac
