#!/usr/bin/env bash
#
# Build, or remove, the fixtures the browser tests need, with the framework's
# own generators. Run from the root of the Pollora site under test, inside
# its container (e.g. `ddev exec bash <path>/tests/e2e/bin/fixtures.sh up`).
#
#   up    - a plugin made by pollora:make:plugin, holding a dynamic and a
#           static block made by pollora:make:block, built by Vite;
#         - a Laravel module with no service provider, holding a copy of that
#           dynamic block (pollora:make:block cannot target a module);
#         - when theme-default is the active theme, a dynamic block made in it
#           by pollora:make:block. Any other theme is left alone: it may be
#           someone's work in progress.
#         - the template hierarchy themes, e2e-full and e2e-index, copied into
#           themes/. They are not activated here: the hierarchy spec activates
#           each in turn and gives the site its own theme back.
#   down  remove all of it, and give modules_statuses.json back as it was
set -euo pipefail

FIXTURES="$(cd "$(dirname "$0")/../fixtures" && pwd)"

PLUGIN=e2e-blocks
PLUGIN_DIR=public/content/plugins/$PLUGIN

MODULE=E2eModule
MODULE_SLUG=e2e-module
MODULE_DIR=Modules/$MODULE
STATUSES=modules_statuses.json
STATUSES_BACKUP=$STATUSES.e2e-backup

THEME=default
THEME_DIR=themes/$THEME
THEME_BLOCK=theme-card

# Install and build in a directory that must hold its own package.json:
# npm would otherwise walk up to the site's root and install there.
build() {
    [ -f "$1/package.json" ] || { echo "No package.json in $1" >&2; exit 1; }
    (cd "$1" && npm install --no-audit --no-fund && npm run build)
}

case "${1:-}" in
    up)
        # Plugin
        if [ ! -d "$PLUGIN_DIR" ]; then
            php artisan pollora:make:plugin "$PLUGIN" --asset=true --no-interaction
        fi

        php artisan pollora:make:block dynamic-card --plugin="$PLUGIN" --title="Dynamic Card" --force --no-interaction
        php artisan pollora:make:block static-card --plugin="$PLUGIN" --title="Static Card" --static --force --no-interaction
        build "$PLUGIN_DIR"
        wp plugin activate "$PLUGIN"

        # Laravel module
        if [ ! -f "$STATUSES_BACKUP" ]; then
            if [ -f "$STATUSES" ]; then cp "$STATUSES" "$STATUSES_BACKUP"; else echo absent > "$STATUSES_BACKUP"; fi
        fi

        rm -rf "$MODULE_DIR"
        mkdir -p Modules
        cp -r "$FIXTURES/modules/$MODULE" "$MODULE_DIR"
        mkdir -p "$MODULE_DIR/resources/views/blocks"
        cp -r "$PLUGIN_DIR/resources/views/blocks/dynamic-card" "$MODULE_DIR/resources/views/blocks/module-card"
        sed -i \
            -e "s#$PLUGIN/dynamic-card#$MODULE_SLUG/module-card#g" \
            -e "s#$PLUGIN-dynamic-card#$MODULE_SLUG-module-card#g" \
            -e "s#Dynamic Card#Module Card#g" \
            -e "s#'$PLUGIN'#'$MODULE_SLUG'#g" \
            -e "s#\"$PLUGIN\"#\"$MODULE_SLUG\"#g" \
            -e "s#\"dynamic-card\"#\"module-card\"#g" \
            "$MODULE_DIR"/resources/views/blocks/module-card/*
        build "$MODULE_DIR"
        php artisan module:enable "$MODULE" --no-interaction

        # Template hierarchy themes
        for theme in e2e-full e2e-index; do
            rm -rf "themes/$theme"
            cp -r "$FIXTURES/themes/$theme" "themes/$theme"
        done

        # Theme, only when it is theme-default
        if [ "$(wp theme list --status=active --field=name)" = "$THEME" ]; then
            php artisan pollora:make:block "$THEME_BLOCK" --theme="$THEME" --title="Theme Card" --force --no-interaction
            build "$THEME_DIR"
        else
            echo "Active theme is not $THEME: no theme block fixture."
        fi
        ;;
    down)
        wp plugin deactivate "$PLUGIN" 2>/dev/null || true
        rm -rf "$PLUGIN_DIR" "public/build/plugin/$PLUGIN" "public/$PLUGIN.hot"

        rm -rf "$MODULE_DIR" "public/build/module/$MODULE_SLUG" "public/$MODULE_SLUG.hot"
        if [ -f "$STATUSES_BACKUP" ]; then
            if [ "$(cat "$STATUSES_BACKUP")" = absent ]; then rm -f "$STATUSES"; else cp "$STATUSES_BACKUP" "$STATUSES"; fi
            rm -f "$STATUSES_BACKUP"
        fi

        rm -rf themes/e2e-full themes/e2e-index

        # The theme block is only ever made in theme-default, and removed from it
        rm -rf "$THEME_DIR/resources/views/blocks/$THEME_BLOCK"
        ;;
    *)
        echo "Usage: $0 up|down" >&2
        exit 1
        ;;
esac
