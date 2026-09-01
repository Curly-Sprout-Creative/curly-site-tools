<?php
/**
 * Post utilities: transient-cached count of posts in the past 3 months.
 *
 * Snippet 20, wrapped in a WP transient so the raw posts_per_page=-1 query is
 * not run on every page load as the post database grows.
 *
 * @package CurlySiteTools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

curly_site_tools_register_toggle(
	'count_posts_in_past',
	__( 'Count posts in the past 3 months', 'curly-site-tools' ),
	__( 'Provide get_3month_post_count() (posts in the last 3 months or sticky), cached in a transient for a few hours.', 'curly-site-tools' ),
	true
);

// Cache the count for a few hours; refresh sooner when posts change.
if ( ! defined( 'CURLY_SITE_TOOLS_COUNT_TRANSIENT' ) ) {
	define( 'CURLY_SITE_TOOLS_COUNT_TRANSIENT', 'curly_site_tools_3month_post_count' );
}

if ( ! defined( 'CURLY_SITE_TOOLS_COUNT_EXPIRATION' ) ) {
	define( 'CURLY_SITE_TOOLS_COUNT_EXPIRATION', 6 * HOUR_IN_SECONDS );
}

add_action(
	'plugins_loaded',
	function () {
		if ( ! curly_site_tools_is_enabled( 'count_posts_in_past' ) ) {
			return;
		}

		// Bust the transient whenever post/sticky state changes.
		add_action( 'save_post', 'curly_site_tools_bust_post_count' );
		add_action( 'delete_post', 'curly_site_tools_bust_post_count' );
		add_action( 'wp_trash_post', 'curly_site_tools_bust_post_count' );
		add_action( 'untrash_post', 'curly_site_tools_bust_post_count' );
		add_action( 'updated_option', 'curly_site_tools_bust_post_count_on_sticky_change', 10, 3 );
	},
	20
);

/**
 * Delete the cached post count.
 */
function curly_site_tools_bust_post_count() {
	delete_transient( CURLY_SITE_TOOLS_COUNT_TRANSIENT );
}

/**
 * Bust the cache when the sticky_posts option changes.
 *
 * @param string $option    Option name.
 * @param mixed  $old_value Old value.
 * @param mixed  $value     New value.
 */
function curly_site_tools_bust_post_count_on_sticky_change( $option, $old_value, $value ) {
	if ( 'sticky_posts' === $option ) {
		curly_site_tools_bust_post_count();
	}
}

/**
 * Count posts published in the last 3 months OR sticky posts.
 *
 * Uses only post IDs (no full post objects) and caches the result in a
 * transient so the query does not run on every page load.
 *
 * @return int
 */
function get_3month_post_count() {
	$cached = get_transient( CURLY_SITE_TOOLS_COUNT_TRANSIENT );
	if ( false !== $cached ) {
		return (int) $cached;
	}

	$recent_args = array(
		'post_type'           => 'post',
		'posts_per_page'      => -1,
		'fields'              => 'ids',
		'date_query'          => array(
			array(
				'after'     => '3 months ago',
				'inclusive' => true,
			),
		),
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
	);

	$recent_query = new WP_Query( $recent_args );
	$recent_ids   = $recent_query->posts;

	$sticky_ids = get_option( 'sticky_posts' );
	if ( ! is_array( $sticky_ids ) ) {
		$sticky_ids = array();
	}

	$combined_ids         = array_merge( $recent_ids, $sticky_ids );
	$unique_combined_ids  = array_unique( $combined_ids );
	$count                = count( $unique_combined_ids );

	set_transient( CURLY_SITE_TOOLS_COUNT_TRANSIENT, $count, CURLY_SITE_TOOLS_COUNT_EXPIRATION );

	return $count;
}
