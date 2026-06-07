<?php

namespace OCA\Ocsms\Db;

use OCP\IDBConnection;
use OCP\AppFramework\Db\QBMapper;

class SendMessageQueueMapper extends QBMapper {

	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'ocsms_sendmessage_queue', SendMessageQueue::class);
	}

	/** Returns pending + failed messages for a specific address (for outbox display) */
	public function getPendingAndFailedByAddress(string $userId, string $address): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'sms_address', 'sms_msg', 'status', 'created_at')
			->from('ocsms_sendmessage_queue')
			->where($qb->expr()->andX(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId)),
				$qb->expr()->eq('sms_address', $qb->createNamedParameter($address)),
				$qb->expr()->neq('status', $qb->createNamedParameter(SendMessageQueue::STATUS_SENT))
			))
			->orderBy('created_at', 'ASC');

		$result = $qb->executeQuery();
		$messages = [];
		while ($row = $result->fetch()) {
			$messages[] = [
				'id'      => (int)$row['id'],
				'address' => $row['sms_address'],
				'msg'     => $row['sms_msg'],
				'status'  => (int)$row['status'],
				'created_at' => $row['created_at'],
			];
		}
		$result->closeCursor();
		return $messages;
	}

	/** Deletes all sent messages from the queue for a user.
	 *  Called by Android after a successful sync so sent items don't
	 *  duplicate what is now visible in oc_ocsms_smsdatas. */
	public function purgeSent(string $userId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete('ocsms_sendmessage_queue')
			->where($qb->expr()->andX(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId)),
				$qb->expr()->eq('status', $qb->createNamedParameter(SendMessageQueue::STATUS_SENT))
			));
		$qb->executeStatement();
	}

	public function resetToPending(string $userId, int $id): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->update('ocsms_sendmessage_queue')
			->set('status', $qb->createNamedParameter(SendMessageQueue::STATUS_PENDING))
			->where($qb->expr()->andX(
				$qb->expr()->eq('id', $qb->createNamedParameter($id)),
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId)),
				$qb->expr()->eq('status', $qb->createNamedParameter(SendMessageQueue::STATUS_FAILED))
			));
		return $qb->executeStatement() > 0;
	}

	public function getPendingMessages(string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'sms_address', 'sms_msg', 'created_at')
			->from('ocsms_sendmessage_queue')
			->where($qb->expr()->andX(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId)),
				$qb->expr()->eq('status', $qb->createNamedParameter(SendMessageQueue::STATUS_PENDING))
			))
			->orderBy('created_at', 'ASC');

		$result = $qb->executeQuery();
		$messages = [];
		while ($row = $result->fetch()) {
			$messages[] = [
				'id'      => (int)$row['id'],
				'address' => $row['sms_address'],
				'msg'     => $row['sms_msg'],
			];
		}
		$result->closeCursor();
		return $messages;
	}

	public function addMessage(string $userId, string $address, string $msg): int {
		$now = date('Y-m-d H:i:s');
		$qb = $this->db->getQueryBuilder();
		$qb->insert('ocsms_sendmessage_queue')
			->values([
				'user_id'     => $qb->createNamedParameter($userId),
				'sms_address' => $qb->createNamedParameter($address),
				'sms_msg'     => $qb->createNamedParameter($msg),
				'status'      => $qb->createNamedParameter(SendMessageQueue::STATUS_PENDING),
				'created_at'  => $qb->createNamedParameter($now),
			]);
		$qb->executeStatement();
		return (int)$this->db->lastInsertId('ocsms_sendmessage_queue');
	}

	public function markSent(string $userId, int $id): bool {
		return $this->updateStatus($userId, $id, SendMessageQueue::STATUS_SENT);
	}

	public function markFailed(string $userId, int $id): bool {
		return $this->updateStatus($userId, $id, SendMessageQueue::STATUS_FAILED);
	}

	private function updateStatus(string $userId, int $id, int $status): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->update('ocsms_sendmessage_queue')
			->set('status', $qb->createNamedParameter($status))
			->where($qb->expr()->andX(
				$qb->expr()->eq('id', $qb->createNamedParameter($id)),
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId))
			));
		return $qb->executeStatement() > 0;
	}
}
