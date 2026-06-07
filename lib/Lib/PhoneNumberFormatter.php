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

use OCA\Ocsms\Lib\CountryCodes;

include(dirname(__FILE__) . '/../vendor/autoload.php');

class PhoneNumberFormatter {
	public static function format($country, $pn) {
		$pn = trim($pn);

		if ($country === false || !array_key_exists($country, CountryCodes::$codes) || strlen($pn) < 6) {
			return $pn;
		}

		if (preg_match('#^[\d\+\(\[\{].*#', $pn)) {
			$phoneUtil = \libphonenumber\PhoneNumberUtil::getInstance();
			try {
				$NumberProto = $phoneUtil->parse($pn, CountryCodes::$countries[$country]);
				$ypn = $phoneUtil->format($NumberProto, \libphonenumber\PhoneNumberFormat::INTERNATIONAL);
				$ypn = preg_replace('#[^\d]#', '', $ypn);
			} catch (\libphonenumber\NumberParseException $e) {
				$ypn = $pn;
			}
		} else {
			$ypn = $pn;
		}

		return $ypn;
	}
}
