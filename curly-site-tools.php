<?php
/**
 * Plugin Name:       Curly Site Tools
 * Plugin URI:        https://github.com/Curly-Sprout-Creative/curly-site-tools
 * Description:       A set of small site-level hardening and behavior toggles for Curly Sprout sites. Admin can enable or disable each change under Tools > Curly Site Tools.
 * Version:           1.1.1
 * Author:            Curly Sprout Creative
 * License:           GPL-2.0-or-later
 * Text Domain:       curly-site-tools
 *
 * @package CurlySiteTools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CURLY_SITE_TOOLS_VERSION', '1.1.1' );
define( 'CURLY_SITE_TOOLS_FILE', __FILE__ );
define( 'CURLY_SITE_TOOLS_DIR', plugin_dir_path( __FILE__ ) );
define( 'CURLY_SITE_TOOLS_URL', plugin_dir_url( __FILE__ ) );

require_once CURLY_SITE_TOOLS_DIR . 'includes/class-curly-site-tools.php';

/**
 * Bootstrap the plugin.
 */
function curly_site_tools() {
	return Curly_Site_Tools::instance();
}

curly_site_tools();

/**
 * Plugin update checker (GitHub Releases, public repo — no auth needed).
 */
require_once CURLY_SITE_TOOLS_DIR . 'vendor/plugin-update-checker/plugin-update-checker.php';
if ( class_exists( '\YahnisElsts\PluginUpdateChecker\v5\PucFactory' ) ) {
	$curly_site_tools_updater = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
		'https://github.com/Curly-Sprout-Creative/curly-site-tools/',
		__FILE__,
		'curly-site-tools'
	);
}
