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

namespace OCA\Ocsms\Controller;


use \OCP\IRequest;
use \OCP\AppFramework\Http;
use \OCP\AppFramework\Controller;
use \OCP\AppFramework\Http\JSONResponse;

use \OCA\Ocsms\Db\SmsMapper;
use \OCA\Ocsms\Db\SendMessageQueueMapper;
use \OCA\Ocsms\Db\DeviceMapper;
use \OCA\Ocsms\Service\PushNotifier;

class ApiController extends Controller {

	private $userId;
	private $smsMapper;
	private $queueMapper;
	private $deviceMapper;
	private $pushNotifier;
	private $errorMsg;

	public function __construct(
		$appName,
		IRequest $request,
		$userId,
		SmsMapper $mapper,
		SendMessageQueueMapper $queueMapper,
		DeviceMapper $deviceMapper,
		PushNotifier $pushNotifier
	) {
		parent::__construct($appName, $request);
		$this->userId       = $userId;
		$this->smsMapper    = $mapper;
		$this->queueMapper  = $queueMapper;
		$this->deviceMapper = $deviceMapper;
		$this->pushNotifier = $pushNotifier;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function getApiVersion () {
		return new JSONResponse(array("version" => 1));
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * This function is used by API v1
	 * Phone will compare its own message list with this
	 * message list and send the missing messages
	 * This call will remain as secure slow sync mode (1 per hour)
	 */
	public function retrieveAllIds () {
		$smsList = $this->smsMapper->getAllIds($this->userId);
		return new JSONResponse(array("smslist" => $smsList));
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * This function is used by API v2
	 * Phone will get this ID to push recent messages
	 * This call will be used combined with retrieveAllIds
	 * but will be used more times
	 */
	public function retrieveLastTimestamp () {
		$ts = $this->smsMapper->getLastTimestamp($this->userId);
		return new JSONResponse(array("timestamp" => $ts));
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @param $smsCount
	 * @param $smsDatas
	 * @return JSONResponse
	 */
	public function push ($smsCount, $smsDatas) {
		if ($this->checkPushStructure($smsCount, $smsDatas) === false) {
			return new JSONResponse(
				array("status" => false, "msg" => $this->errorMsg),
				Http::STATUS_BAD_REQUEST
			);
		}

		$this->smsMapper->writeToDB($this->userId, $smsDatas);
		return new JSONResponse(array("status" => true, "msg" => "OK"));
	}

	/**
	 * @NoAdminRequired
	 * @param $smsCount
	 * @param $smsDatas
	 * @return JSONResponse
	 */
	 public function replace($smsCount, $smsDatas) {
		if ($this->checkPushStructure($smsCount, $smsDatas) === false) {
			return new JSONResponse(
				array("status" => false, "msg" => $this->errorMsg),
				Http::STATUS_BAD_REQUEST
			);
		}

		$this->smsMapper->writeToDB($this->userId, $smsDatas, true);
		return new JSONResponse(array("status" => true, "msg" => "OK"));
	}

	/**
	 * @param $smsCount
	 * @param $smsDatas
	 * @return bool
     */
	private function checkPushStructure (&$smsCount, &$smsDatas) {
		if ($smsCount === NULL) {
			$this->errorMsg = "Error: smsCount field is NULL";
			return false;
		}

		if ($smsDatas === NULL) {
			$this->errorMsg = "Error: smsDatas field is NULL";
			return false;
		}

		if ($smsCount != count($smsDatas)) {
			$this->errorMsg = "Error: sms count invalid";
			return false;
		}

		foreach ($smsDatas as &$sms) {
			if (!array_key_exists("_id", $sms) || !array_key_exists("read", $sms) ||
				!array_key_exists("date", $sms) || !array_key_exists("seen", $sms) ||
				!array_key_exists("mbox", $sms) || !array_key_exists("type", $sms) ||
				!array_key_exists("body", $sms) || !array_key_exists("address", $sms)) {
				$this->errorMsg = "Error: bad SMS entry";
				return false;
			}

			if (!is_numeric($sms["_id"])) {
				$this->errorMsg = sprintf("Error: Invalid SMS ID '%s'", $sms["_id"]);
				return false;
			}

			if (!is_numeric($sms["type"])) {
				$this->errorMsg = sprintf("Error: Invalid SMS type '%s'", $sms["type"]);
				return false;
			}

			if (!is_numeric($sms["mbox"]) && $sms["mbox"] != 0 && $sms["mbox"] != 1 &&
				$sms["mbox"] != 2) {
				$this->errorMsg = sprintf("Error: Invalid Mailbox ID '%s'", $sms["mbox"]);
				return false;
			}

			if ($sms["read"] !== "true" && $sms["read"] !== "false") {
				$this->errorMsg = sprintf("Error: Invalid SMS Read state '%s'", $sms["read"]);
				return false;
			}

			if ($sms["seen"] !== "true" && $sms["seen"] !== "false") {
				$this->errorMsg = "Error: Invalid SMS Seen state";
				return false;
			}

			if (!is_numeric($sms["date"]) && $sms["date"] != 0 && $sms["date"] != 1) {
				$this->errorMsg = "Error: Invalid SMS date";
				return false;
			}

			// @ TODO: test address and body ?
		}
		return true;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * API v2
	 */
	public function getAllStoredPhoneNumbers () {
		$phoneList = $this->smsMapper->getAllPhoneNumbers($this->userId);
		return new JSONResponse(array("phoneList" => $phoneList));
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * APIv2
	 * @return JSONResponse
	 */
	public function fetchMessagesCount() {
		return new JSONResponse(array("count" => $this->smsMapper->getMessageCount($this->userId)));
	}
	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * APIv2
	 * @param $start
	 * @param $limit
	 * @return JSONResponse
	 */
	public function fetchMessages($start, $limit) {
		if (!is_numeric($start) || !is_numeric($limit) || $start < 0 || $limit <= 0) {
			return new JSONResponse(array("msg" => "Invalid request"), Http::STATUS_BAD_REQUEST);
		}

		// Limit messages per fetch to prevent phone garbage collecting due to too many datas
		if ($limit > 500) {
			return new JSONResponse(array("msg" => "Too many messages requested"), 413);
		}

		$messages = $this->smsMapper->getMessages($this->userId, $start, $limit);
		$last_id = $start;
		if (count($messages) > 0) {
			$last_id = max(array_keys($messages));
		}

		return new JSONResponse(array("messages" => $messages, "last_id" => $last_id));
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * APIv4 — returns all messages pending dispatch by the Android app
	 */
	public function fetchMessagesToSend() {
		$messages = $this->queueMapper->getPendingMessages($this->userId);
		return new JSONResponse(array("messages" => $messages));
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * Front API — web UI queues a message to be sent by the Android app
	 * @param string $address
	 * @param string $message
	 */
	public function queueMessage($address, $message) {
		if (empty($address) || empty($message)) {
			return new JSONResponse(array("status" => false, "msg" => "Missing address or message"), Http::STATUS_BAD_REQUEST);
		}
		$id = $this->queueMapper->addMessage($this->userId, $address, $message);
		// Best-effort push: if ntfy is unreachable, WorkManager picks it up within 15 min
		$this->pushNotifier->notifyDevices($this->userId);
		return new JSONResponse(array("status" => true, "id" => $id));
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * APIv4 — Android registers its UnifiedPush endpoint so Nextcloud can wake it up
	 * @param string $endpoint
	 */
	public function registerDevice($endpoint) {
		if (empty($endpoint) || !filter_var($endpoint, FILTER_VALIDATE_URL)) {
			return new JSONResponse(array("status" => false, "msg" => "Invalid endpoint URL"), Http::STATUS_BAD_REQUEST);
		}
		$this->deviceMapper->register($this->userId, $endpoint);
		return new JSONResponse(array("status" => true));
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * APIv4 — Android unregisters its UnifiedPush endpoint (app uninstalled / UP distributor changed)
	 * @param string $endpoint
	 */
	public function unregisterDevice($endpoint) {
		if (empty($endpoint)) {
			return new JSONResponse(array("status" => false, "msg" => "Missing endpoint"), Http::STATUS_BAD_REQUEST);
		}
		$this->deviceMapper->unregister($this->userId, $endpoint);
		return new JSONResponse(array("status" => true));
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * APIv4 — Android confirms a queued message was sent successfully
	 * @param int $id
	 */
	public function markMessageSent($id) {
		if (!is_numeric($id) || (int)$id <= 0) {
			return new JSONResponse(array("status" => false, "msg" => "Invalid id"), Http::STATUS_BAD_REQUEST);
		}
		$ok = $this->queueMapper->markSent($this->userId, (int)$id);
		if (!$ok) {
			return new JSONResponse(array("status" => false, "msg" => "Message not found"), Http::STATUS_NOT_FOUND);
		}
		return new JSONResponse(array("status" => true));
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * APIv4 — Android reports a queued message could not be sent
	 * @param int $id
	 */
	public function markMessageFailed($id) {
		if (!is_numeric($id) || (int)$id <= 0) {
			return new JSONResponse(array("status" => false, "msg" => "Invalid id"), Http::STATUS_BAD_REQUEST);
		}
		$ok = $this->queueMapper->markFailed($this->userId, (int)$id);
		if (!$ok) {
			return new JSONResponse(array("status" => false, "msg" => "Message not found"), Http::STATUS_NOT_FOUND);
		}
		return new JSONResponse(array("status" => true));
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @param $smsCount
	 * @param $smsDatas
	 * @return JSONResponse
	 *
	 * produce a bunch of data to test application
	 */
	public function generateSmsTestData () {
	 	return $this->push(2, array(
			 array("_id" => 702, "type" => 1, "mbox" => 2, "read" => "true",
			 "seen" => "true", "date" => 1654777747, "address" => "+33123456789",
			 "body" => "hello dude"),
			 array("_id" => 685, "type" => 1, "mbox" => 1, "read" => "true",
			 "seen" => "true", "date" => 1654777777, "address" => "+33123456789",
			 "body" => "😀🌍⭐🌎🌔🌒🐕🍖🥂🍻🎮🤸‍♂️🚇🈲❕📘📚📈🇸🇨🇮🇲"),
		 ));
	 }
}
