<?php
/**
 * Local business structured data (schema.org).
 *
 * Search engines cannot reliably tell a site like "Two Rivers Gong Fu" apart
 * from a directory listing without an explicit business entity: name, address,
 * phone, hours. This include adds a LocalBusiness entity to the site's
 * structured data so a physical location can be matched to the site.
 *
 * Integration: when The SEO Framework (Autodescription) is active, the entity
 * is merged into that plugin's own `@graph` on its existing Organization node
 * (matched by `@id`), so the site still emits exactly one Organization. On a
 * site without TSF the entity is printed in its own `application/ld+json`
 * block. Nothing is output until the toggle is on AND a business name and
 * street address are filled in below.
 *
 * Toggle + NAP fields live under Tools > Curly Site Tools. The toggle defaults
 * to OFF: schema output is site-visible and the fields must be filled in first,
 * so this is opt-in per site (same posture as the Turnstile toggle).
 *
 * @package CurlySiteTools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const CURLY_SITE_TOOLS_SCHEMA_TYPE       = 'curly_site_tools_schema_type';
const CURLY_SITE_TOOLS_SCHEMA_NAME       = 'curly_site_tools_schema_name';
const CURLY_SITE_TOOLS_SCHEMA_STREET     = 'curly_site_tools_schema_street';
const CURLY_SITE_TOOLS_SCHEMA_CITY       = 'curly_site_tools_schema_city';
const CURLY_SITE_TOOLS_SCHEMA_REGION     = 'curly_site_tools_schema_region';
const CURLY_SITE_TOOLS_SCHEMA_POSTAL     = 'curly_site_tools_schema_postal_code';
const CURLY_SITE_TOOLS_SCHEMA_COUNTRY    = 'curly_site_tools_schema_country';
const CURLY_SITE_TOOLS_SCHEMA_PHONE      = 'curly_site_tools_schema_phone';
const CURLY_SITE_TOOLS_SCHEMA_PRICE      = 'curly_site_tools_schema_price_range';
const CURLY_SITE_TOOLS_SCHEMA_LAT        = 'curly_site_tools_schema_latitude';
const CURLY_SITE_TOOLS_SCHEMA_LNG        = 'curly_site_tools_schema_longitude';
const CURLY_SITE_TOOLS_SCHEMA_HOURS      = 'curly_site_tools_schema_hours';
const CURLY_SITE_TOOLS_SCHEMA_SAME_AS    = 'curly_site_tools_schema_same_as';

/**
 * Read one of the schema option fields.
 *
 * @param string $constant Option-name constant.
 * @param string $default  Fallback when unset.
 * @return string
 */
function curly_site_tools_schema_option( $constant, $default = '' ) {
	return trim( (string) get_option( constant( $constant ), $default ) );
}

/**
 * The business name for schema output (falls back to the site title).
 *
 * @return string
 */
function curly_site_tools_schema_name() {
	$name = curly_site_tools_schema_option( 'CURLY_SITE_TOOLS_SCHEMA_NAME' );
	return '' !== $name ? $name : (string) get_bloginfo( 'name' );
}

/**
 * Whether the LocalBusiness schema should be emitted.
 *
 * Requires the toggle on plus the two fields an entity is meaningless without.
 *
 * @return bool
 */
function curly_site_tools_schema_ready() {
	return curly_site_tools_is_enabled( 'schema' )
		&& '' !== curly_site_tools_schema_name()
		&& '' !== curly_site_tools_schema_option( 'CURLY_SITE_TOOLS_SCHEMA_STREET' );
}

/**
 * Normalise a day token to its canonical 3-letter key.
 *
 * Accepts full names ("Tuesday"), 3-letter ("Tue") and 2-letter ("Tu")
 * abbreviations, in any case.
 *
 * @param string $token Day token.
 * @return string Canonical key ("tue"), or '' when unrecognised.
 */
function curly_site_tools_schema_day_key( $token ) {
	static $keys = array( 'mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun' );

	$token = strtolower( preg_replace( '/[^a-z]/i', '', (string) $token ) );
	if ( '' === $token ) {
		return '';
	}

	if ( strlen( $token ) >= 3 ) {
		$token = substr( $token, 0, 3 );
		return in_array( $token, $keys, true ) ? $token : '';
	}

	foreach ( $keys as $key ) {
		if ( 0 === strpos( $key, $token ) ) {
			return $key;
		}
	}

	return '';
}

