# Google Reviews for Craft CMS 5

Sync and display Google reviews in Craft from server-side stored data.

This plugin supports two data sources:

- **Google Places API (quick setup):** easiest way to show reviews with minimal onboarding.
- **Google Business Profile API (advanced beta):** OAuth-based setup for deeper account-backed data and owner replies (requires Google API access approval).

## Why Server-Side Storage

Reviews are fetched on a schedule and stored in Craft, instead of being requested from Google on every page load. This helps:

- Minimize Google API calls and reduce quota usage
- Improve frontend performance and reliability
- Avoid exposing API credentials in frontend code
- Keep review output stable even if an API request is temporarily unavailable

## Features

- Sync Google reviews into Craft
- Control Panel index view for imported reviews
- Frontend Twig query API (`craft.googleReviews.reviews()`)
- Starter carousel template (`google-reviews/_components/reviews-example`)
- Optional owner reply support in Business Profile mode (beta)
- Author photo support in frontend and CP table
- Mock mode for local/testing workflows

## Requirements

- Craft CMS `^5.3.0`
- PHP version compatible with your Craft 5 install

## Installation

Install from the Craft Plugin Store or via Composer:

```bash
composer require splendidweb/craft-google-reviews
```

Then install from **Control Panel > Settings > Plugins**.

## Quick Start (Places API)

If you want the fastest setup, use Places mode.

1. Enable **Places API** in Google Cloud Console.
2. Create an API key in **APIs & Services -> Credentials**.
3. Restrict the key for server use:
   - Application restriction: **IP addresses** (recommended)
   - API restriction: **Places API**
4. Find your Place ID (see [Finding Your Place ID](#finding-your-place-id)).
5. In plugin settings:
   - `Enable Sync` = on
   - `Review Source Mode` = `Places API`
   - set `Places API Key`
   - set `Place ID`
6. Run a sync:

```bash
php craft google-reviews/sync
```

## Business Profile Setup (Advanced OAuth - Beta)

Use this mode if you need owner-level API access and review replies.
Availability depends on Google approving API access for your project.

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
   - `Enable Sync` = on
   - `Review Source Mode` = `Business Profile API`
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

See example template in `/templates/_components/reviews-example.twig`.

```twig
{% set reviews = craft.googleReviews.reviews(12).all() %}
{% if reviews|length %}
  <div class="google-reviews-carousel" data-google-reviews-carousel>
    {% for review in reviews %}
      <article class="google-review-card">
        <header class="google-review-card__header">
          {% set authorName = review.authorName ?? review.title ?? "Anonymous" %}
          {% if review.authorPhotoUrl ?? null %}
            <img
              class="google-review-card__avatar"
              src="{{ review.authorPhotoUrl }}"
              alt="{{ authorName }} profile photo"
              loading="lazy"
              width="40"
              height="40"
            >
          {% else %}
            <span class="google-review-card__avatar-fallback" aria-hidden="true">
              {{ authorName|slice(0, 1)|upper }}
            </span>
          {% endif %}
          <strong>
            {% if review.reviewUrl ?? null %}
              <a href="{{ review.reviewUrl }}" rel="nofollow noopener" target="_blank">{{ authorName }}</a>
            {% else %}
              {{ authorName }}
            {% endif %}
          </strong>
          {% if review.reviewDate ?? null %}
            {% set daysAgo = ((now|date('U') - (review.reviewDate|date('U'))) / 86400)|round(0, 'floor') %}
            {% set daysAgo = daysAgo < 0 ? 0 : daysAgo %}
            <p class="google-review-card__date">
              {% if daysAgo < 1 %}
                Today
              {% elseif daysAgo < 7 %}
                {{ daysAgo }} day{{ daysAgo == 1 ? '' : 's' }} ago
              {% else %}
                {% set weeksAgo = (daysAgo / 7)|round(0, 'floor') %}
                {{ weeksAgo }} week{{ weeksAgo == 1 ? '' : 's' }} ago
              {% endif %}
            </p>
          {% endif %}
          {% set rating = (review.rating ?? 0)|round(0, 'floor') %}
          <div class="google-review-card__rating" aria-label="{{ rating }} out of 5 stars">
            {% for i in 1..5 %}{{ i <= rating ? '★' : '☆' }}{% endfor %}
          </div>
        </header>
        {% if review.reviewText ?? null %}
          <p class="google-review-card__text">{{ review.reviewText|truncate(150) }}</p>
        {% endif %}
        {% if review.replyText ?? null %}
          <div class="google-review-card__reply">
            <strong>Business reply:</strong>
            <p>{{ review.replyText }}</p>
          </div>
        {% endif %}
      </article>
    {% endfor %}
  </div>
{% else %}
  <p class="google-reviews-empty">No reviews available.</p>
{% endif %}
```

## Console Command

Run a manual sync at any time (from your Craft project root):

```bash
php craft google-reviews/sync
```

For production, schedule this command via cron.

## Automated Sync (Cron)

Use your server cron to keep reviews up to date automatically.

Example (once per day at 02:00 server time):

```bash
0 2 * * * /usr/bin/php /path/to/project/craft google-reviews/sync >> /path/to/project/storage/logs/google-reviews-sync.log 2>&1
```

Recommended:

- Start with once per day, then increase frequency only if needed.
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
- Owner replies are only available via Business Profile mode, not Places mode.
- Keep credentials in environment variables where possible.
