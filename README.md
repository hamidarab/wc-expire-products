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

The plugin bootstrap remains readable so WordPress can detect the header and fail safely. The generated `RTL_License_700d86aad12a6fe9.php` runtime is integrity-checked before it is loaded. Do not alter that file without regenerating the SDK and updating the expected SHA-1 in the adapter.

Do not distribute repository metadata, tests, graph output, or build tooling inside the customer ZIP. Before an RTL-Theme submission, complete the manual items in `docs/release-checklist-fa.md`, including a clean install, supported-version test matrix, VirusTotal scan, non-AI icon and cover, Persian demo, and review of the authenticated seller rules.
