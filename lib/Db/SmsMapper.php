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

namespace OCA\Ocsms\Db;

use OCP\IDBConnection;
use OCP\AppFramework\Db\QBMapper;
use OCA\Ocsms\Lib\PhoneNumberFormatter;

class SmsMapper extends QBMapper {
	private static $mailboxNames = [0 => "inbox", 1 => "sent", 2 => "drafts"];
	private static $messageTypes = [
		0 => "all", 1 => "inbox",
		2 => "sent", 3 => "drafts",
		4 => "outbox", 5 => "failed",
		6 => "queued"
	];
	private $convStateMapper;

	public function __construct(IDBConnection $db, ConversationStateMapper $cmapper) {
		parent::__construct($db, 'ocsms_smsdatas');
		$this->convStateMapper = $cmapper;
	}

	public function getAllIds($userId) {
		$qb = $this->db->getQueryBuilder();
		$qb->select('sms_id', 'sms_mailbox')
			->from('ocsms_smsdatas')
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
		$result = $qb->executeQuery();

		$smsList = [];
		while ($row = $result->fetch()) {
			if (!in_array((int)$row["sms_mailbox"], SmsMapper::$mailboxNames)) {
				continue;
			}
			$mbox = SmsMapper::$mailboxNames[$row["sms_mailbox"]];
			if (!isset($smsList[$mbox])) {
				$smsList[$mbox] = [];
			}
			if (!in_array($row["sms_id"], $smsList[$mbox])) {
				array_push($smsList[$mbox], $row["sms_id"]);
			}
		}
		$result->closeCursor();
		return $smsList;
	}

	public function getLastTimestamp($userId) {
		$qb = $this->db->getQueryBuilder();
		$qb->selectAlias($qb->createFunction('MAX(sms_date)'), 'mx')
			->from('ocsms_smsdatas')
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
		$result = $qb->executeQuery();

		if ($row = $result->fetch()) {
			return $row["mx"];
		}
		return 0;
	}

