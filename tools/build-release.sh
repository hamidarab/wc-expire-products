#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
PLUGIN_SLUG="product-expiration-easy-peasy"
OUTPUT_ROOT="${PLUGIN_ROOT}/dist"
PLUGIN_OUTPUT="${OUTPUT_ROOT}/Plugin"
TEMP_DIR="$(mktemp -d)"
LICENSE_FILE="RTL_License_ca50fd723e00b32e.php"
EXPECTED_LICENSE_HASH="7e5cf81e7b51082f1f5e3fbc119fa2e8f98c57c6"
# RTL-Theme's ionCube-protected copy of the license check; it replaces the
# readable source in the ZIP. It is only copied as a file, never read or rewritten.
LICENSE_CHECK_SOURCE="includes/class-license-check.php"
LICENSE_CHECK_PROTECTED="protected/includes/class-license-check.php"
# SHA-1 of the readable source that was sent for protection, written by
# tools/record-protected-source.sh.
LICENSE_CHECK_SOURCE_SHA1="${LICENSE_CHECK_PROTECTED}.source-sha1"

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

if [[ ! -f "${PLUGIN_ROOT}/${LICENSE_FILE}" ]]; then
	printf 'Missing RTL-Theme license runtime %s.\n' "${LICENSE_FILE}" >&2
	exit 1
fi

ACTUAL_LICENSE_HASH="$(sha1sum "${PLUGIN_ROOT}/${LICENSE_FILE}" | awk '{print $1}')"
if [[ "${ACTUAL_LICENSE_HASH}" != "${EXPECTED_LICENSE_HASH}" ]]; then
	printf 'RTL-Theme license runtime failed its SHA-1 integrity check.\n' >&2
	exit 1
fi

if [[ ! -f "${PLUGIN_ROOT}/${LICENSE_CHECK_PROTECTED}" ]]; then
	printf 'Missing %s. Send %s to RTL-Theme for protection first.\n' "${LICENSE_CHECK_PROTECTED}" "${LICENSE_CHECK_SOURCE}" >&2
	exit 1
fi

if [[ ! -f "${PLUGIN_ROOT}/${LICENSE_CHECK_SOURCE_SHA1}" ]]; then
	printf 'Missing %s. Run tools/record-protected-source.sh right after sending %s for protection.\n' "${LICENSE_CHECK_SOURCE_SHA1}" "${LICENSE_CHECK_SOURCE}" >&2
	exit 1
fi

RECORDED_SOURCE_HASH="$(tr -d '[:space:]' < "${PLUGIN_ROOT}/${LICENSE_CHECK_SOURCE_SHA1}")"
ACTUAL_SOURCE_HASH="$(sha1sum "${PLUGIN_ROOT}/${LICENSE_CHECK_SOURCE}" | awk '{print $1}')"
if [[ "${RECORDED_SOURCE_HASH}" != "${ACTUAL_SOURCE_HASH}" ]]; then
	printf '%s changed since it was sent for protection. Send it to RTL-Theme again, then run tools/record-protected-source.sh.\n' "${LICENSE_CHECK_SOURCE}" >&2
	exit 1
fi

# The plugin header is the single source of truth for the release version.
VERSION="$(sed -n 's/^ \* Version:[[:space:]]*\([0-9.]*\)$/\1/p' "${PLUGIN_ROOT}/product-expiration-easy-peasy.php")"
VERSION_RE="${VERSION//./\\.}"

if [[ -z "${VERSION}" ]]; then
	printf 'Could not read the plugin header version.\n' >&2
	exit 1
fi

if ! rg -q "^define\( 'PRODUCT_EXPIRATION_EASY_PEASY_VERSION', '${VERSION_RE}' \);$" "${PLUGIN_ROOT}/product-expiration-easy-peasy.php"; then
	printf 'PRODUCT_EXPIRATION_EASY_PEASY_VERSION does not match header version %s.\n' "${VERSION}" >&2
	exit 1
fi

if ! rg -q "^Stable tag: ${VERSION_RE}$" "${PLUGIN_ROOT}/readme.txt"; then
	printf 'readme.txt stable tag does not match header version %s.\n' "${VERSION}" >&2
	exit 1
fi

if ! rg -q "^= ${VERSION_RE} =$" "${PLUGIN_ROOT}/readme.txt"; then
	printf 'readme.txt has no changelog entry for %s.\n' "${VERSION}" >&2
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

cp "${PLUGIN_ROOT}/${LICENSE_CHECK_PROTECTED}" "${TEMP_DIR}/${PLUGIN_SLUG}/${LICENSE_CHECK_SOURCE}"

if [[ "$(sha1sum "${TEMP_DIR}/${PLUGIN_SLUG}/${LICENSE_FILE}" | awk '{print $1}')" != "${EXPECTED_LICENSE_HASH}" ]]; then
	printf 'The staged license runtime does not match its expected SHA-1.\n' >&2
	exit 1
fi

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
