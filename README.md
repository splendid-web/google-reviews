# Google Reviews (Craft CMS 5 Plugin)

Craft CMS 5 plugin scaffold for syncing Google Business Profile reviews into Craft and rendering from stored data.

## Status

Initial scaffold is in place:

- Plugin package metadata and bootstrap
- CP settings model + settings template
- Sync service and console command skeleton
- Twig variable and starter rendering partial

Google API ingestion is still stubbed, but plugin-owned custom element persistence is in place.

## Requirements

- Craft CMS `^5.3.0`
- PHP compatible with your Craft 5 project

## Install (Local Development via Path Repository)

In your consuming Craft project `composer.json`, add:

```json
{
  "minimum-stability": "dev",
  "prefer-stable": true,
  "repositories": [
    {
      "type": "path",
      "url": "/Users/Rob/Sites/google-reviews"
    }
  ]
}
```

Then require the package:

```bash
composer require splendidweb/craft-google-reviews
```

## Plugin Settings

Open **Settings -> Plugins -> Google Reviews** and configure:

- Enabled toggle
- Google account/location identifiers
- Credentials path (env-backed)
- Max reviews and optional minimum rating
- Attribution text + URL

## Console Command

```bash
php craft google-reviews/sync
```

This scaffold currently syncs deterministic mock reviews so you can test end-to-end flow (migration -> sync -> custom elements -> render) before wiring the real Google API client.

## Twig Usage (Starter)

```twig
{% set reviews = craft.googleReviews.reviews(12).all() %}
{% include "google-reviews/_components/reviews-carousel" with {
  reviews: reviews,
  attributionText: "Reviews from Google",
  attributionUrl: "https://www.google.com/maps"
} %}
```

## Next Implementation Milestones

1. Implement Google Business Profile API client and token handling.
2. Wire CP element index/source definitions for GoogleReview elements.
3. Implement idempotent upsert by `googleReviewId`.
4. Add archiving strategy for removed reviews.
5. Add test coverage for normalization and sync behavior.
