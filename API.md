# Blue Wing API

REST API for scheduling and managing social media posts. All endpoints require authentication via Sanctum API tokens.

## Base URL

```
https://your-app-url.com/api
```

## Authentication

All requests must include a valid API token in the `Authorization` header:

```
Authorization: Bearer <token>
```

Tokens are created in the Blue Wing UI under **Settings > API Tokens**. The raw token is shown once at creation time and cannot be retrieved again.

Unauthenticated requests return `401 Unauthorized`.

## Endpoints

- [Upload Media](#upload-media)
- [Delete Media](#delete-media)
- [List Social Accounts](#list-social-accounts)
- [List Posts](#list-posts)
- [Create Post](#create-post)

---

### Upload Media

Uploads a media file for later attachment to a post. This is the first step of a two-step upload flow: upload the file, then pass the returned `media_id` when creating a post.

```
POST /api/media
```

#### Request

Send as `multipart/form-data`.

| Field      | Type   | Required | Description |
|------------|--------|----------|-------------|
| `file`     | file   | Yes      | The media file. Supported formats: `jpg`, `jpeg`, `png`, `gif`, `webp`, `mp4`, `mov`, `avi`, `webm`. Max 512 MB |
| `alt_text` | string | No       | Accessibility description (max 1000 characters) |

#### Response

```
201 Created
```

```json
{
  "data": {
    "id": 1,
    "type": "image",
    "original_filename": "photo.jpg",
    "mime_type": "image/jpeg",
    "size_bytes": 245000,
    "alt_text": "A sunset over the ocean",
    "width": null,
    "height": null,
    "duration_seconds": null,
    "url": "https://your-app-url.com/storage/media/abc123.jpg",
    "created_at": "2026-02-25T10:00:00+00:00"
  }
}
```

The `type` field is automatically detected from the file MIME type: `image`, `gif`, or `video`.

#### Errors

| Status | Reason |
|--------|--------|
| 401    | Missing or invalid token |
| 422    | Missing file, unsupported format, or file too large |

---

### Delete Media

Deletes an uploaded media file that has not yet been attached to a post.

```
DELETE /api/media/{id}
```

#### Response

```
204 No Content
```

#### Errors

| Status | Reason |
|--------|--------|
| 401    | Missing or invalid token |
| 403    | Media belongs to another user |
| 404    | Media not found |
| 422    | Media is already attached to a post |

---

### List Social Accounts

Returns social accounts the authenticated user can access (owned and shared).

```
GET /api/social-accounts
```

#### Query Parameters

| Parameter  | Type   | Required | Description |
|------------|--------|----------|-------------|
| `provider` | string | No       | Filter by provider. Values: `x`, `bluesky` |

#### Response

```
200 OK
```

```json
{
  "data": [
    {
      "id": 1,
      "provider": "x",
      "display_name": "myxhandle",
      "external_identifier": "123456789",
      "created_at": "2026-02-24T12:00:00+00:00",
      "updated_at": "2026-02-24T12:00:00+00:00"
    },
    {
      "id": 2,
      "provider": "bluesky",
      "display_name": "me.bsky.social",
      "external_identifier": "did:plc:abc123",
      "created_at": "2026-02-24T12:30:00+00:00",
      "updated_at": "2026-02-24T12:30:00+00:00"
    }
  ]
}
```

Credentials are never exposed in the response.

#### Errors

| Status | Reason |
|--------|--------|
| 401    | Missing or invalid token |
| 422    | Invalid `provider` value |

---

### List Posts

Returns a paginated list of posts the authenticated user can access, ordered by scheduled date (newest first).

```
GET /api/posts
```

#### Query Parameters

| Parameter  | Type    | Required | Description |
|------------|---------|----------|-------------|
| `status`   | string  | No       | Filter by status. Values: `draft`, `scheduled`, `queued`, `publishing`, `sent`, `failed`, `cancelled` |
| `provider` | string  | No       | Filter by target provider. Values: `x`, `bluesky` |
| `from`     | string  | No       | Start date/datetime (inclusive). ISO 8601 or `YYYY-MM-DD` |
| `to`       | string  | No       | End date/datetime (inclusive). Must be after or equal to `from` |
| `per_page` | integer | No       | Results per page (1–100). Default: `15` |
| `page`     | integer | No       | Page number. Default: `1` |
| `include`  | string  | No       | Comma-separated list of relations to include. Supported: `variants` |

#### Response

```
200 OK
```

```json
{
  "data": [
    {
      "id": 1,
      "user_id": 1,
      "scheduled_for": "2026-03-01T14:00:00+00:00",
      "status": "scheduled",
      "sent_at": null,
      "created_at": "2026-02-24T12:00:00+00:00",
      "updated_at": "2026-02-24T12:00:00+00:00",
      "targets": [
        {
          "id": 1,
          "social_account_id": 1,
          "status": "pending",
          "sent_at": null,
          "error_message": null,
          "social_account": {
            "id": 1,
            "provider": "x",
            "display_name": "myxhandle",
            "external_identifier": "123456789",
            "created_at": "2026-02-24T12:00:00+00:00",
            "updated_at": "2026-02-24T12:00:00+00:00"
          }
        }
      ]
    }
  ],
  "links": {
    "first": "https://your-app-url.com/api/posts?page=1",
    "last": "https://your-app-url.com/api/posts?page=3",
    "prev": null,
    "next": "https://your-app-url.com/api/posts?page=2"
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 3,
    "per_page": 15,
    "to": 15,
    "total": 42
  }
}
```

Targets (with their nested social account) are always included. Variants are excluded by default and only included when `include=variants` is passed.

#### Response with `include=variants`

When requesting `?include=variants`, each post object will also contain a `variants` array:

```json
{
  "variants": [
    {
      "id": 1,
      "scope_type": "default",
      "scope_value": null,
      "body_text": "Check out our new release!"
    },
    {
      "id": 2,
      "scope_type": "provider",
      "scope_value": "x",
      "body_text": "New release! 🚀 Check it out"
    },
    {
      "id": 3,
      "scope_type": "social_account",
      "scope_value": "1",
      "body_text": "Custom text for this specific account"
    }
  ]
}
```

#### Variant Scope Types

| `scope_type`     | `scope_value`       | Description |
|------------------|---------------------|-------------|
| `default`        | `null`              | Default text applied to all targets |
| `provider`       | Provider name (`x`, `bluesky`) | Override for all accounts on a specific platform |
| `social_account` | Social account ID   | Override for a specific social account |

When publishing, content is resolved with this precedence: **social_account > provider > default**.

#### Errors

| Status | Reason |
|--------|--------|
| 401    | Missing or invalid token |
| 422    | Invalid filter values, or `from` is after `to` |

---

### Create Post

Creates a new scheduled post with one or more target social accounts.

```
POST /api/posts
```

#### Request Body

```json
{
  "scheduled_for": "2026-03-01T14:00:00Z",
  "body_text": "Check out our new release!",
  "targets": [1, 2],
  "provider_overrides": {
    "x": "New release! 🚀 Check it out"
  },
  "account_overrides": {
    "1": "Custom text for this specific X account"
  },
  "media_ids": [10, 11],
  "alt_texts": {
    "10": "A screenshot of the new feature",
    "11": "Logo of the project"
  }
}
```

#### Body Parameters

| Parameter            | Type   | Required | Description |
|----------------------|--------|----------|-------------|
| `scheduled_for`      | string | Yes      | ISO 8601 datetime. Must not be significantly in the past |
| `body_text`          | string | Yes      | Default post text (max 5000 characters) |
| `targets`            | array  | Yes      | Array of social account IDs to post to. Minimum 1 |
| `provider_overrides` | object | No       | Keyed by provider name (`x`, `bluesky`). Value is override text (max 5000 characters) |
| `account_overrides`  | object | No       | Keyed by social account ID. Value is override text (max 5000 characters) |
| `media_ids`          | array  | No       | Array of media IDs from prior `POST /api/media` uploads. Max 4 |
| `alt_texts`          | object | No       | Keyed by media ID. Alt text for each media item (max 1000 characters each) |

#### Media Constraints

- Upload media first via `POST /api/media`, then reference by ID.
- Max 4 images or 1 video per post. Cannot mix images and video.
- GIFs count toward the image limit.
- Media must belong to the authenticated user.
- Platform limits are enforced based on the strictest target. See [Media Limits](#media-limits).

The authenticated user must have **editor** permission on every target social account. Account owners always have editor access. Users with only viewer access cannot create posts.

#### Response

```
201 Created
```

```json
{
  "data": {
    "id": 5,
    "user_id": 1,
    "scheduled_for": "2026-03-01T14:00:00+00:00",
    "status": "scheduled",
    "sent_at": null,
    "created_at": "2026-02-24T13:00:00+00:00",
    "updated_at": "2026-02-24T13:00:00+00:00",
    "targets": [
      {
        "id": 10,
        "social_account_id": 1,
        "status": "pending",
        "sent_at": null,
        "error_message": null,
        "social_account": {
          "id": 1,
          "provider": "x",
          "display_name": "myxhandle",
          "external_identifier": "123456789",
          "created_at": "2026-02-24T12:00:00+00:00",
          "updated_at": "2026-02-24T12:00:00+00:00"
        }
      },
      {
        "id": 11,
        "social_account_id": 2,
        "status": "pending",
        "sent_at": null,
        "error_message": null,
        "social_account": {
          "id": 2,
          "provider": "bluesky",
          "display_name": "me.bsky.social",
          "external_identifier": "did:plc:abc123",
          "created_at": "2026-02-24T12:30:00+00:00",
          "updated_at": "2026-02-24T12:30:00+00:00"
        }
      }
    ],
    "variants": [
      {
        "id": 20,
        "scope_type": "default",
        "scope_value": null,
        "body_text": "Check out our new release!"
      },
      {
        "id": 21,
        "scope_type": "provider",
        "scope_value": "x",
        "body_text": "New release! 🚀 Check it out"
      },
      {
        "id": 22,
        "scope_type": "social_account",
        "scope_value": "1",
        "body_text": "Custom text for this specific X account"
      }
    ],
    "media": [
      {
        "id": 10,
        "type": "image",
        "original_filename": "screenshot.png",
        "mime_type": "image/png",
        "size_bytes": 340000,
        "alt_text": "A screenshot of the new feature",
        "width": null,
        "height": null,
        "duration_seconds": null,
        "url": "https://your-app-url.com/storage/media/abc123.png",
        "created_at": "2026-02-25T10:00:00+00:00"
      }
    ]
  }
}
```

The create response always includes targets, variants, and media.

#### Errors

| Status | Reason |
|--------|--------|
| 401    | Missing or invalid token |
| 403    | User lacks editor permission on one or more target accounts |
| 422    | Validation failed (see error details below) |

Example `422` response:

```json
{
  "message": "At least one target social account is required.",
  "errors": {
    "targets": [
      "At least one target social account is required."
    ]
  }
}
```

---

## Media Limits

Platform-specific upload limits are enforced when creating a post with media. When cross-posting to multiple platforms, the **strictest** limit applies.

| Platform | Type  | Max Size |
|----------|-------|----------|
| X        | Image | 5 MB |
| X        | GIF   | 15 MB |
| X        | Video | 512 MB |
| Bluesky  | Image | 1 MB |
| Bluesky  | Video | 100 MB |

**Cross-posting example:** When posting an image to both X and Bluesky, the Bluesky limit of 1 MB applies because it is stricter than the X limit of 5 MB.

Additional constraints:
- Max 4 images per post (GIFs count as images).
- Max 1 video per post.
- Cannot mix images and video in the same post.
- Bluesky images support per-image alt text.

---

## Post Lifecycle

Posts move through the following statuses:

```
draft → scheduled → queued → publishing → sent
                                       ↘ failed
              scheduled → cancelled
```

| Status       | Description |
|--------------|-------------|
| `draft`      | Saved but not scheduled for publishing |
| `scheduled`  | Queued to be dispatched at `scheduled_for` time |
| `queued`     | Dispatcher has picked it up and jobs are enqueued |
| `publishing` | At least one target is currently being published |
| `sent`       | All targets published successfully |
| `failed`     | One or more targets failed to publish |
| `cancelled`  | Post was cancelled before publishing |

Target statuses: `pending`, `queued`, `sent`, `failed`, `skipped`.

---

## Rate Limiting

The API does not currently impose its own rate limits beyond what Laravel's default throttle middleware provides. Social media platform rate limits are handled during the publishing pipeline and will result in a `failed` target status if exceeded.

---

## Content-Type

JSON endpoints:

```
Content-Type: application/json
Accept: application/json
```

Media upload endpoint (`POST /api/media`):

```
Content-Type: multipart/form-data
Accept: application/json
```
