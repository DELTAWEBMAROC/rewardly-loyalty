<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
class Rewardly_Loyalty_Admin {
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	public static function add_menu() {
		add_menu_page( __( 'Nodevora Customer Rewards for WooCommerce', 'nodevora-customer-rewards-for-woocommerce' ), __( 'Nodevora Rewards', 'nodevora-customer-rewards-for-woocommerce' ), 'nodevora_view', 'rewardly-loyalty-settings', array( __CLASS__, 'render_page' ) );
	}

	public static function enqueue_assets( $hook ) {
		$is_settings_page = 'toplevel_page_rewardly-loyalty-settings' === $hook;
		$is_product_page  = in_array( $hook, array( 'post.php', 'post-new.php' ), true ) && 'product' === get_post_type();

		if ( ! $is_settings_page && ! $is_product_page ) {
			return;
		}

		wp_register_style( 'rewardly-loyalty-admin-inline', REWARDLY_LOYALTY_URL.'assets/css/admin-legacy.css', array(), filemtime(REWARDLY_LOYALTY_PATH.'assets/css/admin-legacy.css') );
		wp_enqueue_style( 'rewardly-loyalty-admin-inline' );
		if($is_settings_page){wp_enqueue_style('nodevora-admin',REWARDLY_LOYALTY_URL.'assets/css/admin.css',array('rewardly-loyalty-admin-inline'),filemtime(REWARDLY_LOYALTY_PATH.'assets/css/admin.css'));if('design'===self::get_current_tab()){Nodevora_Design_Admin::enqueue();}}
		if($is_settings_page&&'data'===self::get_current_tab()){wp_enqueue_script('nodevora-admin-data',REWARDLY_LOYALTY_URL.'assets/js/admin-data.js',array(),filemtime(REWARDLY_LOYALTY_PATH.'assets/js/admin-data.js'),true);wp_localize_script('nodevora-admin-data','nodevoraData',array('url'=>admin_url('admin-ajax.php'),'nonce'=>wp_create_nonce('nodevora_data'),'checked'=>__('accounts checked','nodevora-customer-rewards-for-woocommerce'),'working'=>__('Working…','nodevora-customer-rewards-for-woocommerce'),'review'=>__('File verified. Review the record count and confirm the import.','nodevora-customer-rewards-for-woocommerce'),'failed'=>__('The operation could not finish. Retry without changing the data.','nodevora-customer-rewards-for-woocommerce')));}
		if ( ! $is_settings_page || 'pro_features' !== self::get_current_tab() ) { return; }
		wp_enqueue_script( 'rewardly-loyalty-admin-pro', REWARDLY_LOYALTY_URL . 'assets/js/admin-pro.js', array( 'jquery' ), filemtime( REWARDLY_LOYALTY_PATH . 'assets/js/admin-pro.js' ), true );
		wp_localize_script( 'rewardly-loyalty-admin-pro', 'rewardlyAdminData', array(
			'productOptions'  => self::get_product_options(),
			'categoryOptions' => self::get_category_options(),
			'i18n'            => array(
				'remove'        => __( 'Remove', 'nodevora-customer-rewards-for-woocommerce' ),
				'selectProduct' => __( 'Start typing a product name…', 'nodevora-customer-rewards-for-woocommerce' ),
				'selectCategory'=> __( 'Start typing a category name…', 'nodevora-customer-rewards-for-woocommerce' ),
			),
		) );
	}

	private static function get_tabs() {
		$tabs = array(
			'general'      => array( 'label' => __( 'General', 'nodevora-customer-rewards-for-woocommerce' ), 'pro' => false ),
			'design' => array( 'label' => __( 'Design', 'nodevora-customer-rewards-for-woocommerce' ), 'pro' => false ),
			'adjustments'  => array( 'label' => __( 'Points Adjustments', 'nodevora-customer-rewards-for-woocommerce' ), 'pro' => false ),
			'shortcodes'   => array( 'label' => __( 'Shortcodes', 'nodevora-customer-rewards-for-woocommerce' ), 'pro' => false ),
			'pro_features' => array( 'label' => __( 'Advanced Features', 'nodevora-customer-rewards-for-woocommerce' ), 'pro' => false ),
			'help'         => array( 'label' => __( 'Help', 'nodevora-customer-rewards-for-woocommerce' ), 'pro' => false ),
		);
		if(current_user_can('manage_options')&&Nodevora_Data::relevant()){$tabs['data']=array('label'=>Nodevora_Data::label(),'pro'=>false);}
		return $tabs;
	}

