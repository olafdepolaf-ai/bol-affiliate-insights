<?php

namespace TuinenBalkon\TBMoneyManager\Admin;

use TuinenBalkon\TBMoneyManager\Service\LinkScanner;
use TuinenBalkon\TBMoneyManager\Service\OrphanedLinkScanner;
use TuinenBalkon\TBMoneyManager\Service\ScanCacheService;
use TuinenBalkon\TBMoneyManager\Service\ThirstyAffiliatesService;
use TuinenBalkon\TBMoneyManager\Service\UnmanagedLinkScanner;

class TATab {

	private ThirstyAffiliatesService $ta_service;
	private OrphanedLinkScanner      $orphaned_scanner;
	private ScanCacheService         $scan_cache;
	private LinkScanner              $link_scanner;
	private UnmanagedLinkScanner     $unmanaged_scanner;

	public function __construct(
		ThirstyAffiliatesService $ta_service,
		OrphanedLinkScanner $orphaned_scanner,
		ScanCacheService $scan_cache,
		LinkScanner $link_scanner,
		UnmanagedLinkScanner $unmanaged_scanner
	) {
		$this->ta_service        = $ta_service;
		$this->orphaned_scanner  = $orphaned_scanner;
		$this->scan_cache        = $scan_cache;
		$this->link_scanner      = $link_scanner;
		$this->unmanaged_scanner = $unmanaged_scanner;
	}

