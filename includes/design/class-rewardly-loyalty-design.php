<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
class Rewardly_Loyalty_Design {
	public static function init() {}

	public static function get_front_inline_css() {
		$s = Rewardly_Loyalty_Helpers::get_settings();
		if ( isset( $s['enable_front_styles'] ) && 'no' === $s['enable_front_styles'] ) { return ''; }

		$template        = $s['design_template'] ?? 'default';
		$icon_style      = $s['icon_style'] ?? 'default';
		$button_style    = $s['button_style'] ?? 'rounded';
		$card_style      = $s['card_style'] ?? 'soft';
		$typography      = $s['typography_preset'] ?? 'default';
		$preset = self::get_template_values( $template );

		$p = sanitize_hex_color( $s['primary_color'] ?? $preset['primary_color'] ) ?: $preset['primary_color'];
		$a = sanitize_hex_color( $s['accent_color'] ?? $preset['accent_color'] ) ?: $preset['accent_color'];
		$r = isset( $s['border_radius'] ) ? max( 0, min( 30, (int) $s['border_radius'] ) ) : (int) $preset['radius'];
		$soft = $preset['soft'];
		$border = $preset['border'];
		$font_size = 'default' === $typography ? '1rem' : ( 'compact' === $typography ? '.95rem' : '1.02rem' );
		$heading_size = 'default' === $typography ? '28px' : ( 'compact' === $typography ? '25px' : '30px' );
		$button_radius = 'rounded' === $button_style ? '999px' : ( 'solid' === $button_style ? '12px' : max( 4, $r ) . 'px' );
		$button_bg = 'outline' === $button_style ? 'transparent' : 'var(--rewardly-primary)';
		$button_color = 'outline' === $button_style ? 'var(--rewardly-primary)' : '#fff';
		$button_border = 'outline' === $button_style ? '1px solid var(--rewardly-primary)' : 'none';
		$icon_radius = 'circle' === $icon_style ? '999px' : ( 'minimal' === $icon_style ? '0' : '8px' );
		$icon_bg = 'minimal' === $icon_style ? 'transparent' : 'rgba(245,166,35,.12)';
		$card_background = 'filled' === $card_style ? 'var(--rewardly-soft)' : '#fff';
		$card_shadow = 'outline' === $card_style ? 'none' : '0 4px 14px rgba(0,0,0,.03)';

		return ":root{--rewardly-primary:{$p};--rewardly-accent:{$a};--rewardly-radius:{$r}px;--rewardly-orange:var(--rewardly-accent);--rewardly-black:var(--rewardly-primary);--rewardly-soft:{$soft};--rewardly-border:{$border};--rewardly-font-size:{$font_size};--rewardly-heading-size:{$heading_size};--rewardly-button-radius:{$button_radius};--rewardly-button-bg:{$button_bg};--rewardly-button-color:{$button_color};--rewardly-button-border:{$button_border};--rewardly-icon-radius:{$icon_radius};--rewardly-icon-bg:{$icon_bg};--rewardly-card-bg:{$card_background};--rewardly-card-shadow:{$card_shadow}}.rewardly-loyalty-account,.rewardly-shortcode-account,.rewardly-shortcode-history,.rewardly-loyalty-box,.rewardly-product-points-notice,.rewardly-cart-points-notice,.rewardly-checkout-guest-notice{font-size:var(--rewardly-font-size)}.rewardly-loyalty-account h3{font-size:var(--rewardly-heading-size)!important}.rewardly-loyalty-card,.rewardly-loyalty-box,.rewardly-product-points-notice,.rewardly-cart-points-notice,.rewardly-checkout-guest-notice,.rewardly-shortcode-account,.rewardly-shortcode-history{border-radius:var(--rewardly-radius)!important;background:var(--rewardly-card-bg)!important;box-shadow:var(--rewardly-card-shadow)!important}.rewardly-loyalty-btn,.button.rewardly-loyalty-btn{background:var(--rewardly-button-bg)!important;border:var(--rewardly-button-border)!important;border-color:var(--rewardly-primary)!important;color:var(--rewardly-button-color)!important;border-radius:var(--rewardly-button-radius)!important}.rewardly-product-points-notice__icon,.rewardly-cart-points-notice__icon,.rewardly-checkout-guest-notice__icon,.rewardly-loyalty-box__icon{color:var(--rewardly-accent)!important;background:var(--rewardly-icon-bg)!important;border-radius:var(--rewardly-icon-radius)!important;padding:" . ( 'minimal' === $icon_style ? '0' : '6px' ) . " !important}.rewardly-history-toggle,.rewardly-shortcode-account__title,.rewardly-product-points-notice__points,.rewardly-product-points-notice__amount,.rewardly-loyalty-account h3,.rewardly-loyalty-account h4{color:var(--rewardly-primary)!important}.rewardly-product-points-notice,.rewardly-cart-points-notice,.rewardly-checkout-guest-notice,.rewardly-shortcode-account,.rewardly-shortcode-history,.rewardly-loyalty-box--connected{border-color:var(--rewardly-border)!important}.rewardly-log-type--revoke,.rewardly-log-type--adjust,.rewardly-log-type--adjust_add{color:var(--rewardly-accent)!important}.rewardly-log-amount--revoke,.rewardly-log-amount--adjust,.rewardly-log-amount--adjust_add{color:var(--rewardly-accent)!important}";
	}

	private static function get_template_values( $template ) {
		$templates = array(
			'default' => array(
				'primary_color' => '#111111',
				'accent_color'  => '#f5a623',
				'radius'        => 12,
				'soft'          => '#fffaf4',
				'border'        => 'rgba(245,166,35,.20)',
			),
			'minimal' => array(
				'primary_color' => '#111111',
				'accent_color'  => '#6b7280',
				'radius'        => 8,
				'soft'          => '#ffffff',
				'border'        => 'rgba(17,17,17,.10)',
			),
			'soft' => array(
				'primary_color' => '#2d3748',
				'accent_color'  => '#c084fc',
				'radius'        => 16,
				'soft'          => '#faf5ff',
				'border'        => 'rgba(192,132,252,.22)',
			),
		);
		return $templates[ $template ] ?? $templates['default'];
	}
}