	public function getAllPhoneNumbers($userId) {
		$qb = $this->db->getQueryBuilder();
		$qb->select('sms_address')
			->from('ocsms_smsdatas')
			->where($qb->expr()->andX(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId)),
				$qb->expr()->in('sms_mailbox', array_map(function ($mbid) use ($qb) {
					return $qb->createNamedParameter($mbid);
				}, [0, 1, 3]))
			));
		$result = $qb->executeQuery();

		$phoneList = [];
		while ($row = $result->fetch()) {
			$pn = $row["sms_address"];
			if (!in_array($pn, $phoneList)) {
				array_push($phoneList, $pn);
			}
		}
		$result->closeCursor();
		return $phoneList;
	}

	public function getAllPhoneNumbersForFPN($userId, $phoneNumber, $country) {
		$qb = $this->db->getQueryBuilder();
		$qb->select('sms_address')
			->from('ocsms_smsdatas')
			->where($qb->expr()->andX(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId)),
				$qb->expr()->in('sms_mailbox', array_map(function ($mbid) use ($qb) {
					return $qb->createNamedParameter($mbid);
				}, [0, 1, 3]))
			));
		$result = $qb->executeQuery();

		$phoneList = [];
		while ($row = $result->fetch()) {
			$pn = $row["sms_address"];
			$fmtPN = PhoneNumberFormatter::format($country, $pn);
			if (!isset($phoneList[$fmtPN])) {
				$phoneList[$fmtPN] = [];
			}
			if (!isset($phoneList[$fmtPN][$pn])) {
				$phoneList[$fmtPN][$pn] = 0;
			}
			$phoneList[$fmtPN][$pn] += 1;
		}
		$result->closeCursor();

		$fpn = $phoneNumber;
		if (isset($phoneList[$fpn])) {
			return $phoneList[$fpn];
		}

		$fpn = PhoneNumberFormatter::format($country, $fpn);
		if (isset($phoneList[$fpn])) {
			return $phoneList[$fpn];
		}

		return [];
	}

	public function getAllMessagesForPhoneNumber($userId, $phoneNumber, $country, $minDate = 0) {
		$phlst = $this->getAllPhoneNumbersForFPN($userId, $phoneNumber, $country);
		$messageList = [];

		foreach ($phlst as $pn => $val) {
			$qb = $this->db->getQueryBuilder();
			$qb->select('sms_date', 'sms_msg', 'sms_type')
				->from('ocsms_smsdatas')
				->where($qb->expr()->andX(
					$qb->expr()->eq('user_id', $qb->createNamedParameter($userId)),
					$qb->expr()->eq('sms_address', $qb->createNamedParameter($pn)),
					$qb->expr()->in('sms_mailbox', array_map(function ($mbid) use ($qb) {
						return $qb->createNamedParameter($mbid);
					}, [0, 1, 3])),
					$qb->expr()->gt('sms_date', $qb->createNamedParameter($minDate))
				));
			$result = $qb->executeQuery();

			while ($row = $result->fetch()) {
				$messageList[$row["sms_date"]] = [
					"msg" => $row["sms_msg"],
					"type" => $row["sms_type"]
				];
			}
			$result->closeCursor();
		}
		return $messageList;
	}

	public function getMessageCount($userId) {
		$qb = $this->db->getQueryBuilder();
		$qb->selectAlias($qb->createFunction('COUNT(*)'), 'count')
			->from('ocsms_smsdatas')
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
		$result = $qb->executeQuery();

		if ($row = $result->fetch()) {
			return $row["count"];
		}
		return 0;
	}

	public function getMessages($userId, $start, $limit) {
		$messageList = [];

		$qb = $this->db->getQueryBuilder();
		$qb->select('sms_address', 'sms_date', 'sms_msg', 'sms_type', 'sms_mailbox')
			->from('ocsms_smsdatas')
			->where($qb->expr()->andX(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId)),
				$qb->expr()->gt('sms_date', $qb->createNamedParameter($start))
			))
			->orderBy('sms_date')
			->setMaxResults((int)$limit);
		$result = $qb->executeQuery();

		while ($row = $result->fetch()) {
			$messageList[$row["sms_date"]] = [
				"address" => $row["sms_address"],
				"mailbox" => intval($row["sms_mailbox"]),
				"msg" => $row["sms_msg"],
				"type" => intval($row["sms_type"])
			];
		}
		$result->closeCursor();
		return $messageList;
	}

	public function countMessagesForPhoneNumber($userId, $phoneNumber, $country) {
		$cnt = 0;
		$phlst = $this->getAllPhoneNumbersForFPN($userId, $phoneNumber, $country);

		foreach ($phlst as $pn => $val) {
			$qb = $this->db->getQueryBuilder();
			$qb->selectAlias($qb->createFunction('COUNT(*)'), 'ct')
				->from('ocsms_smsdatas')
				->where($qb->expr()->andX(
					$qb->expr()->eq('user_id', $qb->createNamedParameter($userId)),
					$qb->expr()->eq('sms_address', $qb->createNamedParameter($pn)),
					$qb->expr()->in('sms_mailbox', array_map(function ($mbid) use ($qb) {
						return $qb->createNamedParameter($mbid);
					}, [0, 1, 3]))
				));
			$result = $qb->executeQuery();

			if ($row = $result->fetch()) {
				$cnt += $row["ct"];
			}
			$result->closeCursor();
		}
		return $cnt;
	}

	public function removeAllMessagesForUser($userId) {
		$this->db->beginTransaction();
		$qb = $this->db->getQueryBuilder();
		$qb->delete('ocsms_smsdatas')
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
		$qb->executeStatement();
		$this->db->commit();
	}

	public function removeMessagesForPhoneNumber($userId, $phoneNumber) {
		$this->db->beginTransaction();
		$qb = $this->db->getQueryBuilder();
		$qb->delete('ocsms_smsdatas')
			->where($qb->expr()->andX(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId)),
				$qb->expr()->eq('sms_address', $qb->createNamedParameter($phoneNumber))
			));
		$qb->executeStatement();
		$this->db->commit();
	}

	public function removeMessage($userId, $phoneNumber, $messageId) {
		$this->db->beginTransaction();
		$qb = $this->db->getQueryBuilder();
		$qb->delete('ocsms_smsdatas')
			->where($qb->expr()->andX(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId)),
				$qb->expr()->eq('sms_address', $qb->createNamedParameter($phoneNumber)),
				$qb->expr()->eq('sms_date', $qb->createNamedParameter($messageId))
			));
		$qb->executeStatement();
		$this->db->commit();
	}

	public function getLastMessageTimestampForAllPhonesNumbers($userId, $order = true) {
		$qb = $this->db->getQueryBuilder();
		$qb->selectAlias($qb->createFunction('MAX(sms_date)'), 'mx')
			->addSelect('sms_address')
			->from('ocsms_smsdatas')
			->where($qb->expr()->andX(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId)),
				$qb->expr()->in('sms_mailbox', array_map(function ($mbid) use ($qb) {
					return $qb->createNamedParameter($mbid);
				}, [0, 1, 3]))
			))
			->groupBy('sms_address');

		if ($order === true) {
			$qb->orderBy('mx', 'DESC');
		}

		$result = $qb->executeQuery();

		$phoneList = [];
		while ($row = $result->fetch()) {
			$phoneNumber = preg_replace("#[ ]#", "", $row["sms_address"]);
			if (!array_key_exists($phoneNumber, $phoneList)) {
				$phoneList[$phoneNumber] = $row["mx"];
			} elseif ($phoneList[$phoneNumber] < $row["mx"]) {
				$phoneList[$phoneNumber] = $row["mx"];
			}
		}
		$result->closeCursor();
		return $phoneList;
	}

	public function getNewMessagesCountForAllPhonesNumbers($userId, $lastDate) {
		$ld = ($lastDate == '') ? 0 : $lastDate;

		$qb = $this->db->getQueryBuilder();
		$qb->selectAlias($qb->createFunction('COUNT(sms_date)'), 'ct')
			->addSelect('sms_address')
			->from('ocsms_smsdatas')
			->where($qb->expr()->andX(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId)),
				$qb->expr()->in('sms_mailbox', array_map(function ($mbid) use ($qb) {
					return $qb->createNamedParameter($mbid);
				}, [0, 1, 3])),
				$qb->expr()->gt('sms_date', $qb->createNamedParameter($ld))
			))
			->groupBy('sms_address');
		$result = $qb->executeQuery();

		$phoneList = [];
		while ($row = $result->fetch()) {
			$phoneNumber = preg_replace("#[ ]#", "", $row["sms_address"]);
			if ($this->convStateMapper->getLastForPhoneNumber($userId, $phoneNumber) < $lastDate) {
				if (!array_key_exists($phoneNumber, $phoneList)) {
					$phoneList[$phoneNumber] = $row["ct"];
				} else {
					$phoneList[$phoneNumber] += $row["ct"];
				}
			}
		}
		$result->closeCursor();
		return $phoneList;
	}

	public function writeToDB($userId, $smsList, $purgeAllSmsBeforeInsert = false) {
		$this->db->beginTransaction();
		$qb = $this->db->getQueryBuilder();

		if ($purgeAllSmsBeforeInsert === true) {
			$qb->delete('ocsms_smsdatas')
				->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
			$qb->executeStatement();
		}

		foreach ($smsList as $sms) {
			$smsFlags = sprintf("%s%s",
				$sms["read"] === "true" ? "1" : "0",
				$sms["seen"] === "true" ? "1" : "0"
			);

			if ($purgeAllSmsBeforeInsert === false) {
				$qb = $this->db->getQueryBuilder();
				$qb->delete('ocsms_smsdatas')
					->where($qb->expr()->andX(
						$qb->expr()->eq('user_id', $qb->createNamedParameter($userId)),
						$qb->expr()->eq('sms_id', $qb->createNamedParameter((int)$sms["_id"]))
					));
				$qb->executeStatement();
			}

			$now = date("Y-m-d H:i:s");
			$qb = $this->db->getQueryBuilder();
			$qb->insert('ocsms_smsdatas')
				->values([
					'user_id' => $qb->createNamedParameter($userId),
					'added' => $qb->createNamedParameter($now),
					'lastmodified' => $qb->createNamedParameter($now),
					'sms_flags' => $qb->createNamedParameter($smsFlags),
					'sms_date' => $qb->createNamedParameter($sms["date"]),
					'sms_id' => $qb->createNamedParameter((int)$sms["_id"]),
					'sms_address' => $qb->createNamedParameter($sms["address"]),
					'sms_msg' => $qb->createNamedParameter($sms["body"]),
					'sms_mailbox' => $qb->createNamedParameter((int)$sms["mbox"]),
					'sms_type' => $qb->createNamedParameter((int)$sms["type"])
				]);
			$qb->executeStatement();
		}

		$this->db->commit();
	}
}
