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

## Google Authentication Setup

Choose one live mode:

### Option A: Places API (Quick Setup)

Best when you want the simplest onboarding for read-only review display.

1. In Google Cloud Console, enable **Places API**.
2. Create an API key in **APIs & Services -> Credentials**.
3. Restrict the key for server-side use:
   - Application restrictions: **IP addresses** (recommended) or unrestricted for temporary local testing.
   - API restrictions: **Places API** only.
4. Find a `Place ID` (see section below).
5. In plugin settings:
   - `Use Mock Data` = off
   - `Use Places API` = on
   - set `Places API Key`
   - set `Place ID`
6. Run sync:

```bash
php craft google-reviews/sync
```

Note: Places responses are typically a limited subset of reviews, not full review history.
Owner reply text is generally not available from Places responses.

### Option B: Business Profile API (Advanced OAuth)

Best when you need owner-account API access and fuller review management options.

1. In Google Cloud Console:
   - Enable Business Profile APIs
   - Configure OAuth consent screen
   - Create OAuth Client ID + Client Secret
2. If your app is in testing, add your Google account as a **Test user**.
3. Generate tokens (for example with OAuth Playground):
   - scope: `https://www.googleapis.com/auth/business.manage`
   - exchange auth code for tokens
   - save the `refresh_token`
4. Get account/location IDs:
   - `GET https://mybusinessaccountmanagement.googleapis.com/v1/accounts`
   - `GET https://mybusinessbusinessinformation.googleapis.com/v1/accounts/{accountId}/locations`
5. In plugin settings:
   - `Use Mock Data` = off
   - `Use Places API` = off
   - set `Google Account ID`
   - set `Google Location ID`
   - set `OAuth Client ID`
   - set `OAuth Client Secret`
   - set `OAuth Refresh Token`
6. Run sync:

```bash
php craft google-reviews/sync
```

Business Profile mode also maps owner reply text (`reviewReply`) when available.

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

1. Add optional UI helper for Place ID lookup/testing.
2. Add retry/backoff handling for Google API rate-limit errors.
3. Add archiving strategy for removed reviews.
4. Add test coverage for normalization and sync behavior.
5. Add docs for production API key restrictions and rotation.
