#!/usr/bin/env bash

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
E2E="$ROOT/tests/e2e"
PORT="${CMP_E2E_PORT:-8891}"
URL="http://localhost:${PORT}"
COMPOSE=(docker compose -p cmp-e2e -f "$E2E/docker-compose.yml")

install_build() {
	bash "$ROOT/bin/build.sh" >/dev/null
	VERSION="$(grep -m1 '^ \* Version:' "$ROOT/consentimental.php" | sed -E 's/^ \* Version:[[:space:]]*//' | tr -d '[:space:]')"
	local tmp
	tmp="$(mktemp -d)"
	unzip -q "$ROOT/dist/consentimental-${VERSION}.zip" -d "$tmp"

	if [ -d "$tmp/consentimental/vendor" ]; then
		echo "The distributable must not depend on vendor/." >&2
		exit 1
	fi

	mkdir -p "$E2E/.build/consentimental"
	find "$E2E/.build/consentimental" -mindepth 1 -delete
	cp -a "$tmp/consentimental/." "$E2E/.build/consentimental/"
	rm -rf "$tmp"
}

wp_cli() {
	"${COMPOSE[@]}" exec -T cli wp --path=/var/www/html "$@"
}

case "${1:-}" in
	up)
		install_build
		"${COMPOSE[@]}" up -d --wait db
		"${COMPOSE[@]}" up -d wordpress cli

		for _ in $(seq 1 60); do
			if "${COMPOSE[@]}" exec -T wordpress test -f /var/www/html/wp-config.php 2>/dev/null; then break; fi
			sleep 1
		done

		if [ -n "${CMP_E2E_WP_VERSION:-}" ]; then
			wp_cli core download --version="$CMP_E2E_WP_VERSION" --force --skip-content >/dev/null
		fi

		wp_cli core install --url="$URL" --title="Consentimental E2E" --admin_user=admin --admin_password=admin \
			--admin_email=admin@example.test --skip-email >/dev/null
		wp_cli rewrite structure '/%postname%/' >/dev/null
		wp_cli plugin activate consentimental >/dev/null
		wp_cli option update cmp_e2e_disposable 1 >/dev/null

		POLICY_ID="$(wp_cli post create --post_type=page --post_status=publish --post_title='Privacy Policy' --porcelain)"
		wp_cli option update wp_page_for_privacy_policy "$POLICY_ID" >/dev/null
		wp_cli post create --post_type=page --post_status=publish --post_title='About' --post_content='About this site.' >/dev/null

		echo "E2E site ready at ${URL} (admin / admin), WordPress $(wp_cli core version), plugin ${VERSION}."
		;;
	sync)
		install_build
		echo "Plugin copy refreshed (${VERSION})."
		;;
	compat)
		shift
		[ "$#" -gt 0 ] || { echo "Name at least one plugin slug." >&2; exit 1; }
		wp_cli plugin install "$@" --activate
		"${COMPOSE[@]}" cp "$E2E/compat-setup.php" cli:/tmp/compat-setup.php
		wp_cli eval-file /tmp/compat-setup.php
		wp_cli rewrite flush >/dev/null
		;;
	wp)
		shift
		wp_cli "$@"
		;;
	php)
		shift
		"${COMPOSE[@]}" exec -T -e CMP_WP_LOAD=/var/www/html/wp-load.php wordpress php "/cmp-src/$1"
		;;
	down)
		"${COMPOSE[@]}" down -v --remove-orphans
		rm -rf "$E2E/.build"
		;;
	*)
		sed -n '2,20p' "$0"
		exit 1
		;;
esac
