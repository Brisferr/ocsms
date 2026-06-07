<?php

declare(strict_types=1);

namespace OCA\Ocsms\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version020201Date20260606000000 extends SimpleMigrationStep {

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options) {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('ocsms_devices')) {
			$table = $schema->createTable('ocsms_devices');
			$table->addColumn('id', 'bigint', [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('user_id', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
			// Full UnifiedPush endpoint URL, e.g. https://ntfy.example.com/mytopic
			$table->addColumn('push_endpoint', 'string', [
				'notnull' => true,
				'length' => 2048,
			]);
			$table->addColumn('registered_at', 'datetime', [
				'notnull' => true,
				'default' => '1970-01-01 00:00:00',
			]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['user_id'], 'devices_user_id');
			// Prevent duplicate registrations for the same endpoint
			$table->addUniqueIndex(['user_id', 'push_endpoint'], 'devices_user_endpoint');
		}

		return $schema;
	}
}
