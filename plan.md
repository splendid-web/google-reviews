Google Reviews Plugin Plan

Approach

Build this as a reusable plugin package (not one-off template code) so it can ship with your base product across client projects.





Keep data ingestion server-side (scheduled sync), not front-end fetches.



Persist reviews as normal Craft entries so Twig/modules can query them like any other content.



Expose a plugin service + console command for manual/scheduled sync.

Where It Fits In This Project





Craft app currently uses Composer-managed plugins in [/Users/Rob/Sites/craft-base/build/craft/composer.json](/Users/Rob/Sites/craft-base/build/craft/composer.json).



Local custom module exists at [/Users/Rob/Sites/craft-base/build/craft/modules/module/Module.php](/Users/Rob/Sites/craft-base/build/craft/modules/module/Module.php), which can be used for temporary wiring/testing.

Recommended: create a new Composer package plugin (e.g. splendidweb/google-reviews) and include it in composer.json like your other splendidweb/* packages.

Plugin Scope (v1)

1) Settings + Auth

Plugin settings page/ENV-backed config:





Google account/location identifiers (Business Profile API)



OAuth/service credentials reference



Sync controls (enabled, max reviews, minimum rating optional)



Attribution URL/text defaults

Keep secrets in ENV, not project config YAML.

2) Storage Model

Use a dedicated section for synced reviews (e.g. googleReviews) with fields:





googleReviewId (unique key)



authorName



authorPhotoUrl



rating (number)



reviewText



reviewDate



reviewUrl



source (Google)



isImported (bool)



optional featured (manual override)

Upsert by googleReviewId to avoid duplicates.

3) Sync Service

Plugin service method:





fetch reviews from Google API



normalize payload to your internal schema



upsert entries in googleReviews



optionally archive/unpublish removed reviews



log sync summary + errors

4) Console Command + Scheduler

Add command route, e.g.:





php craft google-reviews/sync

Then schedule with cron/GitHub Action during deployment operations (or daily).

5) Front-end Rendering

No direct Google API calls from Twig.

Rendering pattern in templates/modules:





query entries from googleReviews



output via existing card/carousel patterns (e.g. your current listing/card/swiper components)



include required Google attribution markup/link

Rollout Steps





Scaffold plugin package and wire into Composer.



Add plugin settings + env mapping.



Create review section/fields and unique ID logic.



Implement sync service + console command.



Seed with first sync and verify entries.



Add one display mode (carousel) using existing templates.



Add operational docs (required env vars, command, schedule).

Validation Checklist





Sync is idempotent (repeat run does not duplicate entries).



Rate limits/errors fail gracefully with logs.



No front-end API dependency for page render.



Reviews display with attribution and degrade gracefully when empty.



Multi-site behavior is defined (shared reviews vs site-specific).

Notes for Future (v2)





Optional moderation workflow (approved flag)



Optional language/location filtering



Optional aggregated rating schema output



Webhook/near-real-time sync if needed later

