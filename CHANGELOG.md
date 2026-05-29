# Changelog

All notable changes to this project are documented in this file.

## 1.0.8 - 2026-05-29

- Fixed duplicate review rows after upgrading to 1.0.7+ by removing legacy un-scoped review elements (empty `sourceLocationId`) via migration and pruning stale imported rows after each sync.
- Stabilised Places API review IDs so reordering in Google's response no longer creates duplicate rows.
- Added optional location-specific summary lookup via `craft.googleReviews.summary(sourceLocationId)` while preserving aggregate `summary()` behavior.
- Added location-aware summary storage in `googlereviews_summary` so sync now writes one summary row per location plus an aggregate row.

## 1.0.7 - 2026-04-29

- Added multi-location sync support for both Places API and Business Profile API by allowing location settings to resolve to either a single ID or a JSON array of IDs.
- Added per-review location metadata storage (`sourceLocationId`, `sourceLocationName`) with migration support for existing installs.
- Updated sync normalization/upsert flow to scope review identity by location, preventing collisions across multiple locations.
- Added location-aware query support (`sourceLocationId`, `sourceLocationName`) and optional Twig filtering args in `craft.googleReviews.reviews()` / `entries()`.
- Added `Location` column to the Control Panel review index for clearer editor visibility.
- Improved Business Profile location naming by resolving a friendly title via Business Information API with safe fallbacks.
- Updated settings help text and README documentation with multi-location configuration and Twig usage examples.

## 1.0.6 - 2026-04-22

- Added native Google Business Profile OAuth connect flow in plugin settings, including authorization start/callback handling.
- Added OAuth credential test action in settings to verify refresh-token exchange before running sync.
- Added a manual `Run Sync Now` button in settings for Control Panel-triggered sync runs with result notices.
- Improved settings action flow to keep users on plugin settings after manual sync.

## 1.0.5 - 2026-03-05

- Improved Business Profile setup documentation with copy-paste OAuth/token/account/location steps.
- Added practical troubleshooting notes for common setup errors (`401`, token formatting, OAuth test users, zsh shebang issue).
- Clarified plugin settings labels by prefixing API-specific fields with `Places API` and `GBP API`.

## 1.0.4 - 2026-03-04

- Added review overview summary support by storing Google-provided aggregate rating and total review count per sync.
- Added Twig summary access via `craft.googleReviews.summary()` for frontend rendering.
- Added summary table migration for existing installs and included summary table creation in fresh installs.
- Updated README frontend example to include summary usage.

## 1.0.3 - 2026-02-28

- Replaced mode lightswitches with a single `Review Source Mode` dropdown (`Mock Data`, `Places API`, `Business Profile API`) and made Mock the default.

## 1.0.2 - 2026-02-25

- Enabled deletion actions for `GoogleReview` elements in the Control Panel index.
- Added explicit element permission checks (`canView`, `canSave`, `canDelete`) so delete actions are actually available in CP.
- Fixed sync upsert behavior to include/restore trashed matching reviews, so deleted reviews can reappear correctly after re-sync.

## 1.0.1 - 2026-02-25

- Removed plugin-level `Attribution Text` and `Attribution URL` settings.
- Removed plugin-level `API Base URL` setting and now use a fixed Business Profile API endpoint internally.
- Removed attribution output from the bundled reviews component so attribution is fully frontend-managed.

## 1.0.0 - 2026-02-25

- Initial public release of the Google Reviews plugin for Craft CMS 5.
- Marked project as commercial-ready for Plugin Store submission.

## 0.7.0 - 2026-02-25

- Added author photo support in frontend carousel and CP review table.
- Added `authorPhoto` table attribute handling on the custom element.

## 0.6.0 - 2026-02-25

- Added Business Profile owner reply support (`replyText`, `replyUpdatedAt`) in sync and storage.
- Added migration support for reply fields in existing installs.
- Improved review normalization to map mixed API response formats safely.

## 0.5.0 - 2026-02-25

- Added hybrid sync modes: Google Places API (New) and Google Business Profile API.
- Implemented OAuth refresh-token exchange for automatic access-token refresh during sync.
- Added Places API (New) mapping for author attribution, review text, publish time, and Maps URL.

## 0.4.0 - 2026-02-24

- Added plugin CP navigation and review index view for synced data.
- Refined CP table columns for author, rating, comment, reply, review date, and updated date.
- Added frontend reviews carousel component for starter rendering.

## 0.3.0 - 2026-02-24

- Pivoted from section/entry storage to a plugin-owned custom `GoogleReview` element type.
- Added element query support and Twig variable access for frontend querying.
- Added persistence logic for review upsert via element save lifecycle.

## 0.2.0 - 2026-02-24

- Added initial review sync service with filtering and normalization pipeline.
- Added console command support for manual sync runs.
- Added deterministic mock review mode for local testing.

## 0.1.0 - 2026-02-23

- Initial Craft CMS 5 plugin scaffold with Composer package metadata.
- Added plugin bootstrap, settings model/template, and README foundation docs.
