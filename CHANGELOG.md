2.5.0
* Send SMS from Nextcloud web UI via Android phone (OutboxWorker + queue)
* UnifiedPush support via self-hosted ntfy — near-instant outbox delivery (< 2 s)
* Incoming replies trigger immediate sync via Android SmsReceiver (SMS_RECEIVED)
* Conversation auto-refreshes every 30 s — no F5 needed for new messages
* Outbox section inline in conversation: pending / sent ✓ / failed with retry
* purgeSentQueue: sent items removed from outbox after sync — no duplicate messages
* API v4: sendqueue CRUD, device register/unregister, purge-sent endpoint
* DeviceMapper: stores UnifiedPush endpoints per user (oc_ocsms_devices table)
* PushNotifier service: HTTP POST to ntfy on message queue

2.4.0
* Full Nextcloud 25–33 compatibility (PSR-4, QBMapper, executeQuery/executeStatement)
* IBootstrap Application class replacing legacy appinfo/app.php
* DB migration: sms_msg and config value columns to text type (NC33 limit fix)
* Removed broken FullTextSearch provider
* Routes rewritten as return-array syntax
* Modern compose bar with Ctrl+Enter to send and New conversation modal
* NC33 theme: full CSS variable support, dark mode
* Contact search with real-time filter
* Message search with highlight and ▲▼ navigation
* md5 polyfill and date filter for Vue 2.x compatibility