	public function render(): void {
		$subtabs = array(
			'aanbeveling-404' => __( 'Aanbeveling 404', 'tbmm' ),
			'orphaned-links'  => __( 'Orphaned Links', 'tbmm' ),
			'link-scanner'    => __( 'Link Scanner', 'tbmm' ),
			'unmanaged-links' => __( 'Unmanaged Links', 'tbmm' ),
		);

		$current_subtab = isset( $_GET['subtab'] ) ? sanitize_key( $_GET['subtab'] ) : 'aanbeveling-404';
		if ( ! array_key_exists( $current_subtab, $subtabs ) ) {
			$current_subtab = 'aanbeveling-404';
		}

		$page_url = admin_url( 'admin.php?page=tb-money-manager&tab=ta' );
		?>
		<style>
			.alc-subtab-nav { display:flex; gap:0; border-bottom:1px solid #ccd0d4; margin-bottom:20px; }
			.alc-subtab-link { padding:8px 16px; text-decoration:none; color:#3c434a; border:1px solid transparent;
				border-bottom:none; border-radius:4px 4px 0 0; font-size:13px; }
			.alc-subtab-link:hover { color:#2271b1; background:#f0f0f1; }
			.alc-subtab-active { background:#fff; border-color:#ccd0d4; color:#1d2327; font-weight:600;
				margin-bottom:-1px; padding-bottom:9px; }

			.alc-ta-table { border-collapse:collapse; width:100%; margin-top:4px; }
			.alc-ta-table th, .alc-ta-table td { padding:8px 12px; border:1px solid #e0e0e0; font-size:13px;
				text-align:left; vertical-align:middle; }
			.alc-ta-table th { background:#f6f7f7; font-weight:600; }
			.alc-ta-table tr:nth-child(even) { background:#fafafa; }
			.alc-ta-table td.alc-clicks-cell { text-align:right; font-variant-numeric:tabular-nums; }
			.alc-ta-table td.alc-rank-cell { text-align:center; color:#646970; width:40px; }
			.alc-ta-zero { color:#b0b0b0; }

			.alc-ta-bar-wrap { display:inline-block; background:#e8edf0; border-radius:3px;
				height:10px; width:80px; vertical-align:middle; margin-left:8px; overflow:hidden; }
			.alc-ta-bar { background:#2271b1; height:100%; border-radius:3px; }

			.alc-ta-pagination { display:flex; align-items:center; gap:6px; margin-top:14px; flex-wrap:wrap; }
			.alc-ta-pagination a, .alc-ta-pagination span { padding:5px 10px; border:1px solid #ccd0d4;
				border-radius:3px; font-size:13px; text-decoration:none; color:#2271b1; background:#fff; }
			.alc-ta-pagination .current-page { background:#2271b1; color:#fff; border-color:#2271b1; font-weight:700; }
			.alc-ta-pagination .dots { border:none; background:none; color:#646970; padding:5px 4px; }

			.alc-ta-summary { font-size:13px; color:#646970; margin-bottom:12px; }

			.alc-ta-no-table { background:#fff8e5; border-left:4px solid #f0b849; padding:12px 16px;
				font-size:13px; border-radius:0 4px 4px 0; margin-top:8px; }
		</style>

		<nav class="alc-subtab-nav">
			<?php foreach ( $subtabs as $slug => $label ) : ?>
			<a href="<?php echo esc_url( $page_url . '&subtab=' . $slug ); ?>"
			   class="alc-subtab-link <?php echo $current_subtab === $slug ? 'alc-subtab-active' : ''; ?>">
				<?php echo esc_html( $label ); ?>
			</a>
			<?php endforeach; ?>
		</nav>

		<?php
		if ( $current_subtab === 'orphaned-links' ) {
			$this->render_orphaned_links_subtab();
		} elseif ( $current_subtab === 'link-scanner' ) {
			$this->render_link_scanner_subtab();
		} elseif ( $current_subtab === 'unmanaged-links' ) {
			$this->render_unmanaged_links_subtab();
		} else {
			$this->render_aanbeveling_404_subtab();
		}
	}

	private function render_aanbeveling_404_subtab(): void {
		$allowed_periods = \TuinenBalkon\TBMoneyManager\Service\OrphanedLinkScanner::PERIODS;
		$selected_period = isset( $_GET['redir_period'] ) && array_key_exists( $_GET['redir_period'], $allowed_periods )
			? sanitize_key( $_GET['redir_period'] )
			: '7days';

		$redir_data = $this->orphaned_scanner->get_redirection_404s( $selected_period );
		$nonce      = wp_create_nonce( 'tbmm_orphan_nonce' );
		?>
		<style>
			.alc-articles-row td { padding:6px 12px !important; }
			.alc-articles-list { margin:4px 0 0; padding:0; list-style:none; }
			.alc-articles-list li { margin-bottom:3px; font-size:12px; }
		</style>

		<p style="font-size:13px; color:#3c434a; max-width:700px; margin-bottom:14px;">
			<?php
			printf(
				wp_kses(
					/* translators: 1: /aanbeveling/ code tag, 2: Redirection plugin, 3: DB table code tag */
					__( 'Toont %1$s-URLs die een <strong>404 hebben opgeleverd</strong> — bezoekers klikten op een link die niet werkte. De data komt uit de <strong>Redirection plugin</strong> (%2$s) en is <strong>realtime</strong> (geen cache). Klik op "Zoek in artikelen" om te zien welk artikel de gebroken link bevat, zodat je hem kunt herstellen in ThirstyAffiliates.', 'tbmm' ),
					array( 'strong' => array(), 'code' => array() )
				),
				'<code>/aanbeveling/</code>',
				'<code>' . esc_html( $GLOBALS['wpdb']->prefix . 'redirection_404' ) . '</code>'
			);
			?>
		</p>

		<form method="get" style="display:flex; align-items:center; gap:8px; margin-bottom:14px;">
			<input type="hidden" name="page"   value="tb-money-manager">
			<input type="hidden" name="tab"    value="ta">
			<input type="hidden" name="subtab" value="aanbeveling-404">
			<label for="alc-redir-period" style="font-size:13px; font-weight:600;"><?php esc_html_e( 'Periode:', 'tbmm' ); ?></label>
			<select id="alc-redir-period" name="redir_period" style="font-size:13px;">
				<?php foreach ( $allowed_periods as $key => $label ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $selected_period, $key ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
				<?php endforeach; ?>
			</select>
			<button type="submit" class="button"><?php esc_html_e( 'Toon', 'tbmm' ); ?></button>
		</form>

		<?php if ( $redir_data['table_missing'] ) : ?>
		<div class="notice notice-warning inline" style="margin:0;">
			<p>
				<?php
				/* translators: %s = database table name */
				printf(
					__( 'Redirection plugin niet actief of 404-logtabel (%s) niet gevonden.', 'tbmm' ),
					'<code>' . esc_html( $GLOBALS['wpdb']->prefix . 'redirection_404' ) . '</code>'
				);
				?>
			</p>
		</div>

		<?php elseif ( empty( $redir_data['items'] ) ) : ?>
		<p style="color:#646970; font-size:13px;"><?php esc_html_e( 'Geen /aanbeveling/ 404-hits gevonden voor deze periode.', 'tbmm' ); ?></p>

		<?php else : ?>
		<table class="alc-ta-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'URL', 'tbmm' ); ?></th>
					<th style="text-align:right; width:60px;"><?php esc_html_e( 'Hits', 'tbmm' ); ?></th>
					<th><?php esc_html_e( 'Laatste hit', 'tbmm' ); ?></th>
					<th style="width:160px;"><?php esc_html_e( 'Zoek in artikelen', 'tbmm' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $redir_data['items'] as $i => $row ) :
				$path = strtok( (string) $row->url, '?' );
				$slug = strtolower( rtrim( str_replace( '/aanbeveling/', '', $path ), '/' ) );
				$last_hit_fmt = $row->last_hit
					? date_i18n( 'd M Y H:i', strtotime( $row->last_hit ) )
					: '—';
			?>
			<tr>
				<td><code><?php echo esc_html( $row->url ); ?></code></td>
				<td style="text-align:right; font-weight:600;"><?php echo esc_html( number_format_i18n( (int) $row->hits ) ); ?></td>
				<td style="font-size:12px; color:#646970;"><?php echo esc_html( $last_hit_fmt ); ?></td>
				<td>
					<button class="button button-small alc-find-btn"
					        data-slug="<?php echo esc_attr( $slug ); ?>"
					        data-row="<?php echo esc_attr( $i ); ?>">
						<?php esc_html_e( 'Zoek in artikelen', 'tbmm' ); ?>
					</button>
				</td>
			</tr>
			<tr class="alc-articles-row" id="alc-ar-<?php echo esc_attr( $i ); ?>" style="display:none; background:#f9f9f9;">
				<td colspan="4"></td>
			</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php endif; ?>

		<script>
		(function() {
			var nonce = <?php echo wp_json_encode( $nonce ); ?>;

			document.querySelectorAll('.alc-find-btn').forEach(function(btn) {
				btn.addEventListener('click', function() {
					var slug  = btn.dataset.slug;
					var rowId = btn.dataset.row;
					var arRow = document.getElementById('alc-ar-' + rowId);
					var cell  = arRow ? arRow.querySelector('td') : null;

					if (!slug || !arRow || !cell) return;

					if (arRow.style.display !== 'none') {
						arRow.style.display = 'none';
						btn.textContent = <?php echo wp_json_encode( __( 'Zoek in artikelen', 'tbmm' ) ); ?>;
						return;
					}

					btn.disabled = true;
					btn.textContent = <?php echo wp_json_encode( __( 'Zoeken…', 'tbmm' ) ); ?>;

					var data = new FormData();
					data.append('action', 'tbmm_orphan_find_articles');
					data.append('nonce',  nonce);
					data.append('slug',   slug);

					fetch(ajaxurl, { method: 'POST', body: data })
						.then(function(r) { return r.json(); })
						.then(function(resp) {
							btn.disabled = false;
							if (resp.success && resp.data.articles && resp.data.articles.length > 0) {
								var html = '<ul class="alc-articles-list">';
								resp.data.articles.forEach(function(a) {
									html += '<li><a href="' + escHtml(a.edit_url) + '" target="_blank">'
										+ escHtml(a.post_title) + '</a></li>';
								});
								html += '</ul>';
								cell.innerHTML = html;
								btn.textContent = <?php echo wp_json_encode( __( 'Verberg ▲', 'tbmm' ) ); ?>;
							} else {
								cell.innerHTML = '<em style="font-size:12px; color:#646970;"><?php echo esc_js( __( 'Niet gevonden in gepubliceerde artikelen.', 'tbmm' ) ); ?></em>';
								btn.textContent = <?php echo wp_json_encode( __( 'Verberg ▲', 'tbmm' ) ); ?>;
							}
							arRow.style.display = '';
						})
						.catch(function() {
							btn.disabled = false;
							btn.textContent = <?php echo wp_json_encode( __( 'Zoek in artikelen', 'tbmm' ) ); ?>;
						});
				});
			});

			function escHtml(str) {
				var d = document.createElement('div');
				d.appendChild(document.createTextNode(String(str)));
				return d.innerHTML;
			}
		})();
		</script>
		<?php
	}

	private function render_orphaned_links_subtab(): void {
		$cached = $this->scan_cache->get( 'orphaned_aanbeveling' );
		$nonce  = wp_create_nonce( 'tbmm_orphan_nonce' );
		?>
		<style>
			.alc-scan-meta { font-size:13px; color:#646970; margin-bottom:14px; display:flex; align-items:center; gap:10px; }
			.alc-orphan-progress-wrap { background:#e0e0e0; border-radius:4px; height:10px; max-width:500px; overflow:hidden; }
			.alc-orphan-bar { background:#2271b1; height:100%; width:0%; transition:width 0.25s; border-radius:4px; }
		</style>

		<p style="font-size:13px; color:#3c434a; max-width:700px; margin-bottom:14px;">
			<?php
			echo wp_kses(
				__( 'Zoekt in alle <strong>live gepubliceerde artikelen</strong> naar links met het patroon <code>/aanbeveling/slug</code> die <strong>niet bekend zijn in ThirstyAffiliates</strong>. Dit zijn <em>dode links</em>: de redirect bestaat niet (meer), waardoor bezoekers op een niet-werkende URL terechtkomen. Elke gevonden link moet worden aangemaakt of gerepareerd in ThirstyAffiliates.', 'tbmm' ),
				array( 'strong' => array(), 'code' => array(), 'em' => array() )
			);
			?>
		</p>

		<?php if ( $cached ) :
			$ts      = date_i18n( 'd M Y \o\m H:i', strtotime( $cached['scanned_at'] ) );
			$results = $cached['results'];
			usort( $results, function( $a, $b ) {
				if ( $b['occurrences'] !== $a['occurrences'] ) {
					return $b['occurrences'] - $a['occurrences'];
				}
				return strcmp( $a['post_title'], $b['post_title'] );
			} );
		?>
		<div class="alc-scan-meta">
			<?php
			/* translators: %s = date/time of last scan */
			echo esc_html( sprintf( __( 'Laatste scan: %s', 'tbmm' ), $ts ) );
			?>
			<button id="alc-orphan-rescan-btn" class="button button-small">↺ <?php esc_html_e( 'Herscan', 'tbmm' ); ?></button>
		</div>

		<?php if ( empty( $results ) ) : ?>
		<p style="color:#646970; font-size:13px;"><?php esc_html_e( 'Geen orphaned /aanbeveling/ links gevonden.', 'tbmm' ); ?></p>

		<?php else : ?>
		<table class="alc-ta-table">
			<thead>
				<tr>
					<th class="alc-rank-cell">#</th>
					<th><?php esc_html_e( 'Artikel', 'tbmm' ); ?></th>
					<th><?php esc_html_e( 'Gevonden URL', 'tbmm' ); ?></th>
					<th style="text-align:right; width:80px;"><?php esc_html_e( 'Voorkomens', 'tbmm' ); ?></th>
					<th style="width:40px;"></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $results as $i => $r ) : ?>
			<tr>
				<td class="alc-rank-cell"><?php echo esc_html( $i + 1 ); ?></td>
				<td>
					<a href="<?php echo esc_url( $r['edit_url'] ); ?>" target="_blank" style="text-decoration:none; color:inherit;">
						<?php echo esc_html( $r['post_title'] ); ?>
					</a>
				</td>
				<td><code style="font-size:12px;"><?php echo esc_html( $r['found_url'] ); ?></code></td>
				<td style="text-align:right; font-variant-numeric:tabular-nums;"><?php echo esc_html( $r['occurrences'] ); ?>×</td>
				<td style="text-align:center;">
					<a href="<?php echo esc_url( $r['edit_url'] ); ?>" target="_blank"
					   title="<?php esc_attr_e( 'Bewerk artikel', 'tbmm' ); ?>" style="text-decoration:none; font-size:15px;">✎</a>
				</td>
			</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php endif; ?>

		<?php else : ?>
		<p style="font-size:13px; color:#646970;">
			<?php esc_html_e( 'Scan de gepubliceerde artikelen op orphaned /aanbeveling/ links — links die in artikelen staan maar niet meer actief zijn in ThirstyAffiliates.', 'tbmm' ); ?>
		</p>
		<button id="alc-orphan-scan-btn" class="button button-primary"><?php esc_html_e( 'Doe analyse', 'tbmm' ); ?></button>
		<?php endif; ?>

		<div id="alc-orphan-progress" style="display:none; margin-top:16px;">
			<div class="alc-orphan-progress-wrap">
				<div id="alc-orphan-bar" class="alc-orphan-bar"></div>
			</div>
			<p id="alc-orphan-label" style="font-size:13px; color:#646970; margin-top:6px;"></p>
		</div>

		<script>
		(function() {
			var nonce      = <?php echo wp_json_encode( $nonce ); ?>;
			var BATCH_SIZE = 15;

			var scanBtn    = document.getElementById('alc-orphan-scan-btn');
			var rescanBtn  = document.getElementById('alc-orphan-rescan-btn');
			var progressEl = document.getElementById('alc-orphan-progress');
			var barEl      = document.getElementById('alc-orphan-bar');
			var labelEl    = document.getElementById('alc-orphan-label');

			function startScan() {
				if (progressEl) progressEl.style.display = 'block';
				if (barEl)      barEl.style.width = '0%';
				if (labelEl)    labelEl.textContent = <?php echo wp_json_encode( __( 'Initialiseren…', 'tbmm' ) ); ?>;
				if (scanBtn)    scanBtn.disabled = true;
				if (rescanBtn)  rescanBtn.disabled = true;

				var initData = new FormData();
				initData.append('action', 'tbmm_orphan_init');
				initData.append('nonce',  nonce);

				fetch(ajaxurl, { method: 'POST', body: initData })
					.then(function(r) { return r.json(); })
					.then(function(resp) {
						if (!resp.success) throw new Error(resp.data ? resp.data.message : 'Fout');
						runBatch(0, resp.data.total_posts, []);
					})
					.catch(function(err) {
						if (labelEl) labelEl.textContent = <?php echo wp_json_encode( __( 'Fout:', 'tbmm' ) ); ?> + ' ' + err.message;
						if (scanBtn)   scanBtn.disabled   = false;
						if (rescanBtn) rescanBtn.disabled = false;
					});
			}

			function runBatch(offset, totalPosts, allOrphans) {
				var pct = totalPosts > 0 ? Math.round((offset / totalPosts) * 100) : 100;
				if (barEl)   barEl.style.width = pct + '%';
				if (labelEl) labelEl.textContent = <?php echo wp_json_encode( __( 'Artikel', 'tbmm' ) ); ?> + ' '
					+ Math.min(offset, totalPosts) + ' ' + <?php echo wp_json_encode( __( 'van', 'tbmm' ) ); ?> + ' ' + totalPosts + '…';

				if (offset >= totalPosts) {
					saveScan(allOrphans);
					return;
				}

				var data = new FormData();
				data.append('action', 'tbmm_orphan_batch');
				data.append('nonce',  nonce);
				data.append('offset', offset);
				data.append('limit',  BATCH_SIZE);

				fetch(ajaxurl, { method: 'POST', body: data })
					.then(function(r) { return r.json(); })
					.then(function(resp) {
						if (resp.success && resp.data.orphans) {
							allOrphans = allOrphans.concat(resp.data.orphans);
						}
						runBatch(offset + BATCH_SIZE, totalPosts, allOrphans);
					})
					.catch(function() {
						runBatch(offset + BATCH_SIZE, totalPosts, allOrphans);
					});
			}

			function saveScan(orphans) {
				if (barEl)   barEl.style.width = '100%';
				if (labelEl) labelEl.textContent = <?php echo wp_json_encode( __( 'Opslaan…', 'tbmm' ) ); ?>;

				var data = new FormData();
				data.append('action',  'tbmm_orphan_save');
				data.append('nonce',   nonce);
				data.append('results', JSON.stringify(orphans));

				fetch(ajaxurl, { method: 'POST', body: data })
					.then(function(r) { return r.json(); })
					.then(function(resp) {
						if (resp.success) {
							if (labelEl) labelEl.textContent = <?php echo wp_json_encode( __( 'Klaar!', 'tbmm' ) ); ?>
								+ ' ' + orphans.length + ' ' + <?php echo wp_json_encode( __( 'orphaned link(s) gevonden. Pagina wordt herladen…', 'tbmm' ) ); ?>;
							setTimeout(function() { window.location.reload(); }, 1500);
						}
					})
					.catch(function() {
						if (labelEl) labelEl.textContent = <?php echo wp_json_encode( __( 'Scan klaar, maar opslaan mislukt.', 'tbmm' ) ); ?>;
						if (scanBtn)   scanBtn.disabled   = false;
						if (rescanBtn) rescanBtn.disabled = false;
					});
			}

			if (scanBtn)   scanBtn.addEventListener('click',   startScan);
			if (rescanBtn) rescanBtn.addEventListener('click', startScan);
		})();
		</script>
		<?php
	}

	private function render_pagination( int $current, int $total, string $base_url ): void {
		if ( $current > 1 ) {
			echo '<a href="' . esc_url( $base_url . ( $current - 1 ) ) . '">‹ ' . esc_html__( 'Vorige', 'tbmm' ) . '</a>';
		}

		$window = 2;
		$shown  = array();

		for ( $p = 1; $p <= $total; $p++ ) {
			if ( $p === 1 || $p === $total || abs( $p - $current ) <= $window ) {
				$shown[] = $p;
			}
		}

		$prev = null;
		foreach ( $shown as $p ) {
			if ( $prev !== null && $p - $prev > 1 ) {
				echo '<span class="dots">…</span>';
			}
			if ( $p === $current ) {
				echo '<span class="current-page">' . esc_html( $p ) . '</span>';
			} else {
				echo '<a href="' . esc_url( $base_url . $p ) . '">' . esc_html( $p ) . '</a>';
			}
			$prev = $p;
		}

		if ( $current < $total ) {
			echo '<a href="' . esc_url( $base_url . ( $current + 1 ) ) . '">' . esc_html__( 'Volgende', 'tbmm' ) . ' ›</a>';
		}
	}

	// -------------------------------------------------------------------------
	// Link Scanner subtab
	// -------------------------------------------------------------------------

	private function render_link_scanner_subtab(): void {
		$stats = $this->link_scanner->get_stats();
		$nonce = wp_create_nonce( 'tbmm_run_scan_nonce' );
		?>
		<h3><?php esc_html_e( 'Link Scanner', 'tbmm' ); ?></h3>
		<p style="max-width:700px; font-size:13px; margin-bottom:16px;">
			<?php
			echo wp_kses(
				__( 'Controleert alle <strong>ThirstyAffiliates destination URLs</strong> op gebroken links door elk adres te opvragen en de HTTP-statuscode te controleren. <strong>Bol.com-links worden overgeslagen</strong> — die zijn altijd geldig zolang het product bestaat. Alle andere links (TradeTracker, adverteerders, directe product-URLs) worden getest. Een scan vindt 404-fouten, serverfouten (5xx) en verbindingsproblemen — zo kun je tijdig dode links herstellen voordat bezoekers erop klikken.', 'tbmm' ),
				array( 'strong' => array() )
			);
			?>
		</p>

		<div class="alc-stats-box">
			<div class="alc-stats-numbers">
				<div class="alc-stat">
					<span class="alc-stat-number"><?php echo esc_html( $stats['total'] ); ?></span>
					<span class="alc-stat-label"><?php esc_html_e( 'Totaal links', 'tbmm' ); ?></span>
				</div>
				<div class="alc-stat alc-stat-skip">
					<span class="alc-stat-number"><?php echo esc_html( $stats['bol_count'] ); ?></span>
					<span class="alc-stat-label"><?php esc_html_e( 'Bol.com (overgeslagen)', 'tbmm' ); ?></span>
				</div>
				<div class="alc-stat alc-stat-scan">
					<span class="alc-stat-number"><?php echo esc_html( $stats['scan_count'] ); ?></span>
					<span class="alc-stat-label"><?php esc_html_e( 'Te scannen', 'tbmm' ); ?></span>
				</div>
			</div>

			<?php if ( ! empty( $stats['domains'] ) ) : ?>
			<table class="alc-domain-table">
				<thead><tr>
					<th><?php esc_html_e( 'Domein', 'tbmm' ); ?></th>
					<th><?php esc_html_e( 'Links', 'tbmm' ); ?></th>
				</tr></thead>
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
			<?php
			/* translators: %d = number of links to scan */
			printf( esc_html__( 'Start Scan (%d links)', 'tbmm' ), $stats['scan_count'] );
			?>
		</button>
		<?php else : ?>
		<p><em><?php esc_html_e( 'Geen links te scannen.', 'tbmm' ); ?></em></p>
		<?php endif; ?>

		<div id="alc-progress" style="display:none; margin-top:20px;">
			<div class="alc-progress-bar-wrap"><div id="alc-progress-bar" class="alc-progress-bar"></div></div>
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
			var btn     = document.getElementById('alc-start-scan');
			var progress = document.getElementById('alc-progress');
			var bar     = document.getElementById('alc-progress-bar');
			var label   = document.getElementById('alc-progress-label');
			var results = document.getElementById('alc-results');
			var tableBody = null, brokenCount = 0;

			if (!btn) return;
			btn.addEventListener('click', function() {
				btn.disabled = true; brokenCount = 0; tableBody = null;
				results.innerHTML = ''; progress.style.display = 'block';
				bar.style.width = '0%'; label.textContent = <?php echo wp_json_encode( __( 'Voorbereiden…', 'tbmm' ) ); ?>;
				scanNext(0);
			});

			function scanNext(index) {
				if (index >= total) {
					bar.style.width = '100%';
					label.textContent = <?php echo wp_json_encode( __( 'Klaar.', 'tbmm' ) ); ?> + ' ' + brokenCount + ' ' + <?php echo wp_json_encode( __( 'gebroken link(s) gevonden.', 'tbmm' ) ); ?>;
					btn.disabled = false;
					if (brokenCount === 0) results.innerHTML = '<div class="notice notice-success inline"><p><?php echo esc_js( __( 'Geen gebroken links gevonden.', 'tbmm' ) ); ?></p></div>';
					return;
				}
				var link = links[index];
				bar.style.width = Math.round((index / total) * 100) + '%';
				label.textContent = <?php echo wp_json_encode( __( 'Link', 'tbmm' ) ); ?> + ' ' + (index + 1) + ' ' + <?php echo wp_json_encode( __( 'van', 'tbmm' ) ); ?> + ' ' + total + ' — ' + link.name;
				var data = new FormData();
				data.append('action', 'tbmm_check_link'); data.append('nonce', nonce);
				data.append('link_id', link.id); data.append('link_url', link.url);
				fetch(ajaxurl, { method: 'POST', body: data })
					.then(function(r) { return r.json(); })
					.then(function(resp) {
						if (resp.success && resp.data.is_broken) { brokenCount++; appendBrokenRow(link, resp.data); }
						scanNext(index + 1);
					})
					.catch(function() { scanNext(index + 1); });
			}

			function appendBrokenRow(link, data) {
				if (!tableBody) {
					var h = document.createElement('p'); h.innerHTML = '<strong><?php echo esc_js( __( 'Gebroken links:', 'tbmm' ) ); ?></strong>'; results.appendChild(h);
					var t = document.createElement('table');
					t.innerHTML = '<thead><tr>'
						+ '<th><?php echo esc_js( __( 'Link naam', 'tbmm' ) ); ?></th>'
						+ '<th><?php echo esc_js( __( 'Destination URL', 'tbmm' ) ); ?></th>'
						+ '<th><?php echo esc_js( __( 'Status', 'tbmm' ) ); ?></th>'
						+ '<th><?php echo esc_js( __( 'Gebruikt in', 'tbmm' ) ); ?></th>'
						+ '<th><?php echo esc_js( __( 'Bewerk', 'tbmm' ) ); ?></th>'
						+ '</tr></thead><tbody></tbody>';
					results.appendChild(t); tableBody = t.querySelector('tbody');
				}
				var sc = data.status === 404 ? 'alc-status-404' : (data.status >= 500 ? 'alc-status-5xx' : 'alc-status-0');
				var sl = data.status === 0 ? <?php echo wp_json_encode( __( 'Verbinding mislukt', 'tbmm' ) ); ?> : data.status;
				var ph = '—';
				if (data.posts && data.posts.length) {
					ph = '<ul class="alc-post-list">' + data.posts.map(function(p) {
						return '<li><a href="' + escHtml(p.edit_url) + '" target="_blank">' + escHtml(p.title) + '</a></li>';
					}).join('') + '</ul>';
				}
				var tr = document.createElement('tr');
				tr.innerHTML = '<td>' + escHtml(link.name) + '</td>'
					+ '<td><a href="' + escHtml(link.url) + '" target="_blank">' + escHtml(link.url) + '</a></td>'
					+ '<td><span class="' + sc + '">' + sl + '</span></td>'
					+ '<td>' + ph + '</td>'
					+ '<td><a href="' + escHtml(data.edit_url) + '" target="_blank" class="button button-small"><?php echo esc_js( __( 'Bewerk in TA', 'tbmm' ) ); ?></a></td>';
				tableBody.appendChild(tr);
			}
			function escHtml(s) { var d = document.createElement('div'); d.appendChild(document.createTextNode(String(s))); return d.innerHTML; }
		})();
		</script>
		<?php
	}

	// -------------------------------------------------------------------------
	// Unmanaged Links subtab
	// -------------------------------------------------------------------------

	private function render_unmanaged_links_subtab(): void {
		$all_types = array_keys( UnmanagedLinkScanner::TYPES );
		$meta      = $this->unmanaged_scanner->get_scan_meta();
		$nonce     = wp_create_nonce( 'tbmm_unmanaged_nonce' );

		$filter_types = isset( $_GET['types'] ) && is_array( $_GET['types'] )
			? array_intersect( array_map( 'sanitize_key', $_GET['types'] ), $all_types )
			: $all_types;

		$rows = $this->unmanaged_scanner->get_results( $filter_types );

		$type_counts = array_fill_keys( $all_types, 0 );
		foreach ( $this->unmanaged_scanner->get_results() as $r ) {
			if ( isset( $type_counts[ $r['link_type'] ] ) ) {
				$type_counts[ $r['link_type'] ]++;
			}
		}

		echo '<h3>' . esc_html__( 'Unmanaged Links Scanner', 'tbmm' ) . '</h3>';
		echo '<p>' . wp_kses(
			__( 'Zoekt in alle gepubliceerde berichten en pagina\'s naar affiliate-links die <strong>niet</strong> via ThirstyAffiliates lopen. Als er een TA-link bestaat met dezelfde bestemmings-URL, kun je de link direct vervangen.', 'tbmm' ),
			array( 'strong' => array() )
		) . '</p>';

		?>
		<style>
			.tbmm-type-badge { display:inline-block; padding:2px 8px; border-radius:10px; font-size:11px; font-weight:600; }
			.tbmm-badge-bol_tracked    { background:#dbeafe; color:#1d4ed8; }
			.tbmm-badge-tradetracker   { background:#fef9c3; color:#92400e; }
			.tbmm-badge-bol_direct     { background:#fee2e2; color:#991b1b; }
			.tbmm-badge-amazon_tracked { background:#ffedd5; color:#9a3412; }
			.tbmm-badge-amazon_direct  { background:#b32d2e; color:#fff; font-weight:700; }
			.tbmm-match-yes { color:#00a32a; font-weight:600; }
			.tbmm-match-no  { color:#b32d2e; }
			.tbmm-url-cell  { font-size:11px; word-break:break-all; max-width:280px; color:#444; }
			.tbmm-patterns-box { background:#f6f7f7; border:1px solid #c3c4c7; border-radius:4px; padding:14px 18px; margin-bottom:16px; }
			.tbmm-patterns-box label { margin-right:20px; font-size:13px; }
		</style>

		<div class="tbmm-patterns-box">
			<strong style="display:block;margin-bottom:8px;"><?php esc_html_e( 'Zoekpatronen (actief bij volgende scan):', 'tbmm' ); ?></strong>
			<form id="tbmm-scan-form" style="display:inline;">
			<?php foreach ( UnmanagedLinkScanner::TYPES as $type_key => $type_label ) : ?>
				<label>
					<input type="checkbox" name="scan_type[]" value="<?php echo esc_attr( $type_key ); ?>" checked>
					<span class="tbmm-type-badge tbmm-badge-<?php echo esc_attr( $type_key ); ?>"><?php echo esc_html( $type_label ); ?></span>
					<?php if ( isset( $type_counts[ $type_key ] ) && ! empty( $meta ) ) : ?>
						<?php
						/* translators: %d = number of links found */
						printf( '<span style="color:#646970;font-size:11px;">(%d ' . esc_html__( 'gevonden', 'tbmm' ) . ')</span>', $type_counts[ $type_key ] );
						?>
					<?php endif; ?>
				</label>
			<?php endforeach; ?>
			</form>
			<div style="margin-top:12px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
				<button type="button" id="tbmm-scan-btn" class="button button-primary" data-nonce="<?php echo esc_attr( $nonce ); ?>">
					<?php echo empty( $meta ) ? '&#128270; ' . esc_html__( 'Scan starten', 'tbmm' ) : '&#128270; ' . esc_html__( 'Herscan', 'tbmm' ); ?>
				</button>
				<span id="tbmm-scan-status" style="font-size:13px;color:#646970;">
				<?php if ( ! empty( $meta['scanned_at'] ) ) : ?>
					<?php
					/* translators: 1: date/time, 2: count */
					printf( __( 'Laatste scan: %1$s — %2$d links gevonden', 'tbmm' ), esc_html( $meta['scanned_at'] ), (int) $meta['total'] );
					?>
				<?php else : ?>
					<?php esc_html_e( 'Nog niet gescand.', 'tbmm' ); ?>
				<?php endif; ?>
				</span>
			</div>
			<div id="tbmm-scan-progress-wrap" style="display:none;margin-top:10px;">
				<div style="background:#e0e0e0;border-radius:3px;height:10px;width:100%;max-width:500px;overflow:hidden;">
					<div id="tbmm-scan-bar" style="background:#2271b1;height:100%;width:0%;transition:width 0.2s;"></div>
				</div>
				<p id="tbmm-scan-label" style="font-size:13px;color:#646970;margin-top:6px;"></p>
			</div>
		</div>

		<?php if ( empty( $meta ) ) : ?>
			<div style="background:#f0f6fc;border-left:4px solid #72aee6;padding:10px 14px;margin:8px 0 16px;font-size:13px;">
				<?php echo wp_kses( __( 'Klik op <strong>Scan starten</strong> om te beginnen.', 'tbmm' ), array( 'strong' => array() ) ); ?>
			</div>
		<?php elseif ( empty( $rows ) ) : ?>
			<div style="background:#edfaef;border-left:4px solid #00a32a;padding:10px 14px;margin:8px 0 16px;font-size:13px;">
				&#10003; <?php esc_html_e( 'Geen onbeheerde links gevonden voor de geselecteerde patronen.', 'tbmm' ); ?>
			</div>
		<?php else :
			echo '<form method="GET" style="margin-bottom:12px;">';
			echo '<input type="hidden" name="page" value="tb-money-manager">';
			echo '<input type="hidden" name="tab" value="ta">';
			echo '<input type="hidden" name="subtab" value="unmanaged-links">';
			echo '<strong style="font-size:13px;">' . esc_html__( 'Filter weergave:', 'tbmm' ) . '</strong> ';
			foreach ( UnmanagedLinkScanner::TYPES as $type_key => $type_label ) {
				$checked = in_array( $type_key, $filter_types, true ) ? 'checked' : '';
				echo '<label style="margin-right:14px;font-size:13px;"><input type="checkbox" name="types[]" value="' . esc_attr( $type_key ) . '" ' . $checked . '> ';
				echo '<span class="tbmm-type-badge tbmm-badge-' . esc_attr( $type_key ) . '">' . esc_html( $type_label ) . '</span>';
				echo ' <span style="color:#646970;font-size:11px;">(' . $type_counts[ $type_key ] . ')</span></label>';
			}
			echo '<input type="submit" value="' . esc_attr__( 'Filter', 'tbmm' ) . '" class="button button-secondary" style="margin-left:8px;">';
			echo '</form>';
			/* translators: %d = number of links shown */
			echo '<p style="color:#646970;font-size:13px;">' . sprintf( __( '%d link(s) weergegeven.', 'tbmm' ), count( $rows ) ) . '</p>';
			echo '<table class="wp-list-table widefat fixed striped" style="table-layout:auto;">';
			echo '<thead><tr>'
				. '<th>' . esc_html__( 'Bericht', 'tbmm' ) . '</th>'
				. '<th>' . esc_html__( 'Type', 'tbmm' ) . '</th>'
				. '<th>' . esc_html__( 'Gevonden URL', 'tbmm' ) . '</th>'
				. '<th>' . esc_html__( 'Anchor tekst', 'tbmm' ) . '</th>'
				. '<th style="text-align:center;">' . esc_html__( 'TA match?', 'tbmm' ) . '</th>'
				. '<th>' . esc_html__( 'Actie', 'tbmm' ) . '</th>'
				. '</tr></thead><tbody>';
			foreach ( $rows as $row ) {
				$edit_url   = esc_url( admin_url( 'post.php?post=' . (int) $row['post_id'] . '&action=edit' ) );
				$has_match  = ! empty( $row['ta_link_id'] );
				$type_key   = $row['link_type'];
				$type_label = UnmanagedLinkScanner::TYPES[ $type_key ] ?? $type_key;
				$url_short  = strlen( $row['url'] ) > 80 ? substr( $row['url'], 0, 77 ) . '…' : $row['url'];
				echo '<tr>';
				echo '<td><a href="' . $edit_url . '" target="_blank" rel="noopener">' . esc_html( $row['post_title'] ) . '</a></td>';
				echo '<td><span class="tbmm-type-badge tbmm-badge-' . esc_attr( $type_key ) . '">' . esc_html( $type_label ) . '</span></td>';
				echo '<td class="tbmm-url-cell" title="' . esc_attr( $row['url'] ) . '"><a href="' . esc_url( $row['url'] ) . '" target="_blank" rel="noopener nofollow">' . esc_html( $url_short ) . '</a></td>';
				echo '<td>' . esc_html( $row['anchor_text'] ) . '</td>';
				if ( $has_match ) {
					echo '<td style="text-align:center;"><span class="tbmm-match-yes" title="' . esc_attr( $row['ta_link_name'] ?? '' ) . '">&#10003;</span></td>';
				} else {
					echo '<td style="text-align:center;"><span class="tbmm-match-no">&#10007;</span></td>';
				}
				if ( $has_match ) {
					echo '<td><button type="button" class="button button-small tbmm-replace-btn"'
						. ' data-row-id="' . (int) $row['id'] . '" data-nonce="' . esc_attr( $nonce ) . '"'
						. ' title="' . esc_attr( sprintf( __( 'Vervang door: %s', 'tbmm' ), $row['ta_redirect_url'] ) ) . '">'
						. '&#9654; ' . esc_html__( 'Vervang door TA-link', 'tbmm' ) . '</button>'
						. '<span style="display:block;font-size:11px;color:#646970;margin-top:2px;">' . esc_html( $row['ta_link_name'] ?? '' ) . '</span></td>';
				} else {
					echo '<td style="color:#646970;font-size:12px;">' . esc_html__( 'Geen match — maak eerst een TA-link', 'tbmm' ) . '</td>';
				}
				echo '</tr>';
			}
			echo '</tbody></table>';
		endif; ?>

		<script>
		(function() {
			var BATCH_SIZE   = 15;
			var scanBtn      = document.getElementById('tbmm-scan-btn');
			var scanStatus   = document.getElementById('tbmm-scan-status');
			var progressWrap = document.getElementById('tbmm-scan-progress-wrap');
			var barEl        = document.getElementById('tbmm-scan-bar');
			var labelEl      = document.getElementById('tbmm-scan-label');

			function setProgress(done, total) {
				barEl.style.width = (total > 0 ? Math.round((done / total) * 100) : 100) + '%';
				labelEl.textContent = <?php echo wp_json_encode( __( 'Artikel', 'tbmm' ) ); ?> + ' ' + Math.min(done, total) + ' ' + <?php echo wp_json_encode( __( 'van', 'tbmm' ) ); ?> + ' ' + total + '…';
			}
			function runBatch(offset, totalPosts, activeTypes, nonce) {
				setProgress(offset, totalPosts);
				if (offset >= totalPosts) {
					scanStatus.textContent = <?php echo wp_json_encode( __( 'Scan klaar. Pagina wordt herladen…', 'tbmm' ) ); ?>;
					setTimeout(function() { window.location.reload(); }, 1000);
					return;
				}
				var body = new URLSearchParams();
				body.append('action', 'tbmm_unmanaged_batch'); body.append('nonce', nonce);
				body.append('offset', offset); body.append('limit', BATCH_SIZE);
				activeTypes.forEach(function(t) { body.append('types[]', t); });
				fetch(ajaxurl, { method: 'POST', body: body })
					.then(function(r) { return r.json(); })
					.then(function(data) {
						if (data.success) { runBatch(offset + BATCH_SIZE, totalPosts, activeTypes, nonce); }
						else { scanStatus.textContent = <?php echo wp_json_encode( __( 'Fout:', 'tbmm' ) ); ?> + ' ' + (data.data && data.data.message ? data.data.message : 'onbekend'); scanBtn.disabled = false; }
					})
					.catch(function(err) { scanStatus.textContent = <?php echo wp_json_encode( __( 'Netwerkfout:', 'tbmm' ) ); ?> + ' ' + err; scanBtn.disabled = false; });
			}
			if (scanBtn) {
				scanBtn.addEventListener('click', function() {
					var checkboxes = document.querySelectorAll('#tbmm-scan-form input[name="scan_type[]"]:checked');
					var types = Array.from(checkboxes).map(function(cb) { return cb.value; });
					if (!types.length) { alert(<?php echo wp_json_encode( __( 'Selecteer minimaal één patroon.', 'tbmm' ) ); ?>); return; }
					var nonce = scanBtn.dataset.nonce;
					scanBtn.disabled = true; scanStatus.textContent = <?php echo wp_json_encode( __( 'Initialiseren…', 'tbmm' ) ); ?>;
					progressWrap.style.display = 'block'; setProgress(0, 1);
					var body = new URLSearchParams();
					body.append('action', 'tbmm_unmanaged_init'); body.append('nonce', nonce);
					types.forEach(function(t) { body.append('types[]', t); });
					fetch(ajaxurl, { method: 'POST', body: body })
						.then(function(r) { return r.json(); })
						.then(function(data) {
							if (data.success) { runBatch(0, data.data.total_posts, data.data.active_types, nonce); }
							else { scanStatus.textContent = <?php echo wp_json_encode( __( 'Fout:', 'tbmm' ) ); ?> + ' ' + (data.data && data.data.message ? data.data.message : 'onbekend'); scanBtn.disabled = false; progressWrap.style.display = 'none'; }
						})
						.catch(function(err) { scanStatus.textContent = <?php echo wp_json_encode( __( 'Netwerkfout:', 'tbmm' ) ); ?> + ' ' + err; scanBtn.disabled = false; progressWrap.style.display = 'none'; });
				});
			}
			document.querySelectorAll('.tbmm-replace-btn').forEach(function(btn) {
				btn.addEventListener('click', function() {
					if (!confirm(<?php echo wp_json_encode( __( 'Weet je zeker dat je deze URL in het artikel wilt vervangen door de ThirstyAffiliates-link?', 'tbmm' ) ); ?>)) return;
					btn.disabled = true; btn.textContent = '…';
					var body = new URLSearchParams();
					body.append('action', 'tbmm_replace_unmanaged_link');
					body.append('nonce', btn.dataset.nonce); body.append('row_id', btn.dataset.rowId);
					fetch(ajaxurl, { method: 'POST', body: body })
						.then(function(r) { return r.json(); })
						.then(function(data) {
							var row = btn.closest('tr');
							if (data.success) { row.style.background = '#edfaef'; row.cells[row.cells.length - 1].innerHTML = '<span style="color:#00a32a;font-size:12px;">&#10003; <?php echo esc_js( __( 'Vervangen', 'tbmm' ) ); ?></span>'; }
							else { row.cells[row.cells.length - 1].innerHTML = '<span style="color:#b32d2e;font-size:12px;">&#9888; ' + (data.data && data.data.message ? data.data.message : <?php echo wp_json_encode( __( 'Onbekende fout', 'tbmm' ) ); ?>) + '</span>'; }
						})
						.catch(function(err) { btn.disabled = false; btn.textContent = '▶ <?php echo esc_js( __( 'Vervang door TA-link', 'tbmm' ) ); ?>'; alert(<?php echo wp_json_encode( __( 'Netwerkfout:', 'tbmm' ) ); ?> + ' ' + err); });
				});
			});
		})();
		</script>
		<?php
	}
}