/**
 * Map a day token (full name, 3-letter or 2-letter abbreviation) to its
 * schema.org DayOfWeek URL.
 *
 * @param string $token Day token, e.g. "tue", "Tu", "Tuesday".
 * @return string DayOfWeek URL, or '' when unrecognised.
 */
function curly_site_tools_schema_day_url( $token ) {
	static $names = array(
		'mon' => 'Monday',
		'tue' => 'Tuesday',
		'wed' => 'Wednesday',
		'thu' => 'Thursday',
		'fri' => 'Friday',
		'sat' => 'Saturday',
		'sun' => 'Sunday',
	);

	$key = curly_site_tools_schema_day_key( $token );

	return '' === $key ? '' : 'https://schema.org/' . $names[ $key ];
}

/**
 * Turn one "Mo,Tu-Fr 09:00-18:00" line into OpeningHoursSpecification entries.
 *
 * Accepts comma-separated days and "A-B" ranges; times must be 24h "HH:MM".
 *
 * @param string $line One line of the opening-hours field.
 * @return array|null OpeningHoursSpecification entity, or null when unparseable.
 */
function curly_site_tools_schema_hours_line( $line ) {
	if ( ! preg_match( '/^\s*([A-Za-z,\s\-]+?)\s+(\d{1,2}:\d{2})\s*[-–]\s*(\d{1,2}:\d{2})\s*$/', $line, $matches ) ) {
		return null;
	}

	$days = array();
	foreach ( explode( ',', $matches[1] ) as $chunk ) {
		$chunk = trim( $chunk );
		if ( '' === $chunk ) {
			continue;
		}
		if ( false !== strpos( $chunk, '-' ) ) {
			list( $from, $to ) = array_map( 'trim', explode( '-', $chunk, 2 ) );
			$order = array( 'sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat' );
			$start = array_search( curly_site_tools_schema_day_key( $from ), $order, true );
			$end   = array_search( curly_site_tools_schema_day_key( $to ), $order, true );
			if ( false !== $start && false !== $end ) {
				$index = $start;
				while ( true ) {
					$url = curly_site_tools_schema_day_url( $order[ $index ] );
					if ( $url ) {
						$days[] = $url;
					}
					if ( $index === $end ) {
						break;
					}
					$index = ( $index + 1 ) % 7;
				}
			}
			continue;
		}
		$url = curly_site_tools_schema_day_url( $chunk );
		if ( $url ) {
			$days[] = $url;
		}
	}

	if ( empty( $days ) ) {
		return null;
	}

	return array(
		'@type'     => 'OpeningHoursSpecification',
		'dayOfWeek' => array_values( array_unique( $days ) ),
		'opens'     => $matches[2],
		'closes'    => $matches[3],
	);
}

/**
 * Build the LocalBusiness entity from the stored options.
 *
 * @return array|null Entity array, or null when not configured.
 */
