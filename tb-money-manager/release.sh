#!/bin/bash
# release.sh — versie bumpen, zip bouwen, GitHub Release aanmaken
# Gebruik: bash release.sh [patch|minor|major]
# Vereist: gh CLI (https://cli.github.com) en git

set -e

PLUGIN_FILE="tb-money-manager.php"
BUMP=${1:-patch}

# Haal huidige versie op
CURRENT=$(grep "Version:" "$PLUGIN_FILE" | grep -v "Update URI" | sed 's/.*Version: *//')
echo "Huidige versie: $CURRENT"

# Splits in major.minor.patch
IFS='.' read -r MAJOR MINOR PATCH <<< "$CURRENT"

case "$BUMP" in
  major) MAJOR=$((MAJOR + 1)); MINOR=0; PATCH=0 ;;
  minor) MINOR=$((MINOR + 1)); PATCH=0 ;;
  patch) PATCH=$((PATCH + 1)) ;;
  *)
    echo "Onbekende bump type: $BUMP. Gebruik patch, minor of major."
    exit 1
    ;;
esac

NEW_VERSION="$MAJOR.$MINOR.$PATCH"
TAG="tbmm-v$NEW_VERSION"
echo "Nieuwe versie: $NEW_VERSION (tag: $TAG)"

# Versie bijwerken in PHP-bestand
sed -i '' "s/ \* Version: $CURRENT/ \* Version: $NEW_VERSION/" "$PLUGIN_FILE"
echo "Versie bijgewerkt in $PLUGIN_FILE"

# Zip bouwen vanuit de root
cd ..
rm -f tb-money-manager.zip
zip -r tb-money-manager.zip tb-money-manager/ \
    --exclude "tb-money-manager/release.sh" \
    --exclude "*/.DS_Store"
echo "Zip gebouwd: tb-money-manager.zip"
cd tb-money-manager

# Commit en tag
git -C ../.. add \
    "tb-money-manager/$PLUGIN_FILE" \
    "tb-money-manager.zip"
git -C ../.. commit -m "chore(tbmm): bump version to $NEW_VERSION"
git -C ../.. tag "$TAG"
git -C ../.. push origin main
git -C ../.. push origin "$TAG"
echo "Gepusht naar GitHub met tag $TAG"

# GitHub Release aanmaken met de zip als asset
gh release create "$TAG" \
    --repo olafdepolaf-ai/bol-affiliate-insights \
    --title "TB Money Manager v$NEW_VERSION" \
    --notes "Versie $NEW_VERSION" \
    "../tb-money-manager.zip#tb-money-manager.zip"

echo ""
echo "✓ Release $TAG aangemaakt op GitHub."
echo "  WordPress detecteert de update automatisch binnen 6 uur."
echo "  Directe update forceren: Plugins-pagina herladen in WP admin."
