#!/usr/bin/env bash

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
VERSION="$(node -p "require('$ROOT/tests/e2e/package.json').devDependencies['@playwright/test']")"

if [ ! -d "$ROOT/tests/e2e/node_modules" ]; then
	(cd "$ROOT/tests/e2e" && npm ci --no-audit --no-fund 2>/dev/null || npm install --no-audit --no-fund)
fi

exec docker run --rm --network host --ipc=host \
	-u "$(id -u):$(id -g)" -e HOME=/tmp -e CI="${CI:-}" -e CMP_E2E_URL="${CMP_E2E_URL:-http://localhost:${CMP_E2E_PORT:-8891}}" \
	-v "$ROOT:/work" -w /work/tests/e2e \
	"mcr.microsoft.com/playwright:v${VERSION}-noble" \
	npx playwright test "$@"
