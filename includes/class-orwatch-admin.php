<?php
/**
 * Admin screen, AJAX endpoints and dashboard widget.
 *
 * @package PluginOrphanWatch
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin UI.
 */
final class ORWATCH_Admin {

	const PAGE_SLUG  = 'plugin-orphan-watch';
	const CAPABILITY = 'activate_plugins';

	/**
	 * Hook suffix of our screen.
	 *
	 * @var string
	 */
	private static $hook_suffix = '';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'wp_ajax_orwatch_scan_plugin', array( __CLASS__, 'ajax_scan' ) );
		add_action( 'wp_ajax_orwatch_toggle_ignore', array( __CLASS__, 'ajax_toggle_ignore' ) );
		add_action( 'wp_dashboard_setup', array( __CLASS__, 'register_widget' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( ORWATCH_FILE ), array( __CLASS__, 'action_links' ) );
	}

	/**
	 * URL of the main screen.
	 *
	 * @return string
	 */
	public static function page_url() {
		return admin_url( 'plugins.php?page=' . self::PAGE_SLUG );
	}

	/**
	 * Add "Orphan Watch" under Plugins.
	 */
	public static function register_menu() {
		self::$hook_suffix = (string) add_plugins_page(
			__( 'Plugin Orphan Watch', 'plugin-orphan-watch' ),
			__( 'Orphan Watch', 'plugin-orphan-watch' ),
			self::CAPABILITY,
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * "Scan plugins" link on the Plugins screen.
	 *
	 * @param string[] $links Existing links.
	 * @return string[]
	 */
	public static function action_links( $links ) {
		array_unshift(
			$links,
			'<a href="' . esc_url( self::page_url() ) . '">' . esc_html__( 'Scan plugins', 'plugin-orphan-watch' ) . '</a>'
		);
		return $links;
	}

	/**
	 * Load CSS/JS on our screen only.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public static function enqueue( $hook ) {
		if ( $hook !== self::$hook_suffix && 'index.php' !== $hook ) {
			return;
		}

		wp_enqueue_style( 'orwatch-admin', ORWATCH_URL . 'assets/admin.css', array(), ORWATCH_VERSION );

		if ( $hook !== self::$hook_suffix ) {
			return;
		}

		wp_enqueue_script( 'orwatch-admin', ORWATCH_URL . 'assets/admin.js', array(), ORWATCH_VERSION, true );
		wp_localize_script(
			'orwatch-admin',
			'orwatchData',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'orwatch_ajax' ),
				'i18n'    => array(
					/* translators: 1: number scanned, 2: total number of plugins */
					'progress' => __( 'Scanned %1$s of %2$s…', 'plugin-orphan-watch' ),
					'done'     => __( 'Scan complete.', 'plugin-orphan-watch' ),
					'scan'     => __( 'Scan all plugins', 'plugin-orphan-watch' ),
					'scanning' => __( 'Scanning…', 'plugin-orphan-watch' ),
					'failed'   => __( 'Request failed. Please try again.', 'plugin-orphan-watch' ),
				),
			)
		);
	}

	/**
	 * Verify capability + nonce for AJAX handlers.
	 */
	private static function check_ajax() {
		check_ajax_referer( 'orwatch_ajax', '_wpnonce' );
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do that.', 'plugin-orphan-watch' ) ), 403 );
		}
	}

	/**
	 * Read + validate the "plugin" request parameter.
	 *
	 * @return string Plugin basename.
	 */
	private static function requested_plugin() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in check_ajax().
		$plugin_file = isset( $_POST['plugin'] ) ? sanitize_text_field( wp_unslash( $_POST['plugin'] ) ) : '';
		if ( '' === $plugin_file || ! array_key_exists( $plugin_file, ORWATCH_Scanner::get_installed() ) ) {
			wp_send_json_error( array( 'message' => __( 'Unknown plugin.', 'plugin-orphan-watch' ) ), 400 );
		}
		return $plugin_file;
	}

	/**
	 * AJAX: scan one plugin and return its refreshed table row.
	 */
	public static function ajax_scan() {
		self::check_ajax();
		$plugin_file = self::requested_plugin();

		$result = ORWATCH_Scanner::scan( $plugin_file );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		wp_send_json_success( array( 'row' => self::render_row( $plugin_file ) ) );
	}

	/**
	 * AJAX: ignore / un-ignore a plugin.
	 */
	public static function ajax_toggle_ignore() {
		self::check_ajax();
		$plugin_file = self::requested_plugin();
		ORWATCH_Scanner::toggle_ignored( $plugin_file );
		wp_send_json_success( array( 'row' => self::render_row( $plugin_file ) ) );
	}

	/**
	 * Render a single table row (also used by AJAX).
	 *
	 * @param string $plugin_file Plugin basename.
	 * @return string HTML (escaped).
	 */
	public static function render_row( $plugin_file ) {
		$installed = ORWATCH_Scanner::get_installed();
		$data      = $installed[ $plugin_file ];
		$results   = ORWATCH_Scanner::get_results();
		$result    = isset( $results[ $plugin_file ] ) ? $results[ $plugin_file ] : null;
		$ignored   = in_array( $plugin_file, ORWATCH_Scanner::get_ignored(), true );
		$statuses  = ORWATCH_Scanner::statuses();
		$status    = ( $result && isset( $statuses[ $result['status'] ] ) ) ? $statuses[ $result['status'] ] : null;

		$level = $ignored ? 'ignored' : ( $status ? $status['level'] : 'none' );

		ob_start();
		?>
		<tr class="orwatch-row orwatch-row--<?php echo esc_attr( $level ); ?>" data-plugin="<?php echo esc_attr( $plugin_file ); ?>">
			<td class="orwatch-col-plugin">
				<strong><?php echo esc_html( $data['Name'] ); ?></strong>
				<span class="orwatch-muted">
					<?php
					/* translators: %s: plugin version number */
					echo esc_html( sprintf( __( 'v%s', 'plugin-orphan-watch' ), $data['Version'] ) );
					?>
					<?php if ( is_plugin_active( $plugin_file ) ) : ?>
						&middot; <?php esc_html_e( 'Active', 'plugin-orphan-watch' ); ?>
					<?php else : ?>
						&middot; <?php esc_html_e( 'Inactive', 'plugin-orphan-watch' ); ?>
					<?php endif; ?>
				</span>
			</td>
			<td>
				<?php if ( $ignored ) : ?>
					<span class="orwatch-badge orwatch-badge--ignored"><?php esc_html_e( 'Ignored', 'plugin-orphan-watch' ); ?></span>
				<?php elseif ( $status ) : ?>
					<span class="orwatch-badge orwatch-badge--<?php echo esc_attr( $status['level'] ); ?>" title="<?php echo esc_attr( $status['help'] ); ?>">
						<?php echo esc_html( $status['label'] ); ?>
					</span>
				<?php else : ?>
					<span class="orwatch-badge orwatch-badge--none"><?php esc_html_e( 'Not scanned', 'plugin-orphan-watch' ); ?></span>
				<?php endif; ?>
			</td>
			<td>
				<?php
				if ( $result && ! empty( $result['last_updated'] ) ) {
					printf(
						'<span title="%1$s">%2$s</span>',
						esc_attr( wp_date( get_option( 'date_format' ), (int) $result['last_updated'] ) ),
						/* translators: %s: human readable time difference, e.g. "2 years" */
						esc_html( sprintf( __( '%s ago', 'plugin-orphan-watch' ), human_time_diff( (int) $result['last_updated'] ) ) )
					);
				} else {
					echo '&mdash;';
				}
				?>
			</td>
			<td>
				<?php
				if ( $result && '' !== $result['tested'] ) {
					$class = ( null !== $result['tested_lag'] && $result['tested_lag'] >= ORWATCH_Scanner::UNTESTED_LAG ) ? 'orwatch-warn-text' : '';
					printf( '<span class="%1$s">%2$s</span>', esc_attr( $class ), esc_html( $result['tested'] ) );
				} else {
					echo '&mdash;';
				}
				?>
			</td>
			<td>
				<?php
				if ( $result && ! empty( $result['active_installs'] ) ) {
					echo esc_html( number_format_i18n( $result['active_installs'] ) . '+' );
				} else {
					echo '&mdash;';
				}
				?>
			</td>
			<td class="orwatch-col-actions">
				<button type="button" class="button-link orwatch-rescan"><?php esc_html_e( 'Re-scan', 'plugin-orphan-watch' ); ?></button>
				<button type="button" class="button-link orwatch-ignore">
					<?php echo $ignored ? esc_html__( 'Stop ignoring', 'plugin-orphan-watch' ) : esc_html__( 'Ignore', 'plugin-orphan-watch' ); ?>
				</button>
			</td>
		</tr>
		<?php
		return trim( (string) ob_get_clean() );
	}

	/**
	 * Sort installed plugins: worst first, unscanned and healthy last.
	 *
	 * @return string[] Plugin basenames.
	 */
	private static function sorted_plugins() {
		$installed = ORWATCH_Scanner::get_installed();
		$results   = ORWATCH_Scanner::get_results();
		$ignored   = ORWATCH_Scanner::get_ignored();
		$statuses  = ORWATCH_Scanner::statuses();

		$rank = static function ( $file ) use ( $results, $ignored, $statuses ) {
			if ( in_array( $file, $ignored, true ) ) {
				return 100;
			}
			if ( ! isset( $results[ $file ], $statuses[ $results[ $file ]['status'] ] ) ) {
				return 50;
			}
			return $statuses[ $results[ $file ]['status'] ]['rank'];
		};

		$files = array_keys( $installed );
		usort(
			$files,
			static function ( $a, $b ) use ( $rank, $installed ) {
				$by_rank = $rank( $a ) <=> $rank( $b );
				return 0 !== $by_rank ? $by_rank : strcasecmp( $installed[ $a ]['Name'], $installed[ $b ]['Name'] );
			}
		);
		return $files;
	}

	/**
	 * Main screen.
	 */
	public static function render_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		$files   = self::sorted_plugins();
		$summary = ORWATCH_Scanner::summary();
		?>
		<div class="wrap orwatch-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Plugin Orphan Watch', 'plugin-orphan-watch' ); ?></h1>
			<button type="button" class="page-title-action" id="orwatch-scan"><?php esc_html_e( 'Scan all plugins', 'plugin-orphan-watch' ); ?></button>
			<hr class="wp-header-end">

			<p class="description">
				<?php esc_html_e( 'Checks every installed plugin against the WordPress.org directory and flags the ones that are closed, abandoned or no longer tested with current WordPress.', 'plugin-orphan-watch' ); ?>
			</p>

			<div class="orwatch-summary" id="orwatch-summary">
				<div class="orwatch-card orwatch-card--critical"><span class="orwatch-card__num"><?php echo esc_html( number_format_i18n( $summary['critical'] ) ); ?></span><?php esc_html_e( 'Critical', 'plugin-orphan-watch' ); ?></div>
				<div class="orwatch-card orwatch-card--warning"><span class="orwatch-card__num"><?php echo esc_html( number_format_i18n( $summary['warning'] ) ); ?></span><?php esc_html_e( 'Warnings', 'plugin-orphan-watch' ); ?></div>
				<div class="orwatch-card orwatch-card--ok"><span class="orwatch-card__num"><?php echo esc_html( number_format_i18n( $summary['ok'] ) ); ?></span><?php esc_html_e( 'Healthy', 'plugin-orphan-watch' ); ?></div>
				<div class="orwatch-card orwatch-card--none"><span class="orwatch-card__num"><?php echo esc_html( number_format_i18n( $summary['unscanned'] + $summary['info'] ) ); ?></span><?php esc_html_e( 'Unverified', 'plugin-orphan-watch' ); ?></div>
			</div>

			<p id="orwatch-progress" class="orwatch-progress" role="status" aria-live="polite"></p>

			<table class="widefat striped orwatch-table" id="orwatch-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Plugin', 'plugin-orphan-watch' ); ?></th>
						<th><?php esc_html_e( 'Status', 'plugin-orphan-watch' ); ?></th>
						<th><?php esc_html_e( 'Last updated', 'plugin-orphan-watch' ); ?></th>
						<th><?php esc_html_e( 'Tested up to', 'plugin-orphan-watch' ); ?></th>
						<th><?php esc_html_e( 'Active installs', 'plugin-orphan-watch' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'plugin-orphan-watch' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach ( $files as $file ) {
						// render_row() output is escaped internally.
						echo self::render_row( $file ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					}
					?>
				</tbody>
			</table>

			<?php
			/**
			 * Fires below the plugin table. Premium add-ons can render extra panels here.
			 */
			do_action( 'orwatch_after_table' );

			if ( ! orwatch_is_pro() ) {
				self::render_pro_box();
			}
			?>
		</div>
		<?php
	}

	/**
	 * Non-intrusive "Pro" info box. Rendered only on our own screen.
	 */
	private static function render_pro_box() {
		?>
		<div class="orwatch-pro">
			<h2><?php esc_html_e( 'Want it to watch your site for you?', 'plugin-orphan-watch' ); ?></h2>
			<p><?php esc_html_e( 'Orphan Watch Pro runs in the background and tells you the moment a plugin becomes a risk:', 'plugin-orphan-watch' ); ?></p>
			<ul>
				<li><?php esc_html_e( 'Scheduled scans (daily or weekly)', 'plugin-orphan-watch' ); ?></li>
				<li><?php esc_html_e( 'Email and Slack alerts when a plugin is closed or abandoned', 'plugin-orphan-watch' ); ?></li>
				<li><?php esc_html_e( 'Known-vulnerability data for each plugin', 'plugin-orphan-watch' ); ?></li>
				<li><?php esc_html_e( 'Suggested maintained alternatives', 'plugin-orphan-watch' ); ?></li>
				<li><?php esc_html_e( 'CSV / PDF reports and multisite support', 'plugin-orphan-watch' ); ?></li>
			</ul>
			<p><a class="button button-primary" href="<?php echo esc_url( orwatch_upgrade_url( 'admin-page' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Learn about Pro', 'plugin-orphan-watch' ); ?></a></p>
		</div>
		<?php
	}

	/**
	 * Dashboard widget.
	 */
	public static function register_widget() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}
		wp_add_dashboard_widget(
			'orwatch_dashboard_widget',
			__( 'Plugin Orphan Watch', 'plugin-orphan-watch' ),
			array( __CLASS__, 'render_widget' )
		);
	}

	/**
	 * Dashboard widget body.
	 */
	public static function render_widget() {
		$summary = ORWATCH_Scanner::summary();

		if ( 0 === $summary['scanned'] ) {
			echo '<p>' . esc_html__( 'Your plugins have not been scanned yet.', 'plugin-orphan-watch' ) . '</p>';
		} elseif ( 0 === $summary['critical'] && 0 === $summary['warning'] ) {
			echo '<p class="orwatch-widget-ok">' . esc_html__( 'No abandoned or outdated plugins found.', 'plugin-orphan-watch' ) . '</p>';
		} else {
			echo '<p>';
			echo esc_html(
				sprintf(
					/* translators: 1: number of critical plugins, 2: number of warning plugins */
					__( '%1$s critical and %2$s warning plugin(s) need your attention.', 'plugin-orphan-watch' ),
					number_format_i18n( $summary['critical'] ),
					number_format_i18n( $summary['warning'] )
				)
			);
			echo '</p>';
		}

		echo '<p><a class="button" href="' . esc_url( self::page_url() ) . '">' . esc_html__( 'Open Orphan Watch', 'plugin-orphan-watch' ) . '</a></p>';
	}
}
