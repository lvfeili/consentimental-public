#!/usr/bin/env bash

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

command -v wp >/dev/null 2>&1 || { echo "wp-cli is required but not found in PATH." >&2; exit 1; }

PHP_BIN="php"
if ! php -m | grep -qi '^mbstring$'; then
	for candidate in php8.3 php8.2 php8.1; do
		if command -v "$candidate" >/dev/null 2>&1 && "$candidate" -m | grep -qi '^mbstring$'; then
			PHP_BIN="$candidate"
			break
		fi
	done
fi

"$PHP_BIN" "$(command -v wp)" i18n make-pot "$ROOT" "$ROOT/languages/consentimental.pot" \
	--domain=consentimental \
	--exclude=tests,dist,build,vendor,node_modules

echo "Wrote languages/consentimental.pot"