function curly_site_tools_schema_entity() {
	if ( ! curly_site_tools_schema_ready() ) {
		return null;
	}

	$types = array( 'Organization' );
	$type  = curly_site_tools_schema_option( 'CURLY_SITE_TOOLS_SCHEMA_TYPE', 'LocalBusiness' );
	if ( '' !== $type ) {
		$types[] = $type;
	}

	$entity = array(
		// Keep 'Organization' first: TSF's node is merged in, and consumers that
		// only read the first type still see a valid entity.
		'@type'   => count( $types ) > 1 ? $types : $types[0],
		// Same @id TSF uses for its Organization, so the two merge in one node.
		'@id'     => home_url( '/' ) . '#/schema/Organization',
		'name'    => curly_site_tools_schema_name(),
		'url'     => home_url( '/' ),
		'address' => array_filter(
			array(
				'@type'           => 'PostalAddress',
				'streetAddress'   => curly_site_tools_schema_option( 'CURLY_SITE_TOOLS_SCHEMA_STREET' ),
				'addressLocality' => curly_site_tools_schema_option( 'CURLY_SITE_TOOLS_SCHEMA_CITY' ),
				'addressRegion'   => curly_site_tools_schema_option( 'CURLY_SITE_TOOLS_SCHEMA_REGION' ),
				'postalCode'      => curly_site_tools_schema_option( 'CURLY_SITE_TOOLS_SCHEMA_POSTAL' ),
				'addressCountry'  => curly_site_tools_schema_option( 'CURLY_SITE_TOOLS_SCHEMA_COUNTRY' ),
			)
		),
	);

	$phone = curly_site_tools_schema_option( 'CURLY_SITE_TOOLS_SCHEMA_PHONE' );
	if ( '' !== $phone ) {
		$entity['telephone'] = $phone;
	}

	$price = curly_site_tools_schema_option( 'CURLY_SITE_TOOLS_SCHEMA_PRICE' );
	if ( '' !== $price ) {
		$entity['priceRange'] = $price;
	}

	$lat = curly_site_tools_schema_option( 'CURLY_SITE_TOOLS_SCHEMA_LAT' );
	$lng = curly_site_tools_schema_option( 'CURLY_SITE_TOOLS_SCHEMA_LNG' );
	if ( is_numeric( $lat ) && is_numeric( $lng ) ) {
		$entity['geo'] = array(
			'@type'     => 'GeoCoordinates',
			'latitude'  => (float) $lat,
			'longitude' => (float) $lng,
		);
	}

	$hours = curly_site_tools_schema_option( 'CURLY_SITE_TOOLS_SCHEMA_HOURS' );
	if ( '' !== $hours ) {
		$specs = array();
		foreach ( preg_split( '/\r\n|\r|\n/', $hours ) as $line ) {
			$spec = curly_site_tools_schema_hours_line( $line );
			if ( $spec ) {
				$specs[] = $spec;
			}
		}
		if ( $specs ) {
			$entity['openingHoursSpecification'] = $specs;
		}
	}

	$same_as = curly_site_tools_schema_option( 'CURLY_SITE_TOOLS_SCHEMA_SAME_AS' );
	if ( '' !== $same_as ) {
		$urls = array_filter( array_map( 'trim', preg_split( '/[\r\n,]+/', $same_as ) ) );
		$urls = array_values( array_filter( $urls, 'esc_url_raw' ) );
		if ( $urls ) {
			$entity['sameAs'] = $urls;
		}
	}

	/**
	 * Filter the LocalBusiness entity before it is emitted.
	 *
	 * @param array $entity Schema.org entity.
	 */
	return apply_filters( 'curly_site_tools_schema_entity', $entity );
}

