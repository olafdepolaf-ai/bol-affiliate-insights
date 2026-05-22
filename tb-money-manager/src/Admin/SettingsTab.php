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
		<style>
			/* ── Instellingen layout ────────────────────────────────────────── */
			.tbmm-settings-wrap {
				max-width: 820px;
				display: flex;
				flex-direction: column;
				gap: 28px;
				padding-bottom: 40px;
			}

			/* ── Sectie-kaart ───────────────────────────────────────────────── */
			.tbmm-settings-card {
				background: #fff;
				border: 1px solid #ccd0d4;
				border-radius: 6px;
				overflow: hidden;
				box-shadow: 0 1px 3px rgba(0,0,0,.04);
			}

			.tbmm-settings-card-header {
				display: flex;
				align-items: center;
				gap: 14px;
				padding: 14px 22px;
				background: #f6f7f7;
				border-bottom: 1px solid #e0e0e0;
			}
			.tbmm-settings-card-icon {
				font-size: 22px;
				line-height: 1;
				flex-shrink: 0;
			}
			.tbmm-settings-card-title {
				font-size: 14px;
				font-weight: 600;
				color: #1d2327;
				margin: 0 0 2px;
				line-height: 1.3;
			}
			.tbmm-settings-card-desc {
				font-size: 12px;
				color: #646970;
				margin: 0;
				line-height: 1.4;
			}

			.tbmm-settings-card-body {
				padding: 22px 24px 24px;
			}

			/* Zorg dat form-tables in de kaart geen extra breedte pakken */
			.tbmm-settings-card-body .form-table {
				max-width: 100%;
				margin-top: 0;
			}
			.tbmm-settings-card-body .form-table th {
				width: 180px;
				padding-left: 0;
				font-weight: 600;
				color: #3c434a;
			}
			.tbmm-settings-card-body .form-table td {
				padding-left: 0;
			}
			.tbmm-settings-card-body h3 {
				margin-top: 0;
				font-size: 13px;
				color: #3c434a;
			}

			/* ── Divider binnen een kaart ────────────────────────────────────── */
			.tbmm-settings-divider {
				border: none;
				border-top: 1px solid #e8e8e8;
				margin: 20px 0;
			}

			/* ── Acties (knoppen) onderaan een sectie ───────────────────────── */
			.tbmm-settings-actions {
				display: flex;
				align-items: center;
				gap: 10px;
				flex-wrap: wrap;
				margin-top: 16px;
			}

			/* ── Verbindingsstatus badge ─────────────────────────────────────── */
			.tbmm-conn-badge {
				display: inline-flex;
				align-items: center;
				gap: 5px;
				padding: 3px 10px;
				border-radius: 20px;
				font-size: 12px;
				font-weight: 600;
			}
			.tbmm-conn-ok   { background: #edfaef; color: #00a32a; border: 1px solid #b8e6be; }
			.tbmm-conn-err  { background: #fcf0f1; color: #b32d2e; border: 1px solid #f5c6c6; }
			.tbmm-conn-none { background: #f0f0f1; color: #646970; border: 1px solid #ccd0d4; }

			@media (max-width: 600px) {
				.tbmm-settings-card-header { padding: 12px 16px; }
				.tbmm-settings-card-body   { padding: 16px; }
				.tbmm-settings-card-body .form-table th,
				.tbmm-settings-card-body .form-table td { display: block; width: 100%; padding-bottom: 6px; }
				.tbmm-settings-card-body .form-table input.regular-text { width: 100%; max-width: 100%; box-sizing: border-box; }
			}
		</style>

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
