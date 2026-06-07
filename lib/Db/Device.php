<?php

namespace OCA\Ocsms\Db;

use OCP\AppFramework\Db\Entity;

class Device extends Entity {
	protected $userId;
	protected $pushEndpoint;
	protected $registeredAt;
}
