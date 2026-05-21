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
		$subtab = sanitize_key( $_GET['subtab'] ?? '' );
		if ( $subtab === 'adsense' ) {
			$this->bridge->clear_adsense_cache();
		} elseif ( $subtab === 'search_console' ) {
			$this->bridge->clear_gsc_cache();
		} else {
			$this->bridge->clear_all_cache();
		}
		wp_safe_redirect( admin_url( 'admin.php?page=tb-money-manager&tab=google&subtab=' . $subtab . '&cache_cleared=1' ) );
		exit;
	}

	public function render(): void {
		$base_url = admin_url( 'admin.php?page=tb-money-manager&tab=google' );
		$subtab   = sanitize_key( $_GET['subtab'] ?? 'search_console' );

		$subtabs = array(
			'search_console' => 'Search Console',
			'adsense'        => 'AdSense',
		);
		?>
		<div style="max-width:960px;">
			<h2 style="margin-bottom:16px;">Google</h2>

			<?php if ( isset( $_GET['cache_cleared'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p>Cache gewist.</p></div>
			<?php endif; ?>

			<?php if ( ! $this->bridge->is_available() ) : ?>
				<div class="notice notice-warning inline">
					<p>
						<strong>Google Site Kit is niet actief.</strong>
						Installeer en activeer de <a href="<?php echo esc_url( admin_url( 'plugin-install.php?s=google-site-kit&tab=search&type=term' ) ); ?>">Google Site Kit plugin</a> om Google-data te tonen.
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
			echo '<strong>Search Console is niet verbonden in Site Kit.</strong> ';
			echo 'Ga naar <a href="' . esc_url( admin_url( 'admin.php?page=googlesitekit-dashboard' ) ) . '">Site Kit → Dashboard</a> en activeer de Search Console module.';
			echo '</p></div>';
			return;
		}

		$result    = $this->bridge->get_gsc_top_pages( 10 );
		$clear_url = $this->make_clear_url( 'search_console' );
		?>
		<div style="display:flex; align-items:center; gap:16px; margin-bottom:16px;">
			<h3 style="margin:0;">Top pagina's — afgelopen 30 dagen</h3>
			<a href="<?php echo esc_url( $clear_url ); ?>" class="button button-small">↻ Cache wissen</a>
		</div>

		<?php if ( is_wp_error( $result ) ) : ?>
			<div class="notice notice-error inline"><p><strong>Fout:</strong> <?php echo esc_html( $result->get_error_message() ); ?></p></div>
		<?php elseif ( empty( $result ) ) : ?>
			<p>Geen data beschikbaar. Mogelijk heeft Search Console nog geen data voor deze periode.</p>
		<?php else : ?>
			<table class="wp-list-table widefat fixed striped" style="table-layout:auto;">
				<thead>
					<tr>
						<th>Pagina</th>
						<th style="width:80px; text-align:right;">Klikken</th>
						<th style="width:100px; text-align:right;">Vertoningen</th>
						<th style="width:70px; text-align:right;">CTR</th>
						<th style="width:80px; text-align:right;">Positie</th>
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
			<p style="color:#646970; font-size:12px; margin-top:8px;">Gecached voor 6 uur.</p>
		<?php endif; ?>
		<?php
	}

	// -------------------------------------------------------------------------
	// AdSense subtab
	// -------------------------------------------------------------------------

	private function render_adsense_subtab(): void {
		if ( ! $this->bridge->is_adsense_connected() ) {
			echo '<div class="notice notice-warning inline"><p>';
			echo '<strong>AdSense is niet verbonden in Site Kit.</strong> ';
			echo 'Ga naar <a href="' . esc_url( admin_url( 'admin.php?page=googlesitekit-dashboard' ) ) . '">Site Kit → Dashboard</a> en activeer de AdSense module.';
			echo '</p></div>';
			return;
		}

		$daily     = $this->bridge->get_adsense_daily_earnings();
		$clear_url = $this->make_clear_url( 'adsense' );

		if ( is_wp_error( $daily ) ) {
			echo '<div class="notice notice-error inline"><p><strong>Fout bij ophalen AdSense data:</strong> ' . esc_html( $daily->get_error_message() ) . '</p></div>';
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

		<style>
			.tbmm-google-metrics { display:flex; flex-wrap:wrap; gap:16px; margin-bottom:20px; }
			.tbmm-google-metric-box {
				border:1px solid #ccd0d4; padding:15px; min-width:140px;
				background:#fff; box-shadow:0 1px 1px rgba(0,0,0,.04); text-align:center;
			}
			.tbmm-google-metric-box h4 { margin:0 0 8px; font-size:13px; color:#646970; font-weight:400; }
			.tbmm-google-metric-box .tbmm-metric-val { font-size:1.7em; font-weight:600; margin:4px 0; }
			.tbmm-google-metric-box .tbmm-metric-delta { font-size:12px; color:#646970; margin-top:4px; }
			.tbmm-google-metric-box .tbmm-delta-up   { color:#00a32a; }
			.tbmm-google-metric-box .tbmm-delta-down { color:#b32d2e; }
		</style>

		<div style="display:flex; align-items:center; gap:16px; margin-bottom:16px;">
			<h3 style="margin:0;">Geschatte inkomsten</h3>
			<a href="<?php echo esc_url( $clear_url ); ?>" class="button button-small">↻ Cache wissen</a>
		</div>

		<div class="tbmm-google-metrics">

			<?php if ( $today_earnings !== null ) : ?>
			<div class="tbmm-google-metric-box">
				<h4>Vandaag</h4>
				<div class="tbmm-metric-val"><?php echo esc_html( $this->fmt_eur( $today_earnings ) ); ?></div>
			</div>
			<?php endif; ?>

			<div class="tbmm-google-metric-box">
				<h4>Gisteren</h4>
				<div class="tbmm-metric-val"><?php echo esc_html( $yesterday_earnings !== null ? $this->fmt_eur( $yesterday_earnings ) : '—' ); ?></div>
				<?php if ( $yesterday_earnings !== null && $week_ago_earnings !== null && $week_ago_earnings > 0 ) : ?>
					<?php $delta = $yesterday_earnings - $week_ago_earnings; $pct = round( $delta / $week_ago_earnings * 100 ); ?>
					<div class="tbmm-metric-delta <?php echo $delta >= 0 ? 'tbmm-delta-up' : 'tbmm-delta-down'; ?>">
						<?php echo esc_html( ( $delta >= 0 ? '▲ +' : '▼ ' ) . $pct . '%' ); ?> vs zelfde dag vorige week
					</div>
				<?php endif; ?>
			</div>

			<div class="tbmm-google-metric-box">
				<h4>Laatste 7 dagen</h4>
				<div class="tbmm-metric-val"><?php echo esc_html( $this->fmt_eur( $last7 ) ); ?></div>
				<?php if ( $prev7 > 0 ) : ?>
					<?php $delta = $last7 - $prev7; $pct = round( $delta / $prev7 * 100 ); ?>
					<div class="tbmm-metric-delta <?php echo $delta >= 0 ? 'tbmm-delta-up' : 'tbmm-delta-down'; ?>">
						<?php echo esc_html( ( $delta >= 0 ? '▲ +' : '▼ ' ) . $pct . '%' ); ?> vs vorige 7 dagen
					</div>
				<?php endif; ?>
			</div>

			<div class="tbmm-google-metric-box">
				<h4>Deze maand</h4>
				<div class="tbmm-metric-val"><?php echo esc_html( $this->fmt_eur( $month ) ); ?></div>
			</div>

		</div>

		<p style="color:#646970; font-size:12px;">Gecached voor 6 uur. Bedragen zijn schattingen van Google AdSense.</p>
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
