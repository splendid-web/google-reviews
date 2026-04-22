# Google Reviews for Craft CMS 5

Sync and display Google reviews in Craft from server-side stored data.

This plugin supports two data sources:

- **Google Places API (quick setup):** easiest way to show reviews with minimal onboarding.
- **Google Business Profile API (advanced):** OAuth-based setup for deeper account-backed data and owner replies (requires Google API access approval).

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
- Frontend summary API (`craft.googleReviews.summary()`) for aggregate rating and total review count
- Starter template (`google-reviews/_components/reviews-example`)
- Optional owner reply support in Business Profile mode
- Author photo support
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

## Business Profile Setup (Advanced OAuth)

Use this mode if you need owner-level API access and review replies.
Availability depends on Google approving API access for your project.

### 1) Configure Google Cloud

1. Enable Business Profile APIs in your Google Cloud project.
2. Configure the OAuth consent screen.
3. Create OAuth credentials (`Client ID` and `Client Secret`).
4. While your app is in testing, add your Google account as a **Test user**.

### 2) Generate a refresh token (one-time)

You can use either method:

- Plugin Settings OAuth connect flow (recommended)
- OAuth 2.0 Playground (manual)

#### Option A: Plugin Settings OAuth connect flow

1. Add your OAuth client credentials in plugin settings:
   - `GBP API OAuth Client ID`
   - `GBP API OAuth Client Secret`
2. Add this callback URL to your Google OAuth Client authorized redirect URIs:
   - `https://YOUR_SITE_URL/actions/google-reviews/oauth/callback`
3. In plugin settings, click **Connect Google Business Profile**.
4. Approve access and return to Craft.
5. If your refresh token field uses an env var reference (for example `$GOOGLE_REVIEWS_REFRESH_TOKEN`), copy the token shown in the success notice into your `.env` value.

#### Option B: OAuth Playground (manual)

Use [OAuth 2.0 Playground](https://developers.google.com/oauthplayground):

1. Open the gear icon and enable **Use your own OAuth credentials**.
2. Paste your OAuth `Client ID` and `Client Secret`.
3. Use scope: `https://www.googleapis.com/auth/business.manage`
4. Authorize, then click **Exchange authorization code for tokens**.
5. Save the **refresh token** (not the access token).

The plugin stores your refresh token and automatically requests fresh access tokens during each sync.

### 3) Fetch account and location IDs

Create a short-lived access token from your refresh token:

```bash
export GOOGLE_CLIENT_ID="YOUR_CLIENT_ID"
export GOOGLE_CLIENT_SECRET="YOUR_CLIENT_SECRET"
export GOOGLE_REFRESH_TOKEN="YOUR_REFRESH_TOKEN"

ACCESS_TOKEN=$(curl -s https://oauth2.googleapis.com/token \
  -d client_id="$GOOGLE_CLIENT_ID" \
  -d client_secret="$GOOGLE_CLIENT_SECRET" \
  -d refresh_token="$GOOGLE_REFRESH_TOKEN" \
  -d grant_type=refresh_token | php -r 'echo json_decode(stream_get_contents(STDIN), true)["access_token"] ?? "";')
```

Fetch accounts:

```bash
curl -s "https://mybusinessaccountmanagement.googleapis.com/v1/accounts" \
  -H "Authorization: Bearer $ACCESS_TOKEN" \
  -H "Accept: application/json"
```

Copy `accountId` from `name` (for example: `accounts/1234567890` -> `1234567890`).

Fetch locations for that account:

```bash
ACCOUNT_ID="1234567890"

curl -s "https://mybusinessbusinessinformation.googleapis.com/v1/accounts/$ACCOUNT_ID/locations?pageSize=100&readMask=name,title,storeCode,metadata" \
  -H "Authorization: Bearer $ACCESS_TOKEN" \
  -H "Accept: application/json"
```

Copy `locationId` from `name` (for example: `locations/9876543210` -> `9876543210`).

### 4) Add values in plugin settings

- `Enable Sync` = on
- `Review Source Mode` = `Business Profile API`
- `GBP API Account ID` = your account ID
- `GBP API Location ID` = your location ID
- `GBP API OAuth Client ID` = your OAuth client ID
- `GBP API OAuth Client Secret` = your OAuth client secret
- `GBP API OAuth Refresh Token` = your refresh token

Run a sync:

```bash
php craft google-reviews/sync
```

## Frontend Usage

See example template in `/templates/_components/reviews-example.twig`.

```twig
{% set summary = craft.googleReviews.summary() %}
{% set reviews = craft.googleReviews.reviews(12).all() %}
{% if summary.totalReviewCount %}
  <p>
    Rated {{ summary.overallRating ?? "?" }} from {{ summary.totalReviewCount }} Google reviews.
  </p>
{% endif %}
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
