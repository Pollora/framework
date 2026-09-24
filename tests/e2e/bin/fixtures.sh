#!/usr/bin/env bash
#
# Build, or remove, the fixtures the browser tests need, with the framework's
# own generators. Run from the root of the Pollora site under test, inside
# its container (e.g. `ddev exec bash <path>/tests/e2e/bin/fixtures.sh up`).
#
#   up    a plugin made by pollora:make:plugin, holding a dynamic and a static
#         block made by pollora:make:block, built by Vite
#   down  deactivate and delete that plugin and its build
set -euo pipefail

PLUGIN=e2e-blocks
PLUGIN_DIR=public/content/plugins/$PLUGIN

case "${1:-}" in
    up)
        if [ ! -d "$PLUGIN_DIR" ]; then
            php artisan pollora:make:plugin "$PLUGIN" --asset=true --no-interaction
        fi

        php artisan pollora:make:block dynamic-card --plugin="$PLUGIN" --title="Dynamic Card" --force --no-interaction
        php artisan pollora:make:block static-card --plugin="$PLUGIN" --title="Static Card" --static --force --no-interaction

        # The package.json pollora:make:block extended is the plugin's own:
        # never let npm walk up to the site's root.
        [ -f "$PLUGIN_DIR/package.json" ] || { echo "No package.json in $PLUGIN_DIR" >&2; exit 1; }
        (cd "$PLUGIN_DIR" && npm install --no-audit --no-fund && npm run build)

        wp plugin activate "$PLUGIN"
        ;;
    down)
        wp plugin deactivate "$PLUGIN" 2>/dev/null || true
        rm -rf "$PLUGIN_DIR" "public/build/plugin/$PLUGIN" "public/$PLUGIN.hot"
        ;;
    *)
        echo "Usage: $0 up|down" >&2
        exit 1
        ;;
esac
