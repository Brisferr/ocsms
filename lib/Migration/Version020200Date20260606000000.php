<?php

declare(strict_types=1);

namespace OCA\Ocsms\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version020200Date20260606000000 extends SimpleMigrationStep {

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options) {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$table = $schema->getTable('ocsms_sendmessage_queue');

		if (!$table->hasColumn('status')) {
			$table->addColumn('status', 'smallint', [
				'notnull' => true,
				'default' => 0,
				'comment' => '0=pending, 1=sent, 2=failed',
			]);
		}

		if (!$table->hasColumn('created_at')) {
			$table->addColumn('created_at', 'datetime', [
				'notnull' => true,
				'default' => '1970-01-01 00:00:00',
			]);
		}

		if (!$table->hasIndex('queue_user_status')) {
			$table->addIndex(['user_id', 'status'], 'queue_user_status');
		}

		return $schema;
	}
}
