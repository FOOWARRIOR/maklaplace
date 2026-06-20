<?php
/**
 * Wallet helper utilities.
 *
 * @package MaklaPlace\Helpers
 */

namespace MaklaPlace\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Wallet-specific helpers.
 */
final class WalletHelper {

	/**
	 * Commission collection threshold in DA.
	 *
	 * @return float
	 */
	public static function threshold() : float {
		return 2000.0;
	}
}
