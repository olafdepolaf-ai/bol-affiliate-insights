#!/bin/bash

# Variables
PLUGIN_SLUG="bol-affiliate-insights"
BUILD_PATH="./build"
ZIP_FILE="${PLUGIN_SLUG}.zip"

# Create build directory
rm -rf $BUILD_PATH
mkdir -p $BUILD_PATH

# Copy plugin files
cp -r ./assets $BUILD_PATH/
cp -r ./src $BUILD_PATH/
cp ./*.php $BUILD_PATH/
cp ./*.txt $BUILD_PATH/

# Create zip file
cd $BUILD_PATH
zip -r ../$ZIP_FILE .

# Return to root
cd ..

# Clean up
rm -rf $BUILD_PATH

echo "Plugin successfully zipped to ${ZIP_FILE}"
