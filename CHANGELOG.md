# Changelog

## [1.0.1] — 2026-10-31

- Normalize current B2B API and asynchronous results, including useful partial data.
- Preserve purchase dates and known warranty data; map exact warranty intervals correctly.
- Enforce Computer/entity and financial permissions before billable calls or writes.
- Encrypt API credentials, escape rendered output and validate API endpoints.
- Resume pending requests and retain completed results when target storage fails.
- Add bounded automatic synchronization with persisted progress beyond 50 Computers.
- Complete English, French and Russian translations.
- Verify lifecycle, database mapping, security and cron on GLPI 10.0.28 and 11.0.12.
- Package runtime files with compiled translations and SHA-256 checksums.

## [1.0.0] — development draft

Initial unpublished prototype. Replaced by the tested 1.0.1 release.
