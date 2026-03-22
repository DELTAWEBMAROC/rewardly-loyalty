<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
class Rewardly_Loyalty_Admin {
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	public static function add_menu() {
		add_submenu_page( 'woocommerce', __( 'Rewardly Loyalty Program', 'rewardly-loyalty' ), __( 'Rewardly Loyalty', 'rewardly-loyalty' ), 'manage_woocommerce', 'rewardly-loyalty-settings', array( __CLASS__, 'render_page' ) );
	}

	public static function enqueue_assets( $hook ) {
		$is_settings_page = 'woocommerce_page_rewardly-loyalty-settings' === $hook;
		$is_product_page  = in_array( $hook, array( 'post.php', 'post-new.php' ), true ) && 'product' === get_post_type();

		if ( ! $is_settings_page && ! $is_product_page ) {
			return;
		}

		wp_register_style( 'rewardly-loyalty-admin-inline', false, array(), REWARDLY_LOYALTY_VERSION );
		wp_enqueue_style( 'rewardly-loyalty-admin-inline' );
		wp_add_inline_style( 'rewardly-loyalty-admin-inline', '.rewardly-admin-tabs{display:flex;gap:8px;margin:18px 0 24px;flex-wrap:wrap}.rewardly-admin-tab{display:inline-flex;align-items:center;gap:8px;padding:10px 14px;background:#fff;border:1px solid #dcdcde;border-radius:8px;text-decoration:none;color:#1d2327}.rewardly-admin-tab.is-active{background:#111;color:#fff;border-color:#111}.rewardly-admin-badge{display:inline-flex;align-items:center;justify-content:center;padding:2px 8px;border-radius:999px;background:#edeff1;color:#50575e;font-size:11px;font-weight:600;text-transform:uppercase}.rewardly-admin-badge--pro{background:#fff2cc;color:#8a6500}.rewardly-admin-badge--ok{background:#ecfdf3;color:#027a48}.rewardly-admin-badge--warn{background:#fff7ed;color:#b54708}.rewardly-admin-tab.is-active .rewardly-admin-badge{background:rgba(255,255,255,.2);color:#fff}.rewardly-admin-card{background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:20px;max-width:1100px}.rewardly-admin-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;max-width:1100px}.rewardly-admin-stat{background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:18px}.rewardly-admin-code{background:#f6f7f7;border:1px solid #dcdcde;border-radius:8px;padding:12px;font-family:monospace;word-break:break-word}.rewardly-admin-list{margin:8px 0 0 18px}.rewardly-admin-list li{margin:6px 0}.rewardly-admin-description{max-width:840px;color:#50575e}.rewardly-admin-section-title{margin:0 0 10px}.rewardly-admin-note{margin:12px 0 0;padding:12px 14px;border-left:4px solid #2271b1;background:#f6f7f7;border-radius:6px}.rewardly-admin-note--warn{border-left-color:#dba617;background:#fffdf5}.rewardly-admin-note--ok{border-left-color:#46b450;background:#f6fff7}.rewardly-admin-kbd{display:inline-block;padding:1px 6px;border:1px solid #dcdcde;border-bottom-width:2px;border-radius:6px;background:#fff;font-family:monospace;font-size:12px}.rewardly-admin-split{display:grid;grid-template-columns:1.2fr .8fr;gap:16px;max-width:1100px}.rewardly-admin-panel-list{margin:0;padding-left:18px}.rewardly-admin-panel-list li{margin:8px 0}.rewardly-pro-panel{position:relative;background:linear-gradient(180deg,#fcfcfd 0%,#f6f7f7 100%);border:1px solid #dcdcde;border-radius:12px;padding:22px;max-width:1100px}.rewardly-pro-locked{opacity:.78}.rewardly-pro-overlay{position:absolute;inset:0;background:rgba(255,255,255,.55);border-radius:12px;pointer-events:none}.rewardly-pro-cta{display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-top:18px}.rewardly-pro-table td,.rewardly-pro-table th{vertical-align:top}.rewardly-pro-mini-lock{font-size:13px;opacity:.8}.rewardly-rule-row,.rewardly-selector-row{display:grid;grid-template-columns:minmax(250px,1fr) 120px 44px;gap:10px;align-items:center;margin:0 0 10px}.rewardly-selector-row{grid-template-columns:minmax(250px,1fr) 44px}.rewardly-row-actions{display:flex;gap:8px;align-items:center}.rewardly-box-note{margin-top:10px;padding:10px 12px;border-left:4px solid #dcdcde;background:#f6f7f7}.rewardly-level-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px}.rewardly-lock-note{display:inline-flex;align-items:center;gap:8px;font-weight:600}@media (max-width: 900px){.rewardly-admin-split{grid-template-columns:1fr}.rewardly-rule-row{grid-template-columns:1fr 120px 44px}.rewardly-selector-row{grid-template-columns:1fr 44px}}' );

		wp_enqueue_script( 'rewardly-loyalty-admin-pro', REWARDLY_LOYALTY_URL . 'assets/js/admin-pro.js', array( 'jquery' ), filemtime( REWARDLY_LOYALTY_PATH . 'assets/js/admin-pro.js' ), true );
		wp_localize_script( 'rewardly-loyalty-admin-pro', 'rewardlyAdminData', array(
			'productOptions'  => self::get_product_options(),
			'categoryOptions' => self::get_category_options(),
			'i18n'            => array(
				'remove'        => __( 'Remove', 'rewardly-loyalty' ),
				'selectProduct' => __( 'Start typing a product name…', 'rewardly-loyalty' ),
				'selectCategory'=> __( 'Start typing a category name…', 'rewardly-loyalty' ),
			),
		) );
	}

