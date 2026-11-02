#!/usr/bin/env bash

# Run right after sending includes/class-license-check.php to RTL-Theme for
# protection. Records which readable source the protected copy was made from,
# so tools/build-release.sh can refuse a protected copy of an older source.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
LICENSE_CHECK_SOURCE="includes/class-license-check.php"
SOURCE_SHA1_FILE="protected/includes/class-license-check.php.source-sha1"

if [[ ! -f "${PLUGIN_ROOT}/${LICENSE_CHECK_SOURCE}" ]]; then
	printf 'Missing %s.\n' "${LICENSE_CHECK_SOURCE}" >&2
	exit 1
fi

mkdir -p "$(dirname "${PLUGIN_ROOT}/${SOURCE_SHA1_FILE}")"
sha1sum "${PLUGIN_ROOT}/${LICENSE_CHECK_SOURCE}" | awk '{print $1}' > "${PLUGIN_ROOT}/${SOURCE_SHA1_FILE}"

printf 'Recorded %s as the source of the protected copy:\n  %s\n' "${LICENSE_CHECK_SOURCE}" "$(cat "${PLUGIN_ROOT}/${SOURCE_SHA1_FILE}")"
