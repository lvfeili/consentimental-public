#!/usr/bin/env bash

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

fail() { echo "FAIL: $*" >&2; exit 1; }
pass() { echo "ok   $*"; }

HEADER_VERSION="$(grep -m1 '^ \* Version:' consentimental.php | sed -E 's/^ \* Version:[[:space:]]*//' | tr -d '[:space:]')"
CONST_VERSION="$(grep -m1 "^const CMP_VERSION" consentimental.php | sed -E "s/.*'([^']+)'.*/\1/")"
[ -n "$HEADER_VERSION" ] && [ "$HEADER_VERSION" = "$CONST_VERSION" ] || fail "plugin header version ($HEADER_VERSION) != CMP_VERSION ($CONST_VERSION)"
pass "version $HEADER_VERSION"

HEADER_PHP="$(grep -m1 '^ \* Requires PHP:' consentimental.php | sed -E 's/.*:[[:space:]]*//' | tr -d '[:space:]')"
grep -q "\"php\": \">=${HEADER_PHP}\"" composer.json || fail "Requires PHP ${HEADER_PHP} does not match composer.json"
pass "requires PHP ${HEADER_PHP}"

while IFS= read -r -d '' file; do
	php -l "$file" >/dev/null 2>&1 || { php -l "$file"; fail "syntax error in $file"; }
done < <(find consentimental.php uninstall.php src templates -name '*.php' -print0)
pass "PHP syntax ($(php -r 'echo PHP_VERSION;'))"

while IFS= read -r -d '' file; do
	grep -q "defined('ABSPATH')" "$file" || fail "$file does not guard against direct access"
done < <(find src templates -name '*.php' -print0)
grep -q "defined('WP_UNINSTALL_PLUGIN')" uninstall.php || fail "uninstall.php must check WP_UNINSTALL_PLUGIN"
pass "direct-access guards"

while IFS= read -r -d '' file; do
	relative="${file#src/}"
	expected_class="$(basename "$relative" .php)"
	expected_namespace="Consentimental$(dirname "/$relative" | sed 's#/$##; s#/#\\#g')"
	[ "$relative" = "functions.php" ] && continue
	grep -Eq "^namespace ${expected_namespace//\\/\\\\};" "$file" || fail "$file: namespace should be ${expected_namespace}"
	grep -Eq "^(final |abstract |readonly )*(class|interface|trait|enum) ${expected_class}\b" "$file" || fail "$file: should declare ${expected_class}"
done < <(find src -name '*.php' -print0)
pass "PSR-4 layout"

TRANSLATION_CALL="(esc_html__|esc_html_e|esc_attr__|esc_attr_e|__|_e)\\('([^'\\\\]|\\\\.)*', '[a-z_-]+'\\)"
WRONG_DOMAIN="$(grep -rnoE "$TRANSLATION_CALL" src templates | grep -v "'consentimental')" || true)"
[ -z "$WRONG_DOMAIN" ] || { echo "$WRONG_DOMAIN" | head; fail "translatable strings with a text domain other than consentimental"; }
[ "$(grep -rhoE "$TRANSLATION_CALL" src templates | wc -l)" -gt 100 ] || fail "text domain check matched too few strings; the pattern is broken"
pass "text domain"

bash bin/build.sh >/dev/null
ZIP="dist/consentimental-${HEADER_VERSION}.zip"
LISTING="$(unzip -Z1 "$ZIP")"
for required in consentimental/consentimental.php consentimental/uninstall.php consentimental/src/Plugin.php \
	consentimental/assets/js/consent.js consentimental/templates/frontend/banner.php \
	consentimental/languages/consentimental.pot consentimental/languages/consentimental-de_DE.mo; do
	grep -qx "$required" <<<"$LISTING" || fail "$ZIP is missing $required"
done
if grep -E '^consentimental/(vendor|tests|docs|node_modules|bin|dist|build|\.github)/|composer\.(json|lock)$|docker-compose|brief\.md|phpunit|\.DS_Store' <<<"$LISTING" >/dev/null; then
	grep -E '^consentimental/(vendor|tests|docs|node_modules|bin|dist|build|\.github)/|composer\.(json|lock)$|docker-compose|brief\.md|phpunit|\.DS_Store' <<<"$LISTING" | head
	fail "$ZIP contains development files"
fi
pass "package contents ($ZIP, $(wc -l <<<"$LISTING") entries)"

echo "Release checks passed. Run the e2e suite (bin/e2e-env.sh up && bin/e2e-test.sh) before tagging."