	private static function get_tabs() {
		return array(
			'general'      => array( 'label' => __( 'General', 'rewardly-loyalty' ), 'pro' => false ),
			'adjustments'  => array( 'label' => __( 'Points Adjustments', 'rewardly-loyalty' ), 'pro' => false ),
			'shortcodes'   => array( 'label' => __( 'Shortcodes', 'rewardly-loyalty' ), 'pro' => false ),
			'pro_features' => array( 'label' => __( 'Advanced Features', 'rewardly-loyalty' ), 'pro' => false ),
			'help'         => array( 'label' => __( 'Help', 'rewardly-loyalty' ), 'pro' => false ),
		);
	}

	private static function get_current_tab() {
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general';
		$tabs = self::get_tabs();
		return isset( $tabs[ $tab ] ) ? $tab : 'general';
	}

	private static function render_tab_link( $tab_key, $tab_data, $current_tab ) {
		$url = admin_url( 'admin.php?page=rewardly-loyalty-settings&tab=' . $tab_key );
		echo '<a class="rewardly-admin-tab ' . ( $current_tab === $tab_key ? 'is-active' : '' ) . '" href="' . esc_url( $url ) . '">';
		echo esc_html( $tab_data['label'] );
		echo '</a>';
	}

	private static function get_product_options() {
		$options = array();
		$ids     = get_posts( array(
			'post_type'      => 'product',
			'post_status'    => array( 'publish', 'private', 'draft' ),
			'posts_per_page' => 250,
			'fields'         => 'ids',
		) );
		foreach ( $ids as $product_id ) {
			$options[] = array(
				'id'    => (int) $product_id,
				'label' => sprintf( '%s (#%d)', get_the_title( $product_id ), $product_id ),
			);
		}
		return $options;
	}

