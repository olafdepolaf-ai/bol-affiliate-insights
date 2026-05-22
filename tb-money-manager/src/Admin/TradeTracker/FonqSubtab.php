<?php

namespace TuinenBalkon\TBMoneyManager\Admin\TradeTracker;

use TuinenBalkon\TBMoneyManager\Service\ThirstyAffiliatesService;

class FonqSubtab {

	private ThirstyAffiliatesService $ta_service;

	public function __construct( ThirstyAffiliatesService $ta_service ) {
		$this->ta_service = $ta_service;
	}

	public function render(): void {
		$links = $this->ta_service->get_links_by_destination( 'fonq' );
		$nonce = wp_create_nonce( 'tbmm_orphan_nonce' );

		if ( empty( $links ) ) :
		?>
		<p style="color:#646970; font-size:13px;"><?php esc_html_e( 'Geen actieve FONQ.nl links gevonden in ThirstyAffiliates.', 'tbmm' ); ?></p>
		<?php return; endif; ?>

		<p class="alc-ta-summary">
			<strong><?php echo esc_html( count( $links ) ); ?></strong>
			<?php esc_html_e( 'FONQ.nl links gevonden in ThirstyAffiliates.', 'tbmm' ); ?>
		</p>

		<table class="alc-ta-table">
			<thead>
				<tr>
					<th class="alc-rank-cell">#</th>
					<th><?php esc_html_e( 'Link naam', 'tbmm' ); ?></th>
					<th><?php esc_html_e( 'Destination URL', 'tbmm' ); ?></th>
					<th style="width:130px;"><?php esc_html_e( 'Bewerk', 'tbmm' ); ?></th>
					<th style="width:160px;"><?php esc_html_e( 'Zoek in artikelen', 'tbmm' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $links as $i => $row ) :
				$edit_url = admin_url( 'post.php?post=' . (int) $row->ID . '&action=edit' );
				$dest_url = $row->destination_url ?: '';
				$slug     = $row->post_name;
			?>
			<tr>
				<td class="alc-rank-cell"><?php echo esc_html( $i + 1 ); ?></td>
				<td>
					<a href="<?php echo esc_url( $edit_url ); ?>" target="_blank"
					   style="text-decoration:none; color:inherit;">
						<?php echo esc_html( $row->post_title ); ?>
					</a>
				</td>
				<td style="max-width:300px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
					<?php if ( $dest_url ) : ?>
					<a href="<?php echo esc_url( $dest_url ); ?>" target="_blank"
					   title="<?php echo esc_attr( $dest_url ); ?>"
					   style="font-size:12px; color:#646970; text-decoration:none;">
						<?php echo esc_html( $dest_url ); ?>
					</a>
					<?php else : ?>
					<span style="color:#b0b0b0; font-size:12px;">—</span>
					<?php endif; ?>
				</td>
				<td>
					<a href="<?php echo esc_url( $edit_url ); ?>" target="_blank"
					   class="button button-small">✎ <?php esc_html_e( 'Bewerk in TA', 'tbmm' ); ?></a>
				</td>
				<td>
					<button class="button button-small alc-fonq-find-btn"
					        data-slug="<?php echo esc_attr( $slug ); ?>"
					        data-row="<?php echo esc_attr( $i ); ?>">
						<?php esc_html_e( 'Zoek in artikelen', 'tbmm' ); ?>
					</button>
				</td>
			</tr>
			<tr class="alc-fonq-articles-row" id="alc-fonq-ar-<?php echo esc_attr( $i ); ?>" style="display:none;">
				<td colspan="5"></td>
			</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<script>
		(function() {
			var nonce = <?php echo wp_json_encode( $nonce ); ?>;

			document.querySelectorAll('.alc-fonq-find-btn').forEach(function(btn) {
				btn.addEventListener('click', function() {
					var slug  = btn.dataset.slug;
					var rowId = btn.dataset.row;
					var arRow = document.getElementById('alc-fonq-ar-' + rowId);
					var cell  = arRow ? arRow.querySelector('td') : null;

					if (!slug || !arRow || !cell) return;

					if (arRow.style.display !== 'none') {
						arRow.style.display = 'none';
						btn.textContent = <?php echo wp_json_encode( __( 'Zoek in artikelen', 'tbmm' ) ); ?>;
						return;
					}

					btn.disabled    = true;
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
								var html = '<ul class="alc-fonq-articles-list">';
								resp.data.articles.forEach(function(a) {
									html += '<li>'
										+ '<a href="' + escHtml(a.edit_url) + '" target="_blank">'
										+ escHtml(a.post_title)
										+ '</a></li>';
								});
								html += '</ul>';
								cell.innerHTML  = html;
								btn.textContent = <?php echo wp_json_encode( __( 'Verberg ▲', 'tbmm' ) ); ?>;
							} else {
								cell.innerHTML  = '<em style="font-size:12px; color:#646970;"><?php echo esc_js( __( 'Niet gevonden in gepubliceerde artikelen.', 'tbmm' ) ); ?></em>';
								btn.textContent = <?php echo wp_json_encode( __( 'Verberg ▲', 'tbmm' ) ); ?>;
							}
							arRow.style.display = '';
						})
						.catch(function() {
							btn.disabled    = false;
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
}
