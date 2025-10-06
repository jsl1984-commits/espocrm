#!/usr/bin/env bash
set -euo pipefail
NAME="JSL Cash Flow"
MODULE="JslCashFlow"
VERSION="0.1.0"
AUTHOR="JSL"
DESC="Cash Flow management (contracts, salaries, VAT)"
TMP="data/tmp/${MODULE}-${VERSION}"
rm -rf "$TMP" "$TMP.zip" || true
mkdir -p "$TMP/Resources"
cp -r application/Espo/Modules/JslCashFlow/Resources "$TMP/Resources"
cp -r application/Espo/Modules/JslCashFlow/Controllers "$TMP/Controllers"
cp -r application/Espo/Modules/JslCashFlow/Hooks "$TMP/Hooks"
cp -r application/Espo/Modules/JslCashFlow/Jobs "$TMP/Jobs" 2>/dev/null || true
cat > "$TMP/manifest.json" <<JSON
{
  "name": "${NAME}",
  "version": "${VERSION}",
  "author": "${AUTHOR}",
  "description": "${DESC}",
  "acceptableVersions": [">=9.1.0"],
  "php": ">=8.1",
  "database": {"mysql": ">=8.0"}
}
JSON
(cd data/tmp && zip -r "${MODULE}-${VERSION}.zip" "${MODULE}-${VERSION}")
echo "Created data/tmp/${MODULE}-${VERSION}.zip"
