#!/bin/bash

# Variables
PLUGIN_SLUG="bol-affiliate-insights"
VERSION=$(grep -m1 "Version:" bol-affiliate-insights.php | sed 's/.*Version:[[:space:]]*//' | tr -d '[:space:]')
DIST_PATH="./dist"
BUILD_DIR="${DIST_PATH}/${PLUGIN_SLUG}"
ZIP_FILE="${DIST_PATH}/${PLUGIN_SLUG}.zip"

echo "Building version ${VERSION}..."

# Clean dist folder completely
rm -rf "${DIST_PATH:?}"/*

# Create build directory with fixed plugin slug (no version in folder name)
# This ensures WordPress recognises it as the same plugin on every update
mkdir -p "$BUILD_DIR"

# Copy plugin files
cp -r ./assets "$BUILD_DIR/"
cp -r ./src "$BUILD_DIR/"
cp ./*.php "$BUILD_DIR/"
cp ./*.txt "$BUILD_DIR/"

# Create zip — inner folder is always "bol-affiliate-insights"
cd "$DIST_PATH"
zip -r "${PLUGIN_SLUG}.zip" "${PLUGIN_SLUG}"
cd ..

echo "Done: ${ZIP_FILE} (v${VERSION})"
