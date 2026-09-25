<?php
/**
 * Removes the connector's stored options when the plugin is deleted.
 *
 * WordPress registers one API key option per connector and this connector reads
 * and writes that option rather than keeping a second copy, so deleting the
 * plugin takes the credential with it instead of leaving a usable key behind.
 *
 * @package Zactonz\AiConnectorForOpenRouter
 */

declare( strict_types=1 );

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$zctz_openrouter_options = array(
	'zctz_openrouter_settings',
	'connectors_ai_' . str_replace( '-', '_', 'openrouter' ) . '_api_key',
);

foreach ( $zctz_openrouter_options as $zctz_openrouter_option ) {
	delete_option( $zctz_openrouter_option );

	if ( is_multisite() ) {
		delete_site_option( $zctz_openrouter_option );
	}
}
