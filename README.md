# Post to Instagram WordPress Plugin

A modern WordPress plugin for posting images from posts directly to Instagram with OAuth 2.0 authentication, React-based UI, and WP-Cron scheduling.

## Features

- **Instagram Integration**: Secure OAuth 2.0 authentication with Instagram Graph API
- **Gutenberg Integration**: Native block editor sidebar panel for seamless workflow
- **Multi-Image Carousels**: Support for up to 10 images with drag-and-drop reordering
- **Image Cropping**: Built-in cropping to Instagram aspect ratios (1:1, 4:5, 3:4, 1.91:1)
- **Scheduling**: WP-Cron based post scheduling with error recovery
- **Modern Stack**: React frontend with WordPress REST API backend

## Installation

### Prerequisites
- WordPress 5.0+
- PHP 7.4+
- Node.js and npm (for development)

### Setup
```bash
# Clone repository
git clone https://github.com/chubes4/post-to-instagram.git

# Install dependencies
npm install

# Build assets
npm run build

# Create production distribution (optional)
./build.sh

# Or start development mode
npm run start
```

## Development

### Project Structure
```
post-to-instagram/
├── inc/
│   ├── Assets/
│   │   ├── src/js/              # React source files
│   │   └── dist/               # Compiled assets
│   └── Core/
│       ├── Actions/
│       │   ├── Post.php        # Instagram posting
│       │   ├── Schedule.php    # WP-Cron scheduling
│       │   └── Cleanup.php     # File cleanup
│       ├── Admin.php           # Asset enqueuing & admin
│       ├── Auth.php            # OAuth flow
│       └── RestApi.php         # REST endpoints
├── auth/
│   └── oauth-handler.html      # OAuth popup handler
└── post-to-instagram.php       # Main plugin file
```

### REST API Endpoints

All endpoints use `/wp-json/pti/v1/` namespace:

**Authentication**
- `GET /auth/status` - Check authentication status
- `POST /auth/credentials` - Save Instagram app credentials
- `POST /disconnect` - Disconnect Instagram account

**Image Processing**
- `POST /upload-cropped-image` - Upload processed images to temp directory

**Posting & Processing**
- `POST /post-now` - Immediate Instagram posting (may return 202 with `processing_key` if containers still processing)
- `GET /post-status?processing_key=...` - Poll async status (`processing`, `publishing`, `completed`, `error`)
- `POST /schedule-post` - Schedule post for later
- `GET /scheduled-posts` - Retrieve scheduled posts

### React Components

**Core Components**
- `AuthPanel.js` - Instagram authentication UI
- `CaptionInput.js` - Caption text input with character count
- `CropImageModal.js` - Image cropping interface
- `CustomImageSelectModal.js` - Custom image selection interface
- `ScheduledPosts.js` - Scheduled post management
- `SidebarPanelContent.js` - Main sidebar content

**Custom Hooks**
- `useInstagramAuth.js` - Authentication state management
- `useInstagramPostActions.js` - Post and schedule actions

### Data Flow

1. **Authentication**: OAuth popup → token exchange → long-lived access token
2. **Image Selection**: Post content analysis → user selection → drag-and-drop ordering
3. **Processing**: Client-side cropping → temp file uploads (temp URLs stored only client-side) → Instagram media container creation
4. **Async Transition**: If any container status is `IN_PROGRESS`, backend stores minimal transient (IDs + statuses) and returns 202 with `processing_key`
5. **Sequential Polling**: Frontend performs awaited polling every ~4s (no overlapping requests) via `/post-status`
6. **Publish Lock**: When all containers become `FINISHED`, backend acquires a transient-based publish lock (stale after 180s) and publishes (single or carousel)
7. **Completion**: Post meta `_pti_instagram_shared_images` updated, success event dispatched, UI shows final success message

### Configuration

**Instagram App Setup**
1. Create Facebook Developer App
2. Add Instagram Graph API product
3. Configure OAuth redirect URI: `{site_url}/pti-oauth/`
4. Enter App ID and Secret in plugin settings

**Development Environment**
```bash
# Watch mode for development
npm run start

# Production build assets only
npm run build

# Create production distribution zip
./build.sh
```

### WordPress Abilities API (v1.2.0+)

When the WordPress Abilities API is available, three abilities are registered for programmatic Instagram posting by AI agents and automated systems:

| Ability | Description | Permission |
|---------|-------------|------------|
| `post-to-instagram/post-from-media` | Post 1-10 media library images to Instagram with auto-cropping | `upload_files` + `edit_posts` |
| `post-to-instagram/list-media` | List media library images, optionally filtering to unposted only | `upload_files` |
| `post-to-instagram/auth-status` | Check if Instagram is authenticated and token is valid | `edit_posts` |

**Example — post from media library:**

```php
$result = wp_execute_ability( 'post-to-instagram/post-from-media', [
    'attachment_ids' => [ 123, 456 ],
    'caption'        => 'Hello from the API! #wordpress',
    'aspect_ratio'   => '4:5',  // 1:1, 4:5, or 1.91:1
] );
```

Ability-initiated posts without a `post_id` use a private system post (`[PTI] Ability Posts`) for tracking. All abilities are exposed via REST and MCP (`meta.mcp.public = true`).

### Security

- All REST endpoints protected with WordPress nonces + capability checks (`edit_posts`, `manage_options`)
- Transient-based publish lock prevents duplicate publish in multi-tab or race scenarios (stale takeover after 180s)
- Sequential polling eliminates overlapping status requests client-side
- Temporary files auto-cleanup after 24 hours
- OAuth state validation with CSRF protection

### Troubleshooting

**Common Issues**
- Check browser console for React errors
- Review WordPress debug.log for PHP errors
- Verify Instagram app permissions and redirect URI
- Ensure temporary directory is writable

**Debug / Observability**
- Inspect Network tab for `POST /post-now` 202 responses and subsequent `GET /post-status` cycles (`processing` → `publishing` → `completed`).
- WordPress `debug.log` will contain container creation / publish errors and stale lock recovery events.

## License

GPL v2 or later

## Author

Chris Huber - [chubes.net](https://chubes.net) | [GitHub](https://github.com/chubes4)