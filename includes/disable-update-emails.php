<?php
/**
 * Disable automatic update notification emails.
 *
 * Snippet 6.
 *
 * @package CurlySiteTools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

curly_site_tools_register_toggle(
	'disable_update_emails',
	__( 'Disable automatic update emails', 'curly-site-tools' ),
	__( 'Stop the emails WordPress sends when it auto-updates core, plugins, or themes.', 'curly-site-tools' ),
	true
);

add_action(
	'plugins_loaded',
	function () {
		if ( ! curly_site_tools_is_enabled( 'disable_update_emails' ) ) {
			return;
		}
		add_filter( 'auto_core_update_send_email', '__return_false' );
		add_filter( 'auto_plugin_update_send_email', '__return_false' );
		add_filter( 'auto_theme_update_send_email', '__return_false' );
	},
	20
);
