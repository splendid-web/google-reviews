# Google Reviews for Craft CMS 5

Sync and display Google reviews in Craft from server-side stored data.

This plugin supports two data sources:

- **Google Places API (quick setup):** easiest way to show reviews with minimal onboarding.
- **Google Business Profile API (advanced):** OAuth-based setup for deeper account-backed data and owner replies.

## Why Server-Side Storage

Reviews are fetched on a schedule and stored in Craft, instead of being requested from Google on every page load. This helps:

- Minimize Google API calls and reduce quota usage
- Improve frontend performance and reliability
- Avoid exposing API credentials in frontend code
- Keep review output stable even if an API request is temporarily unavailable

## Features

- Sync Google reviews into a custom Craft element type (`GoogleReview`)
- Control Panel index view for imported reviews
- Frontend Twig query API (`craft.googleReviews.reviews()`)
- Starter carousel template (`google-reviews/_components/reviews-carousel`)
- Optional owner reply support in Business Profile mode
- Author photo support in frontend and CP table
- Mock mode for local/testing workflows

## Requirements

- Craft CMS `^5.3.0`
- PHP version compatible with your Craft 5 install

## Installation

Install from the Craft Plugin Store, or via Composer:

```bash
composer require splendidweb/craft-google-reviews
```

Then install from **Control Panel -> Settings -> Plugins**.

## Quick Start (Places API)

If you want the fastest setup, use Places mode.

1. Enable **Places API** in Google Cloud Console.
2. Create an API key in **APIs & Services -> Credentials**.
3. Restrict the key for server use:
   - Application restriction: **IP addresses** (recommended)
   - API restriction: **Places API**
4. Find your Place ID (see [Finding Your Place ID](#finding-your-place-id)).
5. In plugin settings:
   - `Enabled` = on
   - `Use Mock Data` = off
   - `Use Places API` = on
   - set `Places API Key`
   - set `Place ID`
6. Run a sync:

```bash
php craft google-reviews/sync
```

## Business Profile Setup (Advanced OAuth)

Use this mode if you need owner-level API access and review replies.

1. In Google Cloud Console:
   - Enable Business Profile APIs
   - Configure OAuth consent screen
   - Create OAuth Client ID + Client Secret
2. Add your Google account as a **Test user** while app is in testing.
3. Generate a refresh token (for example via OAuth Playground):
   - scope: `https://www.googleapis.com/auth/business.manage`
4. Fetch your account and location IDs:
   - `GET https://mybusinessaccountmanagement.googleapis.com/v1/accounts`
   - `GET https://mybusinessbusinessinformation.googleapis.com/v1/accounts/{accountId}/locations`
5. In plugin settings:
   - `Enabled` = on
   - `Use Mock Data` = off
   - `Use Places API` = off
   - set `Google Account ID`
   - set `Google Location ID`
   - set `OAuth Client ID`
   - set `OAuth Client Secret`
   - set `OAuth Refresh Token`
6. Run a sync:

```bash
php craft google-reviews/sync
```

The plugin uses your refresh token to automatically request new access tokens on each sync.

## Frontend Usage

```twig
{% set reviews = craft.googleReviews.reviews(12).all() %}
{% include "google-reviews/_components/reviews-carousel" with {
  reviews: reviews,
  attributionText: "Reviews from Google",
  attributionUrl: "https://www.google.com/maps"
} %}
```

## Console Command

Run a manual sync at any time:

```bash
php craft google-reviews/sync
```

For production, schedule this command via cron at your preferred interval.

## Automated Sync (Cron)

Use your server cron to keep reviews up to date automatically.

Example (once per day at 02:00 server time):

```bash
0 2 * * * /usr/bin/php /path/to/project/craft google-reviews/sync >> /path/to/project/storage/logs/google-reviews-sync.log 2>&1
```

Recommended:

- Start with every 6-12 hours for most sites.
- Use absolute paths for both PHP and your Craft project.
- Log output so failed runs can be diagnosed quickly.
- Run cron on the production host where Craft is installed.

## Finding Your Place ID

You can use either method:

1. Google Place ID Finder:
   - https://developers.google.com/maps/documentation/places/web-service/place-id
2. Places API Text Search:

```bash
curl -X POST "https://places.googleapis.com/v1/places:searchText" \
  -H "Content-Type: application/json" \
  -H "X-Goog-Api-Key: YOUR_PLACES_API_KEY" \
  -H "X-Goog-FieldMask: places.id,places.displayName,places.formattedAddress" \
  -d '{
    "textQuery": "YOUR_BUSINESS_NAME YOUR_CITY"
  }'
```

Use `places[].id` from the response as your plugin `Place ID`.

## Notes and Limits

- Places API (New) returns a maximum of 5 reviews per place in the place details response.
- If you need more than 5 reviews and owner replies, use Business Profile mode.
- Owner replies are generally available via Business Profile mode, not Places mode.
- Keep credentials in environment variables where possible.

## Support

If you run into setup issues, include:

- your selected mode (Places or Business Profile)
- the sync command output
- any relevant Google API error response
