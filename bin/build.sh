#!/usr/bin/env bash

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SLUG="consentimental"

command -v zip >/dev/null 2>&1 || { echo "zip is required but not found in PATH." >&2; exit 1; }

VERSION="$(grep -m1 '^ \* Version:' "$ROOT/consentimental.php" | sed -E 's/^ \* Version:[[:space:]]*//' | tr -d '[:space:]')"

if [ -z "$VERSION" ]; then
	echo "Could not read the plugin version from consentimental.php." >&2
	exit 1
fi

BUILD_DIR="$ROOT/build"
PLUGIN_DIR="$BUILD_DIR/$SLUG"
DIST_DIR="$ROOT/dist"
ZIP_PATH="$DIST_DIR/${SLUG}-${VERSION}.zip"

echo "Building ${SLUG} ${VERSION}..."

rm -rf "$BUILD_DIR"
mkdir -p "$PLUGIN_DIR"

cp "$ROOT/consentimental.php" "$PLUGIN_DIR/"
[ -f "$ROOT/uninstall.php" ] && cp "$ROOT/uninstall.php" "$PLUGIN_DIR/"
[ -f "$ROOT/readme.txt" ] && cp "$ROOT/readme.txt" "$PLUGIN_DIR/"
cp -R "$ROOT/src" "$PLUGIN_DIR/"
cp -R "$ROOT/templates" "$PLUGIN_DIR/"
cp -R "$ROOT/assets" "$PLUGIN_DIR/"
cp -R "$ROOT/languages" "$PLUGIN_DIR/"
find "$PLUGIN_DIR" -name '.gitkeep' -delete
find "$PLUGIN_DIR" -name '.DS_Store' -delete

mkdir -p "$DIST_DIR"
rm -f "$ZIP_PATH"

( cd "$BUILD_DIR" && zip -rq "$ZIP_PATH" "$SLUG" )

rm -rf "$BUILD_DIR"

echo "Built: ${ZIP_PATH} ($(du -h "$ZIP_PATH" | cut -f1))"
