<?php

namespace OCA\Ocsms\Service;

use OCA\Ocsms\Db\DeviceMapper;
use OCP\Http\Client\IClientService;
use Psr\Log\LoggerInterface;

class PushNotifier {

	private $deviceMapper;
	private $httpClientService;
	private $logger;

	public function __construct(DeviceMapper $deviceMapper, IClientService $httpClientService, LoggerInterface $logger) {
		$this->deviceMapper      = $deviceMapper;
		$this->httpClientService = $httpClientService;
		$this->logger            = $logger;
	}

	/**
	 * Wake up all registered devices for a user so they poll the outbox.
	 * The payload is intentionally minimal — the actual SMS content is fetched
	 * securely via the authenticated API, not embedded in the push notification.
	 */
	public function notifyDevices(string $userId): void {
		$endpoints = $this->deviceMapper->getEndpointsForUser($userId);
		if (empty($endpoints)) {
			return;
		}

		$client  = $this->httpClientService->newClient();
		$payload = json_encode(['action' => 'poll_outbox']);

		foreach ($endpoints as $endpoint) {
			try {
				$client->post($endpoint, [
					'body'    => $payload,
					'headers' => ['Content-Type' => 'application/json'],
					'timeout' => 5,
				]);
			} catch (\Exception $e) {
				// Push failed (device offline, ntfy unreachable…) — WorkManager will
				// pick up the message on its next 15-min cycle, so no data is lost.
				$this->logger->warning(
					'ocsms: UnifiedPush notification failed for endpoint {endpoint}: {error}',
					['endpoint' => $endpoint, 'error' => $e->getMessage()]
				);
			}
		}
	}
}
