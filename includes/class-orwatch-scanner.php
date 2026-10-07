<?php
/**
 * Scanning + classification logic.
 *
 * @package OrphanWatch
 */

defined( 'ABSPATH' ) || exit;

/**
 * Looks plugins up on WordPress.org and decides how healthy they are.
 */
final class ORWATCH_Scanner {

	const OPTION_RESULTS = 'orwatch_results';
	const OPTION_IGNORED = 'orwatch_ignored';

	/** Months without an update before a plugin is "stale". */
	const STALE_MONTHS = 12;

	/** Months without an update before a plugin is "abandoned". */
	const ABANDONED_MONTHS = 24;

	/** How many WordPress releases behind "Tested up to" may be before we warn. */
	const UNTESTED_LAG = 3;

	/**
	 * All statuses: label, severity level and a short explanation.
	 *
	 * @return array<string, array{label:string, level:string, help:string, rank:int}>
	 */
	public static function statuses() {
		return array(
			'closed'     => array(
				'label' => __( 'Closed', 'orphan-watch' ),
				'level' => 'critical',
				'help'  => __( 'Removed from WordPress.org (often for security or guideline reasons). It no longer receives updates.', 'orphan-watch' ),
				'rank'  => 0,
			),
			'abandoned'  => array(
				'label' => __( 'Abandoned', 'orphan-watch' ),
				'level' => 'critical',
				'help'  => __( 'Not updated for over 2 years. Known issues will not be fixed.', 'orphan-watch' ),
				'rank'  => 1,
			),
			'stale'      => array(
				'label' => __( 'Stale', 'orphan-watch' ),
				'level' => 'warning',
				'help'  => __( 'Not updated for over a year.', 'orphan-watch' ),
				'rank'  => 2,
			),
			'untested'   => array(
				'label' => __( 'Untested', 'orphan-watch' ),
				'level' => 'warning',
				'help'  => __( 'The author has not tested it with recent WordPress releases.', 'orphan-watch' ),
				'rank'  => 3,
			),
			'error'      => array(
				'label' => __( 'Check failed', 'orphan-watch' ),
				'level' => 'info',
				'help'  => __( 'WordPress.org could not be reached. Try again later.', 'orphan-watch' ),
				'rank'  => 4,
			),
			'not_listed' => array(
				'label' => __( 'Not on WordPress.org', 'orphan-watch' ),
				'level' => 'info',
				'help'  => __( 'Not found in the WordPress.org directory. It may be a premium or custom plugin, or one that was removed. Check its source.', 'orphan-watch' ),
				'rank'  => 5,
			),
			'external'   => array(
				'label' => __( 'Self-updating', 'orphan-watch' ),
				'level' => 'info',
				'help'  => __( 'Updates come from its own server (Update URI), so it cannot be checked here.', 'orphan-watch' ),
				'rank'  => 6,
			),
			'ok'         => array(
				'label' => __( 'Healthy', 'orphan-watch' ),
				'level' => 'ok',
				'help'  => __( 'Recently updated and tested with a current WordPress version.', 'orphan-watch' ),
				'rank'  => 7,
			),
		);
	}

	/**
	 * Directory slug for a plugin basename ("foo/foo.php" => "foo").
	 *
	 * @param string $plugin_file Plugin basename.
	 * @return string
	 */
	public static function slug_from_file( $plugin_file ) {
		$dir = dirname( $plugin_file );
		return '.' === $dir ? basename( $plugin_file, '.php' ) : $dir;
	}

