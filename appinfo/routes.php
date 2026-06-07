<?php
/**
 * Nextcloud - Phone Sync
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Loic Blot <loic.blot@unix-experience.fr>
 * @copyright Loic Blot 2014-2017
 */

return ['routes' => [
	['name' => 'sms#index', 'url' => '/', 'verb' => 'GET'],
	['name' => 'sms#delete_conversation', 'url' => '/delete/conversation', 'verb' => 'POST'],
	['name' => 'sms#delete_message', 'url' => '/delete/message', 'verb' => 'POST'],

	['name' => 'settings#set_country', 'url' => '/set/country', 'verb' => 'POST'],
	['name' => 'settings#set_messagelimit', 'url' => '/set/msglimit', 'verb' => 'POST'],
	['name' => 'settings#set_notification_state', 'url' => '/set/notification_state', 'verb' => 'POST'],
	['name' => 'settings#set_contact_order', 'url' => '/set/contact_order', 'verb' => 'POST'],

	// Front API
	['name' => 'sms#retrieve_all_peers', 'url' => '/front-api/v1/peerlist', 'verb' => 'GET'],
	['name' => 'sms#get_conversation', 'url' => '/front-api/v1/conversation', 'verb' => 'GET'],
	['name' => 'sms#check_new_messages',    'url' => '/front-api/v1/new_messages',           'verb' => 'GET'],
	['name' => 'sms#get_queued_messages',   'url' => '/front-api/v1/queued',                 'verb' => 'GET'],
	['name' => 'sms#retry_queued_message',  'url' => '/front-api/v1/queued/{id}/retry',      'verb' => 'POST'],
	['name' => 'sms#wipe_all_user_messages', 'url' => '/front-api/v1/delete/all',            'verb' => 'POST'],
	['name' => 'settings#get_settings', 'url' => '/front-api/v1/settings', 'verb' => 'GET'],

	// Android API v1
	['name' => 'api#get_api_version', 'url' => '/get/apiversion', 'verb' => 'GET'],
	['name' => 'api#push', 'url' => '/push', 'verb' => 'POST'],
	['name' => 'api#replace', 'url' => '/replace', 'verb' => 'POST'],
	['name' => 'api#retrieve_all_ids', 'url' => '/get/smsidlist', 'verb' => 'GET'],
	['name' => 'api#retrieve_last_timestamp', 'url' => '/get/lastmsgtime', 'verb' => 'GET'],

	// Android API v2
	['name' => 'api#get_all_stored_phone_numbers', 'url' => '/api/v2/phones/list', 'verb' => 'GET'],
	['name' => 'api#fetch_messages', 'url' => '/api/v2/messages/{start}/{limit}', 'verb' => 'GET'],
	['name' => 'api#fetch_messages_count', 'url' => '/api/v2/messages/count', 'verb' => 'GET'],

	// Android API v3
	['name' => 'api#generate_sms_test_data', 'url' => '/api/v3/test/generate_sms_data', 'verb' => 'POST'],

	// Android API v4
	['name' => 'api#fetch_messages_to_send', 'url' => '/api/v4/messages/sendqueue',              'verb' => 'GET'],
	['name' => 'api#mark_message_sent',      'url' => '/api/v4/messages/sendqueue/{id}/sent',    'verb' => 'POST'],
	['name' => 'api#mark_message_failed',    'url' => '/api/v4/messages/sendqueue/{id}/failed',  'verb' => 'POST'],
	['name' => 'api#register_device',        'url' => '/api/v4/device/register',                 'verb' => 'POST'],
	['name' => 'api#unregister_device',      'url' => '/api/v4/device/unregister',               'verb' => 'POST'],
	['name' => 'api#purge_sent_queue',       'url' => '/api/v4/messages/sendqueue/purge-sent',   'verb' => 'POST'],

	// Front API (web UI → compose)
	['name' => 'api#queue_message',          'url' => '/front-api/v1/send',                      'verb' => 'POST'],
]];
