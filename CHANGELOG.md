# Changelog

All notable changes to the CheckWarranty GLPI plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Release preparation
- Fail builds on invalid PHP and mismatched release tags.
- Package runtime files only; compile translations and generate SHA-256 checksums.
- Create draft prereleases in CI until runtime acceptance is complete.
- Restore the complete GPLv3 license text.
- Correct catalog metadata to alpha status and a Composer compatibility constraint.

### Known limitations
- The integration below is under development. See RELEASE.md for release blockers;
  GLPI 10/11 runtime compatibility and the listed features are not yet verified.

### Added
- Dedicated "Warranty (CheckWarranty)" tab in Computer asset details.
- 1-Click live warranty refresh button for administrators.
- Automatic creation and updating of GLPI `Infocom` records (`warranty_date`, `buy_date`, `warranty_duration`, `warranty_info`).
- Support for Dell, Lenovo, HP, and Apple warranty and SLA tiers (*ProSupport Plus*, *Care Pack NBD*).
- Support for factory BOM hardware specifications enrichment (CPU model, RAM configuration, Storage size).
- Background scheduled task (`CronTask`) `checkwarranty_sync` for automated overnight batch updates.
- Smart recheck filter to skip computers with active warranties and save API credits.
- CSRF compliance and full uninstallation database cleanup.
- Localization support with English (`en_GB`), French (`fr_FR`), and Russian (`ru_RU`) translations.