	/**
	 * Installed plugins keyed by basename.
	 *
	 * @return array<string, array>
	 */
	public static function get_installed() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		return get_plugins();
	}

	/**
	 * Stored results for plugins that are still installed.
	 *
	 * @return array<string, array>
	 */
	public static function get_results() {
		$results = get_option( self::OPTION_RESULTS, array() );
		if ( ! is_array( $results ) ) {
			return array();
		}
		return array_intersect_key( $results, self::get_installed() );
	}

	/**
	 * Basenames of plugins the user chose to ignore.
	 *
	 * @return string[]
	 */
	public static function get_ignored() {
		$ignored = get_option( self::OPTION_IGNORED, array() );
		return is_array( $ignored ) ? array_values( $ignored ) : array();
	}

	/**
	 * Toggle the ignore flag.
	 *
	 * @param string $plugin_file Plugin basename.
	 * @return bool New ignored state.
	 */
	public static function toggle_ignored( $plugin_file ) {
		$ignored = self::get_ignored();
		$key     = array_search( $plugin_file, $ignored, true );
		if ( false === $key ) {
			$ignored[] = $plugin_file;
			$state     = true;
		} else {
			unset( $ignored[ $key ] );
			$state = false;
		}
		update_option( self::OPTION_IGNORED, array_values( $ignored ), false );
		return $state;
	}

	/**
	 * Count plugins per severity (ignored plugins excluded).
	 *
	 * @return array{critical:int, warning:int, info:int, ok:int, unscanned:int, scanned:int}
	 */
	public static function summary() {
		$counts   = array(
			'critical'  => 0,
			'warning'   => 0,
			'info'      => 0,
			'ok'        => 0,
			'unscanned' => 0,
			'scanned'   => 0,
		);
		$results  = self::get_results();
		$ignored  = self::get_ignored();
		$statuses = self::statuses();

		foreach ( array_keys( self::get_installed() ) as $file ) {
			if ( in_array( $file, $ignored, true ) ) {
				continue;
			}
			if ( ! isset( $results[ $file ], $statuses[ $results[ $file ]['status'] ] ) ) {
				++$counts['unscanned'];
				continue;
			}
			++$counts['scanned'];
			++$counts[ $statuses[ $results[ $file ]['status'] ]['level'] ];
		}
		return $counts;
	}

	/**
	 * Scan a single installed plugin against WordPress.org.
	 *
	 * @param string $plugin_file Plugin basename.
	 * @return array|WP_Error Result record.
	 */
	public static function scan( $plugin_file ) {
		$installed = self::get_installed();
		if ( ! isset( $installed[ $plugin_file ] ) ) {
			return new WP_Error( 'orwatch_unknown_plugin', __( 'That plugin is not installed.', 'orphan-watch' ) );
		}

		$data   = $installed[ $plugin_file ];
		$slug   = self::slug_from_file( $plugin_file );
		$result = array(
			'slug'            => $slug,
			'status'          => 'error',
			'last_updated'    => 0,
			'tested'          => '',
			'tested_lag'      => null,
			'active_installs' => 0,
			'checked'         => time(),
		);

		$update_uri = isset( $data['UpdateURI'] ) ? trim( (string) $data['UpdateURI'] ) : '';
		if ( '' !== $update_uri && 'wordpress.org' !== wp_parse_url( $update_uri, PHP_URL_HOST ) ) {
			$result['status'] = 'external';
		} else {
			if ( ! function_exists( 'plugins_api' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
			}

			$api = plugins_api(
				'plugin_information',
				array(
					'slug'   => $slug,
					'fields' => array(
						'sections'          => false,
						'description'       => false,
						'short_description' => false,
						'banners'           => false,
						'icons'             => false,
						'reviews'           => false,
						'versions'          => false,
						'screenshots'       => false,
						'contributors'      => false,
						'tags'              => false,
						'ratings'           => false,
						'donate_link'       => false,
						'language_packs'    => false,
						'active_installs'   => true,
						'last_updated'      => true,
					),
				)
			);

			if ( is_wp_error( $api ) ) {
				$result['status'] = self::status_from_error( $api );
			} else {
				$result                    = array_merge(
					$result,
					self::classify(
						isset( $api->last_updated ) ? strtotime( $api->last_updated ) : 0,
						isset( $api->tested ) ? (string) $api->tested : ''
					)
				);
				$result['active_installs'] = isset( $api->active_installs ) ? (int) $api->active_installs : 0;
				$result['tested']          = isset( $api->tested ) ? (string) $api->tested : '';
				$result['last_updated']    = isset( $api->last_updated ) ? (int) strtotime( $api->last_updated ) : 0;
			}
		}

		/**
		 * Filter a scan result before it is stored. Premium add-ons can enrich it
		 * (e.g. with vulnerability data).
		 *
		 * @param array  $result      Result record.
		 * @param string $plugin_file Plugin basename.
		 * @param array  $data        Plugin header data.
		 */
		$result = apply_filters( 'orwatch_result', $result, $plugin_file, $data );

		// A transient failure must not wipe a previous good result.
		if ( 'error' !== $result['status'] ) {
			$results                 = get_option( self::OPTION_RESULTS, array() );
			$results                 = is_array( $results ) ? $results : array();
			$results[ $plugin_file ] = $result;
			update_option( self::OPTION_RESULTS, $results, false );
		}

		/**
		 * Fires after a plugin has been scanned.
		 *
		 * @param string $plugin_file Plugin basename.
		 * @param array  $result      Result record.
		 */
		do_action( 'orwatch_scan_complete', $plugin_file, $result );

		return $result;
	}

	/**
	 * Map a plugins_api() error to a status.
	 *
	 * The directory answers `{"error":"Plugin not found."}` for unknown slugs and
	 * an error mentioning "closed" for closed plugins. Anything else is treated as
	 * a transient failure.
	 *
	 * @param WP_Error $error Error from plugins_api().
	 * @return string
	 */
	private static function status_from_error( WP_Error $error ) {
		$message = strtolower( $error->get_error_message() );
		$data    = $error->get_error_data();
		if ( is_string( $data ) ) {
			$message .= ' ' . strtolower( $data );
		}

		if ( false !== strpos( $message, 'closed' ) ) {
			return 'closed';
		}
		if ( false !== strpos( $message, 'not found' ) ) {
			return 'not_listed';
		}
		return 'error';
	}

	/**
	 * Decide a status from the plugin's last-update time and "Tested up to".
	 *
	 * Pure function (no I/O) so it is easy to unit test.
	 *
	 * @param int         $last_updated Unix timestamp of the last release (0 = unknown).
	 * @param string      $tested       "Tested up to" version.
	 * @param int|null    $now          Current time.
	 * @param string|null $wp_version   Current WordPress version.
	 * @return array{status:string, tested_lag:int|null}
	 */
	public static function classify( $last_updated, $tested, $now = null, $wp_version = null ) {
		$now        = null === $now ? time() : (int) $now;
		$wp_version = null === $wp_version ? get_bloginfo( 'version' ) : $wp_version;

		$lag    = self::version_lag( $tested, $wp_version );
		$months = $last_updated ? ( $now - $last_updated ) / ( 30.44 * DAY_IN_SECONDS ) : null;

		if ( null !== $months && $months >= self::ABANDONED_MONTHS ) {
			$status = 'abandoned';
		} elseif ( null !== $months && $months >= self::STALE_MONTHS ) {
			$status = 'stale';
		} elseif ( null !== $lag && $lag >= self::UNTESTED_LAG ) {
			$status = 'untested';
		} else {
			$status = 'ok';
		}

		return array(
			'status'     => $status,
			'tested_lag' => $lag,
		);
	}

	/**
	 * How many WordPress releases "tested up to" is behind the running version.
	 *
	 * Versions are compared as major*10+minor (6.9 => 69, 7.0 => 70), which matches
	 * the WordPress release cadence.
	 *
	 * @param string $tested     Tested-up-to version.
	 * @param string $wp_version Running version.
	 * @return int|null Null when unknown.
	 */
	public static function version_lag( $tested, $wp_version ) {
		$tested_n = self::branch_number( $tested );
		$wp_n     = self::branch_number( $wp_version );
		if ( null === $tested_n || null === $wp_n ) {
			return null;
		}
		return max( 0, $wp_n - $tested_n );
	}

	/**
	 * "6.9.1" => 69.
	 *
	 * @param string $version Version string.
	 * @return int|null
	 */
	private static function branch_number( $version ) {
		if ( ! preg_match( '/^(\d+)\.(\d+)/', (string) $version, $m ) ) {
			return null;
		}
		return ( (int) $m[1] * 10 ) + (int) $m[2];
	}
}
