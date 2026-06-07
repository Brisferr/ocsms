<?php
/**
 * Nextcloud - Phone Sync
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Loic Blot <loic.blot@unix-experience.fr>
 * @contributor: stagprom <https://github.com/stagprom/>
 * @copyright Loic Blot 2014-2017
 */

namespace OCA\Ocsms\Lib;

use OCP\Contacts\IManager as IContactsManager;
use OCA\Ocsms\Db\ConfigMapper;

class ContactCache {
	private $contacts;
	private $contactsInverted;
	private $contactPhotos;
	private $contactUids;

	private $cfgMapper;
	private $contactsManager;

	public function __construct(ConfigMapper $cfgMapper, IContactsManager $contactsManager) {
		$this->contacts = [];
		$this->contactPhotos = [];
		$this->contactUids = [];
		$this->contactsInverted = [];

		$this->cfgMapper = $cfgMapper;
		$this->contactsManager = $contactsManager;
	}

	public function getContacts() {
		if (count($this->contacts) == 0) {
			$this->loadContacts();
		}
		return $this->contacts;
	}

	public function getInvertedContacts() {
		if (count($this->contactsInverted) == 0) {
			$this->loadContacts();
		}
		return $this->contactsInverted;
	}

	public function getContactPhotos() {
		if (count($this->contactPhotos) == 0) {
			$this->loadContacts();
		}
		return $this->contactPhotos;
	}

	public function getContactUids() {
		if (count($this->contactUids) == 0) {
			$this->loadContacts();
		}
		return $this->contactUids;
	}

	private function loadContacts() {
		$this->contacts = [];
		$this->contactsInverted = [];

		$configuredCountry = $this->cfgMapper->getCountry();

		$cm = $this->contactsManager;
		if ($cm === null) {
			return;
		}

		$result = $cm->search('', ['FN']);

		foreach ($result as $r) {
			if (isset($r["TEL"])) {
				$phoneIds = $r["TEL"];
				if (is_array($phoneIds)) {
					foreach ($phoneIds as $phoneId) {
						$phoneNumber = preg_replace("#[ ]#", "", $phoneId);
						$this->pushPhoneNumberToCache($phoneNumber, $r["FN"], $configuredCountry);
					}
				} else {
					$phoneNumber = preg_replace("#[ ]#", "", $phoneIds);
					$this->pushPhoneNumberToCache($phoneNumber, $r["FN"], $configuredCountry);
				}

				if (isset($r["PHOTO"])) {
					$photoURL = preg_replace("#^VALUE=uri:#", "", $r["PHOTO"], 1);
					$this->contactPhotos[$r["FN"]] = $photoURL;
				}

				if (isset($r["UID"])) {
					$this->contactUids[$r["FN"]] = $r["UID"];
				}
			}
		}
	}

	private function pushPhoneNumberToCache($rawPhone, $contactName, $country) {
		$phoneNb = PhoneNumberFormatter::format($country, $rawPhone);
		$this->contacts[$phoneNb] = $contactName;
		if (!isset($this->contactsInverted[$contactName])) {
			$this->contactsInverted[$contactName] = [];
		}
		array_push($this->contactsInverted[$contactName], $phoneNb);
	}
}
