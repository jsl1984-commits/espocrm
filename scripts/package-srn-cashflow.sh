#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SRC_DIR="$ROOT_DIR/extensions/srn-cashflow"
OUT_DIR="$ROOT_DIR/dist"

if [ ! -d "$SRC_DIR" ]; then
  echo "Source directory not found: $SRC_DIR" >&2
  exit 1
fi

cd "$SRC_DIR"

# Determine version from manifest.json
VERSION="$(sed -n 's/.*"version"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p' manifest.json | head -n 1 || true)"
if [ -z "${VERSION:-}" ]; then
  VERSION="0.0.0-$(date +%Y%m%d%H%M%S)"
fi

mkdir -p "$OUT_DIR"
OUT_ZIP="$OUT_DIR/srn-cashflow-$VERSION.zip"

# Validate required structure
for p in manifest.json files scripts; do
  if [ ! -e "$p" ]; then
    echo "Missing required path in source: $p" >&2
    exit 1
  fi
done

# Build include list
INCLUDE=(manifest.json files scripts)
if [ -f README.md ]; then INCLUDE+=(README.md); fi
if [ -f README_JOBS_HOOKS.md ]; then INCLUDE+=(README_JOBS_HOOKS.md); fi

# Create zip
zip -r -9 "$OUT_ZIP" "${INCLUDE[@]}" \
  -x "**/.DS_Store" "**/__MACOSX/**" >/dev/null

echo "$OUT_ZIP"