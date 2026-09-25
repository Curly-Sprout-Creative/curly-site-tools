<?php
/**
 * Cloudflare Turnstile for Breakdance / Oxygen forms.
 *
 * Breakdance Forms only knows Google reCAPTCHA, which loads a Google script and
 * sets cookies. This include swaps in Cloudflare Turnstile instead: it prints
 * the Turnstile widget inside every Breakdance form, verifies the token
 * server-side on submit, and stops the reCAPTCHA script from loading.
 *
 * Toggle + keys live under Tools > Curly Site Tools. When the toggle is off, or
 * either key is empty, nothing changes and Breakdance's own reCAPTCHA keeps
 * working. On non-Oxygen sites the Breakdance hooks simply never fire.
 *
 * @package CurlySiteTools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const CURLY_SITE_TOOLS_TURNSTILE_SITE_KEY = 'curly_site_tools_turnstile_site_key';
const CURLY_SITE_TOOLS_TURNSTILE_SECRET_KEY = 'curly_site_tools_turnstile_secret_key';

/**
 * Stored Turnstile site key.
 *
 * @return string
 */
function curly_site_tools_turnstile_site_key() {
	return (string) get_option( CURLY_SITE_TOOLS_TURNSTILE_SITE_KEY, '' );
}

/**
 * Stored Turnstile secret key.
 *
 * @return string
 */
function curly_site_tools_turnstile_secret_key() {
	return (string) get_option( CURLY_SITE_TOOLS_TURNSTILE_SECRET_KEY, '' );
}

/**
 * Whether Turnstile is enabled and fully configured.
 *
 * @return bool
 */
function curly_site_tools_turnstile_ready() {
	return curly_site_tools_is_enabled( 'turnstile' )
		&& '' !== curly_site_tools_turnstile_site_key()
		&& '' !== curly_site_tools_turnstile_secret_key();
}

curly_site_tools_register_toggle(
	'turnstile',
	__( 'Cloudflare Turnstile on forms', 'curly-site-tools' ),
	__( 'Adds a Cloudflare Turnstile widget to Breakdance/Oxygen forms and verifies it server-side, replacing Google reCAPTCHA.', 'curly-site-tools' ),
	false,
	array(
		'fields' => array(
			array(
				'type'        => 'text',
				'label'       => __( 'Turnstile Site Key', 'curly-site-tools' ),
				'option'      => CURLY_SITE_TOOLS_TURNSTILE_SITE_KEY,
				'placeholder' => '0x4AAAAAAA...',
			),
			array(
				'type'  => 'password',
				'label' => __( 'Turnstile Secret Key', 'curly-site-tools' ),
				'option' => CURLY_SITE_TOOLS_TURNSTILE_SECRET_KEY,
			),
		),
	)
);

/**
 * Register the front-end hooks only once the toggle is usable.
 */
add_action(
	'plugins_loaded',
	function () {
		if ( ! curly_site_tools_turnstile_ready() ) {
			return;
		}

		// Keep Breakdance from loading the Google reCAPTCHA script.
		add_filter( 'breakdance_load_recaptcha_script', '__return_false' );

		// Load the Turnstile API on the front end.
		add_action(
			'wp_enqueue_scripts',
			function () {
				wp_enqueue_script(
					'cf-turnstile',
					'https://challenges.cloudflare.com/turnstile/v0/api.js',
					array(),
					null,
					true
				);
			}
		);

		// Print the widget just before each Breakdance form's closing tag.
		add_action(
			'breakdance_form_before_footer',
			function () {
				printf(
					'<div class="cf-turnstile" data-sitekey="%s" data-theme="light"></div>',
					esc_attr( curly_site_tools_turnstile_site_key() )
				);
			}
		);

		// Verify the token server-side; add a field error to block the submit.
		add_filter(
			'breakdance_form_validate_field',
			function ( $field_errors, $field, $form_id, $post_id ) {
				static $verified = false;

				// Run the network check once per request, not once per field.
				if ( $verified ) {
					return $field_errors;
				}
				$verified = true;

				$token = isset( $_POST['cf-turnstile-response'] )
					? sanitize_text_field( wp_unslash( $_POST['cf-turnstile-response'] ) )
					: '';

				if ( '' === $token ) {
					$field_errors->add(
						'turnstile_missing',
						__( 'Please complete the anti-spam check and try again.', 'curly-site-tools' )
					);
					return $field_errors;
				}

				$response = wp_remote_post(
					'https://challenges.cloudflare.com/turnstile/v0/siteverify',
					array(
						'timeout' => 10,
						'body'    => array(
							'secret'   => curly_site_tools_turnstile_secret_key(),
							'response' => $token,
							'remoteip' => isset( $_SERVER['REMOTE_ADDR'] )
								? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
								: '',
						),
					)
				);

				if ( is_wp_error( $response ) ) {
					$field_errors->add(
						'turnstile_error',
						__( 'Anti-spam verification failed. Please try again.', 'curly-site-tools' )
					);
					return $field_errors;
				}

				$body = json_decode( wp_remote_retrieve_body( $response ), true );

				if ( empty( $body['success'] ) ) {
					$field_errors->add(
						'turnstile_failed',
						__( 'Anti-spam verification failed. Please try again.', 'curly-site-tools' )
					);
				}

				return $field_errors;
			},
			10,
			4
		);
	}
);
