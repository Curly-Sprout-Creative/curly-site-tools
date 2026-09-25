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

// Remove the configurable upload-limit option.
delete_option( 'curly_site_tools_upload_limit_mb' );

// Remove Turnstile keys and local Google Fonts state.
delete_option( 'curly_site_tools_turnstile_site_key' );
delete_option( 'curly_site_tools_turnstile_secret_key' );
delete_option( 'curly_site_tools_google_fonts_remote_url' );
delete_option( 'curly_site_tools_google_fonts_local_url' );
delete_option( 'curly_site_tools_google_fonts_built_at' );
delete_transient( 'curly_site_tools_fonts_auto_try' );

// Remove the post-count transient(s).
global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_curly_site_tools_3month_post_count%' OR option_name LIKE '_transient_timeout_curly_site_tools_3month_post_count%'" );
