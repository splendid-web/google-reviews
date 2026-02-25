# Google Reviews (Craft CMS 5 Plugin)

Craft CMS 5 plugin scaffold for syncing Google Business Profile reviews into Craft and rendering from stored data.

## Status

Initial scaffold is in place:

- Plugin package metadata and bootstrap
- CP settings model + settings template
- Sync service and console command skeleton
- Twig variable and starter rendering partial

Google API ingestion is wired with OAuth refresh-token auth. Mock mode remains available for local testing.

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
- Use Mock Data toggle
- Use Places API toggle (hybrid mode)
- Places API key + Place ID (for quick setup mode)
- Google account/location identifiers
- OAuth client ID / secret / refresh token (env-backed)
- API base URL
- Max reviews and optional minimum rating
- Attribution text + URL

## Console Command

```bash
php craft google-reviews/sync
```

By default, the scaffold can sync deterministic mock reviews for local testing. Disable mock mode to fetch live reviews from either:
- Places API (hybrid quick setup), or
- Business Profile API (OAuth flow).

## Twig Usage (Starter)

```twig
{% set reviews = craft.googleReviews.reviews(12).all() %}
{% include "google-reviews/_components/reviews-carousel" with {
  reviews: reviews,
  attributionText: "Reviews from Google",
  attributionUrl: "https://www.google.com/maps"
} %}
```

## Finding Your Place ID (for Places mode)

Use one of these methods:

1. Place ID Finder tool:
   - https://developers.google.com/maps/documentation/places/web-service/place-id
2. Places API (New) Text Search (quick CLI test):

```bash
curl -X POST "https://places.googleapis.com/v1/places:searchText" \
  -H "Content-Type: application/json" \
  -H "X-Goog-Api-Key: YOUR_PLACES_API_KEY" \
  -H "X-Goog-FieldMask: places.id,places.displayName,places.formattedAddress" \
  -d '{
    "textQuery": "YOUR_BUSINESS_NAME YOUR_CITY"
  }'
``` 

Then set the returned `places[].id` in plugin settings as **Place ID**.

## Next Implementation Milestones

1. Implement Google Business Profile API client and token handling.
2. Wire CP element index/source definitions for GoogleReview elements.
3. Implement idempotent upsert by `googleReviewId`.
4. Add archiving strategy for removed reviews.
5. Add test coverage for normalization and sync behavior.
