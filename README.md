# Blue Wing

Open source social media scheduling tool. Schedule posts to X (Twitter) and Bluesky from a single interface, with support for multiple accounts and per-platform content customisation.

## Features

- **Multi-platform scheduling** - publish to X and Bluesky simultaneously
- **Multiple accounts** - connect as many X and Bluesky accounts as you need
- **Content customisation** - write default text, then override per-provider or per-account
- **Team access** - grant other users viewer or editor access to your social accounts
- **Media attachments** - upload images, GIFs, and video to posts with per-platform limit enforcement
- **Background publishing** - posts are dispatched via queue jobs for reliable delivery
- **Calendar view** - see your scheduled content on a month-by-month calendar
- **Extensible architecture** - add new social providers without touching core scheduling logic
- **REST API** - schedule posts and query accounts programmatically via token-authenticated endpoints ([API docs](API.md))

## Requirements

- PHP 8.2+
- Composer
- Node.js 18+ and npm
- SQLite (default) or MySQL / PostgreSQL

## Installation

```bash
git clone git@github.com:mikebarlow/bluewing.git
cd bluewing

composer install
cp .env.example .env
php artisan key:generate
```

Configure the database related variables in `.env`, along with any extra settings needed then run.

```bash
php artisan migrate

npm install
npm run build
```
## Environment Variables

The `.env.example` file ships with sensible defaults. Key variables to review:

| Variable | Purpose | Default |
|----------|---------|---------|
| `APP_URL` | Your application URL | `http://localhost` |
| `DB_CONNECTION` | Database driver | `sqlite` |
| `QUEUE_CONNECTION` | Queue backend | `database` |
| `SESSION_DRIVER` | Session storage | `database` |
| `X_CLIENT_ID` | X (Twitter) OAuth 2.0 Client ID | - |
| `X_CLIENT_SECRET` | X (Twitter) OAuth 2.0 Client Secret | - |
| `X_REDIRECT_URI` | OAuth callback URL (override only if needed) | `{APP_URL}/social-accounts/connect/x/callback` |
| `X_API_BASE_URL` | X REST API base URL | `https://api.x.com/2` |
| `X_UPLOAD_BASE_URL` | X media upload base URL (v2 supports OAuth 2.0) | `https://upload.x.com/2` |
| `BLUEWING_MEDIA_DISK` | Filesystem disk for media storage | `public` |

