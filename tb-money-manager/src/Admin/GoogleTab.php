<?php

namespace TuinenBalkon\TBMoneyManager\Admin;

use TuinenBalkon\TBMoneyManager\Google\SiteKitBridge;

class GoogleTab {

	private SiteKitBridge $bridge;

	public function __construct( SiteKitBridge $bridge ) {
		$this->bridge = $bridge;
		add_action( 'admin_post_tbmm_google_clear_cache', array( $this, 'handle_clear_cache' ) );
	}

	public function handle_clear_cache(): void {
		check_admin_referer( 'tbmm_google_clear_cache' );
		$subtab     = sanitize_key( $_GET['subtab'] ?? '' );
		$gsc_period = sanitize_key( $_GET['gsc_period'] ?? '' );
		if ( $subtab === 'adsense' ) {
			$this->bridge->clear_adsense_cache();
		} elseif ( $subtab === 'search_console' ) {
			$this->bridge->clear_gsc_cache();
		} else {
			$this->bridge->clear_all_cache();
		}
		$redirect = admin_url( 'admin.php?page=tb-money-manager&tab=google&subtab=' . $subtab . '&cache_cleared=1' );
		if ( $gsc_period ) {
			$redirect = add_query_arg( 'gsc_period', $gsc_period, $redirect );
		}
		wp_safe_redirect( $redirect );
		exit;
	}

