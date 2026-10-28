# Release readiness — 2026-10-28

Status: **not ready for a stable release or catalog submission**.
The version constant `1.0.0` is a target version, not evidence of a released build.

## Blocking findings from source inspection

1. **B2B response shape**: `front/computer.form.php` and `inc/cron.class.php`
   read `data.warranty` / `data.specs`. The current API DTO and sandbox service in
   the application return top-level `device` and `warranty`; asynchronous scan
   items also contain top-level `device` and `warranty`. Successful results can
   therefore be ignored or recorded as empty success. Normalize both transport
   paths and test sandbox, not-found, errors and partial useful results.
2. **Asset authorization**: checking global `computer:UPDATE` in
   `front/computer.form.php` does not establish authorization for the submitted
   Computer ID/entity or financial data. Use the core object's permission check
   before any paid request and verify cross-entity denial. The tab also displays
   Infocom data and the action without corresponding object permission checks.
3. **Warranty semantics**: source writes warranty expiry into `warranty_date`
   and warranty start into `buy_date`. Verify the target GLPI schema semantics
   against the core before using these fields: warranty commencement, purchase
   and expiry are different dates. Never fabricate Dell startDate or overwrite
   a purchase date using a warranty date. Verify unknown/estimated start dates,
   specs-only results and dates without a full warranty interval.
4. **Cron fairness and time budget**: `LIMIT 50` has no persisted cursor or
   eligibility pagination; later computers can starve behind the first batch.
   Up to 50 synchronous polls may also exceed the automatic action's time budget.
   Add progress tracking and bounded work, and verify a dataset larger than 50.
5. **GLPI compatibility**: syntax checks do not load GLPI parent classes and
   cannot verify overridden method signatures, hooks, configuration tabs, CSRF,
   query builder joins or uninstall APIs. Test actual supported GLPI 10 and 11
   installations. Check GLPI 11 migration restrictions before claiming support.
6. **HTML output and configuration**: replace `Html::cleanInputText()` as an
   assumed output encoder with context-appropriate escaping; test quotes and
   markup in serials, notes and configuration. Review API URL validation,
   credential storage, HTTP error handling and the unused auto-detect toggle.
7. **Translations and code standards**: current PO files cover only part of the
   interface. Extract all strings and complete translations. Run an actual
   GLPI coding-standard checker; PHP syntax lint is not a standards/security audit.

These findings were identified by reading source, not by executing a GLPI
instance. No paid scans or production database modifications were performed.

## Acceptance evidence required

- Install, activate, configure, deactivate, reactivate, upgrade and uninstall on
  isolated GLPI 10 and 11 databases; verify tables and automatic-action cleanup.
- Exercise manual refresh and cron using sandbox results for Dell, Lenovo, HP and
  Apple; confirm actual dates and fields on the Computer page.
- Verify error, not-found, expired, partial useful data and Dell without startDate.
- Verify users without rights, cross-entity IDs, CSRF failures and malicious HTML.
- Verify more than 50 assets progress without repeat charges or starvation.
- Attach real screenshots and exact GLPI/PHP versions/results to the release.
- Validate catalog logo (40×40), screenshots, metadata and every public URL.

## Actual catalog submission route

Official references:

- https://glpi-developer-documentation.readthedocs.io/en/master/plugins/tutorial.html#publishing-your-plugin
- https://glpi-developer-documentation.readthedocs.io/en/master/plugins/guidelines.html
- https://glpi-developer-documentation.readthedocs.io/en/master/codingstandards.html
- https://plugins.glpi-project.org/#/submit

The catalog accepts a public Git repository and raw XML metadata. Neither a
transfer to pluginsGLPI nor a services.glpi-network.com developer submission is
required by the documented catalog procedure. Catalog inclusion requires
moderator activation; do not advertise this as official approval in advance.

1. Finish the blocking fixes and runtime acceptance above.
2. Publish source to `checkwarranty/glpi-plugin-checkwarranty` with full license.
3. Review `plugin.xml`: state, version, tested Composer compatibility constraint,
   public logo/screenshots and release asset URL. Its current download URL is a
   future release target and is not usable while the release is a draft.
4. Build the version-matching archives and verify SHA-256 checksums. Tag the exact
   accepted commit `v1.0.0`. CI creates a draft prerelease for review.
5. After acceptance, publish the release and verify anonymous download of both
   the archive and raw metadata URL:
   `https://raw.githubusercontent.com/checkwarranty/glpi-plugin-checkwarranty/main/plugin.xml`.
6. Sign in to the catalog and submit that raw XML URL. The browser checked on
   2026-10-28 showed **You must be authed** on the submission page. Account login
   or registration and moderation remain external steps.

GPL-3.0-or-later is this project's chosen license. The documentation does not
establish French translation or this specific license as universal requirements.
Keep the current alpha metadata out of a stable submission until acceptance.
