<?php
/**
 * Uninstall handler for Curly Site Tools.
 *
 * Runs when the plugin is deleted via wp-admin. Removes the Site Admin role,
 * plugin options, and any leftover transients.
 *
 * @package CurlySiteTools
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Remove the Site Admin role created on activation.
if ( function_exists( 'get_role' ) && function_exists( 'remove_role' ) ) {
	if ( get_role( 'site_admin' ) ) {
		remove_role( 'site_admin' );
	}
}

// Remove the enabled-toggles option.
delete_option( 'curly_site_tools_enabled' );

// Remove the post-count transient(s).
global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_curly_site_tools_3month_post_count%' OR option_name LIKE '_transient_timeout_curly_site_tools_3month_post_count%'" );
