# Product Expiration Easy Peasy

WooCommerce product-expiration management with validated product and variation dates, stock automation, staff notifications, order snapshots, and built-in Jalali display for Persian locales.

## Development checks

Run the standalone safety tests:

```bash
php tests/run.php
```

Run WordPress Coding Standards with the PHPCS bundled by the local Plugin Check installation:

```bash
php ../plugin-check/vendor/bin/phpcs --standard=phpcs.xml.dist
```

Build the directly installable RTL-Theme package and outer submission structure:

```bash
./tools/build-release.sh
```

The generated output is placed in `dist/`, with the installable ZIP under `dist/Plugin/` and the Persian guide at `dist/help.pdf`.

## Release policy

The main plugin file stays readable: it is the loader, and it requires RTL-Theme's `RTL_License_ca50fd723e00b32e.php` runtime only when ionCube Loader is present. The license check lives in `includes/class-license-check.php`, which RTL-Theme protects with ionCube; it verifies the runtime's SHA-1 before asking whether the license is active. Never open or re-save the runtime or the protected copy; `.gitattributes` marks both binary, and `protected/` is excluded from PHPCS and from ripgrep/fd searches (`.ignore`), so no formatter or search-and-replace should touch it.

After any change to `includes/class-license-check.php`, send it to RTL-Theme for protection again and put the encoded copy at `protected/includes/class-license-check.php`. Right after sending it, run `tools/record-protected-source.sh` to record the SHA-1 of the source you sent. `tools/build-release.sh` ships the protected copy in place of the readable source and refuses to build if it is missing or if the source no longer matches the recorded SHA-1.

Do not distribute repository metadata, tests, graph output, or build tooling inside the customer ZIP. Before an RTL-Theme submission, complete the manual items in `docs/release-checklist-fa.md`, including a clean install, supported-version test matrix, VirusTotal scan, non-AI icon and cover, Persian demo, and review of the authenticated seller rules.
