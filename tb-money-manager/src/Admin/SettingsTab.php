<?php

namespace TuinenBalkon\TBMoneyManager\Admin;

class SettingsTab {

	private BolTab          $bol_tab;
	private TradeTrackerTab $tt_tab;

	public function __construct( BolTab $bol_tab, TradeTrackerTab $tt_tab ) {
		$this->bol_tab = $bol_tab;
		$this->tt_tab  = $tt_tab;
	}

	public function render(): void {
		?>
<div class="tbmm-settings-wrap">

			<?php $this->render_section(
				'🛍️',
				__( 'Bol.com', 'tbmm' ),
				__( 'API credentials voor de Bol.com Affiliate Insights API. Maak een Client ID en Secret aan via het Bol.com partnerplatform.', 'tbmm' ),
				function() { $this->bol_tab->render_settings(); }
			); ?>

			<?php $this->render_section(
				'📊',
				__( 'TradeTracker', 'tbmm' ),
				__( 'Klant-ID en toegangssleutel voor de TradeTracker Publisher API. Te vinden in je TradeTracker dashboard onder Account → API.', 'tbmm' ),
				function() { $this->tt_tab->render_settings(); }
			); ?>

		</div>
		<?php
	}

	/**
	 * Rendert één instellingen-kaart.
	 *
	 * @param string   $icon     Emoji of karakter voor het icoontje.
	 * @param string   $title    Sectie-titel.
	 * @param string   $desc     Korte beschrijving onder de titel.
	 * @param callable $content  Callback die de body-HTML uitvoert.
	 */
	private function render_section( string $icon, string $title, string $desc, callable $content ): void {
		?>
		<div class="tbmm-settings-card">
			<div class="tbmm-settings-card-header">
				<span class="tbmm-settings-card-icon" aria-hidden="true"><?php echo esc_html( $icon ); ?></span>
				<div>
					<p class="tbmm-settings-card-title"><?php echo esc_html( $title ); ?></p>
					<p class="tbmm-settings-card-desc"><?php echo esc_html( $desc ); ?></p>
				</div>
			</div>
			<div class="tbmm-settings-card-body">
				<?php $content(); ?>
			</div>
		</div>
		<?php
	}
}
