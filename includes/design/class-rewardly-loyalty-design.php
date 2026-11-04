<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
class Rewardly_Loyalty_Design {
	public static function init() {}

	public static function component_variables() { return array('button_background'=>'--rewardly-button-bg','button_text'=>'--rewardly-button-color','button_hover_background'=>'--rewardly-button-hover-bg','button_hover_text'=>'--rewardly-button-hover-color','card_background'=>'--rewardly-card-bg','card_border'=>'--rewardly-border','text_color'=>'--rewardly-black','info_background'=>'--rewardly-soft','info_text'=>'--rewardly-info-color'); }
	public static function component_selectors() { return array('cart'=>':where([data-nodevora-context="cart"],.woocommerce-cart .rewardly-loyalty-box)','checkout'=>':where([data-nodevora-context="checkout"],.woocommerce-checkout .rewardly-loyalty-box)','account'=>':where(.rewardly-loyalty-account,.rewardly-shortcode-account)','history'=>':where(.rewardly-shortcode-history,.rewardly-loyalty-account .shop_table)','notice'=>':where(.rewardly-product-points-notice,.rewardly-cart-points-notice,.rewardly-checkout-guest-notice)'); }
	public static function presets(){return array('default'=>self::get_template_values('default'),'minimal'=>self::get_template_values('minimal'),'soft'=>self::get_template_values('soft'));}
	public static function additional_colors() { static $keys=null;if(null!==$keys){return$keys;}$keys=array('text_color','info_background','info_text');foreach(array_keys(self::component_selectors())as$prefix){foreach(array_keys(self::component_variables())as$key){$keys[]=$prefix.'_'.$key;}}return$keys; }

	public static function get_front_inline_css() {
		$s = Rewardly_Loyalty_Helpers::get_settings();
		if ( 'no' === $s['enable_front_styles'] ) { return ''; }
		$preset = self::get_template_values( $s['design_template'] );
		$color = static function( $key, $fallback ) use ( $s ) { return sanitize_hex_color( $s[$key] ?? '' ) ?: $fallback; };
		$p = $color( 'primary_color', $preset['primary_color'] ); $body_text=$color('text_color',$p);$info_bg=$color('info_background',$preset['soft']);$info_text=$color('info_text',$body_text); $a = $color( 'accent_color', $preset['accent_color'] );
		$r = max( 0, min( 30, (int) $s['border_radius'] ) );
		$background = $color( 'button_background', 'outline' === $s['button_style'] ? '#ffffff' : $p );
		$text = $color( 'button_text', 'outline' === $s['button_style'] ? $p : '#ffffff' );
		$hover = $color( 'button_hover_background', '#243b53' ); $hover_text = $color( 'button_hover_text', '#ffffff' );
		$card = $color( 'card_background', 'filled' === $s['card_style'] ? $preset['soft'] : '#ffffff' );
		$border = $color( 'card_border', '#cbd5e1' );$shadow='outline'===$s['card_style']?'none':'0 4px 20px rgb(15 23 42 / 4%)';
		$padding = 'compact' === ( $s['density'] ?? '' ) ? '12px' : '20px';
		$font = 'compact' === $s['typography_preset'] ? '.9375rem' : '1rem';
		$button_radius = 'rounded' === $s['button_style'] ? '999px' : $r . 'px';
		$scope = ':where(.rewardly-loyalty-account,.rewardly-shortcode-account,.rewardly-shortcode-history,.rewardly-loyalty-card,.rewardly-loyalty-box,.rewardly-product-points-notice,.rewardly-cart-points-notice,.rewardly-checkout-guest-notice)';
		 $css = $scope . "{--rewardly-primary:{$p};--rewardly-accent:{$a};--rewardly-black:{$body_text};--rewardly-orange:{$a};--rewardly-soft:{$info_bg};--rewardly-info-color:{$info_text};--rewardly-border:{$border};--rewardly-radius:{$r}px;--rewardly-radius-sm:{$r}px;--rewardly-radius-md:{$r}px;--rewardly-radius-lg:{$r}px;--rewardly-button-bg:{$background};--rewardly-button-color:{$text};--rewardly-button-hover-bg:{$hover};--rewardly-button-hover-color:{$hover_text};--rewardly-button-radius:{$button_radius};--rewardly-card-bg:{$card};--rewardly-card-shadow:{$shadow};--rewardly-padding:{$padding};font-size:{$font};}";
		foreach(self::component_selectors()as$prefix=>$selector){$values='';foreach(self::component_variables()as$key=>$variable){$override=sanitize_hex_color($s[$prefix.'_'.$key]??'');if($override){$values.=$variable.':'.$override.';';}}if($values){$css.=$selector.'{'.$values.'}';}}
		return $css;
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
