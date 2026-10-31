# Release 1.0.1 — 2026-10-31

## Acceptance evidence

The plugin passes isolated runtime acceptance against **GLPI 10.0.28** and **GLPI 11.0.12**, using the official core containers, separate MariaDB databases and a fake CheckWarranty B2B server. The current test harness was also run locally on 2026-10-31. No production databases or paid lookups are used.

Coverage includes installation, activation, deactivation/reactivation and uninstall with table cleanup; manual refresh and bounded cron; expiry-only and exact-interval warranties; preservation of purchase dates and known partial data; specifications-only, not-found, asynchronous, failed and transport-error responses; encrypted API credentials; object/entity and financial permissions; invalid CSRF; HTML escaping; persisted progress beyond the first 50 Computers; and cleanup on Computer purge.

The source includes PHP 8.0–8.4 lint/contract/package CI, PHP-CS-Fixer validation, complete EN/FR/RU translation catalogs and archive regression tests. Runtime compatibility beyond the two tested GLPI versions follows the stated supported ranges but has not been separately exercised for every patch release. GLPI 12 is unsupported.

## Release and catalog procedure

1. Publish the accepted source to [checkwarranty/glpi-plugin-checkwarranty](https://github.com/checkwarranty/glpi-plugin-checkwarranty).
2. Build using `bash tools/package.sh v1.0.1`. Verify both archives and the SHA-256 file. Tag the accepted commit `v1.0.1`; CI initially creates a draft prerelease for review.
3. Publish that release as stable only after the checks pass. Verify anonymous access to the archive and [raw plugin.xml](https://raw.githubusercontent.com/checkwarranty/glpi-plugin-checkwarranty/main/plugin.xml).
4. Submit the raw XML URL through the authenticated [GLPI catalog form](https://plugins.glpi-project.org/#/submit).

Catalog activation is controlled by GLPI moderators. A submitted entry must not be described as approved until the catalog confirms it. The source repository, release archives and this file remain the canonical installation and validation references.

Official guidance: [publishing a plugin](https://glpi-developer-documentation.readthedocs.io/en/master/plugins/tutorial.html#publishing-your-plugin), [plugin guidelines](https://glpi-developer-documentation.readthedocs.io/en/master/plugins/guidelines.html).
