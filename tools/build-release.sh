#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
PLUGIN_SLUG="product-expiration-easy-peasy"
OUTPUT_ROOT="${PLUGIN_ROOT}/dist"
PLUGIN_OUTPUT="${OUTPUT_ROOT}/Plugin"
TEMP_DIR="$(mktemp -d)"
EXPECTED_LICENSE_HASH="f1e5fcf95fe320f67ad54e9280173cc604afb5ce"

cleanup() {
	if [[ -n "${TEMP_DIR}" && -d "${TEMP_DIR}" && "${TEMP_DIR}" == /tmp/* ]]; then
		rm -rf "${TEMP_DIR}"
	fi
}
trap cleanup EXIT

if [[ ! -f "${PLUGIN_ROOT}/help.pdf" ]]; then
	printf 'Missing help.pdf. Generate the Persian guide before building.\n' >&2
	exit 1
fi

if [[ ! -f "${PLUGIN_ROOT}/RTL_License_700d86aad12a6fe9.php" ]]; then
	printf 'Missing generated RTL-Theme license runtime.\n' >&2
	exit 1
fi

ACTUAL_LICENSE_HASH="$(sha1sum "${PLUGIN_ROOT}/RTL_License_700d86aad12a6fe9.php" | awk '{print $1}')"
if [[ "${ACTUAL_LICENSE_HASH}" != "${EXPECTED_LICENSE_HASH}" ]]; then
	printf 'RTL-Theme license runtime failed its SHA-1 integrity check.\n' >&2
	exit 1
fi

if ! rg -q '^ \* Version:     3\.2\.1$' "${PLUGIN_ROOT}/product-expiration-easy-peasy.php"; then
	printf 'Plugin header version is not 3.2.1.\n' >&2
	exit 1
fi

if ! rg -q "^define\( 'PRODUCT_EXPIRATION_EASY_PEASY_VERSION', '3\.2\.1' \);$" "${PLUGIN_ROOT}/product-expiration-easy-peasy.php"; then
	printf 'PRODUCT_EXPIRATION_EASY_PEASY_VERSION is not 3.2.1.\n' >&2
	exit 1
fi

if ! rg -q '^Stable tag: 3\.2\.1$' "${PLUGIN_ROOT}/readme.txt"; then
	printf 'readme.txt stable tag is not 3.2.1.\n' >&2
	exit 1
fi

# Every translatable string must be translated, and the .mo / .l10n.php
# must be compiled from the current .po.
if ! php "${PLUGIN_ROOT}/tools/check-translations.php" fa_IR; then
	exit 1
fi

mkdir -p "${TEMP_DIR}/${PLUGIN_SLUG}" "${PLUGIN_OUTPUT}"

rsync -a \
	--exclude-from="${PLUGIN_ROOT}/.distignore" \
	"${PLUGIN_ROOT}/" \
	"${TEMP_DIR}/${PLUGIN_SLUG}/"

(
	cd "${TEMP_DIR}"
	zip -q -r "${PLUGIN_SLUG}.zip" "${PLUGIN_SLUG}"
)

mv "${TEMP_DIR}/${PLUGIN_SLUG}.zip" "${PLUGIN_OUTPUT}/${PLUGIN_SLUG}.zip"
cp "${PLUGIN_ROOT}/help.pdf" "${OUTPUT_ROOT}/help.pdf"

(
	cd "${OUTPUT_ROOT}"
	sha256sum "Plugin/${PLUGIN_SLUG}.zip" "help.pdf" > SHA256SUMS.txt
	zip -q -r "${TEMP_DIR}/${PLUGIN_SLUG}-rtl-theme.zip" Plugin help.pdf
)

mv "${TEMP_DIR}/${PLUGIN_SLUG}-rtl-theme.zip" "${OUTPUT_ROOT}/${PLUGIN_SLUG}-rtl-theme.zip"

printf 'Release package created:\n'
printf '  %s\n' "${PLUGIN_OUTPUT}/${PLUGIN_SLUG}.zip"
printf '  %s\n' "${OUTPUT_ROOT}/help.pdf"
printf '  %s\n' "${OUTPUT_ROOT}/SHA256SUMS.txt"
printf '  %s\n' "${OUTPUT_ROOT}/${PLUGIN_SLUG}-rtl-theme.zip"
