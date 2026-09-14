<?php
/**
 * Media handling: 301-redirect attachment pages and cap Editor uploads.
 *
 * Snippets 7 (attachment pages) and 28 (upload limit).
 *
 * @package CurlySiteTools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

curly_site_tools_register_toggle(
	'disable_attachment_pages',
	__( 'Redirect attachment pages', 'curly-site-tools' ),
	__( '301-redirect bare attachment URLs to their parent post (or home when there is no parent).', 'curly-site-tools' ),
	true
);

curly_site_tools_register_toggle(
	'limit_uploads_1mb',
	__( 'Limit Editor uploads', 'curly-site-tools' ),
	__( 'Cap file uploads for non-admin (Editor) users and show a note in the media uploader.', 'curly-site-tools' ),
	true,
	array(
		'field' => array(
			'label'   => __( 'Maximum size', 'curly-site-tools' ),
			'option'  => 'curly_site_tools_upload_limit_mb',
			'min'     => 1,
			'step'    => 1,
			'unit'    => 'MB',
			'default' => 1,
		),
	)
);

add_action(
	'plugins_loaded',
	function () {
		if ( curly_site_tools_is_enabled( 'disable_attachment_pages' ) ) {
			add_action(
				'template_redirect',
				function () {
					global $post;
					if ( ! is_attachment() || ! isset( $post->post_parent ) || ! is_numeric( $post->post_parent ) ) {
						return;
					}

					// If the parent post is trashed, fall back to the homepage.
					if ( 0 !== $post->post_parent && 'trash' !== get_post_status( $post->post_parent ) ) {
						wp_safe_redirect( get_permalink( $post->post_parent ), 301 );
					} else {
						wp_safe_redirect( get_bloginfo( 'wpurl' ), 302 );
					}
					exit;
				},
				1
			);
		}

		if ( curly_site_tools_is_enabled( 'limit_uploads_1mb' ) ) {
			// Configurable limit for the Editor role, in whole megabytes.
			$limit_mb = (int) curly_site_tools_get_value( 'limit_uploads_1mb', 1 );
			if ( $limit_mb < 1 ) {
				$limit_mb = 1;
			}

			add_filter(
				'wp_handle_upload_prefilter',
				function ( $file ) use ( $limit_mb ) {
					if ( current_user_can( 'edit_posts' ) && ! current_user_can( 'manage_options' ) ) {
						$max_size = $limit_mb * 1024 * 1024;
						if ( $file['size'] > $max_size ) {
							$file['error'] = sprintf(
								/* translators: %d: maximum upload size in megabytes. */
								__( 'File size exceeds the %dMB limit. Please upload to Google Drive or similar and link instead', 'curly-site-tools' ),
								$limit_mb
							);
						}
					}
					return $file;
				}
			);

			// Show the limit in the media uploader for Editors.
			add_action(
				'post-upload-ui',
				function () use ( $limit_mb ) {
					if ( current_user_can( 'edit_posts' ) && ! current_user_can( 'manage_options' ) ) {
						printf(
							'<p><strong>%s</strong></p>',
							esc_html(
								sprintf(
									/* translators: %d: maximum upload size in megabytes. */
									__( 'Upload limit for your role: %dMB', 'curly-site-tools' ),
									$limit_mb
								)
							)
						);
					}
				}
			);
		}
	},
	20
);
