<?php

namespace TuinenBalkon\TBMoneyManager\Admin;

use TuinenBalkon\TBMoneyManager\Service\LinkScanner;

class ScanPage {

	private LinkScanner $link_scanner;
	private TradeTrackerTab $tt_tab;
	private TATab $ta_tab;
	private BolTab $bol_tab;

	public function __construct( LinkScanner $link_scanner, TradeTrackerTab $tt_tab, TATab $ta_tab, BolTab $bol_tab ) {
		$this->link_scanner = $link_scanner;
		$this->tt_tab       = $tt_tab;
		$this->ta_tab       = $ta_tab;
		$this->bol_tab      = $bol_tab;
	}

	public function render(): void {
		// Handle manual update check request — gebruik JS redirect want headers zijn al verstuurd
		$update_notice    = '';
		$update_redirect  = '';
		if ( isset( $_GET['tbmm_check_updates'] ) && check_admin_referer( 'tbmm_check_updates' ) ) {
			delete_transient( 'tbmm_github_update' );
			delete_site_transient( 'update_plugins' );
			// Forceer directe update-check zodat onze filter de verse GitHub-data toevoegt
			// vóór de redirect — anders wacht WordPress op z'n eigen cron/admin_init timing.
			if ( ! function_exists( 'wp_update_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/update.php';
			}
			wp_update_plugins();
			$update_redirect = esc_url( remove_query_arg( [ 'tbmm_check_updates', '_wpnonce' ] ) );
			$update_notice   = '<div class="notice notice-success"><p>Cache gewist. WordPress controleert nu op updates.</p></div>';
		}

		$current_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'scanner';
		$page_url    = admin_url( 'admin.php?page=tb-money-manager' );

		$tabs = [
			'scanner'      => 'Link Scanner',
			'tradetracker' => 'TradeTracker',
			'ta'           => 'ThirstyAffiliates',
			'bol'          => 'Bol.com',
		];

		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$plugin_data    = get_plugin_data( TBMM_FILE );
		$current_ver    = $plugin_data['Version'] ?? '?';
		$check_url      = wp_nonce_url(
			add_query_arg( 'tbmm_check_updates', '1', $page_url . ( $current_tab !== 'scanner' ? '&tab=' . $current_tab : '' ) ),
			'tbmm_check_updates'
		);
		?>
		<div class="wrap">
			<h1 style="display:flex; align-items:center; gap:16px;">
				TB Money Manager
				<span style="font-size:13px; font-weight:400; color:#646970;">v<?php echo esc_html( $current_ver ); ?></span>
				<a href="<?php echo esc_url( $check_url ); ?>"
				   class="button button-small"
				   style="font-size:12px; margin-top:2px;">
					↻ Controleer op updates
				</a>
			</h1>

			<?php echo wp_kses_post( $update_notice ); ?>
			<?php if ( $update_redirect ) : ?>
			<script>setTimeout(function(){ window.location.href = <?php echo wp_json_encode( $update_redirect ); ?>; }, 1200);</script>
			<?php endif; ?>

			<nav class="nav-tab-wrapper" style="margin-bottom:20px;">
				<?php foreach ( $tabs as $slug => $label ) : ?>
				<a href="<?php echo esc_url( $page_url . '&tab=' . $slug ); ?>"
				   class="nav-tab <?php echo $current_tab === $slug ? 'nav-tab-active' : ''; ?>">
					<?php echo esc_html( $label ); ?>
				</a>
				<?php endforeach; ?>
			</nav>

			<?php if ( $current_tab === 'tradetracker' ) : ?>
				<?php $this->tt_tab->render(); ?>
			<?php elseif ( $current_tab === 'ta' ) : ?>
				<?php $this->ta_tab->render(); ?>
			<?php elseif ( $current_tab === 'bol' ) : ?>
				<?php $this->bol_tab->render(); ?>
			<?php else : ?>
				<?php $this->render_scanner(); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	private function render_scanner(): void {
		$stats = $this->link_scanner->get_stats();
		$nonce = wp_create_nonce( 'tbmm_run_scan_nonce' );
		?>
		<div class="alc-stats-box">
			<div class="alc-stats-numbers">
				<div class="alc-stat">
					<span class="alc-stat-number"><?php echo esc_html( $stats['total'] ); ?></span>
					<span class="alc-stat-label">Totaal links</span>
				</div>
				<div class="alc-stat alc-stat-skip">
					<span class="alc-stat-number"><?php echo esc_html( $stats['bol_count'] ); ?></span>
					<span class="alc-stat-label">Bol.com (overgeslagen)</span>
				</div>
				<div class="alc-stat alc-stat-scan">
					<span class="alc-stat-number"><?php echo esc_html( $stats['scan_count'] ); ?></span>
					<span class="alc-stat-label">Te scannen</span>
				</div>
			</div>

			<?php if ( ! empty( $stats['domains'] ) ) : ?>
			<table class="alc-domain-table">
				<thead>
					<tr><th>Domein</th><th>Links</th></tr>
				</thead>
				<tbody>
					<?php foreach ( $stats['domains'] as $domain => $count ) : ?>
					<tr class="<?php echo strpos( $domain, 'overgeslagen' ) !== false ? 'alc-domain-skip' : ''; ?>">
						<td><?php echo esc_html( $domain ); ?></td>
						<td><?php echo esc_html( $count ); ?></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php endif; ?>
		</div>

		<?php if ( $stats['scan_count'] > 0 ) : ?>
		<button id="alc-start-scan" class="button button-primary" style="margin-top:16px;">
			Start Scan (<?php echo esc_html( $stats['scan_count'] ); ?> links)
		</button>
		<?php else : ?>
		<p><em>Geen links te scannen.</em></p>
		<?php endif; ?>

		<div id="alc-progress" style="display:none; margin-top:20px;">
			<div class="alc-progress-bar-wrap">
				<div id="alc-progress-bar" class="alc-progress-bar"></div>
			</div>
			<p id="alc-progress-label"></p>
		</div>

		<div id="alc-results" style="margin-top:20px;"></div>

		<style>
			.alc-stats-box { background:#fff; border:1px solid #ccd0d4; border-radius:4px; padding:16px 20px; max-width:680px; }
			.alc-stats-numbers { display:flex; gap:32px; margin-bottom:16px; }
			.alc-stat { display:flex; flex-direction:column; }
			.alc-stat-number { font-size:28px; font-weight:700; line-height:1; }
			.alc-stat-label { font-size:12px; color:#646970; margin-top:4px; }
			.alc-stat-skip .alc-stat-number { color:#646970; }
			.alc-stat-scan .alc-stat-number { color:#2271b1; }

			.alc-domain-table { border-collapse:collapse; width:100%; }
			.alc-domain-table th, .alc-domain-table td { padding:5px 10px; border:1px solid #e0e0e0; font-size:13px; }
			.alc-domain-table th { background:#f6f7f7; font-weight:600; }
			.alc-domain-skip td { color:#999; font-style:italic; }

			.alc-progress-bar-wrap { background:#e0e0e0; border-radius:4px; height:10px; width:100%; max-width:500px; overflow:hidden; }
			.alc-progress-bar { background:#2271b1; height:100%; width:0%; transition:width 0.2s; border-radius:4px; }
			#alc-progress-label { color:#646970; font-size:13px; margin-top:6px; }

			#alc-results table { border-collapse:collapse; width:100%; margin-top:10px; }
			#alc-results th, #alc-results td { padding:8px 12px; border:1px solid #ccd0d4; text-align:left; vertical-align:top; }
			#alc-results th { background:#f0f0f1; font-weight:600; }
			#alc-results tr:nth-child(even) { background:#f9f9f9; }
			.alc-status-404 { color:#d63638; font-weight:bold; }
			.alc-status-5xx { color:#996800; font-weight:bold; }
			.alc-status-0   { color:#888; font-weight:bold; }
			.alc-post-list  { margin:0; padding:0; list-style:none; }
			.alc-post-list li { margin-bottom:2px; }
		</style>

		<script>
		(function() {
			var links   = <?php echo wp_json_encode( $stats['scan_links'] ); ?>;
			var nonce   = <?php echo wp_json_encode( $nonce ); ?>;
			var total   = links.length;

			var btn      = document.getElementById('alc-start-scan');
			var progress = document.getElementById('alc-progress');
			var bar      = document.getElementById('alc-progress-bar');
			var label    = document.getElementById('alc-progress-label');
			var results  = document.getElementById('alc-results');

			var tableBody   = null;
			var brokenCount = 0;

			if (!btn) return;

			btn.addEventListener('click', function() {
				btn.disabled = true;
				brokenCount  = 0;
				tableBody    = null;
				results.innerHTML = '';
				progress.style.display = 'block';
				bar.style.width = '0%';
				label.textContent = 'Voorbereiden…';

				scanNext(0);
			});

			function scanNext(index) {
				if (index >= total) {
					bar.style.width = '100%';
					label.textContent = 'Klaar. ' + brokenCount + ' gebroken link(s) gevonden.';
					btn.disabled = false;
					if (brokenCount === 0) {
						results.innerHTML = '<div class="notice notice-success inline"><p>Geen gebroken links gevonden.</p></div>';
					}
					return;
				}

				var link = links[index];
				var pct  = Math.round((index / total) * 100);
				bar.style.width = pct + '%';
				label.textContent = 'Link ' + (index + 1) + ' van ' + total + ' — ' + link.name;

				var data = new FormData();
				data.append('action',   'tbmm_check_link');
				data.append('nonce',    nonce);
				data.append('link_id',  link.id);
				data.append('link_url', link.url);

				fetch(ajaxurl, { method: 'POST', body: data })
					.then(function(r) { return r.json(); })
					.then(function(resp) {
						if (resp.success && resp.data.is_broken) {
							brokenCount++;
							appendBrokenRow(link, resp.data);
						}
						scanNext(index + 1);
					})
					.catch(function() {
						scanNext(index + 1);
					});
			}

			function appendBrokenRow(link, data) {
				if (!tableBody) {
					var header = document.createElement('p');
					header.innerHTML = '<strong>Gebroken links:</strong>';
					results.appendChild(header);

					var table = document.createElement('table');
					table.innerHTML = '<thead><tr>'
						+ '<th>Link naam</th>'
						+ '<th>Destination URL</th>'
						+ '<th>Status</th>'
						+ '<th>Gebruikt in</th>'
						+ '<th>Bewerk</th>'
						+ '</tr></thead><tbody></tbody>';
					results.appendChild(table);
					tableBody = table.querySelector('tbody');
				}

				var statusClass = data.status === 404 ? 'alc-status-404'
					: (data.status >= 500 ? 'alc-status-5xx' : 'alc-status-0');
				var statusLabel = data.status === 0 ? 'Verbinding mislukt' : data.status;

				var postsHtml = '—';
				if (data.posts && data.posts.length > 0) {
					postsHtml = '<ul class="alc-post-list">';
					data.posts.forEach(function(post) {
						postsHtml += '<li><a href="' + escHtml(post.edit_url) + '" target="_blank">'
							+ escHtml(post.title) + '</a></li>';
					});
					postsHtml += '</ul>';
				}

				var tr = document.createElement('tr');
				tr.innerHTML = '<td>' + escHtml(link.name) + '</td>'
					+ '<td><a href="' + escHtml(link.url) + '" target="_blank">' + escHtml(link.url) + '</a></td>'
					+ '<td><span class="' + statusClass + '">' + statusLabel + '</span></td>'
					+ '<td>' + postsHtml + '</td>'
					+ '<td><a href="' + escHtml(data.edit_url) + '" target="_blank" class="button button-small">Bewerk in TA</a></td>';
				tableBody.appendChild(tr);
			}

			function escHtml(str) {
				var d = document.createElement('div');
				d.appendChild(document.createTextNode(String(str)));
				return d.innerHTML;
			}
		})();
		</script>
		<?php
	}
}
