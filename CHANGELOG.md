# Changelog

All notable changes to this project are documented in this file.

## Unreleased

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
