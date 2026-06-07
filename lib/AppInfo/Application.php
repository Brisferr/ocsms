<?php
/**
 * Nextcloud - Phone Sync
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Loic Blot <loic.blot@unix-experience.fr>
 */

namespace OCA\Ocsms\AppInfo;

use OCA\Ocsms\Controller\ApiController;
use OCA\Ocsms\Controller\SmsController;
use OCA\Ocsms\Db\ConfigMapper;
use OCA\Ocsms\Db\ConversationStateMapper;
use OCA\Ocsms\Db\DeviceMapper;
use OCA\Ocsms\Db\SendMessageQueueMapper;
use OCA\Ocsms\Db\SmsMapper;
use OCA\Ocsms\Service\PushNotifier;
use OCP\AppFramework\App;
use OCP\Http\Client\IClientService;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Contacts\IManager as IContactsManager;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\INavigationManager;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCP\Security\ICrypto;

class Application extends App implements IBootstrap {
	public const APP_ID = 'ocsms';

	public function __construct() {
		parent::__construct(self::APP_ID);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerService('UserId', function ($c) {
			$user = $c->get(IUserSession::class)->getUser();
			return $user ? $user->getUID() : null;
		});

		$context->registerService(ConfigMapper::class, function ($c) {
			return new ConfigMapper(
				$c->get(IDBConnection::class),
				$c->get('UserId'),
				$c->get(ICrypto::class)
			);
		});

		$context->registerService(SmsController::class, function ($c) {
			return new SmsController(
				self::APP_ID,
				$c->get(IRequest::class),
				$c->get('UserId'),
				$c->get(SmsMapper::class),
				$c->get(ConversationStateMapper::class),
				$c->get(ConfigMapper::class),
				$c->get(IContactsManager::class),
				$c->get(IURLGenerator::class),
				$c->get(SendMessageQueueMapper::class),
				$c->get(PushNotifier::class)
			);
		});

		$context->registerService(PushNotifier::class, function ($c) {
			return new PushNotifier(
				$c->get(DeviceMapper::class),
				$c->get(IClientService::class),
				$c->get(\Psr\Log\LoggerInterface::class)
			);
		});

		$context->registerService(ApiController::class, function ($c) {
			return new ApiController(
				self::APP_ID,
				$c->get(IRequest::class),
				$c->get('UserId'),
				$c->get(SmsMapper::class),
				$c->get(SendMessageQueueMapper::class),
				$c->get(DeviceMapper::class),
				$c->get(PushNotifier::class)
			);
		});
	}

	public function boot(IBootContext $context): void {
		$context->injectFn([$this, 'registerNavigation']);
	}

	public function registerNavigation(INavigationManager $navigationManager, IURLGenerator $urlGenerator, IL10N $l10n): void {
		$navigationManager->add(function () use ($urlGenerator, $l10n) {
			return [
				'id' => self::APP_ID,
				'order' => 10,
				'href' => $urlGenerator->linkToRoute('ocsms.sms.index'),
				'icon' => $urlGenerator->imagePath(self::APP_ID, 'app.svg'),
				'name' => $l10n->t('Phone Sync'),
			];
		});
	}
}
