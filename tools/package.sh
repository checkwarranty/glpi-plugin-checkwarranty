#!/usr/bin/env bash
# Works both in this monorepo and in the standalone plugin repository.
set -euo pipefail
PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
VERSION="$(sed -n "s/^define('PLUGIN_CHECKWARRANTY_VERSION', '\([^']*\)');/\1/p" "$PLUGIN_DIR/setup.php")"
[[ "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || { echo 'Invalid plugin version' >&2; exit 1; }
if [[ $# -gt 0 && "${1#v}" != "$VERSION" ]]; then
    echo "Release tag $1 does not match setup.php version $VERSION" >&2
    exit 1
fi
for command in php msgfmt tar zip shasum; do
    command -v "$command" >/dev/null || { echo "Required command missing: $command" >&2; exit 1; }
done
# A loop propagates each lint failure; find -exec alone does not.
while IFS= read -r -d '' source; do
    php -l "$source"
done < <(find "$PLUGIN_DIR" -path "$PLUGIN_DIR/vendor" -prune -o -name '*.php' -print0)
STAGING_DIR="$(mktemp -d)"
trap 'rm -rf "$STAGING_DIR"' EXIT
mkdir "$STAGING_DIR/checkwarranty"
# Explicit runtime allowlist prevents credentials, VCS data and dev tools leaking.
for entry in setup.php hook.php inc front locales LICENSE README.md CHANGELOG.md RELEASE.md; do
    cp -R "$PLUGIN_DIR/$entry" "$STAGING_DIR/checkwarranty/"
done
find "$STAGING_DIR" -name '.*' -type f -delete
for po in "$STAGING_DIR/checkwarranty/locales/"*.po; do
    msgfmt --check --output-file="${po%.po}.mo" "$po"
done
DIST_DIR="$PLUGIN_DIR/dist"
mkdir -p "$DIST_DIR"
ARCHIVE="glpi-checkwarranty-$VERSION"
# No deletion of unrelated previous releases or files in dist.
COPYFILE_DISABLE=1 tar -czf "$STAGING_DIR/$ARCHIVE.tar.gz" -C "$STAGING_DIR" checkwarranty
(cd "$STAGING_DIR" && zip -q -r "$ARCHIVE.zip" checkwarranty)
mv "$STAGING_DIR/$ARCHIVE.tar.gz" "$STAGING_DIR/$ARCHIVE.zip" "$DIST_DIR/"
(cd "$DIST_DIR" && shasum -a 256 "$ARCHIVE.tar.gz" "$ARCHIVE.zip" > "$ARCHIVE.sha256")
echo "Built $DIST_DIR/$ARCHIVE.{tar.gz,zip,sha256}"
