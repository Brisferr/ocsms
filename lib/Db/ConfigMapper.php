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
use OCP\Security\ICrypto;

class ConfigMapper extends QBMapper {
	private $user;
	private $crypto;

	public function __construct(IDBConnection $db, $user, ICrypto $crypto) {
		parent::__construct($db, 'ocsms_config');
		$this->user = $user;
		$this->crypto = $crypto;
	}

	public function set(string $key, $value): void {
		$value = $this->crypto->encrypt($value);
		$qb = $this->db->getQueryBuilder();

		if ($this->hasKey($key)) {
			$qb->update('ocsms_config')
				->set('value', $qb->createNamedParameter($value))
				->where(
					$qb->expr()->eq('user', $qb->createNamedParameter($this->user)),
					$qb->expr()->eq('key', $qb->createNamedParameter($key))
				);
			$qb->executeStatement();
		} else {
			$qb->insert('ocsms_config')
				->values([
					'user' => $qb->createNamedParameter($this->user),
					'key' => $qb->createNamedParameter($key),
					'value' => $qb->createNamedParameter($value),
				]);
			$qb->executeStatement();
		}
	}

	public function hasKey(string $key): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->select('key')
			->from('ocsms_config')
			->where(
				$qb->expr()->eq('key', $qb->createNamedParameter($key)),
				$qb->expr()->eq('user', $qb->createNamedParameter($this->user))
			);
		$result = $qb->executeQuery();
		$exists = $result->fetch() !== false;
		$result->closeCursor();
		return $exists;
	}

	public function getKey($key) {
		try {
			$qb = $this->db->getQueryBuilder();
			$qb->select('value')
				->from('ocsms_config')
				->where(
					$qb->expr()->eq('key', $qb->createNamedParameter($key)),
					$qb->expr()->eq('user', $qb->createNamedParameter($this->user))
				);
			$result = $qb->executeQuery();
			if ($row = $result->fetch()) {
				$result->closeCursor();
				return $this->crypto->decrypt($row["value"]);
			}
			return false;
		} catch (\Exception $e) {
			return false;
		}
	}

	public function getCountry() {
		return $this->getKey("country");
	}

	public function getMessageLimit() {
		$limit = $this->getKey("message_limit");
		return $limit === false ? 500 : $limit;
	}

	public function getNotificationState() {
		$st = $this->getKey("notification_state");
		return $st === false ? 1 : $st;
	}

	public function getContactOrder() {
		$order = $this->getKey("contact_order");
		return $order === false ? "lastmsg" : $order;
	}

	public function getContactOrderReverse() {
		$rev = $this->getKey("contact_order_reverse");
		return $rev === false ? "true" : $rev;
	}
}