	private static function get_category_options() {
		$options = array();
		$terms   = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
		if ( is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				$options[] = array(
					'id'    => (int) $term->term_id,
					'label' => sprintf( '%s (#%d)', $term->name, $term->term_id ),
				);
			}
		}
		return $options;
	}

	private static function find_option_label_by_id( $id, $options ) {
		foreach ( $options as $option ) {
			if ( (int) $option['id'] === (int) $id ) {
				return (string) $option['label'];
			}
		}
		return '#' . (int) $id;
	}


	private static function get_notice_display_mode_summary( $mode ) {
		switch ( $mode ) {
			case 'cart':
				return __( 'Card in cart. Informational notice in checkout.', 'rewardly-loyalty' );
			case 'checkout':
				return __( 'Card in checkout. Informational notice in cart.', 'rewardly-loyalty' );
			case 'shortcode':
				return __( 'Automatic card and notice are disabled. Use the provided shortcodes only.', 'rewardly-loyalty' );
			default:
				return __( 'Card is shown automatically in cart and checkout. Auto notice stays hidden.', 'rewardly-loyalty' );
		}
	}

	private static function get_template_summary( $template ) {
		switch ( $template ) {
			case 'minimal':
				return __( 'Minimal template with a cleaner neutral look.', 'rewardly-loyalty' );
			case 'soft':
				return __( 'Soft template with a gentler card feel.', 'rewardly-loyalty' );
			default:
				return __( 'Default template with the standard Rewardly visual balance.', 'rewardly-loyalty' );
		}
	}


	public static function render_page() {
		$tabs        = self::get_tabs();
		$current_tab = self::get_current_tab(); ?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Rewardly Loyalty Program', 'rewardly-loyalty' ); ?></h1>
			<p class="rewardly-admin-description"><?php esc_html_e( 'Configure the loyalty program, adjust customer balances, place shortcodes and manage all loyalty tools from a single place.', 'rewardly-loyalty' ); ?></p>
			<nav class="rewardly-admin-tabs"><?php foreach ( $tabs as $tab_key => $tab_data ) { self::render_tab_link( $tab_key, $tab_data, $current_tab ); } ?></nav>
			<?php
			switch ( $current_tab ) {
				case 'adjustments':
					Rewardly_Loyalty_Admin_Adjustments::render_tab();
					break;
				case 'shortcodes':
					self::render_shortcodes_tab();
					break;
				case 'pro_features':
					self::render_pro_features_tab();
					break;
				case 'help':
					self::render_help_tab();
					break;
				case 'general':
				default:
					self::render_general_tab();
			}
			?>
		</div>
		<?php
	}

	private static function render_general_tab() {
		Rewardly_Loyalty_Settings::render_settings_tab();
		self::render_design_tab();
	}

	private static function render_design_tab() {
		$settings = Rewardly_Loyalty_Helpers::get_settings(); ?>
		<form method="post" action="options.php" class="rewardly-admin-card"><?php settings_fields( 'rewardly_loyalty_group' ); ?>
			<h2><?php esc_html_e( 'Design settings', 'rewardly-loyalty' ); ?></h2>
			<p class="rewardly-admin-description"><?php esc_html_e( 'These settings apply only to Rewardly interface elements and keep the plugin compatible with most WooCommerce themes.', 'rewardly-loyalty' ); ?></p><div class="rewardly-admin-note"><p><strong><?php esc_html_e( 'Selected template', 'rewardly-loyalty' ); ?>:</strong> <?php echo esc_html( self::get_template_summary( $settings['design_template'] ?? 'default' ) ); ?></p></div>
			<table class="form-table rewardly-pro-table">
				<tr><th><?php esc_html_e( 'Enable Rewardly front styles', 'rewardly-loyalty' ); ?></th><td><select name="rewardly_loyalty_settings[enable_front_styles]"><option value="yes" <?php selected( $settings['enable_front_styles'], 'yes' ); ?>><?php esc_html_e( 'Yes', 'rewardly-loyalty' ); ?></option><option value="no" <?php selected( $settings['enable_front_styles'], 'no' ); ?>><?php esc_html_e( 'No', 'rewardly-loyalty' ); ?></option></select></td></tr>
				<tr><th><?php esc_html_e( 'Free template', 'rewardly-loyalty' ); ?></th><td><select name="rewardly_loyalty_settings[design_template]"><option value="default" <?php selected( $settings['design_template'], 'default' ); ?>><?php esc_html_e( 'Default', 'rewardly-loyalty' ); ?></option><option value="minimal" <?php selected( $settings['design_template'], 'minimal' ); ?>><?php esc_html_e( 'Minimal', 'rewardly-loyalty' ); ?></option><option value="soft" <?php selected( $settings['design_template'], 'soft' ); ?>><?php esc_html_e( 'Soft', 'rewardly-loyalty' ); ?></option></select><p class="description"><?php esc_html_e( 'Choose one of the three free visual presets.', 'rewardly-loyalty' ); ?></p></td></tr>
				<tr><th><?php esc_html_e( 'Primary color', 'rewardly-loyalty' ); ?></th><td><input type="color" name="rewardly_loyalty_settings[primary_color]" value="<?php echo esc_attr( $settings['primary_color'] ); ?>"></td></tr>
				<tr><th><?php esc_html_e( 'Accent color', 'rewardly-loyalty' ); ?></th><td><input type="color" name="rewardly_loyalty_settings[accent_color]" value="<?php echo esc_attr( $settings['accent_color'] ); ?>"></td></tr>
				<tr><th><?php esc_html_e( 'Border radius', 'rewardly-loyalty' ); ?></th><td><input type="range" min="0" max="30" name="rewardly_loyalty_settings[border_radius]" value="<?php echo esc_attr( $settings['border_radius'] ); ?>"> <strong><?php echo esc_html( (int) $settings['border_radius'] ); ?>px</strong></td></tr>
				<tr><th><?php esc_html_e( 'Icon style', 'rewardly-loyalty' ); ?></th><td><select name="rewardly_loyalty_settings[icon_style]"><option value="default" <?php selected( $settings['icon_style'], 'default' ); ?>><?php esc_html_e( 'Default', 'rewardly-loyalty' ); ?></option><option value="minimal" <?php selected( $settings['icon_style'], 'minimal' ); ?>><?php esc_html_e( 'Minimal', 'rewardly-loyalty' ); ?></option><option value="circle" <?php selected( $settings['icon_style'], 'circle' ); ?>><?php esc_html_e( 'Circle', 'rewardly-loyalty' ); ?></option></select></td></tr>
				<tr><th><?php esc_html_e( 'Button style', 'rewardly-loyalty' ); ?></th><td><select name="rewardly_loyalty_settings[button_style]"><option value="rounded" <?php selected( $settings['button_style'], 'rounded' ); ?>><?php esc_html_e( 'Rounded', 'rewardly-loyalty' ); ?></option><option value="solid" <?php selected( $settings['button_style'], 'solid' ); ?>><?php esc_html_e( 'Solid', 'rewardly-loyalty' ); ?></option><option value="outline" <?php selected( $settings['button_style'], 'outline' ); ?>><?php esc_html_e( 'Outline', 'rewardly-loyalty' ); ?></option></select></td></tr>
				<tr><th><?php esc_html_e( 'Card style', 'rewardly-loyalty' ); ?></th><td><select name="rewardly_loyalty_settings[card_style]"><option value="soft" <?php selected( $settings['card_style'], 'soft' ); ?>><?php esc_html_e( 'Soft', 'rewardly-loyalty' ); ?></option><option value="outline" <?php selected( $settings['card_style'], 'outline' ); ?>><?php esc_html_e( 'Outline', 'rewardly-loyalty' ); ?></option><option value="filled" <?php selected( $settings['card_style'], 'filled' ); ?>><?php esc_html_e( 'Filled', 'rewardly-loyalty' ); ?></option></select></td></tr>
				<tr><th><?php esc_html_e( 'Typography preset', 'rewardly-loyalty' ); ?></th><td><select name="rewardly_loyalty_settings[typography_preset]"><option value="default" <?php selected( $settings['typography_preset'], 'default' ); ?>><?php esc_html_e( 'Default', 'rewardly-loyalty' ); ?></option><option value="compact" <?php selected( $settings['typography_preset'], 'compact' ); ?>><?php esc_html_e( 'Compact', 'rewardly-loyalty' ); ?></option><option value="modern" <?php selected( $settings['typography_preset'], 'modern' ); ?>><?php esc_html_e( 'Modern', 'rewardly-loyalty' ); ?></option></select></td></tr>
			</table>
			<?php submit_button(); ?>
		</form>
		<?php
	}

	private static function render_shortcodes_tab() {
		$settings = Rewardly_Loyalty_Helpers::get_settings();
		$mode     = $settings['notice_display_mode'] ?? 'both'; ?>
		<div class="rewardly-admin-split">
			<div class="rewardly-admin-card">
				<h2><?php esc_html_e( 'Shortcodes', 'rewardly-loyalty' ); ?></h2>
				<p class="rewardly-admin-description"><?php esc_html_e( 'Use these shortcodes to display Rewardly data in any page, block or builder section.', 'rewardly-loyalty' ); ?></p>
				<ul class="rewardly-admin-list">
					<li><code>[rewardly_points_balance]</code> — <?php esc_html_e( 'Display the current customer points balance.', 'rewardly-loyalty' ); ?></li>
					<li><code>[rewardly_points_value]</code> — <?php esc_html_e( 'Display the current customer monetary points value.', 'rewardly-loyalty' ); ?></li>
					<li><code>[rewardly_points_history limit="10"]</code> — <?php esc_html_e( 'Display recent loyalty history.', 'rewardly-loyalty' ); ?></li>
					<li><code>[rewardly_account_block]</code> — <?php esc_html_e( 'Display a loyalty summary block.', 'rewardly-loyalty' ); ?></li>
					<li><code>[rewardly_loyalty_notice]</code> — <?php esc_html_e( 'Display the informational loyalty notice anywhere. Supports context="cart" or context="checkout".', 'rewardly-loyalty' ); ?></li>
					<li><code>[rewardly_loyalty_card]</code> — <?php esc_html_e( 'Display the full loyalty redeem card anywhere. Supports context="cart" or context="checkout".', 'rewardly-loyalty' ); ?></li>
				</ul>
			</div>
			<div class="rewardly-admin-card">
				<h2><?php esc_html_e( 'Current display mode', 'rewardly-loyalty' ); ?></h2>
				<p><strong><?php echo esc_html( self::get_notice_display_mode_summary( $mode ) ); ?></strong></p>
				<div class="rewardly-admin-note<?php echo 'shortcode' === $mode ? ' rewardly-admin-note--ok' : ''; ?>">
					<p><strong><?php esc_html_e( 'Quick reminder', 'rewardly-loyalty' ); ?>:</strong> <?php esc_html_e( 'When the mode is set to Shortcode only, place both the loyalty card and the informational notice with shortcodes where you want them to appear.', 'rewardly-loyalty' ); ?></p>
				</div>
				<p class="rewardly-admin-section-title"><strong><?php esc_html_e( 'Recommended snippets', 'rewardly-loyalty' ); ?></strong></p>
				<div class="rewardly-admin-code">[rewardly_loyalty_card]<br>[rewardly_loyalty_card context="cart"]<br>[rewardly_loyalty_card context="checkout"]<br><br>[rewardly_loyalty_notice]<br>[rewardly_loyalty_notice context="cart"]<br>[rewardly_loyalty_notice context="checkout"]</div>
			</div>
		</div>
		<?php
	}

	private static function render_pro_locked_panel( $title, $description, $items ) {
		?>
		<div class="rewardly-pro-panel rewardly-pro-locked">
			<div class="rewardly-pro-overlay"></div>
			<h2><?php echo esc_html( $title ); ?></h2>
			<p class="rewardly-admin-description"><?php echo esc_html( $description ); ?></p>
			<ul class="rewardly-admin-list">
				<?php foreach ( $items as $item ) : ?>
					<li><strong><?php echo esc_html( $item['title'] ); ?>:</strong> <?php echo esc_html( $item['text'] ); ?></li>
				<?php endforeach; ?>
			</ul>
			<div class="rewardly-pro-cta">
				<span class="rewardly-lock-note">✓ <?php esc_html_e( 'All advanced features are available in this free release.', 'rewardly-loyalty' ); ?></span>
			</div>
		</div>
		<?php
	}

	private static function render_pro_features_tab() {
		self::render_advanced_rules_tab();
		self::render_extra_points_tab();
		self::render_campaigns_tab();
		self::render_levels_tab();
	}

	private static function render_advanced_rules_tab() {

		$settings          = Rewardly_Loyalty_Helpers::get_settings();
		$product_options   = self::get_product_options();
		$category_options  = self::get_category_options();
		$category_rules    = $settings['pro_category_rules'];
		$excluded_products = $settings['pro_excluded_product_ids'];
		$excluded_cats     = $settings['pro_excluded_category_ids'];
		?>
		<form method="post" action="options.php" class="rewardly-admin-card"><?php settings_fields( 'rewardly_loyalty_group' ); ?>
			<h2><?php esc_html_e( 'Advanced Rules', 'rewardly-loyalty' ); ?></h2>
			<p class="rewardly-admin-description"><?php esc_html_e( 'Build category rules and exclusions with search-assisted selectors. Product-level overrides are available directly inside the Product data panel.', 'rewardly-loyalty' ); ?></p>
			<table class="form-table rewardly-pro-table">
				<tr>
					<th><?php esc_html_e( 'Exclude sale products from earning', 'rewardly-loyalty' ); ?></th>
					<td><select name="rewardly_loyalty_settings[pro_exclude_sale_products]"><option value="no" <?php selected( $settings['pro_exclude_sale_products'], 'no' ); ?>><?php esc_html_e( 'No', 'rewardly-loyalty' ); ?></option><option value="yes" <?php selected( $settings['pro_exclude_sale_products'], 'yes' ); ?>><?php esc_html_e( 'Yes', 'rewardly-loyalty' ); ?></option></select></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Category earning rules', 'rewardly-loyalty' ); ?></th>
					<td>
						<div id="rewardly-category-rules-wrap">
							<?php if ( ! empty( $category_rules ) ) : foreach ( $category_rules as $rule ) : ?>
								<div class="rewardly-rule-row rewardly-category-rule-row">
									<input type="text" class="regular-text rewardly-category-search" value="<?php echo esc_attr( self::find_option_label_by_id( $rule['term_id'], $category_options ) ); ?>" list="rewardly-category-options" placeholder="<?php esc_attr_e( 'Start typing a category name…', 'rewardly-loyalty' ); ?>">
									<input type="hidden" name="rewardly_loyalty_settings[pro_category_rule_term_id][]" class="rewardly-category-id" value="<?php echo esc_attr( (int) $rule['term_id'] ); ?>">
									<input type="number" min="1" name="rewardly_loyalty_settings[pro_category_rule_rate][]" value="<?php echo esc_attr( (int) $rule['rate'] ); ?>" placeholder="5">
									<button type="button" class="button button-secondary rewardly-remove-row">×</button>
								</div>
							<?php endforeach; endif; ?>
						</div>
						<p><button type="button" class="button" id="rewardly-add-category-rule"><?php esc_html_e( 'Add category rule', 'rewardly-loyalty' ); ?></button></p>
						<p class="description"><?php esc_html_e( 'Each rule defines how many points are earned for each 1 unit of store currency spent in products from that category.', 'rewardly-loyalty' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Excluded products', 'rewardly-loyalty' ); ?></th>
					<td>
						<div id="rewardly-excluded-products-wrap">
							<?php foreach ( $excluded_products as $product_id ) : ?>
								<div class="rewardly-selector-row rewardly-product-selector-row">
									<input type="text" class="regular-text rewardly-product-search" value="<?php echo esc_attr( self::find_option_label_by_id( $product_id, $product_options ) ); ?>" list="rewardly-product-options" placeholder="<?php esc_attr_e( 'Start typing a product name…', 'rewardly-loyalty' ); ?>">
									<input type="hidden" name="rewardly_loyalty_settings[pro_excluded_product_ids][]" class="rewardly-product-id" value="<?php echo esc_attr( (int) $product_id ); ?>">
									<button type="button" class="button button-secondary rewardly-remove-row">×</button>
								</div>
							<?php endforeach; ?>
						</div>
						<p><button type="button" class="button" id="rewardly-add-excluded-product"><?php esc_html_e( 'Add excluded product', 'rewardly-loyalty' ); ?></button></p>
						<p class="description"><?php esc_html_e( 'You can also type raw product IDs manually inside the selector field if needed.', 'rewardly-loyalty' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Excluded categories', 'rewardly-loyalty' ); ?></th>
					<td>
						<div id="rewardly-excluded-categories-wrap">
							<?php foreach ( $excluded_cats as $term_id ) : ?>
								<div class="rewardly-selector-row rewardly-category-selector-row">
									<input type="text" class="regular-text rewardly-category-search" value="<?php echo esc_attr( self::find_option_label_by_id( $term_id, $category_options ) ); ?>" list="rewardly-category-options" placeholder="<?php esc_attr_e( 'Start typing a category name…', 'rewardly-loyalty' ); ?>">
									<input type="hidden" name="rewardly_loyalty_settings[pro_excluded_category_ids][]" class="rewardly-category-id" value="<?php echo esc_attr( (int) $term_id ); ?>">
									<button type="button" class="button button-secondary rewardly-remove-row">×</button>
								</div>
							<?php endforeach; ?>
						</div>
						<p><button type="button" class="button" id="rewardly-add-excluded-category"><?php esc_html_e( 'Add excluded category', 'rewardly-loyalty' ); ?></button></p>
					</td>
				</tr>
			</table>
			<datalist id="rewardly-product-options"><?php foreach ( $product_options as $option ) : ?><option value="<?php echo esc_attr( $option['label'] ); ?>"></option><?php endforeach; ?></datalist>
			<datalist id="rewardly-category-options"><?php foreach ( $category_options as $option ) : ?><option value="<?php echo esc_attr( $option['label'] ); ?>"></option><?php endforeach; ?></datalist>
			<?php submit_button(); ?>
		</form>
		<?php
	}

	private static function render_extra_points_tab() {

		$settings = Rewardly_Loyalty_Helpers::get_settings(); ?>
		<form method="post" action="options.php" class="rewardly-admin-card"><?php settings_fields( 'rewardly_loyalty_group' ); ?>
			<h2><?php esc_html_e( 'Extra Points Engine', 'rewardly-loyalty' ); ?></h2>
			<p class="rewardly-admin-description"><?php esc_html_e( 'Set optional one-time bonuses for customer lifecycle events.', 'rewardly-loyalty' ); ?></p>
			<table class="form-table rewardly-pro-table">
				<tr><th><?php esc_html_e( 'Registration bonus', 'rewardly-loyalty' ); ?></th><td><input type="number" min="0" name="rewardly_loyalty_settings[pro_points_registration]" value="<?php echo esc_attr( (int) $settings['pro_points_registration'] ); ?>"><p class="description"><?php esc_html_e( 'Awarded once when a new user account is created.', 'rewardly-loyalty' ); ?></p></td></tr>
				<tr><th><?php esc_html_e( 'First order bonus', 'rewardly-loyalty' ); ?></th><td><input type="number" min="0" name="rewardly_loyalty_settings[pro_points_first_order]" value="<?php echo esc_attr( (int) $settings['pro_points_first_order'] ); ?>"><p class="description"><?php esc_html_e( 'Awarded once when the first eligible order is processed.', 'rewardly-loyalty' ); ?></p></td></tr>
				<tr><th><?php esc_html_e( 'Approved product review bonus', 'rewardly-loyalty' ); ?></th><td><input type="number" min="0" name="rewardly_loyalty_settings[pro_points_review]" value="<?php echo esc_attr( (int) $settings['pro_points_review'] ); ?>"><p class="description"><?php esc_html_e( 'Awarded once per approved product review.', 'rewardly-loyalty' ); ?></p></td></tr>
				<tr><th><?php esc_html_e( 'Birthday bonus', 'rewardly-loyalty' ); ?></th><td><input type="number" min="0" name="rewardly_loyalty_settings[pro_points_birthday]" value="<?php echo esc_attr( (int) $settings['pro_points_birthday'] ); ?>"><p class="description"><?php esc_html_e( 'Reserved for the next profile/birthday workflow.', 'rewardly-loyalty' ); ?></p></td></tr>
				<tr><th><?php esc_html_e( 'Referral bonus', 'rewardly-loyalty' ); ?></th><td><input type="number" min="0" name="rewardly_loyalty_settings[pro_referral_points]" value="<?php echo esc_attr( (int) $settings['pro_referral_points'] ); ?>"><p class="description"><?php esc_html_e( 'Reserved for the future referral workflow.', 'rewardly-loyalty' ); ?></p></td></tr>
			</table>
			<?php submit_button(); ?>
		</form>
		<?php
	}

	private static function render_campaigns_tab() {

		$settings = Rewardly_Loyalty_Helpers::get_settings();
		$selected_statuses = Rewardly_Loyalty_Helpers::sanitize_status_list( $settings['pro_pending_points_statuses'] ?? array() ); ?>
		<form method="post" action="options.php" class="rewardly-admin-card"><?php settings_fields( 'rewardly_loyalty_group' ); ?>
			<h2><?php esc_html_e( 'Campaigns', 'rewardly-loyalty' ); ?></h2>
			<p class="rewardly-admin-description"><?php esc_html_e( 'Configure customer-facing messages that make loyalty earnings easier to understand.', 'rewardly-loyalty' ); ?></p>
			<table class="form-table rewardly-pro-table">
				<tr><th><?php esc_html_e( 'Enable pending points notice', 'rewardly-loyalty' ); ?></th><td><select name="rewardly_loyalty_settings[pro_pending_points_notice_enabled]"><option value="no" <?php selected( $settings['pro_pending_points_notice_enabled'] ?? 'no', 'no' ); ?>><?php esc_html_e( 'No', 'rewardly-loyalty' ); ?></option><option value="yes" <?php selected( $settings['pro_pending_points_notice_enabled'] ?? 'no', 'yes' ); ?>><?php esc_html_e( 'Yes', 'rewardly-loyalty' ); ?></option></select><p class="description"><?php esc_html_e( 'Display a custom message in My Account when a customer has an eligible order that will earn points later.', 'rewardly-loyalty' ); ?></p></td></tr>
				<tr><th><?php esc_html_e( 'Pending points notice text', 'rewardly-loyalty' ); ?></th><td><textarea name="rewardly_loyalty_settings[pro_pending_points_notice_text]" rows="4" cols="70"><?php echo esc_textarea( $settings['pro_pending_points_notice_text'] ?? '' ); ?></textarea><p class="description"><?php esc_html_e( 'Available placeholders: {points}, {order_number}, {status}, {total}, {currency}, {product_name}, {product_link}, {order_link}, {order_summary}.', 'rewardly-loyalty' ); ?></p></td></tr>
				<tr><th><?php esc_html_e( 'Statuses considered pending', 'rewardly-loyalty' ); ?></th><td>
					<label><input type="checkbox" name="rewardly_loyalty_settings[pro_pending_points_statuses][]" value="pending" <?php checked( in_array( 'pending', $selected_statuses, true ) ); ?>> <?php esc_html_e( 'Pending', 'rewardly-loyalty' ); ?></label><br>
					<label><input type="checkbox" name="rewardly_loyalty_settings[pro_pending_points_statuses][]" value="on-hold" <?php checked( in_array( 'on-hold', $selected_statuses, true ) ); ?>> <?php esc_html_e( 'On hold', 'rewardly-loyalty' ); ?></label><br>
					<label><input type="checkbox" name="rewardly_loyalty_settings[pro_pending_points_statuses][]" value="processing" <?php checked( in_array( 'processing', $selected_statuses, true ) ); ?>> <?php esc_html_e( 'Processing', 'rewardly-loyalty' ); ?></label>
					<p class="description"><?php esc_html_e( 'Only the latest eligible order in one of these statuses will be used for the pending message.', 'rewardly-loyalty' ); ?></p>
				</td></tr>
			</table>
			<div class="rewardly-box-note"><?php esc_html_e( 'Order-linked references in My Account history are enabled automatically when a history entry is tied to an order owned by the current customer.', 'rewardly-loyalty' ); ?></div>
			<?php submit_button(); ?>
		</form>
		<?php
	}

	private static function render_levels_tab() {

		$settings = Rewardly_Loyalty_Helpers::get_settings(); ?>
		<form method="post" action="options.php" class="rewardly-admin-card"><?php settings_fields( 'rewardly_loyalty_group' ); ?>
			<h2><?php esc_html_e( 'Levels & Badges', 'rewardly-loyalty' ); ?></h2>
			<p class="rewardly-admin-description"><?php esc_html_e( 'Define milestone thresholds based on the total earned points of each customer.', 'rewardly-loyalty' ); ?></p>
			<table class="form-table rewardly-pro-table">
				<tr><th><?php esc_html_e( 'Enable levels system', 'rewardly-loyalty' ); ?></th><td><select name="rewardly_loyalty_settings[pro_levels_enabled]"><option value="no" <?php selected( $settings['pro_levels_enabled'], 'no' ); ?>><?php esc_html_e( 'No', 'rewardly-loyalty' ); ?></option><option value="yes" <?php selected( $settings['pro_levels_enabled'], 'yes' ); ?>><?php esc_html_e( 'Yes', 'rewardly-loyalty' ); ?></option></select></td></tr>
			</table>
			<div class="rewardly-level-grid">
				<div class="rewardly-admin-stat"><strong><?php esc_html_e( 'Bronze threshold', 'rewardly-loyalty' ); ?></strong><p><input type="number" min="0" name="rewardly_loyalty_settings[pro_level_bronze_threshold]" value="<?php echo esc_attr( (int) $settings['pro_level_bronze_threshold'] ); ?>"></p></div>
				<div class="rewardly-admin-stat"><strong><?php esc_html_e( 'Silver threshold', 'rewardly-loyalty' ); ?></strong><p><input type="number" min="0" name="rewardly_loyalty_settings[pro_level_silver_threshold]" value="<?php echo esc_attr( (int) $settings['pro_level_silver_threshold'] ); ?>"></p></div>
				<div class="rewardly-admin-stat"><strong><?php esc_html_e( 'Gold threshold', 'rewardly-loyalty' ); ?></strong><p><input type="number" min="0" name="rewardly_loyalty_settings[pro_level_gold_threshold]" value="<?php echo esc_attr( (int) $settings['pro_level_gold_threshold'] ); ?>"></p></div>
			</div>
			<div class="rewardly-box-note"><?php esc_html_e( 'Levels are displayed in My Account with a badge and a progress bar.', 'rewardly-loyalty' ); ?></div>
			<?php submit_button(); ?>
		</form>
		<?php
	}

	private static function render_help_tab() {
		$settings = Rewardly_Loyalty_Helpers::get_settings(); ?>
		<div class="rewardly-admin-split">
			<div class="rewardly-admin-card">
				<h2><?php esc_html_e( 'Help and quick notes', 'rewardly-loyalty' ); ?></h2>
				<ul class="rewardly-admin-list">
					<li><?php esc_html_e( 'Use the general tab for the core loyalty conversion settings.', 'rewardly-loyalty' ); ?></li>
					<li><?php esc_html_e( 'Use General to manage the core loyalty settings and built-in design options.', 'rewardly-loyalty' ); ?></li>
					<li><?php esc_html_e( 'Use Advanced Features to manage advanced rules, bonuses, campaigns and levels.', 'rewardly-loyalty' ); ?></li>
					<li><?php esc_html_e( 'Shortcodes can be inserted in builders, custom pages and account dashboards.', 'rewardly-loyalty' ); ?></li>
				</ul>
				<div class="rewardly-admin-note"><p><strong><?php esc_html_e( 'Display mode', 'rewardly-loyalty' ); ?>:</strong> <?php echo esc_html( self::get_notice_display_mode_summary( $settings['notice_display_mode'] ?? 'both' ) ); ?></p></div>
			</div>
			<div class="rewardly-admin-card">
				<h2><?php esc_html_e( 'Launch checklist', 'rewardly-loyalty' ); ?></h2>
				<ol class="rewardly-admin-panel-list">
					<li><?php esc_html_e( 'Confirm the earning and redeem conversion values in the main settings tab.', 'rewardly-loyalty' ); ?></li>
					<li><?php esc_html_e( 'Choose where the loyalty card should appear automatically.', 'rewardly-loyalty' ); ?></li>
					<li><?php esc_html_e( 'Select a free template and verify the store front result on desktop and mobile.', 'rewardly-loyalty' ); ?></li>
					<li><?php esc_html_e( 'Review the advanced features tab and enable the options you need for your store.', 'rewardly-loyalty' ); ?></li>
					<li><?php esc_html_e( 'Run one real order test and one refund or cancel test before release.', 'rewardly-loyalty' ); ?></li>
				</ol>
			</div>
		</div>
		<?php self::render_debug_tab(); ?>
		<?php
	}

	private static function render_debug_tab() {
		$settings   = Rewardly_Loyalty_Helpers::get_settings();
		$shortcodes = array( '[rewardly_points_balance]', '[rewardly_points_value]', '[rewardly_points_history]', '[rewardly_account_block]', '[rewardly_loyalty_notice]', '[rewardly_loyalty_card]' ); ?>
		<div class="rewardly-admin-card">
			<h2><?php esc_html_e( 'Debug information', 'rewardly-loyalty' ); ?></h2>
			<div class="rewardly-admin-grid">
				<div class="rewardly-admin-stat"><strong><?php esc_html_e( 'Plugin version', 'rewardly-loyalty' ); ?></strong><div class="rewardly-admin-code"><?php echo esc_html( REWARDLY_LOYALTY_VERSION ); ?></div></div>
				<div class="rewardly-admin-stat"><strong><?php esc_html_e( 'WooCommerce active', 'rewardly-loyalty' ); ?></strong><div class="rewardly-admin-code"><?php echo esc_html( class_exists( 'WooCommerce' ) ? __( 'Yes', 'rewardly-loyalty' ) : __( 'No', 'rewardly-loyalty' ) ); ?></div></div>
				<div class="rewardly-admin-stat"><strong><?php esc_html_e( 'Store currency', 'rewardly-loyalty' ); ?></strong><div class="rewardly-admin-code"><?php echo esc_html( Rewardly_Loyalty_Helpers::get_store_currency_code() ); ?></div></div>
				<div class="rewardly-admin-stat"><strong><?php esc_html_e( 'Program enabled', 'rewardly-loyalty' ); ?></strong><div class="rewardly-admin-code"><?php echo esc_html( 'yes' === $settings['enabled'] ? __( 'Yes', 'rewardly-loyalty' ) : __( 'No', 'rewardly-loyalty' ) ); ?></div></div>
				<div class="rewardly-admin-stat"><strong><?php esc_html_e( 'Selected template', 'rewardly-loyalty' ); ?></strong><div class="rewardly-admin-code"><?php echo esc_html( $settings['design_template'] ?? 'default' ); ?> — <?php echo esc_html( self::get_template_summary( $settings['design_template'] ?? 'default' ) ); ?></div></div>
				<div class="rewardly-admin-stat"><strong><?php esc_html_e( 'Category rules', 'rewardly-loyalty' ); ?></strong><div class="rewardly-admin-code"><?php echo esc_html( count( $settings['pro_category_rules'] ) ); ?></div></div>
				<div class="rewardly-admin-stat"><strong><?php esc_html_e( 'Excluded products', 'rewardly-loyalty' ); ?></strong><div class="rewardly-admin-code"><?php echo esc_html( count( $settings['pro_excluded_product_ids'] ) ); ?></div></div>
				<div class="rewardly-admin-stat"><strong><?php esc_html_e( 'Excluded categories', 'rewardly-loyalty' ); ?></strong><div class="rewardly-admin-code"><?php echo esc_html( count( $settings['pro_excluded_category_ids'] ) ); ?></div></div>
				<div class="rewardly-admin-stat"><strong><?php esc_html_e( 'Levels enabled', 'rewardly-loyalty' ); ?></strong><div class="rewardly-admin-code"><?php echo esc_html( 'yes' === $settings['pro_levels_enabled'] ? __( 'Yes', 'rewardly-loyalty' ) : __( 'No', 'rewardly-loyalty' ) ); ?></div></div>
				<div class="rewardly-admin-stat"><strong><?php esc_html_e( 'Display mode summary', 'rewardly-loyalty' ); ?></strong><div class="rewardly-admin-code"><?php echo esc_html( self::get_notice_display_mode_summary( $settings['notice_display_mode'] ?? 'both' ) ); ?></div></div><div class="rewardly-admin-stat"><strong><?php esc_html_e( 'Available shortcodes', 'rewardly-loyalty' ); ?></strong><div class="rewardly-admin-code"><?php echo esc_html( implode( ' | ', $shortcodes ) ); ?></div></div>
			</div>
		</div>
		<?php
	}
}