curly_site_tools_register_toggle(
	'schema',
	__( 'Local business structured data', 'curly-site-tools' ),
	__( 'Adds a schema.org LocalBusiness entity (name, address, phone, hours) to the site\'s structured data. Reuses The SEO Framework\'s Organization node when it is active. Fill in the fields below before enabling.', 'curly-site-tools' ),
	false,
	array(
		'fields' => array(
			array(
				'type'        => 'text',
				'label'       => __( 'Business type', 'curly-site-tools' ),
				'option'      => CURLY_SITE_TOOLS_SCHEMA_TYPE,
				'default'     => 'LocalBusiness',
				'placeholder' => 'LocalBusiness',
				'description' => __( 'A schema.org LocalBusiness subtype, e.g. SportsActivityLocation, ExerciseGym, HealthAndBeautyBusiness, Restaurant.', 'curly-site-tools' ),
			),
			array(
				'type'    => 'text',
				'label'   => __( 'Business name', 'curly-site-tools' ),
				'option'  => CURLY_SITE_TOOLS_SCHEMA_NAME,
				'default' => '',
			),
			array(
				'type'    => 'text',
				'label'   => __( 'Street address', 'curly-site-tools' ),
				'option'  => CURLY_SITE_TOOLS_SCHEMA_STREET,
				'default' => '',
			),
			array(
				'type'    => 'text',
				'label'   => __( 'City', 'curly-site-tools' ),
				'option'  => CURLY_SITE_TOOLS_SCHEMA_CITY,
				'default' => '',
			),
			array(
				'type'        => 'text',
				'label'       => __( 'State / region', 'curly-site-tools' ),
				'option'      => CURLY_SITE_TOOLS_SCHEMA_REGION,
				'default'     => '',
				'placeholder' => 'VT',
			),
			array(
				'type'    => 'text',
				'label'   => __( 'Postal code', 'curly-site-tools' ),
				'option'  => CURLY_SITE_TOOLS_SCHEMA_POSTAL,
				'default' => '',
			),
			array(
				'type'        => 'text',
				'label'       => __( 'Country', 'curly-site-tools' ),
				'option'      => CURLY_SITE_TOOLS_SCHEMA_COUNTRY,
				'default'     => 'US',
				'placeholder' => 'US',
				'description' => __( 'Two-letter ISO country code.', 'curly-site-tools' ),
			),
			array(
				'type'        => 'text',
				'label'       => __( 'Phone', 'curly-site-tools' ),
				'option'      => CURLY_SITE_TOOLS_SCHEMA_PHONE,
				'default'     => '',
				'placeholder' => '+1 802-555-0100',
			),
			array(
				'type'        => 'text',
				'label'       => __( 'Price range', 'curly-site-tools' ),
				'option'      => CURLY_SITE_TOOLS_SCHEMA_PRICE,
				'default'     => '',
				'placeholder' => '$10-20',
			),
			array(
				'type'    => 'text',
				'label'   => __( 'Latitude', 'curly-site-tools' ),
				'option'  => CURLY_SITE_TOOLS_SCHEMA_LAT,
				'default' => '',
			),
			array(
				'type'    => 'text',
				'label'   => __( 'Longitude', 'curly-site-tools' ),
				'option'  => CURLY_SITE_TOOLS_SCHEMA_LNG,
				'default' => '',
			),
			array(
				'type'        => 'text',
				'label'       => __( 'Opening hours', 'curly-site-tools' ),
				'option'      => CURLY_SITE_TOOLS_SCHEMA_HOURS,
				'default'     => '',
				'placeholder' => 'Mo,Tu,Th 09:00-18:00',
				'description' => __( 'One rule per line, 24-hour times: "Mo-Fr 09:00-18:00". Leave blank when the business has no public counter hours.', 'curly-site-tools' ),
			),
			array(
				'type'        => 'text',
				'label'       => __( 'Social profiles', 'curly-site-tools' ),
				'option'      => CURLY_SITE_TOOLS_SCHEMA_SAME_AS,
				'default'     => '',
				'placeholder' => 'https://www.facebook.com/…, https://www.instagram.com/…',
				'description' => __( 'Full profile URLs, comma or newline separated. Only add profiles that represent this business.', 'curly-site-tools' ),
			),
		),
	)
);

/**
 * Merge the entity into The SEO Framework's schema graph.
 *
 * TSF is the site's structured-data source when active, so extend its graph
 * rather than printing a competing one. Its first-party filter is documented
 * for exactly this ("Use this only to adjust write dynamic references. Use
 * `the_seo_framework_schema_graph_data` for direct alteration instead.").
 */
add_filter(
	'the_seo_framework_schema_graph_data',
	function ( $graph ) {
		$entity = curly_site_tools_schema_entity();
		if ( ! $entity ) {
			return $graph;
		}
		if ( ! is_array( $graph ) ) {
			return $graph;
		}

		foreach ( $graph as $index => $node ) {
			if ( is_array( $node ) && isset( $node['@id'] ) && $node['@id'] === $entity['@id'] ) {
				// Merge onto TSF's Organization node: our keys win, its logo and
				// any other properties survive.
				$graph[ $index ] = array_merge( $node, $entity );
				return $graph;
			}
		}

		$graph[] = $entity;
		return $graph;
	}
);

/**
 * Print the entity on sites without The SEO Framework.
 *
 * Runs late on wp_head; TSF prints its graph earlier, so the guard below keeps
 * the entity from being emitted twice on a TSF site.
 */
add_action(
	'wp_head',
	function () {
		if ( function_exists( 'the_seo_framework' ) ) {
			// TSF prints the graph via its own filter chain above.
			return;
		}

		$entity = curly_site_tools_schema_entity();
		if ( ! $entity ) {
			return;
		}

		printf(
			'<script type="application/ld+json">%s</script>' . "\n",
			wp_json_encode(
				array(
					'@context' => 'https://schema.org',
					'@graph'   => array( $entity ),
				),
				JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
			)
		);
	},
	20
);
