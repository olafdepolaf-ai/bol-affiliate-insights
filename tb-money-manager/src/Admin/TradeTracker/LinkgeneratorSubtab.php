<?php

namespace TuinenBalkon\TBMoneyManager\Admin\TradeTracker;

use TuinenBalkon\TBMoneyManager\Service\TradeTrackerService;

class LinkgeneratorSubtab {

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

		$campaigns = $this->service->get_campaigns( $site_id );
		if ( is_wp_error( $campaigns ) ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html( $campaigns->get_error_message() ) . '</p></div>';
			return;
		}

		$material_urls = $this->service->get_text_material_urls( $site_id );
		if ( is_wp_error( $material_urls ) ) {
			$material_urls = [];
		}

		$campaign_list = [];
		foreach ( $campaigns as $c ) {
			$c = (object) $c;
			if ( ! empty( $c->ID ) && ! empty( $c->name ) ) {
				$campaign_list[] = [
					'id'   => (string) $c->ID,
					'name' => $c->name,
					'url'  => (string) ( $c->URL ?? '' ),
				];
			}
		}
		usort( $campaign_list, fn( $a, $b ) => strcmp( $a['name'], $b['name'] ) );
		?>

		<div class="alc-gen-wrap">
			<div class="alc-gen-field">
				<label for="alc-gen-campaign"><?php esc_html_e( 'Campagne', 'tbmm' ); ?></label>
				<select id="alc-gen-campaign">
					<option value=""><?php esc_html_e( '— Selecteer campagne —', 'tbmm' ); ?></option>
					<?php foreach ( $campaign_list as $c ) : ?>
					<option value="<?php echo esc_attr( $c['id'] ); ?>"><?php echo esc_html( $c['name'] ); ?></option>
					<?php endforeach; ?>
				</select>
				<p class="description" style="margin-top:4px;"><?php esc_html_e( 'Alleen geaccepteerde campagnes.', 'tbmm' ); ?></p>
			</div>

			<div class="alc-gen-field">
				<label for="alc-gen-url"><?php esc_html_e( 'Doel-URL', 'tbmm' ); ?> <span style="font-weight:400; color:#646970;">(<?php esc_html_e( 'leeg = homepage van campagne', 'tbmm' ); ?>)</span></label>
				<input type="url" id="alc-gen-url" placeholder="https://www.voorbeeld.nl/pagina/" class="regular-text" />
				<div class="alc-gen-url-hint" id="alc-gen-url-hint"></div>
			</div>

			<div class="alc-gen-field">
				<label for="alc-gen-ref"><?php esc_html_e( 'Referentie', 'tbmm' ); ?> <span style="font-weight:400; color:#646970;">(<?php esc_html_e( 'optioneel', 'tbmm' ); ?>)</span></label>
				<input type="text" id="alc-gen-ref" placeholder="<?php esc_attr_e( 'bijv. loopkamille', 'tbmm' ); ?>" class="regular-text" maxlength="64" />
				<p class="description" style="margin-top:4px;"><?php esc_html_e( 'Gebruik alleen letters, cijfers en koppeltekens.', 'tbmm' ); ?></p>
			</div>

			<div class="alc-gen-result" id="alc-gen-result">
				<label><?php esc_html_e( 'Gegenereerde tekstlink', 'tbmm' ); ?></label>
				<div class="alc-gen-result-url" id="alc-gen-result-url"></div>
				<div class="alc-gen-source" id="alc-gen-source"></div>
				<div class="alc-gen-copy">
					<button type="button" class="button" id="alc-gen-copy-btn"><?php esc_html_e( 'Kopieer link', 'tbmm' ); ?></button>
					<span class="alc-gen-copied" id="alc-gen-copied">✓ <?php esc_html_e( 'Gekopieerd!', 'tbmm' ); ?></span>
				</div>
			</div>
		</div>

