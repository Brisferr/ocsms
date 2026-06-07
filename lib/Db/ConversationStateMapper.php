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

class ConversationStateMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'ocsms_smsdatas');
	}

	public function getLast($userId) {
		$qb = $this->db->getQueryBuilder();
		$qb->selectAlias($qb->createFunction('MAX(int_date)'), 'mx')
			->from('ocsms_conversation_read_states')
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
		$result = $qb->executeQuery();

		if ($row = $result->fetch()) {
			return $row["mx"];
		}
		return 0;
	}

	public function getLastForPhoneNumber($userId, $phoneNumber) {
		$qb = $this->db->getQueryBuilder();
		$qb->selectAlias($qb->createFunction('MAX(int_date)'), 'mx')
			->from('ocsms_conversation_read_states')
			->where($qb->expr()->andX(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId)),
				$qb->expr()->eq('phone_number', $qb->createNamedParameter($phoneNumber))
			));
		$result = $qb->executeQuery();

		if ($row = $result->fetch()) {
			return $row["mx"];
		}
		return 0;
	}

	public function setLast($userId, $phoneNumber, $lastDate) {
		$this->db->beginTransaction();

		$qb = $this->db->getQueryBuilder();
		$qb->delete('ocsms_conversation_read_states')
			->where($qb->expr()->andX(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId)),
				$qb->expr()->eq('phone_number', $qb->createNamedParameter($phoneNumber))
			));
		$qb->executeStatement();

		$qb = $this->db->getQueryBuilder();
		$qb->insert('ocsms_conversation_read_states')
			->values([
				'user_id' => $qb->createNamedParameter($userId),
				'phone_number' => $qb->createNamedParameter($phoneNumber),
				'int_date' => $qb->createNamedParameter($lastDate)
			]);
		$qb->executeStatement();

		$this->db->commit();
	}

	public function migrate() {
		$qb = $this->db->getQueryBuilder();
		$qb->select('user_id', 'datakey', 'datavalue')
			->from('ocsms_user_datas')
			->where($qb->expr()->like('datakey', $qb->createNamedParameter('lastReadDate-%')));
		$result = $qb->executeQuery();

		while ($row = $result->fetch()) {
			$pn = preg_replace("#lastReadDate[-]#", "", $row["datakey"]);
			$this->setLast($row["user_id"], $pn, $row["datavalue"]);
		}
		$result->closeCursor();

		$qb = $this->db->getQueryBuilder();
		$qb->delete('ocsms_user_datas')
			->where($qb->expr()->like('datakey', $qb->createNamedParameter('lastReadDate-%')));
		$qb->executeStatement();
	}
}