`X_CLIENT_ID` and `X_CLIENT_SECRET` are required to connect X accounts. Get them from the [X Developer Portal](https://developer.x.com/en/portal/dashboard). Media uploads use the v2 upload endpoint (`upload.x.com/2`) which supports OAuth 2.0; if you see a 403 on media upload, check your app's access level in the portal (e.g. Elevated or Basic may be required for media). Bluesky credentials are entered per-account and do not require app-level env vars. All per-account tokens are encrypted at rest via Laravel's `encrypted` cast.

## Running Locally

Start all services (web server, queue worker, Vite, and log tail) with one command:

```bash
composer dev
```

This runs:
- `php artisan serve` - local web server
- `php artisan queue:listen` - queue worker for publishing jobs
- `npm run dev` - Vite dev server with HMR
- `php artisan pail` - real-time log viewer

## Queue Worker

Posts are published in the background via queue jobs. In production, run a persistent queue worker:

```bash
php artisan queue:work --tries=3 --backoff=30
```

For process management, use Supervisor or a similar tool. See the [Laravel queue documentation](https://laravel.com/docs/12.x/queues#supervisor-configuration) for configuration examples.

## Scheduler

The scheduler dispatches due posts every minute. Add this cron entry to your server:

```
* * * * * cd /path-to-bluewing && php artisan schedule:run >> /dev/null 2>&1
```

This runs the `bluewing:dispatch-due-posts` command, which:

1. Finds posts with `status = scheduled` and `scheduled_for <= now()`
2. Transitions each post and its targets to `queued`
3. Dispatches a `PublishPostTargetJob` for each target

You can also run it manually:

```bash
php artisan bluewing:dispatch-due-posts
```

## Connecting Social Accounts

### X (Twitter)

X accounts are connected via OAuth 2.0 with PKCE. Users click **Connect with X** and authorize via the standard X consent screen - no manual API key entry required.

**Setup (one-time, by the application admin):**

1. Create an application in the [X Developer Portal](https://developer.x.com/en/portal/dashboard).
2. Under **User authentication settings**, enable OAuth 2.0 and set:
   - **Type of App:** Web App (confidential client)
   - **Callback URL:** `https://yourdomain.com/social-accounts/connect/x/callback`
   - **Website URL:** your application URL
3. Copy the **Client ID** and **Client Secret** into your `.env`:
   ```
   X_CLIENT_ID=your_client_id
   X_CLIENT_SECRET=your_client_secret
   ```

**Scopes requested:** `tweet.read`, `tweet.write`, `users.read`, `offline.access`

- `offline.access` provides a refresh token so Blue Wing can automatically refresh expired access tokens without requiring re-authorization.
- Access tokens are refreshed automatically before publishing if they are within 5 minutes of expiry.
- If a refresh token is revoked or expires, the user will need to reconnect their account.

**Troubleshooting:**

| Problem | Solution |
|---------|----------|
| "X OAuth2 Client ID is not configured" | Set `X_CLIENT_ID` and `X_CLIENT_SECRET` in `.env` |
| "Invalid OAuth state" | Session expired between redirect and callback - try again |
| "Failed to exchange authorization code" | Callback URL mismatch - ensure the URL in X Developer Portal matches exactly |
| Token refresh fails during publishing | The user's refresh token was revoked - reconnect the X account |

### Bluesky

You need:

- **Handle** - your Bluesky handle (e.g. `yourname.bsky.social`)
- **App Password** - generate one in Bluesky settings under *Privacy and Security → App Passwords*

Navigate to **Social Accounts → Connect Bluesky Account** and enter both fields.

## Post Content Customisation

When creating a post, content is resolved using a three-tier precedence system:

1. **Default text** - applies to all targets
2. **Provider override** - overrides the default for all accounts on a specific platform (e.g. all X accounts)
3. **Account override** - overrides everything for a specific social account

This lets you tailor content per platform (e.g. shorter text for X, longer for Bluesky) or per account.

## Media Attachments

Posts can include images, GIFs, or video. Media is uploaded separately and then attached when scheduling a post.

### Supported Formats

Images: JPG, PNG, WebP, GIF  
Video: MP4, MOV, AVI, WebM

### Platform Limits

| Platform | Type  | Max Size |
|----------|-------|----------|
| X        | Image | 5 MB |
| X        | GIF   | 15 MB |
| X        | Video | 512 MB |
| Bluesky  | Image | 1 MB |
| Bluesky  | Video | 100 MB |

### Constraints

- Up to 4 images per post (GIFs count as images).
- 1 video per post. Cannot mix images and video.
- Bluesky images support per-image alt text.
- **Cross-posting rule:** when posting to multiple platforms, the strictest limit applies. For example, an image posted to both X and Bluesky must be under 1 MB (the Bluesky limit).

### Upload via API

Media uses a two-step flow: upload the file first via `POST /api/media`, then pass the returned `media_id` when creating a post via `POST /api/posts`. See [API.md](API.md) for full details.

### X API Domains

All X API calls use `x.com` domains:

- REST API: `https://api.x.com`
- Media uploads: `https://upload.x.com`

Base URLs are configurable via `X_API_BASE_URL` and `X_UPLOAD_BASE_URL` environment variables. No `twitter.com` domains are used anywhere in the codebase.

## Users and Permissions

Social accounts are owned by the user who connected them. Owners can grant access to other users:

| Role | Can view posts/calendar | Can create/edit/schedule posts |
|------|------------------------|-------------------------------|
| **Viewer** | Yes | No |
| **Editor** | Yes | Yes |

Permissions are enforced via Laravel Policies at the authorization layer, not just the UI.

## Architecture

```
app/
├── Console/Commands/
│   └── DispatchDuePostsCommand.php     # Scheduler command
├── Domain/
│   ├── Media/
│   │   ├── MediaLimits.php             # Per-provider size constants
│   │   └── ValidateMediaForTargetsAction.php  # Shared media validation
│   ├── Posts/
│   │   ├── CreatePostAction.php        # Create post with variants, targets, media
│   │   ├── UpdatePostAction.php        # Update post with variants, targets, media
│   │   ├── ListPostsQuery.php          # Filtered post queries
│   │   └── PostData.php                # Portable post DTO
│   └── SocialAccounts/
│       └── GetAccessibleAccountsQuery.php
├── Enums/                              # PostStatus, PostTargetStatus, Provider, MediaType, etc.
├── Http/Controllers/
│   ├── Api/                            # Thin API controllers (posts, accounts, media)
│   └── XOAuthController.php           # X OAuth2 connect + callback
├── Jobs/
│   └── PublishPostTargetJob.php        # Queue job per target (handles media upload)
├── Livewire/
│   ├── Dashboard.php                   # Post list with filters
│   ├── Posts/                          # Create, Edit, Calendar (with media upload)
│   └── SocialAccounts/                 # Connect, Index, Permissions
├── Models/                             # User, Post, PostTarget, PostVariant, PostMedia, etc.
├── Policies/                           # SocialAccountPolicy, PostPolicy
└── Services/
    └── SocialProviders/
        ├── Contracts/                  # SocialProviderClient, ProviderMediaItem, DTOs
        ├── Bluesky/BlueskyClient.php   # AT Protocol posting + blob upload
        ├── X/XClient.php              # OAuth2 publishing + media upload via upload.x.com
        └── SocialProviderFactory.php   # Provider resolver
```

### Adding a New Provider

1. Create a new client class implementing `SocialProviderClient` under `app/Services/SocialProviders/YourProvider/`
2. Add a case to the `Provider` enum in `app/Enums/Provider.php`
3. Register it in `SocialProviderFactory::$providers`
4. Create a connect form Livewire component and view
5. Add a route and navigation link

The scheduling pipeline, variant resolution, and publish job need no changes.

## API

Blue Wing exposes a REST API for scheduling posts and querying social accounts. All endpoints require authentication via Sanctum API tokens, which can be created under **Settings > API Tokens** in the web UI.

See [API.md](API.md) for full endpoint documentation, request parameters, and response examples.

## Running Tests

```bash
php artisan test
```

Or with lint check:

```bash
composer test
```

The test suite covers:

- Variant precedence logic (account > provider > default)
- Permission enforcement (owner, viewer, editor, stranger)
- Dispatch command status transitions and job dispatching
- Publish job status updates, post reconciliation, and credential persistence
- Publish job media upload and PostTargetMedia record creation
- X OAuth2 connect flow (state validation, PKCE, callback handling)
- X OAuth2 token refresh during publishing (expired, buffer, missing refresh token)
- X API base URL assertions (`api.x.com`, `upload.x.com`, no `twitter.com`)
- Provider factory resolution and credential validation
- Media validation (size limits, mixing rules, cross-posting enforcement)
- Livewire component interactions (CRUD, filters, authorization, media upload)
- API endpoints (authentication, pagination, filtering, creation, authorization)
- API media upload and deletion endpoints
- API post creation with media attachments and alt text
- API token management (creation, rolling, deletion, prefix storage)
- Policy enforcement for social accounts and posts

## Code Style

This project follows PSR-12 via [Laravel Pint](https://laravel.com/docs/12.x/pint):

```bash
./vendor/bin/pint
```

## License

BlueWing is open-source software licensed under the [MIT License](LICENSE).
