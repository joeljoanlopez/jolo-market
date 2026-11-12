#!/usr/bin/env bats
# E7.4: deploy/install-auto-deploy.sh (PD-50).

load test_helper

setup() {
    setup_repos
    export XDG_CONFIG_HOME="$BATS_TEST_TMPDIR/config"
    export USER=maria
    UNIT_DIR="$XDG_CONFIG_HOME/systemd/user"
}

install_auto_deploy() {
    "$SERVER/deploy/install-auto-deploy.sh" "$@"
}

@test "it installs a service that runs the auto-deploy script of this copy" {
    run install_auto_deploy

    [ "$status" -eq 0 ]
    grep -qx "ExecStart=\"$SERVER/deploy/auto-deploy.sh\"" "$UNIT_DIR/jolo-market-deploy.service"
    grep -qx 'Type=oneshot' "$UNIT_DIR/jolo-market-deploy.service"
}

@test "it installs a timer that runs every 5 minutes, and enables it at once" {
    run install_auto_deploy

    [ "$status" -eq 0 ]
    grep -qx 'OnUnitInactiveSec=5min' "$UNIT_DIR/jolo-market-deploy.timer"
    grep -qx 'WantedBy=timers.target' "$UNIT_DIR/jolo-market-deploy.timer"
    [[ $(calls) == *"systemctl --user daemon-reload
systemctl --user enable --now jolo-market-deploy.timer"* ]]
}

@test "AUTO_DEPLOY_INTERVAL changes the interval" {
    AUTO_DEPLOY_INTERVAL=10min run install_auto_deploy

    [ "$status" -eq 0 ]
    grep -qx 'OnUnitInactiveSec=10min' "$UNIT_DIR/jolo-market-deploy.timer"
}

@test "install is the default action" {
    run install_auto_deploy install

    [ "$status" -eq 0 ]
    [ -f "$UNIT_DIR/jolo-market-deploy.timer" ]
}

@test "it warns when the timer would only run while the user is logged in" {
    export FAKE_LINGER=no

    run install_auto_deploy

    [ "$status" -eq 0 ]
    [[ $output == *"loginctl enable-linger maria"* ]]
}

@test "it says nothing about lingering when it is on" {
    run install_auto_deploy

    [[ $output != *enable-linger* ]]
}

@test "it warns when AUTO_DEPLOY is not true, using the last assignment of the .env" {
    printf 'AUTO_DEPLOY=true\nAUTO_DEPLOY=false\n' >"$SERVER/.env"

    run install_auto_deploy

    [ "$status" -eq 0 ]
    [[ $output == *"AUTO_DEPLOY is not true"* ]]
}

@test "it does not warn when AUTO_DEPLOY is true" {
    printf 'AUTO_DEPLOY="true"\n' >"$SERVER/.env"

    run install_auto_deploy

    [[ $output != *"AUTO_DEPLOY is not true"* ]]
}

@test "uninstall stops the timer and removes the units" {
    install_auto_deploy >/dev/null
    : >"$CALLS_LOG"

    run install_auto_deploy uninstall

    [ "$status" -eq 0 ]
    [ ! -e "$UNIT_DIR/jolo-market-deploy.service" ]
    [ ! -e "$UNIT_DIR/jolo-market-deploy.timer" ]
    [[ $(calls) == *"systemctl --user disable --now jolo-market-deploy.timer"* ]]
    [[ $(calls) == *"systemctl --user daemon-reload"* ]]
}

@test "it rejects an unknown action with its usage" {
    run install_auto_deploy reinstall

    [ "$status" -eq 2 ]
    [[ $output == *"Usage:"* ]]
}
