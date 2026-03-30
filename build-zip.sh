#!/bin/bash
# Build a clean distribution ZIP for Polyglot Translate for Contact Form 7.
# Excludes .git, __MACOSX, node_modules, tests, and build scripts.
#
# Usage: bash build-zip.sh
# Output: ../polyglot-translate-cf7-{version}.zip

set -euo pipefail

PLUGIN_DIR="$(cd "$(dirname "$0")" && pwd)"
PLUGIN_SLUG="polyglot-translate-cf7"

VERSION=$(grep -m1 "Version:" "$PLUGIN_DIR/$PLUGIN_SLUG.php" | sed 's/.*Version:[[:space:]]*//' | tr -d '[:space:]')

if [ -z "$VERSION" ]; then
    echo "Error: Could not extract version from $PLUGIN_SLUG.php"
    exit 1
fi

OUTPUT_DIR="$(dirname "$PLUGIN_DIR")"
ZIP_FILE="$OUTPUT_DIR/${PLUGIN_SLUG}-${VERSION}.zip"

echo "Building $PLUGIN_SLUG v$VERSION..."
echo "Output: $ZIP_FILE"

# PHP lint
FAILED=0
while IFS= read -r -d '' f; do
    if ! php -l "$f" >/dev/null 2>&1; then
        echo "PHP lint error: $f"
        php -l "$f" 2>&1 || true
        FAILED=1
    fi
done < <(find "$PLUGIN_DIR" -name "*.php" -not -path "*/tests/*" -not -path "*/vendor/*" -not -path "*/node_modules/*" -print0 2>/dev/null || true)
if [ $FAILED -eq 1 ]; then
    echo "Aborting: fix PHP errors first."
    exit 1
fi
echo "PHP lint OK."

# Create temp directory
TMPDIR=$(mktemp -d)
STAGING="$TMPDIR/$PLUGIN_SLUG"
mkdir -p "$STAGING"

# Copy main plugin file
cp "$PLUGIN_DIR/$PLUGIN_SLUG.php" "$STAGING/"

# Copy directories if they exist
for dir in includes assets languages; do
    if [ -d "$PLUGIN_DIR/$dir" ]; then
        cp -r "$PLUGIN_DIR/$dir" "$STAGING/"
    fi
done

# Copy optional root files
for file in readme.txt uninstall.php LICENSE index.php; do
    if [ -f "$PLUGIN_DIR/$file" ]; then
        cp "$PLUGIN_DIR/$file" "$STAGING/"
    fi
done

# Clean up stray files
find "$STAGING" -name ".git" -type d -exec rm -rf {} + 2>/dev/null || true
find "$STAGING" -name "__MACOSX" -type d -exec rm -rf {} + 2>/dev/null || true
find "$STAGING" -name ".DS_Store" -delete 2>/dev/null || true
find "$STAGING" -name "*.map" -delete 2>/dev/null || true

# Build ZIP
cd "$TMPDIR"
rm -f "$ZIP_FILE"
zip -rq "$ZIP_FILE" "$PLUGIN_SLUG" -x "*.git*" "*__MACOSX*" "*.DS_Store"

# Generate SHA256 hash
HASH=$(shasum -a 256 "$ZIP_FILE" | awk '{print $1}')
echo "$HASH" > "$ZIP_FILE.sha256"

# Cleanup
rm -rf "$TMPDIR"

echo ""
echo "Done! $ZIP_FILE"
echo "SHA256: $HASH"
echo "Contents:"
unzip -l "$ZIP_FILE" | tail -5
