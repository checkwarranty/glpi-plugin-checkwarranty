# CheckWarranty for GLPI

Development version of a native GLPI plugin for hardware warranty lookup through
[CheckWarranty](https://checkwarranty.info). Licensed under GPL-3.0-or-later.

**Status: alpha, unreleased. Do not install on production GLPI.**
The current code has known API, authorization and warranty mapping problems.
Read [RELEASE.md](RELEASE.md) before testing. GLPI 10 and 11 are intended targets;
compatibility has not been established by a running integration test.
This plugin has not been approved or listed by Teclib'.

## Intended features

- Warranty tab on Computer records with a manual refresh action.
- Synchronization with native financial/warranty records (Infocom).
- Optional specification notes and an automatic synchronization action.
- English, French and Russian localization (translation coverage is incomplete).

A CheckWarranty API key is required. Live lookups can consume paid API credits;
use a sandbox key and test serials for acceptance tests. An empty serial echo,
provider error or not-found result does not represent useful device data.

## Development

Requirements: PHP 8.0+, curl/json extensions, Composer, Python 3,
GNU gettext (`msgfmt`), tar, zip and shasum.

```bash
composer validate --strict
composer lint
composer test
bash tools/package.sh
```

The builder compiles translations in a temporary staging directory, lints PHP,
and produces `dist/glpi-checkwarranty-1.0.0.tar.gz`, `.zip` and `.sha256`.
Both archives contain exactly one top-level `checkwarranty/` directory and the
same runtime files. Development tools, repository metadata and credentials are
excluded. A mismatched version argument fails the build:
`bash tools/package.sh v1.0.0`.

For isolated testing, extract the archive under GLPI's `plugins/` directory,
then install and activate it from Setup > Plugins. Configure a sandbox key under
Setup > Plugins > CheckWarranty. Keep the automatic action disabled until the
release acceptance checklist passes. Back up the database before install testing.

## Publication

The source repository is intended to be
[checkwarranty/glpi-plugin-checkwarranty](https://github.com/checkwarranty/glpi-plugin-checkwarranty).
The standalone repository must contain this directory's contents at its root,
including `.github/`, `tools/` and `tests/`; do not upload the parent monorepo.
Tagged builds create a **draft prerelease**, requiring runtime acceptance before
public release. See [RELEASE.md](RELEASE.md) for the submission procedure.
