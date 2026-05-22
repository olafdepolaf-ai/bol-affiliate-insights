<?php

namespace TuinenBalkon\TBMoneyManager\Admin\TradeTracker;

use TuinenBalkon\TBMoneyManager\Service\TradeTrackerService;

class ProductfeedSubtab {

	private TradeTrackerService $service;

	public function __construct( TradeTrackerService $service ) {
		$this->service = $service;
	}

	public function render(): void {
		$customer_id = get_option( 'tbmm_tt_customer_id', '' );
		$access_key  = get_option( 'tbmm_tt_access_key', '' );
		if ( empty( $customer_id ) || empty( $access_key ) ) {
			echo '<p><em>' . esc_html__( 'Vul eerst de inloggegevens in via het tabblad Instellingen.', 'tbmm' ) . '</em></p>';
			return;
		}

		$site_id = $this->service->get_primary_site_id();
		if ( is_wp_error( $site_id ) ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html( $site_id->get_error_message() ) . '</p></div>';
			return;
		}

		$feeds_result = $this->service->get_feeds( $site_id, 'accepted' );
		if ( is_wp_error( $feeds_result ) ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html( $feeds_result->get_error_message() ) . '</p></div>';
			return;
		}

		$feeds_list     = [];
		$campaigns_seen = [];
		foreach ( $feeds_result as $feed ) {
			$f           = is_object( $feed ) ? $feed : (object) $feed;
			$feed_id     = (int) ( $f->ID ?? 0 );
			$feed_name   = (string) ( $f->name ?? '' );
			$camp        = is_object( $f->campaign ?? null ) ? $f->campaign : null;
			$camp_id     = $camp ? (string) ( $camp->ID ?? '' ) : '';
			$camp_name   = $camp ? (string) ( $camp->name ?? '' ) : '';

			if ( ! $feed_id ) {
				continue;
			}
			$feeds_list[] = [
				'id'            => $feed_id,
				'name'          => $feed_name,
				'campaign_id'   => $camp_id,
				'campaign_name' => $camp_name,
			];
			if ( $camp_id && ! isset( $campaigns_seen[ $camp_id ] ) ) {
				$campaigns_seen[ $camp_id ] = $camp_name;
			}
		}
		asort( $campaigns_seen );

		$nonce = wp_create_nonce( 'tbmm_tt_feed_nonce' );
		?>

		<div class="alc-pf-filters">
			<div>
				<label for="alc-pf-campaign"><?php esc_html_e( 'Campagne', 'tbmm' ); ?></label>
				<select id="alc-pf-campaign" style="min-width:180px;">
					<option value=""><?php esc_html_e( '— Alle campagnes —', 'tbmm' ); ?></option>
					<?php foreach ( $campaigns_seen as $cid => $cname ) : ?>
					<option value="<?php echo esc_attr( $cid ); ?>"><?php echo esc_html( $cname ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div>
				<label for="alc-pf-feed"><?php esc_html_e( 'Productfeed', 'tbmm' ); ?></label>
				<select id="alc-pf-feed" style="min-width:220px;">
					<option value="0"><?php esc_html_e( '— Alle productfeeds —', 'tbmm' ); ?></option>
					<?php foreach ( $feeds_list as $f ) : ?>
					<option value="<?php echo esc_attr( $f['id'] ); ?>"
					        data-campaign="<?php echo esc_attr( $f['campaign_id'] ); ?>">
						<?php echo esc_html( $f['name'] ); ?>
					</option>
					<?php endforeach; ?>
				</select>
			</div>
			<div>
				<label for="alc-pf-search"><?php esc_html_e( 'Zoekwoord', 'tbmm' ); ?></label>
				<input type="text" id="alc-pf-search" placeholder="<?php esc_attr_e( 'bijv. vogelvoer', 'tbmm' ); ?>" class="regular-text" style="width:200px;" />
			</div>
			<div>
				<label for="alc-pf-perpage"><?php esc_html_e( 'Per pagina', 'tbmm' ); ?></label>
				<select id="alc-pf-perpage">
					<option value="10">10</option>
					<option value="25" selected>25</option>
					<option value="50">50</option>
					<option value="100">100</option>
					<option value="500">500</option>
				</select>
			</div>
			<div>
				<label>&nbsp;</label>
				<button type="button" id="alc-pf-search-btn" class="button button-primary"><?php esc_html_e( 'Zoeken', 'tbmm' ); ?></button>
			</div>
		</div>

		<div id="alc-pf-results" class="alc-pf-results"></div>

		<script>
		(function() {
			var nonce     = <?php echo wp_json_encode( $nonce ); ?>;
			var feedsList = <?php echo wp_json_encode( $feeds_list ); ?>;

			var elCampaign  = document.getElementById('alc-pf-campaign');
			var elFeed      = document.getElementById('alc-pf-feed');
			var elSearch    = document.getElementById('alc-pf-search');
			var elPerPage   = document.getElementById('alc-pf-perpage');
			var elSearchBtn = document.getElementById('alc-pf-search-btn');
			var elResults   = document.getElementById('alc-pf-results');

			var currentPage = 1;
			var lastFeedId  = 0;
			var lastSearch  = '';
			var lastPerPage = 25;

			elCampaign.addEventListener('change', function() {
				var selectedCamp = elCampaign.value;
				var prevFeed     = elFeed.value;
				var opts         = elFeed.querySelectorAll('option');

				opts.forEach(function(opt) {
					if (opt.value === '0') return;
					var campAttr = opt.getAttribute('data-campaign');
					opt.style.display = (!selectedCamp || campAttr === selectedCamp) ? '' : 'none';
				});

				if (prevFeed && prevFeed !== '0') {
					var selOpt = elFeed.querySelector('option[value="' + prevFeed + '"]');
					if (selOpt && selOpt.style.display === 'none') {
						elFeed.value = '0';
					}
				}
			});

			elSearch.addEventListener('keydown', function(e) {
				if (e.key === 'Enter') { currentPage = 1; doSearch(); }
			});

			elSearchBtn.addEventListener('click', function() {
				currentPage = 1;
				doSearch();
			});

			function doSearch() {
				var feedId  = elFeed.value || '0';
				var search  = elSearch.value.trim();
				var perPage = parseInt(elPerPage.value, 10);

				if (feedId === '0' && !elCampaign.value && !search) {
					elResults.innerHTML = '<p class="alc-pf-status"><?php echo esc_js( __( 'Selecteer een campagne of voer een zoekwoord in.', 'tbmm' ) ); ?></p>';
					return;
				}

				currentPage = 1;
				lastFeedId  = feedId;
				lastSearch  = search;
				lastPerPage = perPage;

				elSearchBtn.disabled    = true;
				elSearchBtn.textContent = '<?php echo esc_js( __( 'Laden…', 'tbmm' ) ); ?>';
				elResults.innerHTML     = '<p class="alc-pf-status"><?php echo esc_js( __( 'Producten ophalen…', 'tbmm' ) ); ?></p>';

				fetchProducts(feedId, search, perPage, currentPage, elCampaign.value, function(resp) {
					elSearchBtn.disabled    = false;
					elSearchBtn.textContent = '<?php echo esc_js( __( 'Zoeken', 'tbmm' ) ); ?>';

					if (!resp.success) {
						elResults.innerHTML = '<p class="alc-pf-status" style="color:#d63638;">' + escHtml(resp.data.message || '<?php echo esc_js( __( 'Fout bij ophalen.', 'tbmm' ) ); ?>') + '</p>';
						return;
					}

					var data = resp.data;
					if (!data.products || data.products.length === 0) {
						elResults.innerHTML = '<p class="alc-pf-status"><?php echo esc_js( __( 'Geen producten gevonden.', 'tbmm' ) ); ?></p>';
						return;
					}

					var showFeedCol = data.all_feeds && data.products.some(function(p){ return p.feed_name; });
					elResults.innerHTML = buildTable(data.products, showFeedCol);

					if (data.all_feeds && data.total_found > data.products.length) {
						var note = document.createElement('p');
						note.style.cssText = 'font-size:12px; color:#646970; margin-top:8px;';
						note.textContent = data.total_found + ' <?php echo esc_js( __( 'resultaten gevonden — selecteer een specifieke feed voor meer resultaten.', 'tbmm' ) ); ?>';
						elResults.appendChild(note);
					}

					if (data.has_more) {
						var moreWrap = document.createElement('div');
						moreWrap.className = 'alc-pf-more';
						moreWrap.innerHTML = '<button type="button" class="button" id="alc-pf-more-btn"><?php echo esc_js( __( 'Volgende', 'tbmm' ) ); ?> ' + escHtml(perPage) + ' <?php echo esc_js( __( 'laden →', 'tbmm' ) ); ?></button>';
						elResults.appendChild(moreWrap);
						document.getElementById('alc-pf-more-btn').addEventListener('click', function() {
							currentPage++;
							loadMore();
						});
					}
				});
			}

			function loadMore() {
				var moreBtn = document.getElementById('alc-pf-more-btn');
				if (moreBtn) { moreBtn.disabled = true; moreBtn.textContent = '<?php echo esc_js( __( 'Laden…', 'tbmm' ) ); ?>'; }

				fetchProducts(lastFeedId, lastSearch, lastPerPage, currentPage, elCampaign.value, function(resp) {
					if (moreBtn && moreBtn.parentNode) moreBtn.parentNode.remove();

					if (!resp.success || !resp.data.products || resp.data.products.length === 0) return;

					var data  = resp.data;
					var tbody = elResults.querySelector('tbody');
					if (tbody) {
						var tmp = document.createElement('table');
						tmp.innerHTML = '<tbody>' + buildRows(data.products, false) + '</tbody>';
						Array.from(tmp.querySelector('tbody').children).forEach(function(row) {
							tbody.appendChild(row);
						});
					}

					if (data.has_more) {
						var moreWrap2 = document.createElement('div');
						moreWrap2.className = 'alc-pf-more';
						moreWrap2.innerHTML = '<button type="button" class="button" id="alc-pf-more-btn"><?php echo esc_js( __( 'Volgende', 'tbmm' ) ); ?> ' + escHtml(lastPerPage) + ' <?php echo esc_js( __( 'laden →', 'tbmm' ) ); ?></button>';
						elResults.appendChild(moreWrap2);
						document.getElementById('alc-pf-more-btn').addEventListener('click', function() {
							currentPage++;
							loadMore();
						});
					}
				});
			}

			function fetchProducts(feedId, search, perPage, page, campaignId, callback) {
				var fd = new FormData();
				fd.append('action',      'tbmm_feed_search');
				fd.append('nonce',       nonce);
				fd.append('feed_id',     feedId);
				fd.append('search',      search);
				fd.append('per_page',    perPage);
				fd.append('page',        page);
				fd.append('campaign_id', campaignId || '');

				fetch(ajaxurl, { method: 'POST', body: fd })
					.then(function(r) { return r.json(); })
					.then(callback)
					.catch(function() {
						callback({ success: false, data: { message: '<?php echo esc_js( __( 'Verbindingsfout.', 'tbmm' ) ); ?>' } });
					});
			}

			function buildTable(products, showFeedCol) {
				return '<table class="alc-pf-table">'
					+ '<thead><tr>'
					+ '<th class="alc-pf-photo-col"><?php echo esc_js( __( 'Foto', 'tbmm' ) ); ?></th>'
					+ '<th><?php echo esc_js( __( 'Naam & beschrijving', 'tbmm' ) ); ?></th>'
					+ (showFeedCol ? '<th class="alc-pf-campaign-col"><?php echo esc_js( __( 'Feed', 'tbmm' ) ); ?></th>' : '<th class="alc-pf-campaign-col"><?php echo esc_js( __( 'Categorie', 'tbmm' ) ); ?></th>')
					+ '<th class="alc-pf-price-col"><?php echo esc_js( __( 'Prijs', 'tbmm' ) ); ?></th>'
					+ '<th class="alc-pf-action-col"><?php echo esc_js( __( 'Actie', 'tbmm' ) ); ?></th>'
					+ '</tr></thead>'
					+ '<tbody>' + buildRows(products, showFeedCol) + '</tbody>'
					+ '</table>';
			}

			function buildRows(products, showFeedCol) {
				return products.map(function(p) {
					var img = p.image
						? '<img src="' + escAttr(p.image) + '" alt="" loading="lazy" />'
						: '<div class="alc-pf-no-img"><?php echo esc_js( __( 'Geen foto', 'tbmm' ) ); ?></div>';

					var price  = p.price ? '€&nbsp;' + escHtml(p.price) : '—';
					var action = p.url
						? '<a href="' + escAttr(p.url) + '" target="_blank" rel="noopener" class="button button-small">↗ <?php echo esc_js( __( 'Bekijk', 'tbmm' ) ); ?></a>'
						: '—';
					var midCol = showFeedCol ? escHtml(p.feed_name) : escHtml(p.category);

					return '<tr>'
						+ '<td class="alc-pf-photo-col">' + img + '</td>'
						+ '<td>'
						+   '<div class="alc-pf-name">' + escHtml(p.name) + '</div>'
						+   (p.desc ? '<div class="alc-pf-desc">' + escHtml(p.desc) + '</div>' : '')
						+ '</td>'
						+ '<td class="alc-pf-campaign-col">' + midCol + '</td>'
						+ '<td class="alc-pf-price-col">' + price + '</td>'
						+ '<td class="alc-pf-action-col">' + action + '</td>'
						+ '</tr>';
				}).join('');
			}

			function escHtml(str) {
				var d = document.createElement('div');
				d.appendChild(document.createTextNode(String(str || '')));
				return d.innerHTML;
			}

			function escAttr(str) {
				return String(str || '').replace(/"/g, '&quot;');
			}
		})();
		</script>
		<?php
	}
}
