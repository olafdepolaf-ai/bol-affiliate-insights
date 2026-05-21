<?php

namespace TuinenBalkon\TBMoneyManager\Admin;

use TuinenBalkon\TBMoneyManager\Service\OrphanedLinkScanner;
use TuinenBalkon\TBMoneyManager\Service\ScanCacheService;
use TuinenBalkon\TBMoneyManager\Service\ThirstyAffiliatesService;

class TATab {

	private ThirstyAffiliatesService $ta_service;
	private OrphanedLinkScanner      $orphaned_scanner;
	private ScanCacheService         $scan_cache;

	public function __construct(
		ThirstyAffiliatesService $ta_service,
		OrphanedLinkScanner $orphaned_scanner,
		ScanCacheService $scan_cache
	) {
		$this->ta_service       = $ta_service;
		$this->orphaned_scanner = $orphaned_scanner;
		$this->scan_cache       = $scan_cache;
	}

	public function render(): void {
		$subtabs = [
			'aanbeveling-404' => 'Aanbeveling 404',
			'orphaned-links'  => 'Orphaned Links',
		];

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
			Toont <code>/aanbeveling/</code>-URLs die een <strong>404 hebben opgeleverd</strong> — bezoekers klikten op een link die niet werkte.
			De data komt uit de <strong>Redirection plugin</strong> (<code><?php echo esc_html( $GLOBALS['wpdb']->prefix . 'redirection_404' ); ?></code>) en is <strong>realtime</strong> (geen cache).
			Klik op "Zoek in artikelen" om te zien welk artikel de gebroken link bevat, zodat je hem kunt herstellen in ThirstyAffiliates.
		</p>

		<form method="get" style="display:flex; align-items:center; gap:8px; margin-bottom:14px;">
			<input type="hidden" name="page"   value="tb-money-manager">
			<input type="hidden" name="tab"    value="ta">
			<input type="hidden" name="subtab" value="aanbeveling-404">
			<label for="alc-redir-period" style="font-size:13px; font-weight:600;">Periode:</label>
			<select id="alc-redir-period" name="redir_period" style="font-size:13px;">
				<?php foreach ( $allowed_periods as $key => $label ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $selected_period, $key ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
				<?php endforeach; ?>
			</select>
			<button type="submit" class="button">Toon</button>
		</form>

		<?php if ( $redir_data['table_missing'] ) : ?>
		<div class="notice notice-warning inline" style="margin:0;">
			<p>Redirection plugin niet actief of 404-logtabel (<code><?php echo esc_html( $GLOBALS['wpdb']->prefix . 'redirection_404' ); ?></code>) niet gevonden.</p>
		</div>

		<?php elseif ( empty( $redir_data['items'] ) ) : ?>
		<p style="color:#646970; font-size:13px;">Geen <code>/aanbeveling/</code> 404-hits gevonden voor deze periode.</p>

		<?php else : ?>
		<table class="alc-ta-table">
			<thead>
				<tr>
					<th>URL</th>
					<th style="text-align:right; width:60px;">Hits</th>
					<th>Laatste hit</th>
					<th style="width:160px;">Zoek in artikelen</th>
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
						Zoek in artikelen
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
						btn.textContent = 'Zoek in artikelen';
						return;
					}

					btn.disabled = true;
					btn.textContent = 'Zoeken…';

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
								btn.textContent = 'Verberg ▲';
							} else {
								cell.innerHTML = '<em style="font-size:12px; color:#646970;">Niet gevonden in gepubliceerde artikelen.</em>';
								btn.textContent = 'Verberg ▲';
							}
							arRow.style.display = '';
						})
						.catch(function() {
							btn.disabled = false;
							btn.textContent = 'Zoek in artikelen';
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
			Zoekt in alle <strong>live gepubliceerde artikelen</strong> naar links met het patroon <code>/aanbeveling/slug</code>
			die <strong>niet bekend zijn in ThirstyAffiliates</strong>. Dit zijn <em>dode links</em>: de redirect bestaat niet (meer),
			waardoor bezoekers op een niet-werkende URL terechtkomen. Elke gevonden link moet worden aangemaakt of gerepareerd in ThirstyAffiliates.
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
			<span>Laatste scan: <?php echo esc_html( $ts ); ?></span>
			<button id="alc-orphan-rescan-btn" class="button button-small">↺ Herscan</button>
		</div>

		<?php if ( empty( $results ) ) : ?>
		<p style="color:#646970; font-size:13px;">Geen orphaned <code>/aanbeveling/</code> links gevonden.</p>

		<?php else : ?>
		<table class="alc-ta-table">
			<thead>
				<tr>
					<th class="alc-rank-cell">#</th>
					<th>Artikel</th>
					<th>Gevonden URL</th>
					<th style="text-align:right; width:80px;">Voorkomens</th>
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
					   title="Bewerk artikel" style="text-decoration:none; font-size:15px;">✎</a>
				</td>
			</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php endif; ?>

		<?php else : ?>
		<p style="font-size:13px; color:#646970;">
			Scan de gepubliceerde artikelen op orphaned <code>/aanbeveling/</code> links — links die in artikelen staan maar niet meer actief zijn in ThirstyAffiliates.
		</p>
		<button id="alc-orphan-scan-btn" class="button button-primary">Doe analyse</button>
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
				if (labelEl)    labelEl.textContent = 'Initialiseren…';
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
						if (labelEl) labelEl.textContent = 'Fout: ' + err.message;
						if (scanBtn)   scanBtn.disabled   = false;
						if (rescanBtn) rescanBtn.disabled = false;
					});
			}

			function runBatch(offset, totalPosts, allOrphans) {
				var pct = totalPosts > 0 ? Math.round((offset / totalPosts) * 100) : 100;
				if (barEl)   barEl.style.width = pct + '%';
				if (labelEl) labelEl.textContent = 'Artikel ' + Math.min(offset, totalPosts)
					+ ' van ' + totalPosts + '…';

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
				if (labelEl) labelEl.textContent = 'Opslaan…';

				var data = new FormData();
				data.append('action',  'tbmm_orphan_save');
				data.append('nonce',   nonce);
				data.append('results', JSON.stringify(orphans));

				fetch(ajaxurl, { method: 'POST', body: data })
					.then(function(r) { return r.json(); })
					.then(function(resp) {
						if (resp.success) {
							if (labelEl) labelEl.textContent = 'Klaar! '
								+ orphans.length + ' orphaned link(s) gevonden. Pagina wordt herladen…';
							setTimeout(function() { window.location.reload(); }, 1500);
						}
					})
					.catch(function() {
						if (labelEl) labelEl.textContent = 'Scan klaar, maar opslaan mislukt.';
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
			echo '<a href="' . esc_url( $base_url . ( $current - 1 ) ) . '">‹ Vorige</a>';
		}

		$window = 2;
		$shown  = [];

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
			echo '<a href="' . esc_url( $base_url . ( $current + 1 ) ) . '">Volgende ›</a>';
		}
	}
}
