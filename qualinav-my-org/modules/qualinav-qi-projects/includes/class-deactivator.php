<?php
/**
 * Deactivation: clears transients only. Does NOT drop tables — data must survive
 * deactivate/reactivate cycles. Uninstall (drop tables) is a separate concern.
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Qualinav_QI_Deactivator {

	public static function deactivate() {
		delete_transient( 'qualinav_qi_template_cache' );
	}
}
