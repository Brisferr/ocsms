# Changelog

## Brisferr fork

### 2.5.0 — 2026-06-08
* Send SMS from Nextcloud web UI via Android phone (OutboxWorker + queue)
* UnifiedPush support via self-hosted ntfy — near-instant outbox delivery (< 2 s)
* Incoming replies trigger immediate sync via Android SmsReceiver (SMS_RECEIVED)
* Conversation auto-refreshes every 30 s — no F5 needed for new messages
* Outbox section inline in conversation: pending / sent ✓ / failed with retry button
* purgeSentQueue: sent items removed from outbox exactly when sync completes — no duplicates
* New API v4 endpoints: sendqueue CRUD, device register/unregister, purge-sent
* DeviceMapper: per-user UnifiedPush endpoint storage (oc_ocsms_devices table)
* PushNotifier service: HTTP POST to ntfy endpoint on message queue
* DB migration: ocsms_devices and ocsms_sendmessage_queue tables

### 2.4.0 — 2026-06-07
* Full Nextcloud 25–33 compatibility — PSR-4 namespace/file structure (NC33 dropped legacy autoloader)
* IBootstrap Application class — appinfo/app.php no longer supported in NC33
* QBMapper everywhere — Mapper class removed in NC25
* executeQuery() / executeStatement() — execute() deprecated in NC22
* DB migration: sms_msg and config value → text type (NC33 caps string columns at 4 000 chars)
* Fixed ConversationStateMapper::setLast() — INSERT was missing its execute() call
* Removed broken FullTextSearch provider (template code, never functional)
* Routes rewritten as return-array syntax — $app->registerRoutes() obsolete
* Modern compose bar: fixed bottom bar, Ctrl+Enter to send, New conversation modal (+)
* New conversation flow: unknown numbers added to contact list immediately in Vue
* NC33 theme: full CSS variable support (--color-primary, --color-main-text, etc.), dark mode
* Two-column layout compatible with NC33 app-navigation structure
* Contact search: real-time filter in left column
* Message search: highlight + ▲▼ navigation between occurrences
* md5 polyfill: NC33 removed the global md5 function
* date filter: Vue 2.x removed all built-in filters
* Outbox live refresh every 15 s with retry button for failed messages

---

## Original upstream (nextcloud/ocsms — Loic Blot)

### 1.7.0
* Enhance the contact list using nicer list like in contact app
* PHP code cleanup (thanks to PHPStorm)
* Angular app code cleanup & enhancements
* Start to implement API calls for restoring messages to phones (using ownCloud SMS app)
* Show the contact avatar in the conversation

### 1.6.0
* You can now limit messages shown when loading a conversation
* Update AngularJS to 1.4.9
* You can now disable desktop notifications
* New application icon (thanks to @skjnldsv)
* Add singapore country code

### 1.5.0
* You can now delete conversation or single messages (only in owncloud, not on your phone via the app)
* Fix a scrolling issue (thanks @animalillo)
* Fix duplicate numbers in conversation in some cases
* Update AngularJS to 1.3
* Rewrite all JS code to use AngularJS

### 1.4.5
* Fix a MySQL issue with some key length
* Fix a mischecked variable in sync process which could block the sync process

### 1.4.4
* Add more european country codes
* Code refactoring to respect owncloud app style
* Minor performance improvements

### 1.4.3
* Add south Africa country code

### 1.4.2
* Fix appframework check issue
* Fix angular.js library

### 1.4.0
* Use contact avatars into the conversation list
* Add a user setting to set your country — deduplicates local/international prefixes and prevents split conversations
* Add angular.js support into template
* Re-organize JS sources

### 1.3.3
* Fix JS code for HTML5 notifications on browsers which don't support it (like IE)

### 1.3.2
* Fix an integer overflow on 32-bit systems which blocks the sync process

### 1.3.1
* Fix a CSRF issue when phone pushes data
