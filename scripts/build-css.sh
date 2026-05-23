#!/usr/bin/env bash
# Build Tailwind CSS. Works on production (NODE_ENV=production) tanpa npm install penuh.
set -euo pipefail
cd "$(dirname "$0")/.."
npx --yes tailwindcss@3.4.17 \
  -i ./assets/css/tailwind-src.css \
  -o ./assets/css/tailwind.css \
  --minify
echo "OK: assets/css/tailwind.css"
