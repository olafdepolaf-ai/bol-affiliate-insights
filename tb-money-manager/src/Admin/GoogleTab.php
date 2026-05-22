<?php

namespace TuinenBalkon\TBMoneyManager\Admin;

use TuinenBalkon\TBMoneyManager\Google\SiteKitBridge;
use TuinenBalkon\TBMoneyManager\Service\ThirstyAffiliatesService;

class GoogleTab {

	private SiteKitBridge            $bridge;
	private ThirstyAffiliatesService $ta_service;

	public function __construct( SiteKitBridge $bridge, ThirstyAffiliatesService $ta_service ) {
		$this->bridge     = $bridge;
		$this->ta_service = $ta_service;
		add_action( 'admin_post_tbmm_google_clear_cache', array( $this, 'handle_clear_cache' ) );
	}

	public function handle_clear_cache(): void {
		check_admin_referer( 'tbmm_google_clear_cache' );
		$subtab     = sanitize_key( $_GET['subtab'] ?? '' );
		$gsc_period = sanitize_key( $_GET['gsc_period'] ?? '' );
		if ( $subtab === 'adsense' ) {
			$this->bridge->clear_adsense_cache();
		} elseif ( $subtab === 'search_console' || $subtab === 'kansen' ) {
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
			'kansen'         => __( 'Kansen', 'tbmm' ),
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

				<div class="tbmm-subnav-wrap">
				<nav class="tbmm-subnav">
					<?php foreach ( $subtabs as $slug => $label ) : ?>
						<a href="<?php echo esc_url( $base_url . '&subtab=' . $slug ); ?>"
						   class="<?php echo $subtab === $slug ? 'tbmm-subnav-active' : ''; ?>">
							<?php echo esc_html( $label ); ?>
						</a>
					<?php endforeach; ?>
				</nav>
				</div>

				<?php if ( $subtab === 'adsense' ) : ?>
					<?php $this->render_adsense_subtab(); ?>
				<?php elseif ( $subtab === 'kansen' ) : ?>
					<?php $this->render_kansen_subtab(); ?>
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
	// Kansen subtab — top GSC-pagina's gekoppeld aan affiliate link-dichtheid
	// -------------------------------------------------------------------------

	private function render_kansen_subtab(): void {
		if ( ! $this->bridge->is_search_console_connected() ) {
			echo '<div class="notice notice-warning inline"><p>';
			echo '<strong>' . esc_html__( 'Search Console is niet verbonden in Site Kit.', 'tbmm' ) . '</strong> ';
			printf(
				/* translators: %s = link to Site Kit dashboard */
				__( 'Ga naar %s en activeer de Search Console module.', 'tbmm' ),
				'<a href="' . esc_url( admin_url( 'admin.php?page=googlesitekit-dashboard' ) ) . '">' . esc_html__( 'Site Kit → Dashboard', 'tbmm' ) . '</a>'
			);
			echo '</p></div>';
			return;
		}

		$base_url = admin_url( 'admin.php?page=tb-money-manager&tab=google&subtab=kansen' );
		$end      = gmdate( 'Y-m-d', strtotime( '-1 day' ) );
		$periods  = array(
			'last_7_days'    => array( 'start' => gmdate( 'Y-m-d', strtotime( '-7 days' ) ),    'end' => $end, 'label' => __( 'Laatste 7 dagen', 'tbmm' ) ),
			'last_30_days'   => array( 'start' => gmdate( 'Y-m-d', strtotime( '-30 days' ) ),   'end' => $end, 'label' => __( 'Laatste 30 dagen', 'tbmm' ) ),
			'last_90_days'   => array( 'start' => gmdate( 'Y-m-d', strtotime( '-90 days' ) ),   'end' => $end, 'label' => __( 'Laatste 90 dagen', 'tbmm' ) ),
			'last_12_months' => array( 'start' => gmdate( 'Y-m-d', strtotime( '-12 months' ) ), 'end' => $end, 'label' => __( 'Laatste 12 maanden', 'tbmm' ) ),
		);

		$selected_period = sanitize_key( $_GET['gsc_period'] ?? 'last_30_days' );
		if ( ! isset( $periods[ $selected_period ] ) ) {
			$selected_period = 'last_30_days';
		}

		$start_date   = $periods[ $selected_period ]['start'];
		$end_date     = $periods[ $selected_period ]['end'];
		$period_label = $periods[ $selected_period ]['label'];

		$result    = $this->bridge->get_gsc_top_pages( $start_date, $end_date, 50 );
		$clear_url = add_query_arg( 'gsc_period', $selected_period, $this->make_clear_url( 'kansen' ) );
		?>

		<p style="font-size:13px; color:#3c434a; max-width:760px; margin-bottom:14px;">
			<?php
			echo wp_kses(
				__( 'Top 50 pagina\'s uit Search Console gekoppeld aan het aantal <strong>ThirstyAffiliates-links</strong> in het artikel. Pagina\'s met veel organisch verkeer maar weinig of geen affiliate links zijn kansen om meer commissie te verdienen.', 'tbmm' ),
				array( 'strong' => array() )
			);
			?>
		</p>

		<div style="display:flex; align-items:center; gap:16px; margin-bottom:10px;">
			<div style="display:flex; gap:4px; flex-wrap:wrap;">
				<?php foreach ( $periods as $slug => $info ) : ?>
					<a href="<?php echo esc_url( $base_url . '&gsc_period=' . $slug ); ?>"
					   class="button button-small"
					   style="<?php echo $slug === $selected_period ? 'font-weight:600; background:#2271b1; color:#fff; border-color:#2271b1;' : ''; ?>">
						<?php echo esc_html( $info['label'] ); ?>
					</a>
				<?php endforeach; ?>
			</div>
			<a href="<?php echo esc_url( $clear_url ); ?>" class="button button-small">↻ <?php esc_html_e( 'Cache wissen', 'tbmm' ); ?></a>
		</div>

		<?php if ( is_wp_error( $result ) ) : ?>
			<div class="notice notice-error inline"><p><strong><?php esc_html_e( 'Fout:', 'tbmm' ); ?></strong> <?php echo esc_html( $result->get_error_message() ); ?></p></div>
		<?php return; endif;

		if ( empty( $result ) ) : ?>
			<p><?php esc_html_e( 'Geen GSC-data beschikbaar voor deze periode.', 'tbmm' ); ?></p>
		<?php return; endif;

		// Resolve page URLs naar WP post IDs en laad TA link-tellingen in één batch.
		$page_rows = [];
		$post_ids  = [];
		foreach ( $result as $row ) {
			$url     = $this->gsc_extract_page( $row );
			$post_id = $url ? url_to_postid( $url ) : 0;
			$page_rows[] = array(
				'url'        => $url,
				'post_id'    => $post_id,
				'clicks'     => (int) $this->gsc_get( $row, 'getClicks', 'clicks' ),
				'impressions'=> (int) $this->gsc_get( $row, 'getImpressions', 'impressions' ),
				'position'   => round( (float) $this->gsc_get( $row, 'getPosition', 'position' ), 1 ),
			);
			if ( $post_id ) {
				$post_ids[] = $post_id;
			}
		}

		$ta_counts = $this->ta_service->count_ta_links_per_post( array_unique( $post_ids ) );
		?>

		<style>
			.tbmm-kansen-tbl { border-collapse:collapse; width:100%; }
			.tbmm-kansen-tbl th, .tbmm-kansen-tbl td { padding:8px 12px; border:1px solid #e0e0e0; font-size:13px; text-align:left; vertical-align:middle; }
			.tbmm-kansen-tbl th { background:#f6f7f7; font-weight:600; white-space:nowrap; }
			.tbmm-kansen-tbl td.num { text-align:right; font-variant-numeric:tabular-nums; }
			.tbmm-kansen-tbl tr:nth-child(even) { background:#fafafa; }
			.tbmm-link-dot { display:inline-block; width:10px; height:10px; border-radius:50%; margin-right:5px; vertical-align:middle; }
			.tbmm-link-red    { background:#d63638; }
			.tbmm-link-orange { background:#dba617; }
			.tbmm-link-green  { background:#00a32a; }
			.tbmm-link-count  { font-weight:700; font-size:14px; }
			.tbmm-no-post { color:#999; font-size:12px; }
		</style>

		<table class="tbmm-kansen-tbl">
			<thead>
				<tr>
					<th style="width:32px;">#</th>
					<th><?php esc_html_e( 'Pagina', 'tbmm' ); ?></th>
					<th style="width:80px;" class="num"><?php esc_html_e( 'Klikken', 'tbmm' ); ?></th>
					<th style="width:100px;" class="num"><?php esc_html_e( 'Vertoningen', 'tbmm' ); ?></th>
					<th style="width:80px;" class="num"><?php esc_html_e( 'Positie', 'tbmm' ); ?></th>
					<th style="width:100px; text-align:center;"><?php esc_html_e( 'Aff. links', 'tbmm' ); ?></th>
					<th style="width:80px;"></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $page_rows as $i => $r ) :
				$post_id  = $r['post_id'];
				$ta_count = isset( $ta_counts[ $post_id ] ) ? $ta_counts[ $post_id ] : ( $post_id ? 0 : null );
				$display  = $r['url'] ? preg_replace( '#^https?://[^/]+#', '', $r['url'] ) : '—';

				if ( $ta_count === null ) {
					$dot_class  = '';
					$count_html = '<span class="tbmm-no-post">—</span>';
				} elseif ( $ta_count === 0 ) {
					$dot_class  = 'tbmm-link-red';
					$count_html = '<span class="tbmm-link-count" style="color:#d63638;">0</span>';
				} elseif ( $ta_count <= 2 ) {
					$dot_class  = 'tbmm-link-orange';
					$count_html = '<span class="tbmm-link-count" style="color:#dba617;">' . $ta_count . '</span>';
				} else {
					$dot_class  = 'tbmm-link-green';
					$count_html = '<span class="tbmm-link-count" style="color:#00a32a;">' . $ta_count . '</span>';
				}

				$edit_url = $post_id ? admin_url( 'post.php?post=' . $post_id . '&action=edit' ) : '';
			?>
			<tr>
				<td style="color:#999; font-size:12px;"><?php echo esc_html( $i + 1 ); ?></td>
				<td>
					<?php if ( $dot_class ) : ?>
					<span class="tbmm-link-dot <?php echo esc_attr( $dot_class ); ?>"></span>
					<?php endif; ?>
					<?php if ( $r['url'] ) : ?>
					<a href="<?php echo esc_url( $r['url'] ); ?>" target="_blank" rel="noopener" style="text-decoration:none;">
						<?php echo esc_html( $display ?: '/' ); ?>
					</a>
					<?php else : ?>
					<span style="color:#999;">—</span>
					<?php endif; ?>
				</td>
				<td class="num"><?php echo esc_html( number_format_i18n( $r['clicks'] ) ); ?></td>
				<td class="num"><?php echo esc_html( number_format_i18n( $r['impressions'] ) ); ?></td>
				<td class="num"><?php echo esc_html( $r['position'] ); ?></td>
				<td style="text-align:center;"><?php echo $count_html; // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
				<td style="text-align:center;">
					<?php if ( $edit_url ) : ?>
					<a href="<?php echo esc_url( $edit_url ); ?>" target="_blank" class="button button-small" title="<?php esc_attr_e( 'Bewerk artikel', 'tbmm' ); ?>">✎</a>
					<?php endif; ?>
				</td>
			</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<div style="margin-top:12px; font-size:12px; color:#646970; display:flex; gap:20px;">
			<span><span class="tbmm-link-dot tbmm-link-red" style="display:inline-block;"></span> <?php esc_html_e( '0 links — kans', 'tbmm' ); ?></span>
			<span><span class="tbmm-link-dot tbmm-link-orange" style="display:inline-block;"></span> <?php esc_html_e( '1–2 links — kan beter', 'tbmm' ); ?></span>
			<span><span class="tbmm-link-dot tbmm-link-green" style="display:inline-block;"></span> <?php esc_html_e( '3+ links — goed', 'tbmm' ); ?></span>
			<span style="color:#bbb;">— <?php esc_html_e( 'geen artikel gevonden', 'tbmm' ); ?></span>
		</div>
		<p style="font-size:12px; color:#646970; margin-top:6px;"><?php esc_html_e( 'GSC-data gecached voor 6 uur. TA link-tellingen zijn realtime.', 'tbmm' ); ?></p>
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
