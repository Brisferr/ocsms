<?php

namespace OCA\Ocsms\Db;

use OCP\AppFramework\Db\Entity;

class SendMessageQueue extends Entity {

	const STATUS_PENDING = 0;
	const STATUS_SENT    = 1;
	const STATUS_FAILED  = 2;

	protected $userId;
	protected $smsAddress;
	protected $smsMsg;
	protected $status;
	protected $createdAt;

	public function __construct() {
		$this->addType('status', 'integer');
	}
}
