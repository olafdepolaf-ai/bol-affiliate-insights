<?php

namespace TuinenBalkon\TBMoneyManager\Admin;

use TuinenBalkon\TBMoneyManager\Service\UnmanagedLinkScanner;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ToolsTab {

	private UnmanagedLinkScanner $scanner;

	public function __construct( UnmanagedLinkScanner $scanner ) {
		$this->scanner = $scanner;
	}

	public function render(): void {
		$base_url = admin_url( 'admin.php?page=tb-money-manager&tab=tools' );
		$subtab   = isset( $_GET['subtab'] ) ? sanitize_key( $_GET['subtab'] ) : 'unmanaged_links';

		$subtabs = array(
			'unmanaged_links' => 'Unmanaged Links',
		);
		?>
		<style>
			.tbmm-subtab-nav { display:flex; align-items:flex-end; gap:4px; margin-bottom:20px; border-bottom:1px solid #c3c4c7; }
			.tbmm-subtab-nav a { display:inline-block; padding:6px 14px; text-decoration:none; font-size:13px; color:#2271b1; border:1px solid transparent; border-bottom:none; border-radius:3px 3px 0 0; margin-bottom:-1px; }
			.tbmm-subtab-nav a:hover { background:#f0f0f1; }
			.tbmm-subtab-nav a.active { background:#fff; border-color:#c3c4c7; color:#1d2327; font-weight:600; }
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
		<nav class="tbmm-subtab-nav">
			<?php foreach ( $subtabs as $slug => $label ) : ?>
			<a href="<?php echo esc_url( $base_url . '&subtab=' . $slug ); ?>"
			   class="<?php echo $subtab === $slug ? 'active' : ''; ?>">
				<?php echo esc_html( $label ); ?>
			</a>
			<?php endforeach; ?>
		</nav>
		<?php

		if ( $subtab === 'unmanaged_links' ) {
			$this->render_unmanaged_links();
		}
	}

	private function render_unmanaged_links(): void {
		$all_types = array_keys( UnmanagedLinkScanner::TYPES );
		$meta      = $this->scanner->get_scan_meta();
		$nonce     = wp_create_nonce( 'tbmm_unmanaged_nonce' );

		// Lees actieve type-filter uit URL (voor weergave-filter)
		$filter_types = isset( $_GET['types'] ) && is_array( $_GET['types'] )
			? array_intersect( array_map( 'sanitize_key', $_GET['types'] ), $all_types )
			: $all_types;

		$rows = $this->scanner->get_results( $filter_types );

		$type_counts = array_fill_keys( $all_types, 0 );
		foreach ( $this->scanner->get_results() as $r ) {
			if ( isset( $type_counts[ $r['link_type'] ] ) ) {
				$type_counts[ $r['link_type'] ]++;
			}
		}

		echo '<h3>Unmanaged Links Scanner</h3>';
		echo '<p>Zoekt in alle gepubliceerde berichten en pagina\'s naar affiliate-links die <strong>niet</strong> via ThirstyAffiliates lopen. Als er een TA-link bestaat met dezelfde bestemmings-URL, kun je de link direct vervangen.</p>';

		// ── Patroonselectie + scan knop ───────────────────────────────────────
		?>
		<div class="tbmm-patterns-box">
			<strong style="display:block;margin-bottom:8px;">Zoekpatronen (actief bij volgende scan):</strong>
			<form id="tbmm-scan-form" style="display:inline;">
			<?php foreach ( UnmanagedLinkScanner::TYPES as $type_key => $type_label ) : ?>
				<label>
					<input type="checkbox" name="scan_type[]" value="<?php echo esc_attr( $type_key ); ?>" checked>
					<span class="tbmm-type-badge tbmm-badge-<?php echo esc_attr( $type_key ); ?>"><?php echo esc_html( $type_label ); ?></span>
					<?php if ( isset( $type_counts[ $type_key ] ) && ! empty( $meta ) ) : ?>
						<span style="color:#646970;font-size:11px;">(<?php echo $type_counts[ $type_key ]; ?> gevonden)</span>
					<?php endif; ?>
				</label>
			<?php endforeach; ?>
			</form>
			<div style="margin-top:12px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
				<button type="button" id="tbmm-scan-btn" class="button button-primary"
					data-nonce="<?php echo esc_attr( $nonce ); ?>">
					<?php echo empty( $meta ) ? '&#128270; Scan starten' : '&#128270; Herscan'; ?>
				</button>
				<span id="tbmm-scan-status" style="font-size:13px;color:#646970;">
				<?php if ( ! empty( $meta['scanned_at'] ) ) : ?>
					Laatste scan: <?php echo esc_html( $meta['scanned_at'] ); ?> — <?php echo (int) $meta['total']; ?> links gevonden
				<?php else : ?>
					Nog niet gescand.
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
				Klik op <strong>Scan starten</strong> om te beginnen.
			</div>
		<?php elseif ( empty( $rows ) ) : ?>
			<div style="background:#edfaef;border-left:4px solid #00a32a;padding:10px 14px;margin:8px 0 16px;font-size:13px;">
				&#10003; Geen onbeheerde links gevonden voor de geselecteerde patronen.
			</div>
		<?php else : ?>

		<?php
		// ── Weergave-filter ───────────────────────────────────────────────────
		echo '<form method="GET" style="margin-bottom:12px;">';
		echo '<input type="hidden" name="page" value="tb-money-manager">';
		echo '<input type="hidden" name="tab" value="tools">';
		echo '<input type="hidden" name="subtab" value="unmanaged_links">';
		echo '<strong style="font-size:13px;">Filter weergave:</strong> ';
		foreach ( UnmanagedLinkScanner::TYPES as $type_key => $type_label ) {
			$checked = in_array( $type_key, $filter_types, true ) ? 'checked' : '';
			echo '<label style="margin-right:14px;font-size:13px;"><input type="checkbox" name="types[]" value="' . esc_attr( $type_key ) . '" ' . $checked . '> ';
			echo '<span class="tbmm-type-badge tbmm-badge-' . esc_attr( $type_key ) . '">' . esc_html( $type_label ) . '</span>';
			echo ' <span style="color:#646970;font-size:11px;">(' . $type_counts[ $type_key ] . ')</span></label>';
		}
		echo '<input type="submit" value="Filter" class="button button-secondary" style="margin-left:8px;">';
		echo '</form>';

		// ── Resultaten tabel ──────────────────────────────────────────────────
		echo '<p style="color:#646970;font-size:13px;">' . count( $rows ) . ' link(s) weergegeven.</p>';
		echo '<table class="wp-list-table widefat fixed striped" style="table-layout:auto;">';
		echo '<thead><tr>';
		echo '<th>Bericht</th>';
		echo '<th>Type</th>';
		echo '<th>Gevonden URL</th>';
		echo '<th>Anchor tekst</th>';
		echo '<th style="text-align:center;">TA match?</th>';
		echo '<th>Actie</th>';
		echo '</tr></thead><tbody>';

		foreach ( $rows as $row ) {
			$edit_url   = esc_url( admin_url( 'post.php?post=' . (int) $row['post_id'] . '&action=edit' ) );
			$has_match  = ! empty( $row['ta_link_id'] );
			$type_key   = $row['link_type'];
			$type_label = UnmanagedLinkScanner::TYPES[ $type_key ] ?? $type_key;
			$url_short  = strlen( $row['url'] ) > 80 ? substr( $row['url'], 0, 77 ) . '…' : $row['url'];

			echo '<tr>';

			// Bericht
			echo '<td><a href="' . $edit_url . '" target="_blank" rel="noopener">'
				. esc_html( $row['post_title'] ) . '</a></td>';

			// Type badge
			echo '<td><span class="tbmm-type-badge tbmm-badge-' . esc_attr( $type_key ) . '">'
				. esc_html( $type_label ) . '</span></td>';

			// URL
			echo '<td class="tbmm-url-cell" title="' . esc_attr( $row['url'] ) . '">'
				. '<a href="' . esc_url( $row['url'] ) . '" target="_blank" rel="noopener nofollow">'
				. esc_html( $url_short ) . '</a></td>';

			// Anchor
			echo '<td>' . esc_html( $row['anchor_text'] ) . '</td>';

			// TA match
			if ( $has_match ) {
				echo '<td style="text-align:center;"><span class="tbmm-match-yes" title="' . esc_attr( $row['ta_link_name'] ?? '' ) . '">&#10003;</span></td>';
			} else {
				echo '<td style="text-align:center;"><span class="tbmm-match-no">&#10007;</span></td>';
			}

			// Actie
			if ( $has_match ) {
				$ta_redirect = esc_attr( $row['ta_redirect_url'] );
				$ta_name     = esc_html( $row['ta_link_name'] ?? '' );
				echo '<td><button type="button" class="button button-small tbmm-replace-btn"'
					. ' data-row-id="' . (int) $row['id'] . '"'
					. ' data-nonce="' . esc_attr( $nonce ) . '"'
					. ' title="Vervang door: ' . $ta_redirect . '">'
					. '&#9654; Vervang door TA-link'
					. '</button>'
					. '<span style="display:block;font-size:11px;color:#646970;margin-top:2px;">' . $ta_name . '</span>'
					. '</td>';
			} else {
				echo '<td style="color:#646970;font-size:12px;">Geen match — maak eerst een TA-link</td>';
			}

			echo '</tr>';
		}

		echo '</tbody></table>';
		endif;

		// ── JavaScript ────────────────────────────────────────────────────────
		?>
		<script>
		(function() {
			var BATCH_SIZE   = 15;
			var scanBtn      = document.getElementById('tbmm-scan-btn');
			var scanStatus   = document.getElementById('tbmm-scan-status');
			var progressWrap = document.getElementById('tbmm-scan-progress-wrap');
			var barEl        = document.getElementById('tbmm-scan-bar');
			var labelEl      = document.getElementById('tbmm-scan-label');

			function setProgress(done, total) {
				var pct = total > 0 ? Math.round((done / total) * 100) : 100;
				barEl.style.width = pct + '%';
				labelEl.textContent = 'Artikel ' + Math.min(done, total) + ' van ' + total + '…';
			}

			function runBatch(offset, totalPosts, activeTypes, nonce) {
				setProgress(offset, totalPosts);
				if (offset >= totalPosts) {
					scanStatus.textContent = 'Scan klaar. Pagina wordt herladen…';
					setTimeout(function() { window.location.reload(); }, 1000);
					return;
				}

				var body = new URLSearchParams();
				body.append('action', 'tbmm_unmanaged_batch');
				body.append('nonce', nonce);
				body.append('offset', offset);
				body.append('limit', BATCH_SIZE);
				activeTypes.forEach(function(t) { body.append('types[]', t); });

				fetch(ajaxurl, { method: 'POST', body: body })
					.then(function(r) { return r.json(); })
					.then(function(data) {
						if (data.success) {
							runBatch(offset + BATCH_SIZE, totalPosts, activeTypes, nonce);
						} else {
							var msg = data.data && data.data.message ? data.data.message : 'onbekend';
							scanStatus.textContent = 'Fout: ' + msg;
							scanBtn.disabled = false;
						}
					})
					.catch(function(err) {
						scanStatus.textContent = 'Netwerkfout: ' + err;
						scanBtn.disabled = false;
					});
			}

			if (scanBtn) {
				scanBtn.addEventListener('click', function() {
					var checkboxes = document.querySelectorAll('#tbmm-scan-form input[name="scan_type[]"]:checked');
					var types = [];
					checkboxes.forEach(function(cb) { types.push(cb.value); });
					if (types.length === 0) {
						alert('Selecteer minimaal één patroon.');
						return;
					}

					var nonce = scanBtn.dataset.nonce;
					scanBtn.disabled = true;
					scanStatus.textContent = 'Initialiseren…';
					progressWrap.style.display = 'block';
					setProgress(0, 1);

					var body = new URLSearchParams();
					body.append('action', 'tbmm_unmanaged_init');
					body.append('nonce', nonce);
					types.forEach(function(t) { body.append('types[]', t); });

					fetch(ajaxurl, { method: 'POST', body: body })
						.then(function(r) { return r.json(); })
						.then(function(data) {
							if (data.success) {
								var totalPosts  = data.data.total_posts;
								var activeTypes = data.data.active_types;
								scanStatus.textContent = 'Scannen…';
								runBatch(0, totalPosts, activeTypes, nonce);
							} else {
								var msg = data.data && data.data.message ? data.data.message : 'onbekend';
								scanStatus.textContent = 'Fout: ' + msg;
								scanBtn.disabled = false;
								progressWrap.style.display = 'none';
							}
						})
						.catch(function(err) {
							scanStatus.textContent = 'Netwerkfout: ' + err;
							scanBtn.disabled = false;
							progressWrap.style.display = 'none';
						});
				});
			}

			// Replace buttons
			document.querySelectorAll('.tbmm-replace-btn').forEach(function(btn) {
				btn.addEventListener('click', function() {
					if (!confirm('Weet je zeker dat je deze URL in het artikel wilt vervangen door de ThirstyAffiliates-link?')) {
						return;
					}
					btn.disabled = true;
					btn.textContent = '…';

					var body = new URLSearchParams();
					body.append('action', 'tbmm_replace_unmanaged_link');
					body.append('nonce', btn.dataset.nonce);
					body.append('row_id', btn.dataset.rowId);

					fetch(ajaxurl, { method: 'POST', body: body })
						.then(function(r) { return r.json(); })
						.then(function(data) {
							var row = btn.closest('tr');
							if (data.success) {
								row.style.background = '#edfaef';
								row.cells[row.cells.length - 1].innerHTML =
									'<span style="color:#00a32a;font-size:12px;">&#10003; Vervangen</span>';
							} else {
								var msg = data.data && data.data.message ? data.data.message : 'Onbekende fout';
								row.cells[row.cells.length - 1].innerHTML =
									'<span style="color:#b32d2e;font-size:12px;">&#9888; ' + msg + '</span>';
							}
						})
						.catch(function(err) {
							btn.disabled = false;
							btn.textContent = '▶ Vervang door TA-link';
							alert('Netwerkfout: ' + err);
						});
				});
			});
		})();
		</script>
		<?php
	}
}
