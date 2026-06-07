# Phone Sync (ocsms) — Nextcloud 25–33 compatible fork

> **Fork of [nextcloud/ocsms](https://github.com/nextcloud/ocsms)** — the original was abandoned in 2020 (max NC 20). This fork brings full compatibility with **Nextcloud 25 through 33**, adds bidirectional SMS: read *and send* SMS from Nextcloud, and integrates **UnifiedPush via ntfy** for near-instant delivery without Google.

**Android companion app → [brisferr/ncsms-android](https://github.com/Brisferr/ncsms-android)**

![Screenshot](https://raw.githubusercontent.com/nextcloud/ocsms/master/appinfo/screenshots/1.png)

---

## Features

| Feature | Description |
|---|---|
| **SMS archive** | Browse and search all SMS conversations in Nextcloud |
| **Send SMS** | Compose SMS in the browser — Android delivers it |
| **Near-instant send** | UnifiedPush via self-hosted ntfy wakes the app in < 2 s |
| **Instant replies** | Incoming SMS triggers an immediate sync — no F5 needed |
| **Auto-refresh** | Conversation polls every 30 s for new replies |
| **Outbox view** | Pending / sent / failed messages shown inline with retry |
| **WorkManager fallback** | 15-min polling when push unavailable — no message lost |
| **Contacts** | Phone numbers matched against Nextcloud contacts |
| **Dark mode** | Full support via NC33 CSS variables |

---

## Requirements

| Component | Version |
|---|---|
| Nextcloud | 25 – 33 |
| PHP | 8.1+ |
| ntfy *(optional, recommended)* | any self-hosted instance |

---

## Installation

### From source

```bash
cd /var/www/html/custom_apps/
git clone https://github.com/Brisferr/ocsms.git
sudo -u www-data php occ app:enable ocsms
```

### Docker / docker compose

```bash
docker cp ocsms/ nextcloud:/var/www/html/custom_apps/
docker exec --user www-data nextcloud php occ app:enable ocsms
```

---

## Full stack setup

### 1 — Self-hosted ntfy (UnifiedPush provider)

Add to your docker compose:

```yaml
services:
  ntfy:
    image: binwiederhier/ntfy:latest
    container_name: ntfy
    command: serve
    environment:
      VIRTUAL_HOST: push.example.com
      VIRTUAL_PORT: "80"
      LETSENCRYPT_HOST: push.example.com
    volumes:
      - ./ntfy/config:/etc/ntfy
      - ./ntfy/data:/var/lib/ntfy
    restart: unless-stopped
```

```yaml
# ntfy/config/server.yml
base-url: https://push.example.com
cache-file: /var/lib/ntfy/cache.db
auth-file: /var/lib/ntfy/auth.db
auth-default-access: deny-all
behind-proxy: true
```

Create a user and allow anonymous write to UnifiedPush topics:

```bash
docker exec -e NTFY_PASSWORD=yourpassword ntfy ntfy user add --role=admin youruser
docker exec ntfy ntfy access '*' 'up*' write-only
```

> **Security note:** `write-only` for `up*` topics means anyone who knows the random topic URL can publish a wake-up signal — they cannot read anything, and the worst outcome is a spurious (harmless) poll of Nextcloud. For stricter setups, store ntfy credentials in `PushNotifier.php`.

### 2 — ocsms (this app)

Install as above. No additional configuration needed — the app auto-discovers registered devices.

### 3 — Android app

Install **[ncsms-android](https://github.com/Brisferr/ncsms-android)** and follow its setup guide.

---

## How send-from-browser works

```
Browser (compose bar)
    │  POST /front-api/v1/send
    ▼
oc_ocsms_sendmessage_queue  (status: pending)
    │  PushNotifier POSTs to ntfy endpoint
    ▼
ntfy  ──UnifiedPush──►  ncsms-android  ──SMS──►  carrier
    │
    ├─ OutboxWorker marks sent
    ├─ SyncWorker uploads SMS archive  (sent message visible in conversation)
    └─ purgeSentQueue removes it from outbox  (no duplicate)
```

Incoming replies trigger an immediate sync via `SmsReceiver` (Android broadcast on `SMS_RECEIVED`).

---

## API reference

### Android API v4 (new in this fork)

| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/v4/messages/sendqueue` | Fetch pending messages to send |
| POST | `/api/v4/messages/sendqueue/{id}/sent` | Mark message delivered |
| POST | `/api/v4/messages/sendqueue/{id}/failed` | Mark message failed |
| POST | `/api/v4/messages/sendqueue/purge-sent` | Remove sent entries after sync |
| POST | `/api/v4/device/register` | Register UnifiedPush endpoint |
| POST | `/api/v4/device/unregister` | Unregister UnifiedPush endpoint |

### Front-end API (web UI → AJAX)

| Method | Endpoint | Description |
|---|---|---|
| GET | `/front-api/v1/peerlist` | Contact list with last message timestamps |
| GET | `/front-api/v1/conversation` | Messages for a phone number |
| GET | `/front-api/v1/new_messages` | Unread counts (used for auto-refresh) |
| POST | `/front-api/v1/send` | Queue an outbound SMS |
| GET | `/front-api/v1/queued` | Pending / failed outbox items for a number |
| POST | `/front-api/v1/queued/{id}/retry` | Reset failed → pending + push notification |

### Database tables (new)

| Table | Purpose |
|---|---|
| `oc_ocsms_sendmessage_queue` | Outbound SMS queue (pending / sent / failed) |
| `oc_ocsms_devices` | Registered UnifiedPush endpoints per user |

---

## Differences from upstream nextcloud/ocsms

### NC25–33 compatibility fixes

| Change | Reason |
|---|---|
| PSR-4 file structure (`lib/Controller/`, `lib/Db/`, …) | NC33 removed the legacy autoloader |
| `IBootstrap` Application class | `appinfo/app.php` no longer supported |
| `QBMapper` everywhere | `Mapper` removed in NC25 |
| `executeQuery()` / `executeStatement()` | `execute()` deprecated in NC22 |
| DB migration: `sms_msg` → `text` type | NC33 caps `string` at 4 000 chars |
| Routes use return-array syntax | `$app->registerRoutes()` obsolete |

### New features

| Feature | Detail |
|---|---|
| Send SMS from browser | Outbox queue + `PushNotifier` + `OutboxWorker` |
| UnifiedPush | `DeviceMapper` stores endpoints; `PushNotifier` posts to ntfy |
| Instant reply sync | Android `SmsReceiver` triggers sync on `SMS_RECEIVED` |
| Sent message visibility | Sync after send + `purgeSentQueue` removes duplicates |
| Auto-refresh | `compose.js` polls `/front-api/v1/new_messages` every 30 s |
| Outbox inline | Pending/failed shown below conversation; retry button |

---

## Contributing

Issues and PRs welcome. This fork targets self-hosted, privacy-first setups.

## License

AGPL-3.0 — Original authors: Loic Blot and contributors.  
NC33 compatibility + send features: [brisferr](https://github.com/Brisferr).  
Twemoji: MIT (code) / CC-BY 4.0 (graphics). libphonenumber-for-php: Apache 2.0.