	private static function get_current_tab() {
		$tab = filter_input( INPUT_GET, 'tab', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$tab = is_string( $tab ) ? sanitize_key( wp_unslash( $tab ) ) : 'general';
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
		static $cached = null; if ( null !== $cached ) { return $cached; }
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
				/* translators: 1: product title, 2: product ID. */
				'label' => sprintf( __( '%1$s (#%2$d)', 'nodevora-customer-rewards-for-woocommerce' ), get_the_title( $product_id ), $product_id ),
			);
		}
		$cached = $options; return $options;
	}

	private static function get_category_options() {
		static $cached = null; if ( null !== $cached ) { return $cached; }
		$options = array();
		$terms   = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false, 'number' => 250 ) );
		if ( is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				$options[] = array(
					'id'    => (int) $term->term_id,
					/* translators: 1: category name, 2: category ID. */
				'label' => sprintf( __( '%1$s (#%2$d)', 'nodevora-customer-rewards-for-woocommerce' ), $term->name, $term->term_id ),
				);
			}
		}
		$cached = $options; return $options;
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
				return __( 'Card in cart. Informational notice in checkout.', 'nodevora-customer-rewards-for-woocommerce' );
			case 'checkout':
				return __( 'Card in checkout. Informational notice in cart.', 'nodevora-customer-rewards-for-woocommerce' );
			case 'shortcode':
				return __( 'Automatic card and notice are disabled. Use the provided shortcodes only.', 'nodevora-customer-rewards-for-woocommerce' );
			default:
				return __( 'Card is shown automatically in cart and checkout. Auto notice stays hidden.', 'nodevora-customer-rewards-for-woocommerce' );
		}
	}

	private static function get_template_summary( $template ) {
		switch ( $template ) {
			case 'minimal':
				return __( 'Minimal template with a cleaner neutral look.', 'nodevora-customer-rewards-for-woocommerce' );
			case 'soft':
				return __( 'Soft template with a gentler card feel.', 'nodevora-customer-rewards-for-woocommerce' );
			default:
				return __( 'Default template with the Nodevora navy and teal visual identity.', 'nodevora-customer-rewards-for-woocommerce' );
		}
	}


	public static function render_page() {
		if ( ! Nodevora_Access::can_view() ) { wp_die( esc_html__( 'You are not allowed to manage rewards.', 'nodevora-customer-rewards-for-woocommerce' ), '', array( 'response' => 403 ) ); }
		$tabs        = self::get_tabs();
		$current_tab = self::get_current_tab(); ?>
		<div class="wrap nodevora-admin <?php echo Nodevora_Access::advanced() ? 'is-advanced' : 'is-simple'; ?>">
			<h1><?php esc_html_e( 'Nodevora Customer Rewards for WooCommerce', 'nodevora-customer-rewards-for-woocommerce' ); ?></h1>
			<p class="rewardly-admin-description"><?php esc_html_e( 'Configure the loyalty program, adjust customer balances, place shortcodes and manage all loyalty tools from a single place.', 'nodevora-customer-rewards-for-woocommerce' ); ?></p>
			<?php Nodevora_Access::mode_control(); ?>
			<nav class="rewardly-admin-tabs"><?php foreach ( $tabs as $tab_key => $tab_data ) { self::render_tab_link( $tab_key, $tab_data, $current_tab ); } ?></nav>
			<?php
			$readonly=!Nodevora_Access::can_edit();if($readonly){echo '<p class="nodevora-readonly-note">'.esc_html__('Read-only access. Ask an authorized manager to change settings or points.','nodevora-customer-rewards-for-woocommerce').'</p><fieldset disabled class="nodevora-readonly">';}
			switch ( $current_tab ) {
				case 'data':
					Nodevora_Data::render();break;
				case 'adjustments':
					Rewardly_Loyalty_Admin_Adjustments::render_tab();
					break;
				case 'design':
					self::render_design_tab();
					break;
				case 'shortcodes':
					self::render_shortcodes_tab();
					break;
				case 'pro_features':
					Nodevora_Access::policy_form();
					self::render_pro_features_tab();
					break;
				case 'help':
					self::render_help_tab();
					break;
				case 'general':
				default:
					self::render_general_tab();
			}
			if($readonly){echo '</fieldset>';}
			?>
		</div>
		<?php
	}

	private static function render_general_tab() {
		Rewardly_Loyalty_Settings::render_settings_tab();
	}

	private static function render_design_tab() { Nodevora_Design_Admin::render(); }

	private static function render_shortcodes_tab() {
		$settings = Rewardly_Loyalty_Helpers::get_settings();
		$mode     = $settings['notice_display_mode'] ?? 'both'; ?>
		<div class="rewardly-admin-split">
			<div class="rewardly-admin-card">
				<h2><?php esc_html_e( 'Shortcodes', 'nodevora-customer-rewards-for-woocommerce' ); ?></h2>
				<p class="rewardly-admin-description"><?php esc_html_e( 'Use these shortcodes to display Nodevora rewards data in any page, block or builder section.', 'nodevora-customer-rewards-for-woocommerce' ); ?></p>
				<ul class="rewardly-admin-list">
					<li><code>[rewardly_points_balance]</code> — <?php esc_html_e( 'Display the current customer points balance.', 'nodevora-customer-rewards-for-woocommerce' ); ?></li>
					<li><code>[rewardly_points_value]</code> — <?php esc_html_e( 'Display the current customer monetary points value.', 'nodevora-customer-rewards-for-woocommerce' ); ?></li>
					<li><code>[rewardly_points_history limit="10"]</code> — <?php esc_html_e( 'Display recent loyalty history.', 'nodevora-customer-rewards-for-woocommerce' ); ?></li>
					<li><code>[rewardly_account_block]</code> — <?php esc_html_e( 'Display a loyalty summary block.', 'nodevora-customer-rewards-for-woocommerce' ); ?></li>
					<li><code>[rewardly_loyalty_notice]</code> — <?php esc_html_e( 'Display the informational loyalty notice anywhere. Supports context="cart" or context="checkout".', 'nodevora-customer-rewards-for-woocommerce' ); ?></li>
					<li><code>[rewardly_loyalty_card]</code> — <?php esc_html_e( 'Display the full loyalty redeem card anywhere. Supports context="cart" or context="checkout".', 'nodevora-customer-rewards-for-woocommerce' ); ?></li>
				</ul>
			</div>
			<div class="rewardly-admin-card">
				<h2><?php esc_html_e( 'Current display mode', 'nodevora-customer-rewards-for-woocommerce' ); ?></h2>
				<p><strong><?php echo esc_html( self::get_notice_display_mode_summary( $mode ) ); ?></strong></p>
				<div class="rewardly-admin-note<?php echo 'shortcode' === $mode ? ' rewardly-admin-note--ok' : ''; ?>">
					<p><strong><?php esc_html_e( 'Quick reminder', 'nodevora-customer-rewards-for-woocommerce' ); ?>:</strong> <?php esc_html_e( 'When the mode is set to Shortcode only, place both the loyalty card and the informational notice with shortcodes where you want them to appear.', 'nodevora-customer-rewards-for-woocommerce' ); ?></p>
				</div>
				<p class="rewardly-admin-section-title"><strong><?php esc_html_e( 'Recommended snippets', 'nodevora-customer-rewards-for-woocommerce' ); ?></strong></p>
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
				<span class="rewardly-lock-note">✓ <?php esc_html_e( 'All advanced features are available in this free release.', 'nodevora-customer-rewards-for-woocommerce' ); ?></span>
			</div>
		</div>
		<?php
	}

	private static function render_pro_features_tab() {
		if(!Nodevora_Access::advanced()){echo '<section class="rewardly-admin-card"><p>'.esc_html__('Enable Advanced mode to review detailed rules, exclusions, expiration and access settings. Existing advanced values remain active.','nodevora-customer-rewards-for-woocommerce').'</p></section>';return;}
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
		<form method="post" action="options.php" class="rewardly-admin-card"><?php settings_fields( 'rewardly_loyalty_group' ); ?><input type="hidden" name="rewardly_loyalty_settings[_section]" value="rules">
			<h2><?php esc_html_e( 'Advanced Rules', 'nodevora-customer-rewards-for-woocommerce' ); ?></h2>
			<p class="rewardly-admin-description"><?php esc_html_e( 'Build category rules and exclusions with search-assisted selectors. Product-level overrides are available directly inside the Product data panel.', 'nodevora-customer-rewards-for-woocommerce' ); ?></p>
			<table class="form-table rewardly-pro-table">
				<tr>
					<th><label for="nodevora-setting-pro_exclude_sale_products"><?php esc_html_e( 'Exclude sale products from earning', 'nodevora-customer-rewards-for-woocommerce' ); ?></label></th>
					<td><select id="nodevora-setting-pro_exclude_sale_products" name="rewardly_loyalty_settings[pro_exclude_sale_products]"><option value="no" <?php selected( $settings['pro_exclude_sale_products'], 'no' ); ?>><?php esc_html_e( 'No', 'nodevora-customer-rewards-for-woocommerce' ); ?></option><option value="yes" <?php selected( $settings['pro_exclude_sale_products'], 'yes' ); ?>><?php esc_html_e( 'Yes', 'nodevora-customer-rewards-for-woocommerce' ); ?></option></select></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Category earning rules', 'nodevora-customer-rewards-for-woocommerce' ); ?></th>
					<td>
						<div id="rewardly-category-rules-wrap">
							<?php if ( ! empty( $category_rules ) ) : foreach ( $category_rules as $rule ) : ?>
								<div class="rewardly-rule-row rewardly-category-rule-row">
									<input type="text" class="regular-text rewardly-category-search" value="<?php echo esc_attr( self::find_option_label_by_id( $rule['term_id'], $category_options ) ); ?>" list="rewardly-category-options" placeholder="<?php esc_attr_e( 'Start typing a category name…', 'nodevora-customer-rewards-for-woocommerce' ); ?>">
									<input type="hidden" name="rewardly_loyalty_settings[pro_category_rule_term_id][]" class="rewardly-category-id" value="<?php echo esc_attr( (int) $rule['term_id'] ); ?>">
									<input type="number" min="1" name="rewardly_loyalty_settings[pro_category_rule_rate][]" value="<?php echo esc_attr( (int) $rule['rate'] ); ?>" placeholder="5">
									<button type="button" class="button button-secondary rewardly-remove-row">×</button>
								</div>
							<?php endforeach; endif; ?>
						</div>
						<p><button type="button" class="button" id="rewardly-add-category-rule"><?php esc_html_e( 'Add category rule', 'nodevora-customer-rewards-for-woocommerce' ); ?></button></p>
						<p class="description"><?php esc_html_e( 'Each rule defines how many points are earned for each 1 unit of store currency spent in products from that category.', 'nodevora-customer-rewards-for-woocommerce' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Excluded products', 'nodevora-customer-rewards-for-woocommerce' ); ?></th>
					<td>
						<div id="rewardly-excluded-products-wrap">
							<?php foreach ( $excluded_products as $product_id ) : ?>
								<div class="rewardly-selector-row rewardly-product-selector-row">
									<input type="text" class="regular-text rewardly-product-search" value="<?php echo esc_attr( self::find_option_label_by_id( $product_id, $product_options ) ); ?>" list="rewardly-product-options" placeholder="<?php esc_attr_e( 'Start typing a product name…', 'nodevora-customer-rewards-for-woocommerce' ); ?>">
									<input type="hidden" name="rewardly_loyalty_settings[pro_excluded_product_ids][]" class="rewardly-product-id" value="<?php echo esc_attr( (int) $product_id ); ?>">
									<button type="button" class="button button-secondary rewardly-remove-row">×</button>
								</div>
							<?php endforeach; ?>
						</div>
						<p><button type="button" class="button" id="rewardly-add-excluded-product"><?php esc_html_e( 'Add excluded product', 'nodevora-customer-rewards-for-woocommerce' ); ?></button></p>
						<p class="description"><?php esc_html_e( 'You can also type raw product IDs manually inside the selector field if needed.', 'nodevora-customer-rewards-for-woocommerce' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Excluded categories', 'nodevora-customer-rewards-for-woocommerce' ); ?></th>
					<td>
						<div id="rewardly-excluded-categories-wrap">
							<?php foreach ( $excluded_cats as $term_id ) : ?>
								<div class="rewardly-selector-row rewardly-category-selector-row">
									<input type="text" class="regular-text rewardly-category-search" value="<?php echo esc_attr( self::find_option_label_by_id( $term_id, $category_options ) ); ?>" list="rewardly-category-options" placeholder="<?php esc_attr_e( 'Start typing a category name…', 'nodevora-customer-rewards-for-woocommerce' ); ?>">
									<input type="hidden" name="rewardly_loyalty_settings[pro_excluded_category_ids][]" class="rewardly-category-id" value="<?php echo esc_attr( (int) $term_id ); ?>">
									<button type="button" class="button button-secondary rewardly-remove-row">×</button>
								</div>
							<?php endforeach; ?>
						</div>
						<p><button type="button" class="button" id="rewardly-add-excluded-category"><?php esc_html_e( 'Add excluded category', 'nodevora-customer-rewards-for-woocommerce' ); ?></button></p>
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
		<form method="post" action="options.php" class="rewardly-admin-card"><?php settings_fields( 'rewardly_loyalty_group' ); ?><input type="hidden" name="rewardly_loyalty_settings[_section]" value="bonuses">
			<h2><?php esc_html_e( 'Extra Points Engine', 'nodevora-customer-rewards-for-woocommerce' ); ?></h2>
			<p class="rewardly-admin-description"><?php esc_html_e( 'Set optional one-time bonuses for customer lifecycle events.', 'nodevora-customer-rewards-for-woocommerce' ); ?></p>
			<table class="form-table rewardly-pro-table">
				<tr><th><label for="nodevora-setting-pro_points_registration"><?php esc_html_e( 'Registration bonus', 'nodevora-customer-rewards-for-woocommerce' ); ?></label></th><td><input type="number" min="0" id="nodevora-setting-pro_points_registration" name="rewardly_loyalty_settings[pro_points_registration]" value="<?php echo esc_attr( (int) $settings['pro_points_registration'] ); ?>"><p class="description"><?php esc_html_e( 'Awarded once when a new user account is created.', 'nodevora-customer-rewards-for-woocommerce' ); ?></p></td></tr>
				<tr><th><label for="nodevora-setting-pro_points_first_order"><?php esc_html_e( 'First order bonus', 'nodevora-customer-rewards-for-woocommerce' ); ?></label></th><td><input type="number" min="0" id="nodevora-setting-pro_points_first_order" name="rewardly_loyalty_settings[pro_points_first_order]" value="<?php echo esc_attr( (int) $settings['pro_points_first_order'] ); ?>"><p class="description"><?php esc_html_e( 'Awarded once when the first eligible order is processed.', 'nodevora-customer-rewards-for-woocommerce' ); ?></p></td></tr>
				<tr><th><label for="nodevora-setting-pro_points_review"><?php esc_html_e( 'Approved product review bonus', 'nodevora-customer-rewards-for-woocommerce' ); ?></label></th><td><input type="number" min="0" id="nodevora-setting-pro_points_review" name="rewardly_loyalty_settings[pro_points_review]" value="<?php echo esc_attr( (int) $settings['pro_points_review'] ); ?>"><p class="description"><?php esc_html_e( 'Awarded once per approved product review.', 'nodevora-customer-rewards-for-woocommerce' ); ?></p></td></tr>
			</table>
			<?php submit_button(); ?>
		</form>
		<?php
	}

	private static function render_campaigns_tab() {

		$settings = Rewardly_Loyalty_Helpers::get_settings();
		$selected_statuses = Rewardly_Loyalty_Helpers::sanitize_status_list( $settings['pro_pending_points_statuses'] ?? array() ); ?>
		<form method="post" action="options.php" class="rewardly-admin-card"><?php settings_fields( 'rewardly_loyalty_group' ); ?><input type="hidden" name="rewardly_loyalty_settings[_section]" value="pending">
			<h2><?php esc_html_e( 'Campaigns', 'nodevora-customer-rewards-for-woocommerce' ); ?></h2>
			<p class="rewardly-admin-description"><?php esc_html_e( 'Configure customer-facing messages that make loyalty earnings easier to understand.', 'nodevora-customer-rewards-for-woocommerce' ); ?></p>
			<table class="form-table rewardly-pro-table">
				<tr><th><label for="nodevora-setting-pro_pending_points_notice_enabled"><?php esc_html_e( 'Enable pending points notice', 'nodevora-customer-rewards-for-woocommerce' ); ?></label></th><td><select id="nodevora-setting-pro_pending_points_notice_enabled" name="rewardly_loyalty_settings[pro_pending_points_notice_enabled]"><option value="no" <?php selected( $settings['pro_pending_points_notice_enabled'] ?? 'no', 'no' ); ?>><?php esc_html_e( 'No', 'nodevora-customer-rewards-for-woocommerce' ); ?></option><option value="yes" <?php selected( $settings['pro_pending_points_notice_enabled'] ?? 'no', 'yes' ); ?>><?php esc_html_e( 'Yes', 'nodevora-customer-rewards-for-woocommerce' ); ?></option></select><p class="description"><?php esc_html_e( 'Display a custom message in My Account when a customer has an eligible order that will earn points later.', 'nodevora-customer-rewards-for-woocommerce' ); ?></p></td></tr>
				<tr><th><label for="nodevora-setting-pro_pending_points_notice_text"><?php esc_html_e( 'Pending points notice text', 'nodevora-customer-rewards-for-woocommerce' ); ?></label></th><td><textarea id="nodevora-setting-pro_pending_points_notice_text" name="rewardly_loyalty_settings[pro_pending_points_notice_text]" rows="4" cols="70"><?php echo esc_textarea( $settings['pro_pending_points_notice_text'] ?? '' ); ?></textarea><p class="description"><?php esc_html_e( 'Available placeholders: {points}, {order_number}, {status}, {total}, {currency}, {product_name}, {product_link}, {order_link}, {order_summary}.', 'nodevora-customer-rewards-for-woocommerce' ); ?></p></td></tr>
				<tr><th><?php esc_html_e( 'Statuses considered pending', 'nodevora-customer-rewards-for-woocommerce' ); ?></th><td>
					<label><input type="checkbox" name="rewardly_loyalty_settings[pro_pending_points_statuses][]" value="pending" <?php checked( in_array( 'pending', $selected_statuses, true ) ); ?>> <?php esc_html_e( 'Pending', 'nodevora-customer-rewards-for-woocommerce' ); ?></label><br>
					<label><input type="checkbox" name="rewardly_loyalty_settings[pro_pending_points_statuses][]" value="on-hold" <?php checked( in_array( 'on-hold', $selected_statuses, true ) ); ?>> <?php esc_html_e( 'On hold', 'nodevora-customer-rewards-for-woocommerce' ); ?></label><br>
					<label><input type="checkbox" name="rewardly_loyalty_settings[pro_pending_points_statuses][]" value="processing" <?php checked( in_array( 'processing', $selected_statuses, true ) ); ?>> <?php esc_html_e( 'Processing', 'nodevora-customer-rewards-for-woocommerce' ); ?></label>
					<p class="description"><?php esc_html_e( 'Only the latest eligible order in one of these statuses will be used for the pending message.', 'nodevora-customer-rewards-for-woocommerce' ); ?></p>
				</td></tr>
			</table>
			<div class="rewardly-box-note"><?php esc_html_e( 'Order-linked references in My Account history are enabled automatically when a history entry is tied to an order owned by the current customer.', 'nodevora-customer-rewards-for-woocommerce' ); ?></div>
			<?php submit_button(); ?>
		</form>
		<?php
	}

	private static function render_levels_tab() {

		$settings = Rewardly_Loyalty_Helpers::get_settings(); ?>
		<form method="post" action="options.php" class="rewardly-admin-card"><?php settings_fields( 'rewardly_loyalty_group' ); ?><input type="hidden" name="rewardly_loyalty_settings[_section]" value="levels">
			<h2><?php esc_html_e( 'Levels & Badges', 'nodevora-customer-rewards-for-woocommerce' ); ?></h2>
			<p class="rewardly-admin-description"><?php esc_html_e( 'Define milestone thresholds based on the total earned points of each customer.', 'nodevora-customer-rewards-for-woocommerce' ); ?></p>
			<table class="form-table rewardly-pro-table">
				<tr><th><label for="nodevora-setting-pro_levels_enabled"><?php esc_html_e( 'Enable levels system', 'nodevora-customer-rewards-for-woocommerce' ); ?></label></th><td><select id="nodevora-setting-pro_levels_enabled" name="rewardly_loyalty_settings[pro_levels_enabled]"><option value="no" <?php selected( $settings['pro_levels_enabled'], 'no' ); ?>><?php esc_html_e( 'No', 'nodevora-customer-rewards-for-woocommerce' ); ?></option><option value="yes" <?php selected( $settings['pro_levels_enabled'], 'yes' ); ?>><?php esc_html_e( 'Yes', 'nodevora-customer-rewards-for-woocommerce' ); ?></option></select></td></tr>
			</table>
			<div class="rewardly-level-grid">
				<div class="rewardly-admin-stat"><strong><?php esc_html_e( 'Bronze threshold', 'nodevora-customer-rewards-for-woocommerce' ); ?></strong><p><input type="number" min="0" name="rewardly_loyalty_settings[pro_level_bronze_threshold]" value="<?php echo esc_attr( (int) $settings['pro_level_bronze_threshold'] ); ?>"></p></div>
				<div class="rewardly-admin-stat"><strong><?php esc_html_e( 'Silver threshold', 'nodevora-customer-rewards-for-woocommerce' ); ?></strong><p><input type="number" min="0" name="rewardly_loyalty_settings[pro_level_silver_threshold]" value="<?php echo esc_attr( (int) $settings['pro_level_silver_threshold'] ); ?>"></p></div>
				<div class="rewardly-admin-stat"><strong><?php esc_html_e( 'Gold threshold', 'nodevora-customer-rewards-for-woocommerce' ); ?></strong><p><input type="number" min="0" name="rewardly_loyalty_settings[pro_level_gold_threshold]" value="<?php echo esc_attr( (int) $settings['pro_level_gold_threshold'] ); ?>"></p></div>
			</div>
			<div class="rewardly-box-note"><?php esc_html_e( 'Levels are displayed in My Account with a badge and a progress bar.', 'nodevora-customer-rewards-for-woocommerce' ); ?></div>
			<?php submit_button(); ?>
		</form>
		<?php
	}

	private static function render_help_tab() {
		$settings = Rewardly_Loyalty_Helpers::get_settings(); ?>
		<div class="rewardly-admin-split">
			<div class="rewardly-admin-card">
				<h2><?php esc_html_e( 'Help and quick notes', 'nodevora-customer-rewards-for-woocommerce' ); ?></h2>
				<ul class="rewardly-admin-list">
					<li><?php esc_html_e( 'Use the general tab for the core loyalty conversion settings.', 'nodevora-customer-rewards-for-woocommerce' ); ?></li>
					<li><?php esc_html_e( 'Use General for loyalty rules and Design for appearance.', 'nodevora-customer-rewards-for-woocommerce' ); ?></li>
					<li><?php esc_html_e( 'Use Advanced Features to manage advanced rules, bonuses, campaigns and levels.', 'nodevora-customer-rewards-for-woocommerce' ); ?></li>
					<li><?php esc_html_e( 'Shortcodes can be inserted in builders, custom pages and account dashboards.', 'nodevora-customer-rewards-for-woocommerce' ); ?></li>
				</ul>
				<div class="rewardly-admin-note"><p><strong><?php esc_html_e( 'Display mode', 'nodevora-customer-rewards-for-woocommerce' ); ?>:</strong> <?php echo esc_html( self::get_notice_display_mode_summary( $settings['notice_display_mode'] ?? 'both' ) ); ?></p></div>
			</div>
			<div class="rewardly-admin-card">
				<h2><?php esc_html_e( 'Launch checklist', 'nodevora-customer-rewards-for-woocommerce' ); ?></h2>
				<ol class="rewardly-admin-panel-list">
					<li><?php esc_html_e( 'Confirm the earning and redeem conversion values in the main settings tab.', 'nodevora-customer-rewards-for-woocommerce' ); ?></li>
					<li><?php esc_html_e( 'Choose where the loyalty card should appear automatically.', 'nodevora-customer-rewards-for-woocommerce' ); ?></li>
					<li><?php esc_html_e( 'Select a free template and verify the store front result on desktop and mobile.', 'nodevora-customer-rewards-for-woocommerce' ); ?></li>
					<li><?php esc_html_e( 'Review the advanced features tab and enable the options you need for your store.', 'nodevora-customer-rewards-for-woocommerce' ); ?></li>
					<li><?php esc_html_e( 'Verify an isolated test order and refund or cancellation before release. Never use a real payment for testing.', 'nodevora-customer-rewards-for-woocommerce' ); ?></li>
				</ol>
			</div>
		</div>
		<?php if(Nodevora_Access::advanced()&&current_user_can('manage_options')){self::render_debug_tab();} ?>
		<?php
	}

	private static function render_debug_tab() {
		$settings   = Rewardly_Loyalty_Helpers::get_settings();
		$shortcodes = array( '[rewardly_points_balance]', '[rewardly_points_value]', '[rewardly_points_history]', '[rewardly_account_block]', '[rewardly_loyalty_notice]', '[rewardly_loyalty_card]' ); ?>
		<div class="rewardly-admin-card">
			<h2><?php esc_html_e( 'Debug information', 'nodevora-customer-rewards-for-woocommerce' ); ?></h2>
			<div class="rewardly-admin-grid">
				<div class="rewardly-admin-stat"><strong><?php esc_html_e( 'Plugin version', 'nodevora-customer-rewards-for-woocommerce' ); ?></strong><div class="rewardly-admin-code"><?php echo esc_html( NODEVORA_CUSTOMER_REWARDS_VERSION ); ?></div></div>
				<div class="rewardly-admin-stat"><strong><?php esc_html_e( 'WooCommerce active', 'nodevora-customer-rewards-for-woocommerce' ); ?></strong><div class="rewardly-admin-code"><?php echo esc_html( class_exists( 'WooCommerce' ) ? __( 'Yes', 'nodevora-customer-rewards-for-woocommerce' ) : __( 'No', 'nodevora-customer-rewards-for-woocommerce' ) ); ?></div></div>
				<div class="rewardly-admin-stat"><strong><?php esc_html_e( 'Store currency', 'nodevora-customer-rewards-for-woocommerce' ); ?></strong><div class="rewardly-admin-code"><?php echo esc_html( Rewardly_Loyalty_Helpers::get_store_currency_code() ); ?></div></div>
				<div class="rewardly-admin-stat"><strong><?php esc_html_e( 'Program enabled', 'nodevora-customer-rewards-for-woocommerce' ); ?></strong><div class="rewardly-admin-code"><?php echo esc_html( 'yes' === $settings['enabled'] ? __( 'Yes', 'nodevora-customer-rewards-for-woocommerce' ) : __( 'No', 'nodevora-customer-rewards-for-woocommerce' ) ); ?></div></div>
				<div class="rewardly-admin-stat"><strong><?php esc_html_e( 'Selected template', 'nodevora-customer-rewards-for-woocommerce' ); ?></strong><div class="rewardly-admin-code"><?php echo esc_html( $settings['design_template'] ?? 'default' ); ?> — <?php echo esc_html( self::get_template_summary( $settings['design_template'] ?? 'default' ) ); ?></div></div>
				<div class="rewardly-admin-stat"><strong><?php esc_html_e( 'Category rules', 'nodevora-customer-rewards-for-woocommerce' ); ?></strong><div class="rewardly-admin-code"><?php echo esc_html( count( $settings['pro_category_rules'] ) ); ?></div></div>
				<div class="rewardly-admin-stat"><strong><?php esc_html_e( 'Excluded products', 'nodevora-customer-rewards-for-woocommerce' ); ?></strong><div class="rewardly-admin-code"><?php echo esc_html( count( $settings['pro_excluded_product_ids'] ) ); ?></div></div>
				<div class="rewardly-admin-stat"><strong><?php esc_html_e( 'Excluded categories', 'nodevora-customer-rewards-for-woocommerce' ); ?></strong><div class="rewardly-admin-code"><?php echo esc_html( count( $settings['pro_excluded_category_ids'] ) ); ?></div></div>
				<div class="rewardly-admin-stat"><strong><?php esc_html_e( 'Levels enabled', 'nodevora-customer-rewards-for-woocommerce' ); ?></strong><div class="rewardly-admin-code"><?php echo esc_html( 'yes' === $settings['pro_levels_enabled'] ? __( 'Yes', 'nodevora-customer-rewards-for-woocommerce' ) : __( 'No', 'nodevora-customer-rewards-for-woocommerce' ) ); ?></div></div>
				<div class="rewardly-admin-stat"><strong><?php esc_html_e( 'Display mode summary', 'nodevora-customer-rewards-for-woocommerce' ); ?></strong><div class="rewardly-admin-code"><?php echo esc_html( self::get_notice_display_mode_summary( $settings['notice_display_mode'] ?? 'both' ) ); ?></div></div><div class="rewardly-admin-stat"><strong><?php esc_html_e( 'Available shortcodes', 'nodevora-customer-rewards-for-woocommerce' ); ?></strong><div class="rewardly-admin-code"><?php echo esc_html( implode( ' | ', $shortcodes ) ); ?></div></div>
			</div>
		</div>
		<?php
	}
}
