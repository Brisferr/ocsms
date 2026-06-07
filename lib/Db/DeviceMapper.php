<?php

namespace OCA\Ocsms\Db;

use OCP\IDBConnection;
use OCP\AppFramework\Db\QBMapper;

class DeviceMapper extends QBMapper {

	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'ocsms_devices', Device::class);
	}

	/** @return string[] list of push endpoint URLs for the given user */
	public function getEndpointsForUser(string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('push_endpoint')
			->from('ocsms_devices')
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));

		$result = $qb->executeQuery();
		$endpoints = [];
		while ($row = $result->fetch()) {
			$endpoints[] = $row['push_endpoint'];
		}
		$result->closeCursor();
		return $endpoints;
	}

	public function register(string $userId, string $endpoint): void {
		$now = date('Y-m-d H:i:s');
		// INSERT OR IGNORE + UPDATE pattern: upsert via delete+insert to stay DB-agnostic
		$qb = $this->db->getQueryBuilder();
		$qb->delete('ocsms_devices')
			->where($qb->expr()->andX(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId)),
				$qb->expr()->eq('push_endpoint', $qb->createNamedParameter($endpoint))
			));
		$qb->executeStatement();

		$qb = $this->db->getQueryBuilder();
		$qb->insert('ocsms_devices')
			->values([
				'user_id'       => $qb->createNamedParameter($userId),
				'push_endpoint' => $qb->createNamedParameter($endpoint),
				'registered_at' => $qb->createNamedParameter($now),
			]);
		$qb->executeStatement();
	}

	public function unregister(string $userId, string $endpoint): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete('ocsms_devices')
			->where($qb->expr()->andX(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId)),
				$qb->expr()->eq('push_endpoint', $qb->createNamedParameter($endpoint))
			));
		$qb->executeStatement();
	}
}
