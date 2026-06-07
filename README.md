# Phone Sync (ocsms) — Nextcloud 25–33 compatible fork

> **Fork of [nextcloud/ocsms](https://github.com/nextcloud/ocsms)** — the original app was abandoned in 2020 (max NC 20). This fork brings full compatibility with **Nextcloud 25 through 33**, and adds bidirectional SMS support: read *and send* SMS directly from Nextcloud.

## Features

| Feature | Description |
|---|---|
| **SMS archive** | All SMS from your phone are synced and readable in the browser |
| **Compose from web** | Write an SMS in Nextcloud — your phone sends it |
| **Outbox queue** | Pending and failed messages shown live in the conversation view |
| **UnifiedPush** | Near-instant delivery via your self-hosted ntfy server (no Google) |
| **WorkManager fallback** | 15-min polling when push is unavailable — no message is ever lost |
| **Dark mode** | Full support via NC33 CSS variables |

## Architecture

```
┌─────────────────────────────────────────┐
│            Nextcloud (ocsms)            │
│                                         │
│  Web UI ──compose──► ocsms_sendmessage  │
│                       _queue (pending)  │
│                           │             │
│                     PushNotifier        │
│                           │ HTTP POST   │
└───────────────────────────┼─────────────┘
                            ▼
                   ntfy (self-hosted)
                            │ UnifiedPush
                            ▼
              ┌─────────────────────────┐
              │    Android (ncsms)      │
              │                         │
              │  UnifiedPushReceiver    │
              │         │               │
              │   OutboxWorker ──SMS──► carrier
              │         │               │
              │  markSent/Failed ──────► Nextcloud API
              │                         │
              │  SyncWorker (1 h) ─────► push SMS archive
              └─────────────────────────┘
```

SMS are uploaded from Android → Nextcloud by `SyncWorker` (existing behaviour).  
SMS are sent from Nextcloud → Android via an outbox queue, woken up by UnifiedPush.

## What changed from the original

### PHP / Server — NC25–33 compatibility

| Change | Reason |
|---|---|
| PSR-4 file structure (`lib/Controller/`, `lib/Db/`, etc.) | NC33 removed the legacy autoloader |
| `IBootstrap` Application class | `appinfo/app.php` no longer supported in NC33 |
| `OCP\AppFramework\Db\QBMapper` | `Mapper` removed in NC25 |
| `executeQuery()` / `executeStatement()` | `$qb->execute()` deprecated in NC22 |
| Namespace `OCA\Ocsms` | NC33 builds namespace from app ID |
| `ConfigMapper::set()` rewritten with QueryBuilder | `Mapper::execute()` removed |
| Fixed `ConversationStateMapper::setLast()` bug | INSERT was missing its execute() |
| DB migration: `sms_msg` and config `value` → `text` type | NC33 caps `string` columns at 4 000 chars |
| Removed broken FullTextSearch provider | Template code was never completed |
| Routes use return-array syntax | `$app->registerRoutes()` pattern obsolete |

### PHP / Server — new send & push features

| Addition | Detail |
|---|---|
| `ocsms_sendmessage_queue` table | Stores outbound SMS with status (pending / sent / failed) |
| `ocsms_devices` table | Stores UnifiedPush endpoint URLs per user |
| `SendMessageQueueMapper` | CRUD for the outbox queue |
| `DeviceMapper` | Register / unregister push endpoints |
| `PushNotifier` service | HTTP POST to ntfy when a message is queued |
| `ApiController` v4 endpoints | Android polls queue, marks sent/failed, registers device |
| `SmsController` outbox endpoints | Web UI fetches pending/failed messages for display |

### JavaScript / UI

| Feature | Detail |
|---|---|
| Two-column layout inside NC33's content area | NC33 manages `#app-navigation` differently |
| NC33 theme colours | CSS variables: `--color-primary`, `--color-main-text`, etc. |
| Contact search | Real-time filter in the left column |
| Message search | Highlight + ▲▼ navigation between occurrences |
| `md5` polyfill | NC33 removed the global `md5` function |
| `date` filter | Vue 2.x removed all built-in filters |
| **Compose panel** | "New message" button in the sidebar; Ctrl+Enter to send |
| **Live outbox** | Pending/failed queue messages shown below the conversation, auto-refreshed every 15 s |
| **Retry button** | One-click retry for failed messages; triggers immediate UnifiedPush wake-up |

## API reference

### Android API

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/get/lastmsgtime` | Latest SMS timestamp on server (v1) |
| `POST` | `/push` | Upload new SMS from phone (v1) |
| `GET` | `/api/v2/messages/{start}/{limit}` | Paginated message fetch (v2) |
| `GET` | `/api/v4/messages/sendqueue` | Poll outbox for messages to send (v4) |
| `POST` | `/api/v4/messages/sendqueue/{id}/sent` | Confirm SMS was sent (v4) |
| `POST` | `/api/v4/messages/sendqueue/{id}/failed` | Report SMS send failure (v4) |
| `POST` | `/api/v4/device/register` | Register UnifiedPush endpoint (v4) |
| `POST` | `/api/v4/device/unregister` | Unregister UnifiedPush endpoint (v4) |

### Front-end API (web UI → AJAX)

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/front-api/v1/peerlist` | Contact list with last message timestamps |
| `GET` | `/front-api/v1/conversation` | Messages for a phone number |
| `GET` | `/front-api/v1/new_messages` | Unread message counts |
| `POST` | `/front-api/v1/send` | Queue an outbound SMS |
| `GET` | `/front-api/v1/queued` | Pending + failed outbox items for a number |
| `POST` | `/front-api/v1/queued/{id}/retry` | Reset failed → pending + push notification |

## Installation

### From source

```bash
cd /var/www/html/custom_apps/
git clone https://github.com/brisferr/ocsms.git
sudo -u www-data php occ app:enable ocsms
sudo -u www-data php occ migrations:execute ocsms 020200Date20260606000000
sudo -u www-data php occ migrations:execute ocsms 020201Date20260606000000
```

### Docker

```bash
docker cp ocsms/ nextcloud:/var/www/html/custom_apps/
docker exec --user www-data nextcloud php occ app:enable ocsms
```

## UnifiedPush setup (recommended)

UnifiedPush enables near-instant SMS sending from Nextcloud (< 2 s latency).  
Without it, the Android app polls every 15 minutes as a fallback.

1. Install **[ntfy](https://ntfy.sh/docs/install/)** on your server (or reuse the one already used by SchildiChat / Element)
2. In the Android app, install a UnifiedPush distributor — **ntfy for Android** from F-Droid
3. Open the NcSMS app → save your server settings → choose ntfy as distributor when prompted
4. The app registers automatically; Nextcloud will wake it up as soon as you hit **Send**

No Google account or Firebase required.

## Requirements

| Component | Version |
|---|---|
| Nextcloud | 25 – 33 |
| PHP | 8.1+ |
| ntfy *(optional)* | any self-hosted instance |

## Android companion app

Use **[brisferr/ncsms-android](https://github.com/brisferr/ncsms-android)** — a modern Kotlin rewrite compatible with Android 7–14.

## License

AGPL-3.0 — Original authors: Loic Blot and contributors. NC33 + send features: [brisferr](https://github.com/brisferr).
