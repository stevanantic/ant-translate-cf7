#!/bin/bash
# Build a clean distribution ZIP for ANT Translate for Contact Form 7.
# Excludes .git, __MACOSX, node_modules, tests, and build scripts.
#
# Usage: bash build-zip.sh
# Output: ../ant-translate-cf7-{version}.zip

set -euo pipefail

PLUGIN_DIR="$(cd "$(dirname "$0")" && pwd)"
PLUGIN_SLUG="ant-translate-cf7"

VERSION=$(grep -m1 "Version:" "$PLUGIN_DIR/$PLUGIN_SLUG.php" | sed 's/.*Version:[[:space:]]*//' | tr -d '[:space:]')

if [ -z "$VERSION" ]; then
    echo "Error: Could not extract version from $PLUGIN_SLUG.php"
    exit 1
fi

OUTPUT_DIR="$(dirname "$PLUGIN_DIR")"
ZIP_FILE="$OUTPUT_DIR/${PLUGIN_SLUG}-${VERSION}.zip"

echo "Building $PLUGIN_SLUG v$VERSION..."
echo "Output: $ZIP_FILE"

TMPDIR=$(mktemp -d)
STAGING="$TMPDIR/$PLUGIN_SLUG"
mkdir -p "$STAGING"

cp "$PLUGIN_DIR/$PLUGIN_SLUG.php" "$STAGING/"

for dir in includes assets languages; do
    if [ -d "$PLUGIN_DIR/$dir" ]; then
        cp -r "$PLUGIN_DIR/$dir" "$STAGING/"
    fi
done

for file in readme.txt README.md LICENSE changelog.txt; do
    if [ -f "$PLUGIN_DIR/$file" ]; then
        cp "$PLUGIN_DIR/$file" "$STAGING/"
    fi
done

find "$STAGING" -name ".git" -type d -exec rm -rf {} + 2>/dev/null || true
find "$STAGING" -name "__MACOSX" -type d -exec rm -rf {} + 2>/dev/null || true
find "$STAGING" -name ".DS_Store" -delete 2>/dev/null || true
find "$STAGING" -name "*.map" -delete 2>/dev/null || true

cd "$TMPDIR"
rm -f "$ZIP_FILE"
zip -r "$ZIP_FILE" "$PLUGIN_SLUG" -x "*.git*" "*__MACOSX*" "*.DS_Store"

rm -rf "$TMPDIR"

echo ""
echo "Done! Distribution ZIP: $ZIP_FILE"
echo "Contents:"
unzip -l "$ZIP_FILE" | tail -5