		<script>
		(function() {
			var siteId       = <?php echo wp_json_encode( $site_id ); ?>;
			var materialUrls = <?php echo wp_json_encode( (object) $material_urls ); ?>;
			var campaigns    = <?php echo wp_json_encode( $campaign_list ); ?>;

			var elCamp   = document.getElementById('alc-gen-campaign');
			var elUrl    = document.getElementById('alc-gen-url');
			var elRef    = document.getElementById('alc-gen-ref');
			var elHint   = document.getElementById('alc-gen-url-hint');
			var elResult = document.getElementById('alc-gen-result');
			var elOut    = document.getElementById('alc-gen-result-url');
			var elSrc    = document.getElementById('alc-gen-source');
			var elCopy   = document.getElementById('alc-gen-copy-btn');
			var elCopied = document.getElementById('alc-gen-copied');

			function hostname(url) {
				try { return new URL(url).hostname.replace(/^www\./, ''); }
				catch(e) { return null; }
			}

			function detectCampaign(destUrl) {
				var host = hostname(destUrl);
				if (!host) return null;
				for (var i = 0; i < campaigns.length; i++) {
					var c = campaigns[i];
					if (!c.url) continue;
					var ch = hostname(c.url);
					if (ch && (host === ch || host.endsWith('.' + ch) || ch.endsWith('.' + host))) {
						return c.id;
					}
				}
				return null;
			}

			function showHint(msg, type) {
				elHint.textContent    = msg;
				elHint.className      = 'alc-gen-url-hint ' + type;
				elHint.style.display  = 'block';
			}
			function hideHint() { elHint.style.display = 'none'; }

			function generate() {
				var campaignId = elCamp.value;
				if (!campaignId) { elResult.style.display = 'none'; return; }

				var destUrl = elUrl.value.trim();
				var ref     = elRef.value.trim().replace(/[^a-zA-Z0-9\-_]/g, '');
				var baseUrl = materialUrls[campaignId] || null;
				var link, source;

				if (baseUrl) {
					link = baseUrl + ref;
					if (destUrl) {
						try {
							var parsed = new URL(destUrl);
							link += '&r=' + encodeURIComponent(parsed.pathname + parsed.search + parsed.hash);
						} catch(e) {}
					}
					source = '✓ Merchant-domein (via TradeTracker materiaal API)';
				} else {
					link = 'https://tc.tradetracker.net/?c=' + encodeURIComponent(campaignId)
					     + '&m=12&a=' + encodeURIComponent(siteId);
					if (ref)     link += '&r=' + encodeURIComponent(ref);
					if (destUrl) link += '&u=' + encodeURIComponent(destUrl);
					source = '↩ Fallback: tc.tradetracker.net (geen tekstmateriaal gevonden voor deze campagne)';
				}

				elOut.textContent      = link;
				elSrc.textContent      = source;
				elResult.style.display = 'block';
				elCopied.style.display = 'none';
			}

			elUrl.addEventListener('input', function() {
				var destUrl = elUrl.value.trim();

				if (!destUrl) { hideHint(); generate(); return; }

				var detectedId = detectCampaign(destUrl);

				if (detectedId) {
					if (elCamp.value !== detectedId) {
						elCamp.value = detectedId;
						var name = campaigns.find(function(c){ return c.id === detectedId; });
						showHint('✓ Campagne automatisch geselecteerd: ' + (name ? name.name : detectedId), 'success');
					} else {
						hideHint();
					}
				} else {
					showHint('⚠ Domein herkend niet als een van je campagnes — controleer de selectie.', 'warning');
				}

				generate();
			});

			elCamp.addEventListener('change', function() {
				var destUrl = elUrl.value.trim();
				if (!destUrl) { hideHint(); generate(); return; }

				var detectedId = detectCampaign(destUrl);
				if (detectedId && elCamp.value !== detectedId) {
					var name = campaigns.find(function(c){ return c.id === detectedId; });
					showHint('⚠ De ingevulde URL hoort bij een andere campagne' + (name ? ' (' + name.name + ')' : '') + '.', 'warning');
				} else {
					hideHint();
				}

				generate();
			});

			elRef.addEventListener('input', generate);

			elCopy.addEventListener('click', function() {
				var text = elOut.textContent;
				if (!text) return;
				if (navigator.clipboard) {
					navigator.clipboard.writeText(text).then(function() {
						elCopied.style.display = 'inline';
						setTimeout(function(){ elCopied.style.display = 'none'; }, 2000);
					});
				} else {
					var ta = document.createElement('textarea');
					ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
					document.body.appendChild(ta); ta.select(); document.execCommand('copy');
					document.body.removeChild(ta);
					elCopied.style.display = 'inline';
					setTimeout(function(){ elCopied.style.display = 'none'; }, 2000);
				}
			});
		})();
		</script>
		<?php
	}
}
