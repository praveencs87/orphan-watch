<?php
/**
 * Site Health integration.
 *
 * @package PluginOrphanWatch
 */

defined( 'ABSPATH' ) || exit;

/**
 * Adds a test to Tools > Site Health.
 */
final class ORWATCH_Integrations {

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_filter( 'site_status_tests', array( __CLASS__, 'register_site_health_test' ) );
	}

	/**
	 * Add the direct test.
	 *
	 * @param array $tests Registered tests.
	 * @return array
	 */
	public static function register_site_health_test( $tests ) {
		$tests['direct']['orwatch_abandoned_plugins'] = array(
			'label' => __( 'Abandoned plugins', 'plugin-orphan-watch' ),
			'test'  => array( __CLASS__, 'site_health_test' ),
		);
		return $tests;
	}

	/**
	 * Site Health test callback (uses stored results; never calls the network).
	 *
	 * @return array
	 */
	public static function site_health_test() {
		$summary = ORWATCH_Scanner::summary();
		$url     = ORWATCH_Admin::page_url();

		$result = array(
			'label'       => __( 'No abandoned plugins detected', 'plugin-orphan-watch' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'Security', 'plugin-orphan-watch' ),
				'color' => 'blue',
			),
			'description' => '<p>' . esc_html__( 'Plugin Orphan Watch did not find closed or abandoned plugins.', 'plugin-orphan-watch' ) . '</p>',
			'actions'     => '',
			'test'        => 'orwatch_abandoned_plugins',
		);

		if ( 0 === $summary['scanned'] ) {
			$result['status']      = 'recommended';
			$result['label']       = __( 'Your plugins have not been checked for abandonment', 'plugin-orphan-watch' );
			$result['description'] = '<p>' . esc_html__( 'Run a scan to find plugins that are no longer maintained.', 'plugin-orphan-watch' ) . '</p>';
			$result['actions']     = '<p><a href="' . esc_url( $url ) . '">' . esc_html__( 'Scan plugins', 'plugin-orphan-watch' ) . '</a></p>';
		} elseif ( $summary['critical'] > 0 ) {
			$result['status']      = 'critical';
			$result['label']       = __( 'Closed or abandoned plugins are installed', 'plugin-orphan-watch' );
			$result['description'] = '<p>' . esc_html(
				sprintf(
					/* translators: %s: number of plugins */
					_n(
						'%s plugin is closed or has not been updated in over two years. Unmaintained plugins are a common way sites get hacked.',
						'%s plugins are closed or have not been updated in over two years. Unmaintained plugins are a common way sites get hacked.',
						$summary['critical'],
						'plugin-orphan-watch'
					),
					number_format_i18n( $summary['critical'] )
				)
			) . '</p>';
			$result['actions'] = '<p><a href="' . esc_url( $url ) . '">' . esc_html__( 'Review plugins', 'plugin-orphan-watch' ) . '</a></p>';
		} elseif ( $summary['warning'] > 0 ) {
			$result['status']      = 'recommended';
			$result['label']       = __( 'Some plugins look unmaintained', 'plugin-orphan-watch' );
			$result['description'] = '<p>' . esc_html(
				sprintf(
					/* translators: %s: number of plugins */
					_n(
						'%s plugin has not been updated for a year or is untested with current WordPress.',
						'%s plugins have not been updated for a year or are untested with current WordPress.',
						$summary['warning'],
						'plugin-orphan-watch'
					),
					number_format_i18n( $summary['warning'] )
				)
			) . '</p>';
			$result['actions'] = '<p><a href="' . esc_url( $url ) . '">' . esc_html__( 'Review plugins', 'plugin-orphan-watch' ) . '</a></p>';
		}

		return $result;
	}
}
