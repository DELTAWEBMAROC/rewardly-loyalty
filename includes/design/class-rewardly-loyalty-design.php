<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
class Rewardly_Loyalty_Design {
	public static function init() {}

	public static function get_front_inline_css() {
		$s = Rewardly_Loyalty_Helpers::get_settings();
		if ( 'no' === $s['enable_front_styles'] ) { return ''; }
		$preset = self::get_template_values( $s['design_template'] );
		$color = static function( $key, $fallback ) use ( $s ) { return sanitize_hex_color( $s[$key] ?? '' ) ?: $fallback; };
		$p = $color( 'primary_color', $preset['primary_color'] ); $a = $color( 'accent_color', $preset['accent_color'] );
		$r = max( 0, min( 30, (int) $s['border_radius'] ) );
		$background = $color( 'button_background', 'outline' === $s['button_style'] ? '#ffffff' : $p );
		$text = $color( 'button_text', 'outline' === $s['button_style'] ? $p : '#ffffff' );
		$hover = $color( 'button_hover_background', '#243b53' ); $hover_text = $color( 'button_hover_text', '#ffffff' );
		$card = $color( 'card_background', 'filled' === $s['card_style'] ? $preset['soft'] : '#ffffff' );
		$border = $color( 'card_border', '#cbd5e1' );
		$padding = 'compact' === ( $s['density'] ?? '' ) ? '12px' : '20px';
		$font = 'compact' === $s['typography_preset'] ? '.9375rem' : '1rem';
		$button_radius = 'rounded' === $s['button_style'] ? '999px' : $r . 'px';
		$scope = ':where(.rewardly-loyalty-account,.rewardly-shortcode-account,.rewardly-shortcode-history,.rewardly-loyalty-card,.rewardly-loyalty-box,.rewardly-product-points-notice,.rewardly-cart-points-notice,.rewardly-checkout-guest-notice)';
		return $scope . "{--rewardly-primary:{$p};--rewardly-accent:{$a};--rewardly-black:{$p};--rewardly-orange:{$a};--rewardly-soft:{$preset['soft']};--rewardly-border:{$border};--rewardly-radius:{$r}px;--rewardly-radius-sm:{$r}px;--rewardly-radius-md:{$r}px;--rewardly-radius-lg:{$r}px;--rewardly-button-bg:{$background};--rewardly-button-color:{$text};--rewardly-button-hover-bg:{$hover};--rewardly-button-hover-color:{$hover_text};--rewardly-button-radius:{$button_radius};--rewardly-card-bg:{$card};--rewardly-padding:{$padding};font-size:{$font};}";
	}

	private static function get_template_values( $template ) {
		$templates = array(
			'default' => array(
				'primary_color' => '#0b1638',
				'accent_color'  => '#12b8a6',
				'radius'        => 12,
				'soft'          => '#e9faf7',
				'border'        => 'rgba(18,184,166,.22)',
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