	public function render(): void {
		$base_url = admin_url( 'admin.php?page=tb-money-manager&tab=google' );
		$subtab   = sanitize_key( $_GET['subtab'] ?? 'search_console' );

		$subtabs = array(
			'search_console' => __( 'Search Console', 'tbmm' ),
			'adsense'        => __( 'AdSense', 'tbmm' ),
		);
		?>
		<div style="max-width:960px;">

			<?php if ( isset( $_GET['cache_cleared'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Cache gewist.', 'tbmm' ); ?></p></div>
			<?php endif; ?>

			<?php if ( ! $this->bridge->is_available() ) : ?>
				<div class="notice notice-warning inline">
					<p>
						<strong><?php esc_html_e( 'Google Site Kit is niet actief.', 'tbmm' ); ?></strong>
						<?php
						/* translators: %s = link to WordPress plugin search */
						printf(
							__( 'Installeer en activeer de %s om Google-data te tonen.', 'tbmm' ),
							'<a href="' . esc_url( admin_url( 'plugin-install.php?s=google-site-kit&tab=search&type=term' ) ) . '">' . esc_html__( 'Google Site Kit plugin', 'tbmm' ) . '</a>'
						);
						?>
					</p>
				</div>
			<?php else : ?>

				<div class="alc-subtab-nav" style="display:flex; align-items:flex-end; gap:4px; margin-bottom:20px; border-bottom:1px solid #c3c4c7; padding-bottom:0;">
					<?php foreach ( $subtabs as $slug => $label ) : ?>
						<a href="<?php echo esc_url( $base_url . '&subtab=' . $slug ); ?>"
						   style="display:inline-block; padding:6px 14px; text-decoration:none; font-size:13px; color:#2271b1; border:1px solid transparent; border-bottom:none; border-radius:3px 3px 0 0; margin-bottom:-1px; <?php echo $subtab === $slug ? 'background:#fff; border-color:#c3c4c7; color:#1d2327; font-weight:600;' : ''; ?>">
							<?php echo esc_html( $label ); ?>
						</a>
					<?php endforeach; ?>
				</div>

				<?php if ( $subtab === 'adsense' ) : ?>
					<?php $this->render_adsense_subtab(); ?>
				<?php else : ?>
					<?php $this->render_search_console_subtab(); ?>
				<?php endif; ?>

			<?php endif; ?>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Search Console subtab
	// -------------------------------------------------------------------------

	private function render_search_console_subtab(): void {
		if ( ! $this->bridge->is_search_console_connected() ) {
			echo '<div class="notice notice-warning inline"><p>';
			echo '<strong>' . esc_html__( 'Search Console is niet verbonden in Site Kit.', 'tbmm' ) . '</strong> ';
			/* translators: %s = link to Site Kit dashboard */
			printf(
				__( 'Ga naar %s en activeer de Search Console module.', 'tbmm' ),
				'<a href="' . esc_url( admin_url( 'admin.php?page=googlesitekit-dashboard' ) ) . '">' . esc_html__( 'Site Kit → Dashboard', 'tbmm' ) . '</a>'
			);
			echo '</p></div>';
			return;
		}

		$base_url = admin_url( 'admin.php?page=tb-money-manager&tab=google&subtab=search_console' );

		// GSC data loopt ~1 dag achter; gisteren is zeker beschikbaar.
		$end     = gmdate( 'Y-m-d', strtotime( '-1 day' ) );
		$periods = array(
			'yesterday'      => array( 'start' => $end,                                         'end' => $end, 'label' => __( 'Gisteren', 'tbmm' ) ),
			'last_7_days'    => array( 'start' => gmdate( 'Y-m-d', strtotime( '-7 days' ) ),    'end' => $end, 'label' => __( 'Laatste 7 dagen', 'tbmm' ) ),
			'last_30_days'   => array( 'start' => gmdate( 'Y-m-d', strtotime( '-30 days' ) ),   'end' => $end, 'label' => __( 'Laatste 30 dagen', 'tbmm' ) ),
			'this_year'      => array( 'start' => gmdate( 'Y' ) . '-01-01',                     'end' => $end, 'label' => __( 'Dit jaar', 'tbmm' ) ),
			'last_12_months' => array( 'start' => gmdate( 'Y-m-d', strtotime( '-12 months' ) ), 'end' => $end, 'label' => __( 'Laatste 12 maanden', 'tbmm' ) ),
		);

		$selected_period = sanitize_key( $_GET['gsc_period'] ?? 'last_30_days' );
		if ( ! isset( $periods[ $selected_period ] ) ) {
			$selected_period = 'last_30_days';
		}

		$start_date   = $periods[ $selected_period ]['start'];
		$end_date     = $periods[ $selected_period ]['end'];
		$period_label = $periods[ $selected_period ]['label'];

		$result    = $this->bridge->get_gsc_top_pages( $start_date, $end_date, 10 );
		$clear_url = add_query_arg( 'gsc_period', $selected_period, $this->make_clear_url( 'search_console' ) );
		?>
		<div style="display:flex; align-items:center; gap:16px; margin-bottom:8px;">
			<h3 style="margin:0;">
				<?php
				/* translators: %s = period label (e.g. "Laatste 30 dagen") */
				printf( esc_html__( 'Top pagina\'s — %s', 'tbmm' ), esc_html( $period_label ) );
				?>
			</h3>
			<a href="<?php echo esc_url( $clear_url ); ?>" class="button button-small">↻ <?php esc_html_e( 'Cache wissen', 'tbmm' ); ?></a>
		</div>

		<div style="margin-bottom:16px; display:flex; gap:4px; flex-wrap:wrap;">
			<?php foreach ( $periods as $slug => $info ) : ?>
				<?php $active = $slug === $selected_period; ?>
				<a href="<?php echo esc_url( $base_url . '&gsc_period=' . $slug ); ?>"
				   class="button button-small"
				   style="<?php echo $active ? 'font-weight:600; background:#2271b1; color:#fff; border-color:#2271b1;' : ''; ?>">
					<?php echo esc_html( $info['label'] ); ?>
				</a>
			<?php endforeach; ?>
		</div>

		<?php if ( is_wp_error( $result ) ) : ?>
			<div class="notice notice-error inline"><p><strong><?php esc_html_e( 'Fout:', 'tbmm' ); ?></strong> <?php echo esc_html( $result->get_error_message() ); ?></p></div>
		<?php elseif ( empty( $result ) ) : ?>
			<p><?php esc_html_e( 'Geen data beschikbaar. Mogelijk heeft Search Console nog geen data voor deze periode.', 'tbmm' ); ?></p>
		<?php else : ?>
			<table class="wp-list-table widefat fixed striped" style="table-layout:auto;">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Pagina', 'tbmm' ); ?></th>
						<th style="width:80px; text-align:right;"><?php esc_html_e( 'Klikken', 'tbmm' ); ?></th>
						<th style="width:100px; text-align:right;"><?php esc_html_e( 'Vertoningen', 'tbmm' ); ?></th>
						<th style="width:70px; text-align:right;"><?php esc_html_e( 'CTR', 'tbmm' ); ?></th>
						<th style="width:80px; text-align:right;"><?php esc_html_e( 'Positie', 'tbmm' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $result as $row ) : ?>
						<?php
						$page        = $this->gsc_extract_page( $row );
						$clicks      = $this->gsc_get( $row, 'getClicks', 'clicks' );
						$impressions = $this->gsc_get( $row, 'getImpressions', 'impressions' );
						$ctr         = $this->gsc_get( $row, 'getCtr', 'ctr' );
						$position    = $this->gsc_get( $row, 'getPosition', 'position' );
						$display     = $page ? preg_replace( '#^https?://[^/]+#', '', $page ) : '—';
						?>
						<tr>
							<td>
								<?php if ( $page ) : ?>
									<a href="<?php echo esc_url( $page ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $display ?: '/' ); ?></a>
								<?php else : ?>
									—
								<?php endif; ?>
							</td>
							<td style="text-align:right;"><?php echo esc_html( number_format( (int) $clicks ) ); ?></td>
							<td style="text-align:right;"><?php echo esc_html( number_format( (int) $impressions ) ); ?></td>
							<td style="text-align:right;"><?php echo esc_html( round( (float) $ctr * 100, 1 ) . '%' ); ?></td>
							<td style="text-align:right;"><?php echo esc_html( round( (float) $position, 1 ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p style="color:#646970; font-size:12px; margin-top:8px;"><?php esc_html_e( 'Gecached voor 6 uur.', 'tbmm' ); ?></p>
		<?php endif; ?>
		<?php
	}

	// -------------------------------------------------------------------------
	// AdSense subtab
	// -------------------------------------------------------------------------

	private function render_adsense_subtab(): void {
		if ( ! $this->bridge->is_adsense_connected() ) {
			echo '<div class="notice notice-warning inline"><p>';
			echo '<strong>' . esc_html__( 'AdSense is niet verbonden in Site Kit.', 'tbmm' ) . '</strong> ';
			/* translators: %s = link to Site Kit dashboard */
			printf(
				__( 'Ga naar %s en activeer de AdSense module.', 'tbmm' ),
				'<a href="' . esc_url( admin_url( 'admin.php?page=googlesitekit-dashboard' ) ) . '">' . esc_html__( 'Site Kit → Dashboard', 'tbmm' ) . '</a>'
			);
			echo '</p></div>';
			return;
		}

		$daily     = $this->bridge->get_adsense_daily_earnings();
		$clear_url = $this->make_clear_url( 'adsense' );

		if ( is_wp_error( $daily ) ) {
			echo '<div class="notice notice-error inline"><p><strong>' . esc_html__( 'Fout bij ophalen AdSense data:', 'tbmm' ) . '</strong> ' . esc_html( $daily->get_error_message() ) . '</p></div>';
			return;
		}

		$today_key     = gmdate( 'Y-m-d' );
		$yesterday_key = gmdate( 'Y-m-d', strtotime( '-1 day' ) );
		$week_ago_key  = gmdate( 'Y-m-d', strtotime( '-7 days' ) );
		$month_start   = gmdate( 'Y-m-01' );

		$today_earnings     = $daily[ $today_key ] ?? null;
		$yesterday_earnings = $daily[ $yesterday_key ] ?? null;
		$week_ago_earnings  = $daily[ $week_ago_key ] ?? null;
		$last7              = $this->sum_range( $daily, strtotime( '-7 days' ), strtotime( '-1 day' ) );
		$prev7              = $this->sum_range( $daily, strtotime( '-14 days' ), strtotime( '-8 days' ) );
		$month              = $this->sum_range( $daily, strtotime( $month_start ), strtotime( 'today' ) );
		?>

		<?php $this->render_metric_styles(); ?>

		<div style="display:flex; align-items:center; gap:16px; margin-bottom:16px;">
			<h3 style="margin:0;"><?php esc_html_e( 'Geschatte inkomsten', 'tbmm' ); ?></h3>
			<a href="<?php echo esc_url( $clear_url ); ?>" class="button button-small">↻ <?php esc_html_e( 'Cache wissen', 'tbmm' ); ?></a>
		</div>

		<div class="tbmm-metrics-row">
			<?php if ( $today_earnings !== null ) : ?>
				<?php $this->render_metric_box( __( 'Vandaag', 'tbmm' ), $this->fmt_eur( $today_earnings ) ); ?>
			<?php endif; ?>

			<?php
			$yday_delta = null;
			if ( $yesterday_earnings !== null && $week_ago_earnings !== null && $week_ago_earnings > 0 ) {
				$yday_delta = array( $yesterday_earnings - $week_ago_earnings, __( 'vs zelfde dag vorige week', 'tbmm' ) );
				$yday_delta[2] = round( $yday_delta[0] / $week_ago_earnings * 100 );
			}
			$this->render_metric_box(
				__( 'Gisteren', 'tbmm' ),
				$yesterday_earnings !== null ? $this->fmt_eur( $yesterday_earnings ) : '—',
				$yday_delta
			);
			?>

			<?php
			$week_delta = null;
			if ( $prev7 > 0 ) {
				$week_delta = array( $last7 - $prev7, __( 'vs vorige 7 dagen', 'tbmm' ) );
				$week_delta[2] = round( $week_delta[0] / $prev7 * 100 );
			}
			$this->render_metric_box( __( 'Laatste 7 dagen', 'tbmm' ), $this->fmt_eur( $last7 ), $week_delta );
			?>

			<?php $this->render_metric_box( __( 'Deze maand', 'tbmm' ), $this->fmt_eur( $month ) ); ?>
		</div>

		<p style="color:#646970; font-size:12px;"><?php esc_html_e( 'Gecached voor 6 uur. Bedragen zijn schattingen van Google AdSense.', 'tbmm' ); ?></p>
		<?php
	}

	/**
	 * Renders a single metric box.
	 *
	 * @param string     $label  Box title.
	 * @param string     $value  Formatted primary value.
	 * @param array|null $delta  Optional: [ float $diff, string $vs_label, int $pct ].
	 */
	private function render_metric_box( string $label, string $value, ?array $delta = null ): void {
		$trend_class = '';
		if ( $delta !== null ) {
			$trend_class = $delta[0] >= 0 ? 'tbmm-trend-up' : 'tbmm-trend-down';
		}
		?>
		<div class="tbmm-metric-box <?php echo esc_attr( $trend_class ); ?>">
			<div class="tbmm-metric-label"><?php echo esc_html( $label ); ?></div>
			<div class="tbmm-metric-value"><?php echo esc_html( $value ); ?></div>
			<?php if ( $delta !== null ) : ?>
				<?php
				[ $diff, $vs, $pct ] = $delta;
				$up   = $diff >= 0;
				$sign = $up ? '+' : '';
				?>
				<div class="tbmm-metric-trend <?php echo $up ? 'up' : 'down'; ?>">
					<span class="tbmm-trend-arrow"><?php echo $up ? '▲' : '▼'; ?></span>
					<span class="tbmm-trend-pct"><?php echo esc_html( $sign . $pct . '%' ); ?></span>
				</div>
				<div class="tbmm-metric-vs"><?php echo esc_html( $vs ); ?></div>
			<?php endif; ?>
		</div>
		<?php
	}

	private function render_metric_styles(): void {
		?>
		<style>
			.tbmm-metrics-row { display:flex; flex-wrap:wrap; gap:14px; margin-bottom:20px; }
			.tbmm-metric-box {
				border:1px solid #ccd0d4; border-bottom-width:3px;
				padding:14px 16px; min-width:130px; flex:1;
				background:#fff; box-shadow:0 1px 1px rgba(0,0,0,.04);
				text-align:center; border-bottom-color:#ccd0d4;
			}
			.tbmm-metric-box.tbmm-trend-up   { border-bottom-color:#00a32a; }
			.tbmm-metric-box.tbmm-trend-down  { border-bottom-color:#b32d2e; }
			.tbmm-metric-label { font-size:12px; color:#646970; margin-bottom:6px; }
			.tbmm-metric-value { font-size:1.6em; font-weight:600; line-height:1.2; color:#1d2327; }
			.tbmm-metric-trend {
				margin-top:8px; font-size:13px; font-weight:600;
				display:flex; align-items:center; justify-content:center; gap:3px;
			}
			.tbmm-metric-trend.up   { color:#00a32a; }
			.tbmm-metric-trend.down { color:#b32d2e; }
			.tbmm-trend-arrow { font-size:11px; }
			.tbmm-trend-pct   { font-size:15px; }
			.tbmm-metric-vs   { font-size:11px; color:#949494; margin-top:2px; }
		</style>
		<?php
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private function sum_range( array $daily, int $from_ts, int $to_ts ): float {
		$total = 0.0;
		for ( $ts = $from_ts; $ts <= $to_ts; $ts += DAY_IN_SECONDS ) {
			$key    = gmdate( 'Y-m-d', $ts );
			$total += $daily[ $key ] ?? 0.0;
		}
		return $total;
	}

	private function fmt_eur( float $amount ): string {
		$sign = $amount < 0 ? '-' : '';
		return $sign . '€' . number_format( abs( $amount ), 2, ',', '.' );
	}

	private function make_clear_url( string $subtab ): string {
		return wp_nonce_url(
			admin_url( 'admin-post.php?action=tbmm_google_clear_cache&subtab=' . $subtab ),
			'tbmm_google_clear_cache'
		);
	}

	private function gsc_extract_page( $row ): string {
		if ( is_object( $row ) && method_exists( $row, 'getKeys' ) ) {
			$keys = $row->getKeys();
			return is_array( $keys ) && isset( $keys[0] ) ? (string) $keys[0] : '';
		}
		if ( is_array( $row ) ) {
			return (string) ( $row['keys'][0] ?? $row['page'] ?? '' );
		}
		return '';
	}

	private function gsc_get( $row, string $getter, string $key ) {
		if ( is_object( $row ) && method_exists( $row, $getter ) ) {
			return $row->$getter();
		}
		return is_array( $row ) ? ( $row[ $key ] ?? 0 ) : 0;
	}
}
