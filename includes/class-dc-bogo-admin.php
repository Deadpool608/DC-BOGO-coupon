<?php
/**
 * Admin class
 *
 * @package DC_BOGO_Coupons
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class DC_BOGO_Admin.
 */
class DC_BOGO_Admin {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
		add_action( 'add_meta_boxes', [ $this, 'add_rule_metaboxes' ] );
		add_action( 'add_meta_boxes', [ $this, 'cleanup_cpt_edit_screen' ], 99 );
		add_action( 'admin_head', [ $this, 'hide_unused_cpt_fields' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts_styles' ], 20 );
		add_action( 'save_post_dc_bogo_rule', [ $this, 'save_rule_metaboxes' ] );
		add_filter( 'post_updated_messages', [ $this, 'customize_cpt_updated_messages' ] );
		add_filter( 'manage_dc_bogo_rule_posts_columns', [ $this, 'set_custom_cpt_columns' ] );
		add_action( 'manage_dc_bogo_rule_posts_custom_column', [ $this, 'render_custom_cpt_columns' ], 10, 2 );
		add_action( 'admin_init', [ $this, 'handle_review_notice_actions' ] );
		add_action( 'admin_notices', [ $this, 'render_review_notice' ] );
	}

	/**
	 * Add the CPT to the WooCommerce Marketing menu.
	 */
	public function add_admin_menu() {
		add_menu_page(
			__( 'BOGO Rules', 'dc-bogo-coupons' ), // Page title
			__( 'BOGO Coupons', 'dc-bogo-coupons' ), // Menu title
			'manage_woocommerce', // Capability
			'edit.php?post_type=dc_bogo_rule', // Menu slug
			'', // Function to display page content
			plugin_dir_url( dirname( __FILE__ ) ) . 'includes/images/icon-20x20.png', // Custom icon
			'55.5' // Position
		);

		add_submenu_page(
			'edit.php?post_type=dc_bogo_rule', // Parent slug
			__( 'Settings', 'dc-bogo-coupons' ), // Page title
			__( 'Settings', 'dc-bogo-coupons' ), // Menu title
			'manage_woocommerce', // Capability
			'dc-bogo-settings', // Menu slug
			[ $this, 'render_settings_page' ] // Callback function
		);
	}

	/**
	 * Add metaboxes to the Rule Creation Screen.
	 */
	public function add_rule_metaboxes() {
		add_meta_box(
			'dc_bogo_rule_setup',
			__( 'Rule Setup', 'dc-bogo-coupons' ),
			[ $this, 'render_rule_setup_metabox' ],
			'dc_bogo_rule',
			'normal',
			'high'
		);
		add_meta_box(
			'dc_bogo_usage_restrictions',
			__( 'Usage Restrictions', 'dc-bogo-coupons' ),
			[ $this, 'render_usage_restrictions_metabox' ],
			'dc_bogo_rule',
			'normal',
			'default'
		);
		add_meta_box(
			'dc_bogo_advanced',
			__( 'Advanced', 'dc-bogo-coupons' ),
			[ $this, 'render_advanced_metabox' ],
			'dc_bogo_rule',
			'normal',
			'default'
		);

		add_meta_box(
			'dc_bogo_status',
			__( 'Rule Status', 'dc-bogo-coupons' ),
			[ $this, 'render_status_metabox' ],
			'dc_bogo_rule',
			'side',
			'high'
		);

		add_meta_box(
			'dc_bogo_developer_support',
			__( 'Support & Custom Development', 'dc-bogo-coupons' ),
			[ $this, 'render_developer_support_metabox' ],
			'dc_bogo_rule',
			'side',
			'low'
		);
	}

	/**
	 * Remove unused WP supports and metaboxes for a compact CPT edit screen.
	 */
	public function cleanup_cpt_edit_screen() {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || empty( $screen->post_type ) || 'dc_bogo_rule' !== $screen->post_type ) {
			return;
		}

		remove_post_type_support( 'dc_bogo_rule', 'title' );
		remove_post_type_support( 'dc_bogo_rule', 'editor' );
		remove_post_type_support( 'dc_bogo_rule', 'thumbnail' );
		remove_post_type_support( 'dc_bogo_rule', 'excerpt' );

		remove_meta_box( 'slugdiv', 'dc_bogo_rule', 'normal' );
		remove_meta_box( 'postimagediv', 'dc_bogo_rule', 'side' );
		remove_meta_box( 'revisionsdiv', 'dc_bogo_rule', 'normal' );
		remove_meta_box( 'commentsdiv', 'dc_bogo_rule', 'normal' );
		remove_meta_box( 'commentstatusdiv', 'dc_bogo_rule', 'normal' );
		remove_meta_box( 'trackbacksdiv', 'dc_bogo_rule', 'normal' );
		remove_meta_box( 'authordiv', 'dc_bogo_rule', 'normal' );
		remove_meta_box( 'postcustom', 'dc_bogo_rule', 'normal' );
	}

	/**
	 * Output lightweight admin CSS to hide title/permalink/featured image as a fallback.
	 */
	public function hide_unused_cpt_fields() {
	}

	/**
	 * Enqueue admin scripts and styles.
	 */
	public function enqueue_admin_scripts_styles( $hook ) {
		global $post_type;
		$is_rule_screen   = ( ( 'edit.php' === $hook || 'post.php' === $hook || 'post-new.php' === $hook ) && 'dc_bogo_rule' === $post_type );
		$is_settings_page = ( is_string( $hook ) && false !== strpos( $hook, 'dc-bogo-settings' ) );

		if ( $is_rule_screen || $is_settings_page ) {

			wp_enqueue_style( 'woocommerce_admin_styles' );
			wp_enqueue_script( 'wc-enhanced-select' );

			if ( $is_settings_page ) {
				wp_enqueue_script( 'wp-color-picker' );
				wp_enqueue_style( 'wp-color-picker' );
			}

			wp_enqueue_script(
				'dc-bogo-admin-js',
				plugin_dir_url( __FILE__ ) . '../admin.js',
				[ 'jquery' ], // Removed all other dependencies to isolate issue
				'1.0.12', // Version bump
				true
			);

			wp_localize_script(
				'dc-bogo-admin-js',
				'dcBogoAdmin',
				[
					'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
					'nonce'          => wp_create_nonce( 'dc_bogo_admin_nonce' ),
					'i18nActive'     => __( 'Active', 'dc-bogo-coupons' ),
					'i18nInactive'   => __( 'Inactive', 'dc-bogo-coupons' ),
					'i18nScheduled'  => __( 'Scheduled', 'dc-bogo-coupons' ),
					'i18nExpired'    => __( 'Expired', 'dc-bogo-coupons' ),
					'i18nError'      => __( 'Error', 'dc-bogo-coupons' ),
					'i18nSameItemError' => __( 'You cannot select the same item in "Buy" and "Get" for this rule type.', 'dc-bogo-coupons' ),
				]
			);

			wp_enqueue_style(
				'dc-bogo-admin-css',
				plugin_dir_url( __DIR__ ) . '../admin-styles.css',
				[ 'woocommerce_admin_styles' ],
				'1.0.1'
			);

			// Inline CSS for Rule Screen
			if ( $is_rule_screen ) {
				$custom_css = '#titlediv, #slugdiv, #postimagediv, #postexcerpt, #revisionsdiv { display: none !important; } .wp-editor-container { display: none !important; }';
				wp_add_inline_style( 'dc-bogo-admin-css', $custom_css );
			}

			if ( $is_settings_page ) {
				wp_add_inline_script( 'dc-bogo-admin-js', "jQuery('.dc-bogo-tab-content.active').show();" );
			}
		}
	}

	/**
	 * Render the plugin settings page.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		if ( isset( $_POST['submit'] ) && check_admin_referer( 'dc_bogo_settings_nonce', 'dc_bogo_settings_nonce_field' ) ) {
			$this->save_plugin_settings();
		}

		$keep_data_on_uninstall = get_option( 'dc_bogo_keep_data_on_uninstall', 'no' );
		$show_progress_bar      = get_option( 'dc_bogo_show_progress_bar', 'yes' );
		$progress_color         = get_option( 'dc_bogo_progress_color', '#17a673' );
		$progress_position      = get_option( 'dc_bogo_progress_position', 'before_totals' );
		$show_on_shop           = get_option( 'dc_bogo_show_on_shop', 'no' );

		$active_tab = 'dc-bogo-general';
		if ( isset( $_REQUEST['dc_bogo_active_tab'] ) && in_array( $_REQUEST['dc_bogo_active_tab'], [ 'dc-bogo-general', 'dc-bogo-progress-bar', 'dc-bogo-advanced' ], true ) ) {
			$active_tab = sanitize_text_field( wp_unslash( $_REQUEST['dc_bogo_active_tab'] ) );
		}
		?>
		<div class="wrap dc-bogo-settings-wrap">
			<h1><?php esc_html_e( 'BOGO Coupons Settings', 'dc-bogo-coupons' ); ?></h1>

			<nav class="nav-tab-wrapper dc-bogo-nav-tab-wrapper">
				<a href="#dc-bogo-general" class="nav-tab <?php echo 'dc-bogo-general' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'General', 'dc-bogo-coupons' ); ?></a>
				<a href="#dc-bogo-progress-bar" class="nav-tab <?php echo 'dc-bogo-progress-bar' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Progress Bar', 'dc-bogo-coupons' ); ?></a>
				<a href="#dc-bogo-advanced" class="nav-tab <?php echo 'dc-bogo-advanced' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Advanced', 'dc-bogo-coupons' ); ?></a>
			</nav>

			<form method="post" action="" class="dc-bogo-settings-form">
				<input type="hidden" name="dc_bogo_active_tab" id="dc_bogo_active_tab" value="<?php echo esc_attr( $active_tab ); ?>" />
				<?php wp_nonce_field( 'dc_bogo_settings_nonce', 'dc_bogo_settings_nonce_field' ); ?>

				<!-- GENERAL TAB -->
				<div id="dc-bogo-general" class="dc-bogo-tab-content <?php echo 'dc-bogo-general' === $active_tab ? 'active' : ''; ?>">
					<div class="dc-bogo-card" style="padding:18px;">
						<div class="dc-bogo-card-header">
							<h2 style="margin:0; text-align:center;"><?php esc_html_e( 'Thank you for choosing DC BOGO Coupons', 'dc-bogo-coupons' ); ?></h2>
						</div>
						<div class="dc-bogo-card-body" style="text-align:center; padding:12px 24px; color:#334155;">
							<p style="margin:0; font-size:15px;"><?php esc_html_e( "We're grateful you've chosen DC BOGO Coupons — we're here to help you increase sales with intelligent BOGO offers. Configure global behavior and rules below.", 'dc-bogo-coupons' ); ?></p>
						</div>
					</div>

					<div class="dc-bogo-card" style="margin-top:12px;">
						<div class="dc-bogo-card-header">
							<h2><?php esc_html_e( 'Rule Selection Strategy', 'dc-bogo-coupons' ); ?></h2>
						</div>
						<div class="dc-bogo-card-body">
							<p><?php esc_html_e( 'Choose how overlapping BOGO rules are resolved. This setting affects which offer is applied when multiple rules could qualify.', 'dc-bogo-coupons' ); ?></p>
							<div class="dc-bogo-form-group">
								<?php $strategy = get_option( 'dc_bogo_selection_strategy', 'max_discount' ); ?>
								<select id="dc_bogo_selection_strategy" name="dc_bogo_selection_strategy">
									<option value="max_discount" <?php selected( $strategy, 'max_discount' ); ?>><?php esc_html_e( 'Apply rule giving maximum discount (recommended)', 'dc-bogo-coupons' ); ?></option>
									<option value="min_discount" <?php selected( $strategy, 'min_discount' ); ?>><?php esc_html_e( 'Apply rule giving minimum discount', 'dc-bogo-coupons' ); ?></option>
									<option value="closest" <?php selected( $strategy, 'closest' ); ?>><?php esc_html_e( 'Apply rule closest to completion', 'dc-bogo-coupons' ); ?></option>
								</select>
								<p class="description"><?php esc_html_e( 'Choose which rule to apply when multiple BOGO offers could qualify. Maximum discount gives customers the best deal.', 'dc-bogo-coupons' ); ?></p>
							</div>
						</div>
					</div>

					<div class="dc-bogo-card" style="margin-top:12px;">
						<div class="dc-bogo-card-header">
							<h2><?php esc_html_e( 'Display Options', 'dc-bogo-coupons' ); ?></h2>
						</div>
						<div class="dc-bogo-card-body">
							<div class="dc-bogo-section">
								<h3 class="dc-bogo-section-title"><?php esc_html_e( 'Savings Notice', 'dc-bogo-coupons' ); ?></h3>
								<div class="dc-bogo-form-group">
									<label class="dc-switch">
										<input type="checkbox" id="dc_bogo_show_savings_notice" name="dc_bogo_show_savings_notice" value="yes" <?php checked( get_option( 'dc_bogo_show_savings_notice', 'yes' ), 'yes' ); ?> />
										<span class="dc-slider round"></span>
									</label>
									<label for="dc_bogo_show_savings_notice" class="dc-bogo-switch-label"><?php esc_html_e( 'Show "You Saved" notice box in cart', 'dc-bogo-coupons' ); ?></label>
									<p class="description"><?php esc_html_e( 'Display a notice showing customers how much they saved with BOGO deals.', 'dc-bogo-coupons' ); ?></p>
								</div>
							</div>

							<div class="dc-bogo-section">
								<h3 class="dc-bogo-section-title"><?php esc_html_e( 'Custom Branding', 'dc-bogo-coupons' ); ?></h3>
								<div class="dc-bogo-form-group">
									<label class="dc-switch">
										<input type="checkbox" id="dc_bogo_use_custom_tag" name="dc_bogo_use_custom_tag" value="yes" <?php checked( get_option( 'dc_bogo_use_custom_tag', 'no' ), 'yes' ); ?> />
										<span class="dc-slider round"></span>
									</label>
									<label for="dc_bogo_use_custom_tag" class="dc-bogo-switch-label"><?php esc_html_e( 'Use custom tag instead of offer name', 'dc-bogo-coupons' ); ?></label>
									<p class="description"><?php esc_html_e( 'Show a generic tag like "BOGO Deal" instead of the specific offer name in savings messages.', 'dc-bogo-coupons' ); ?></p>
								</div>

								<div class="dc-bogo-form-group dc-bogo-custom-tag-field" style="margin-top: 15px; <?php echo 'yes' === get_option( 'dc_bogo_use_custom_tag', 'no' ) ? '' : 'display:none;'; ?>">
									<label for="dc_bogo_custom_tag_text"><?php esc_html_e( 'Custom Tag Text', 'dc-bogo-coupons' ); ?></label>
									<input type="text" id="dc_bogo_custom_tag_text" name="dc_bogo_custom_tag_text" value="<?php echo esc_attr( get_option( 'dc_bogo_custom_tag_text', 'BOGO Deal' ) ); ?>" placeholder="<?php esc_attr_e( 'e.g., BOGO Deal, Special Offer', 'dc-bogo-coupons' ); ?>" style="width: 100%; max-width: 400px;" />
									<p class="description"><?php esc_html_e( 'This text will be shown instead of the offer name in savings messages.', 'dc-bogo-coupons' ); ?></p>
								</div>
							</div>
						</div>
					</div>
				</div> <!-- End General Tab -->

				<!-- PROGRESS BAR TAB -->
				<div id="dc-bogo-progress-bar" class="dc-bogo-tab-content <?php echo 'dc-bogo-progress-bar' === $active_tab ? 'active' : ''; ?>">
					<div class="dc-bogo-card">
						<div class="dc-bogo-card-header">
							<h2><?php esc_html_e( 'Progress Bar Customization', 'dc-bogo-coupons' ); ?></h2>
						</div>
						<div class="dc-bogo-card-body">
							<div class="dc-bogo-form-group">
								<label class="dc-switch">
									<input type="checkbox" id="dc_bogo_show_progress_bar" name="dc_bogo_show_progress_bar" value="yes" <?php checked( 'yes', $show_progress_bar ); ?> />
									<span class="dc-slider round"></span>
								</label>
								<label for="dc_bogo_show_progress_bar" class="dc-bogo-switch-label"><?php esc_html_e( 'Show progress bar in cart', 'dc-bogo-coupons' ); ?></label>
								<p class="description"><?php esc_html_e( 'Enable this to display a visual progress bar showing how close customers are to a BOGO deal.', 'dc-bogo-coupons' ); ?></p>
							</div>

							<div class="dc-bogo-preview-container" style="margin-bottom:12px;">
								<label><?php esc_html_e( 'Live Preview', 'dc-bogo-coupons' ); ?></label>
								<div id="dc-bogo-progress-full-preview" style="--dc-accent: <?php echo esc_attr( $progress_color ); ?>; <?php echo 'yes' === $show_progress_bar ? '' : 'display:none;'; ?>">
									<div class="dc-bogo-custom-notice-box" style="padding:12px;">
										<div class="dc-bogo-group-pills" style="margin-bottom:8px;">
											<button class="dc-bogo-pill active" type="button"><?php esc_html_e( 'Example Deal', 'dc-bogo-coupons' ); ?></button>
										</div>
										<p class="dc-bogo-reminder-text" style="margin:6px 0 8px 0"><?php esc_html_e( 'Add 1 more qualifying item to get the "Example Deal"!', 'dc-bogo-coupons' ); ?></p>
										<div class="dc-bogo-progress-bar-container" style="max-width:420px;">
											<div class="dc-bogo-progress-bar" style="background:#e6eef7; position:relative;">
												<div id="dc-bogo-progress-full-fill" class="dc-bogo-progress-bar-fill" style="padding-top:12px; height:20px;width:55%;"></div>
												<span class="dc-bogo-progress-bar-label" style="position:absolute; left:12px; font-size:12px; color:#223;">55% — <?php esc_html_e( 'Example progress', 'dc-bogo-coupons' ); ?></span>
												<div class="dc-bogo-milestone" style="left:50%;" title="Example Deal (3 items)"></div>
											</div>
										</div>
									</div>
								</div>
							</div>

							<div class="dc-bogo-progress-bar-options" style="<?php echo 'yes' === $show_progress_bar ? '' : 'display: none;'; ?>">
								<div class="dc-bogo-flex-container">
									<div class="dc-bogo-form-group">
										<label for="dc_bogo_progress_color"><?php esc_html_e( 'Accent Color', 'dc-bogo-coupons' ); ?></label>
										<input type="text" id="dc_bogo_progress_color" name="dc_bogo_progress_color" value="<?php echo esc_attr( $progress_color ); ?>" class="dc-color-field" />
									</div>
									<div class="dc-bogo-form-group">
										<label for="dc_bogo_progress_position"><?php esc_html_e( 'Position', 'dc-bogo-coupons' ); ?></label>
										<select id="dc_bogo_progress_position" name="dc_bogo_progress_position">
											<option value="before_totals" <?php selected( $progress_position, 'before_totals' ); ?>><?php esc_html_e( 'Before Cart Totals', 'dc-bogo-coupons' ); ?></option>
											<option value="before_cart" <?php selected( $progress_position, 'before_cart' ); ?>><?php esc_html_e( 'Above Cart', 'dc-bogo-coupons' ); ?></option>
										</select>
									</div>
								</div>

								<div class="dc-bogo-form-group" style="margin-top: 20px;">
									<label class="dc-switch">
										<input type="checkbox" id="dc_bogo_show_on_shop" name="dc_bogo_show_on_shop" value="yes" <?php checked( $show_on_shop, 'yes' ); ?> />
										<span class="dc-slider round"></span>
									</label>
									<label for="dc_bogo_show_on_shop" class="dc-bogo-switch-label"><?php esc_html_e( 'Also show on Shop page', 'dc-bogo-coupons' ); ?></label>
									<p class="description"><?php esc_html_e( 'If enabled, the progress bar will also appear on the main Shop page.', 'dc-bogo-coupons' ); ?></p>
								</div>
							</div>
						</div>
					</div>
				</div>

				<div id="dc-bogo-advanced" class="dc-bogo-tab-content <?php echo 'dc-bogo-advanced' === $active_tab ? 'active' : ''; ?>">
					<div class="dc-bogo-card">
						<div class="dc-bogo-card-header">
							<h2><?php esc_html_e( 'Data Management', 'dc-bogo-coupons' ); ?></h2>
						</div>
						<div class="dc-bogo-card-body">
							<div class="dc-bogo-form-group">
								<label class="dc-switch">
									<input type="checkbox" id="dc_bogo_keep_data_on_uninstall" name="dc_bogo_keep_data_on_uninstall" value="yes" <?php checked( 'yes', $keep_data_on_uninstall ); ?> />
									<span class="dc-slider round"></span>
								</label>
								<label for="dc_bogo_keep_data_on_uninstall" class="dc-bogo-switch-label"><?php esc_html_e( 'Keep plugin data on uninstall', 'dc-bogo-coupons' ); ?></label>
								<p class="description"><?php esc_html_e( 'Enable this to preserve all your BOGO rules and settings if the plugin is uninstalled.', 'dc-bogo-coupons' ); ?></p>
							</div>
						</div>
					</div>
				</div>

				<?php wp_nonce_field( 'dc_bogo_settings_action', 'dc_bogo_settings_nonce' ); ?>
				<p class="submit">
					<input type="submit" name="submit" id="submit" class="button button-primary" value="<?php esc_attr_e( 'Save Changes', 'dc-bogo-coupons' ); ?>">
				</p>
			</form>

			<div class="dc-bogo-card" style="margin-top: 24px; border-left: 4px solid #2271b1; background: #fff;">
				<div class="dc-bogo-card-header" style="border-bottom: 1px solid #e2e8f0; padding: 14px 20px;">
					<h2 style="margin: 0; font-size: 15px; color: #1d2327;"><?php esc_html_e( 'Developer Support & Custom WooCommerce Development', 'dc-bogo-coupons' ); ?></h2>
				</div>
				<div class="dc-bogo-card-body" style="padding: 16px 20px;">
					<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;">
						<div style="background: #f8fafc; padding: 16px; border-radius: 6px; border: 1px solid #e2e8f0;">
							<h3 style="margin-top: 0; font-size: 14px; color: #1e293b;">💼 <?php esc_html_e( 'Need Custom Features or Store Setup?', 'dc-bogo-coupons' ); ?></h3>
							<p style="color: #64748b; font-size: 13px; line-height: 1.5;"><?php esc_html_e( 'Need bespoke BOGO logic, checkout customizations, or custom WooCommerce plugin development? Hire the creator of DC BOGO directly.', 'dc-bogo-coupons' ); ?></p>
							<a href="https://www.ramira.in/contact/" target="_blank" class="button button-primary" style="font-weight: 500;"><?php esc_html_e( 'Contact / Hire Developer', 'dc-bogo-coupons' ); ?> &rarr;</a>
						</div>
						<div style="background: #f8fafc; padding: 16px; border-radius: 6px; border: 1px solid #e2e8f0;">
							<h3 style="margin-top: 0; font-size: 14px; color: #1e293b;">☕ <?php esc_html_e( 'Support Free Plugin Development', 'dc-bogo-coupons' ); ?></h3>
							<p style="color: #64748b; font-size: 13px; line-height: 1.5;"><?php esc_html_e( 'DC BOGO is 100% free with all category rules unlocked. If it brings sales to your store, consider tipping or supporting development.', 'dc-bogo-coupons' ); ?></p>
							<a href="https://www.ramira.in/support/" target="_blank" class="button button-secondary" style="font-weight: 500;"><?php esc_html_e( 'Support & Donate', 'dc-bogo-coupons' ); ?> ❤️</a>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Save plugin settings.
	 */
	private function save_plugin_settings() {
		if ( ! isset( $_POST['dc_bogo_settings_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['dc_bogo_settings_nonce'] ), 'dc_bogo_settings_action' ) ) {
			return;
		}

		$show_progress_bar = isset( $_POST['dc_bogo_show_progress_bar'] ) ? 'yes' : 'no';
		update_option( 'dc_bogo_show_progress_bar', $show_progress_bar );

		$keep_data_on_uninstall = isset( $_POST['dc_bogo_keep_data_on_uninstall'] ) ? 'yes' : 'no';
		update_option( 'dc_bogo_keep_data_on_uninstall', $keep_data_on_uninstall );

		$show_savings_notice = isset( $_POST['dc_bogo_show_savings_notice'] ) ? 'yes' : 'no';
		update_option( 'dc_bogo_show_savings_notice', $show_savings_notice );

		$use_custom_tag = isset( $_POST['dc_bogo_use_custom_tag'] ) ? 'yes' : 'no';
		update_option( 'dc_bogo_use_custom_tag', $use_custom_tag );

		$custom_tag_text = isset( $_POST['dc_bogo_custom_tag_text'] ) ? sanitize_text_field( wp_unslash( $_POST['dc_bogo_custom_tag_text'] ) ) : 'BOGO Deal';
		update_option( 'dc_bogo_custom_tag_text', $custom_tag_text );

		$progress_color = isset( $_POST['dc_bogo_progress_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['dc_bogo_progress_color'] ) ) : '#17a673';
		update_option( 'dc_bogo_progress_color', $progress_color );

		$progress_position = isset( $_POST['dc_bogo_progress_position'] ) ? sanitize_text_field( wp_unslash( $_POST['dc_bogo_progress_position'] ) ) : 'before_totals';
		$allowed_positions = [ 'before_totals', 'before_cart' ];
		if ( ! in_array( $progress_position, $allowed_positions, true ) ) {
			$progress_position = 'before_totals';
		}
		update_option( 'dc_bogo_progress_position', $progress_position );

		$show_on_shop = isset( $_POST['dc_bogo_show_on_shop'] ) ? 'yes' : 'no';
		update_option( 'dc_bogo_show_on_shop', $show_on_shop );

		$selection_strategy = isset( $_POST['dc_bogo_selection_strategy'] ) ? sanitize_text_field( wp_unslash( $_POST['dc_bogo_selection_strategy'] ) ) : 'max_discount';
		$allowed_strats = [ 'max_discount', 'min_discount', 'closest' ];
		if ( ! in_array( $selection_strategy, $allowed_strats, true ) ) {
			$selection_strategy = 'max_discount';
		}
		update_option( 'dc_bogo_selection_strategy', $selection_strategy );

		if ( function_exists( 'wc_admin_notice' ) ) {
			wc_admin_notice( __( 'Your settings have been saved.', 'dc-bogo-coupons' ), 'success' );
		} else {
			add_settings_error( 'dc_bogo', 'dc_bogo_settings_saved', __( 'Your settings have been saved.', 'dc-bogo-coupons' ), 'updated' );
		}
	}

	/**
	 * Render the Status Metabox.
	 */
	public function render_status_metabox( $post ) {
		wp_nonce_field( 'dc_bogo_save', 'dc_bogo_nonce' );
		$is_active = get_post_meta( $post->ID, '_dc_bogo_is_active', true );
		?>
		<div class="form-field" style="border: none; padding: 5px;">
			<label class="dc-switch">
				<input type="checkbox" name="_dc_bogo_is_active" value="yes" <?php checked( $is_active, 'yes' ); ?>>
				<span class="dc-slider round"></span>
			</label>
			<label for="_dc_bogo_is_active" style="display:inline-block; margin-left:10px; vertical-align: middle;"><?php esc_html_e( 'Activate Rule', 'dc-bogo-coupons' ); ?></label>
		</div>
		<?php
	}

	/**
	 * Render Metabox for Tab 1: Rule Setup.
	 */
	public function render_rule_setup_metabox( $post ) {
		$offer_name     = get_post_meta( $post->ID, '_dc_bogo_offer_name', true );
		$bogo_type      = get_post_meta( $post->ID, '_dc_bogo_type', true ) ?: 'buy_x_get_y_diff';
		$buy_qty        = get_post_meta( $post->ID, '_dc_bogo_buy_qty', true ) ?: 1;
		$buy_match      = get_post_meta( $post->ID, '_dc_bogo_buy_match', true ) ?: 'products';
		$get_qty        = get_post_meta( $post->ID, '_dc_bogo_get_qty', true ) ?: 1;
		$get_match      = get_post_meta( $post->ID, '_dc_bogo_get_match', true ) ?: 'products';
		$discount_type  = get_post_meta( $post->ID, '_dc_bogo_discount_type', true ) ?: 'free';
		$discount_val   = get_post_meta( $post->ID, '_dc_bogo_discount_val', true ) ?: 100;
		$buy_products   = get_post_meta( $post->ID, '_dc_bogo_buy_products', true ) ?: [];
		$buy_categories = get_post_meta( $post->ID, '_dc_bogo_buy_categories', true ) ?: [];
		$get_products   = get_post_meta( $post->ID, '_dc_bogo_get_products', true ) ?: [];
		$get_categories = get_post_meta( $post->ID, '_dc_bogo_get_categories', true ) ?: [];
		$auto_add_get_product = get_post_meta( $post->ID, '_dc_bogo_auto_add_get_product', true );

		echo '<div class="dc-bogo-metabox">';

		echo '<div class="form-field"><h4>' . esc_html__( 'Offer Name', 'dc-bogo-coupons' ) . '</h4>';
		echo '<input type="text" name="_dc_bogo_offer_name" value="' . esc_attr( $offer_name ) . '" placeholder="' . esc_html__( 'e.g., B1G2 Special', 'dc-bogo-coupons' ) . '" required style="width: 100%;" />';
		echo '<p class="description">' . esc_html__( 'This name will be shown to the customer in the cart. This is a mandatory field.', 'dc-bogo-coupons' ) . '</p>';
		echo '</div>';

		echo '<div class="form-field"><h4>' . esc_html__( 'BOGO Type', 'dc-bogo-coupons' ) . '</h4>';
		echo '<div class="sub-field-group" style="border-top: none; padding-top: 0; margin-top: 10px;">';
		echo '<div class="sub-field"><input type="radio" id="bogo_type_diff" name="_dc_bogo_type" value="buy_x_get_y_diff" ' . checked( $bogo_type, 'buy_x_get_y_diff', false ) . '><label for="bogo_type_diff">' . esc_html__( 'Buy X, Get Y (Different Items)', 'dc-bogo-coupons' ) . '</label></div>';
		echo '<div class="sub-field"><input type="radio" id="bogo_type_same" name="_dc_bogo_type" value="buy_x_get_x_same" ' . checked( $bogo_type, 'buy_x_get_x_same', false ) . '><label for="bogo_type_same">' . esc_html__( 'Buy X, Get Y (Same Item)', 'dc-bogo-coupons' ) . '</label></div>';
		echo '</div></div>';

		echo '<div class="dc-bogo-buy-get-container">';

		echo '<div class="dc-bogo-buy-conditions"><h4>' . esc_html__( 'Buy Condition', 'dc-bogo-coupons' ) . '</h4>';
		echo '<label for="_dc_bogo_buy_qty">' . esc_html__( 'Quantity:', 'dc-bogo-coupons' ) . '</label>';
		echo '<input type="number" id="_dc_bogo_buy_qty" name="_dc_bogo_buy_qty" value="' . esc_attr( $buy_qty ) . '" min="1" step="1" style="width: 100px;"/>';
		echo '<p class="description">' . esc_html__( 'Customer must buy at least this many of the items specified below.', 'dc-bogo-coupons' ) . '</p>';

		echo '<div class="sub-field-group">';
			echo '<div class="sub-field"><input type="radio" id="buy_match_products" name="_dc_bogo_buy_match" value="products" ' . checked( $buy_match, 'products', false ) . '><label for="buy_match_products">' . esc_html__( 'Specific Products', 'dc-bogo-coupons' ) . '</label></div>';
			echo '<div class="sub-field"><input type="radio" id="buy_match_categories" name="_dc_bogo_buy_match" value="categories" ' . checked( $buy_match, 'categories', false ) . '><label for="buy_match_categories">' . esc_html__( 'Product Categories', 'dc-bogo-coupons' ) . '</label></div>';
		echo '</div>';

			$allowed_html_select = [
				'select'   => [
					'id'               => [],
					'name'             => [],
					'class'            => [],
					'multiple'         => [],
					'data-placeholder' => [],
					'style'            => [],
				],
				'option'   => [
					'value'    => [],
					'selected' => [],
				],
				'optgroup' => [
					'label' => [],
				],
			];

			echo '<div class="field-container dc-bogo-buy-products-field" style="display:none;">' . wp_kses( dc_bogo_render_product_selector( '_dc_bogo_buy_products[]', $buy_products ), $allowed_html_select ) . '</div>';
			echo '<div class="field-container dc-bogo-buy-categories-field" style="display:none;">' . wp_kses( dc_bogo_render_taxonomy_selector( '_dc_bogo_buy_categories[]', 'product_cat', $buy_categories ), $allowed_html_select ) . '</div>';
		echo '</div>';

		echo '<div class="dc-bogo-get-conditions"><h4>' . esc_html__( 'Get Offer', 'dc-bogo-coupons' ) . '</h4>';
		echo '<label for="_dc_bogo_get_qty">' . esc_html__( 'Quantity:', 'dc-bogo-coupons' ) . '</label>';
		echo '<input type="number" id="_dc_bogo_get_qty" name="_dc_bogo_get_qty" value="' . esc_attr( $get_qty ) . '" min="1" step="1" style="width: 100px;"/>';
		echo '<p class="description">' . esc_html__( 'They will receive this many of the items specified below.', 'dc-bogo-coupons' ) . '</p>';

		echo '<div class="sub-field-group">';
			echo '<div class="sub-field"><input type="radio" id="get_match_products" name="_dc_bogo_get_match" value="products" ' . checked( $get_match, 'products', false ) . '><label for="get_match_products">' . esc_html__( 'Specific Products', 'dc-bogo-coupons' ) . '</label></div>';
			echo '<div class="sub-field"><input type="radio" id="get_match_categories" name="_dc_bogo_get_match" value="categories" ' . checked( $get_match, 'categories', false ) . '><label for="get_match_categories">' . esc_html__( 'Product Categories', 'dc-bogo-coupons' ) . '</label></div>';
		echo '</div>';

			echo '<div class="field-container dc-bogo-get-products-field" style="display:none;">';
			echo wp_kses( dc_bogo_render_product_selector( '_dc_bogo_get_products[]', $get_products ), $allowed_html_select );
			echo '<div style="margin-top: 15px;"><label class="dc-switch"><input type="checkbox" name="_dc_bogo_auto_add_get_product" value="yes" ' . checked( $auto_add_get_product, 'yes', false ) . '><span class="dc-slider round"></span></label><label for="_dc_bogo_auto_add_get_product" style="display:inline-block; margin-left:10px; vertical-align: middle;">' . esc_html__( 'Auto-add this item to the cart when conditions are met.', 'dc-bogo-coupons' ) . '</label></div>';
			echo '</div>';
			echo '<div class="field-container dc-bogo-get-categories-field" style="display:none;">' . wp_kses( dc_bogo_render_taxonomy_selector( '_dc_bogo_get_categories[]', 'product_cat', $get_categories ), $allowed_html_select ) . '</div>';

			echo '<div class="sub-field-group" style="margin-top: 15px;">';
				echo '<label>' . esc_html__( 'At a Discount of:', 'dc-bogo-coupons' ) . '</label>';
				echo '<div class="sub-field"><input type="radio" id="discount_type_free" name="_dc_bogo_discount_type" value="free" ' . checked( $discount_type, 'free', false ) . '><label for="discount_type_free">' . esc_html__( 'Free (100%)', 'dc-bogo-coupons' ) . '</label></div>';
				echo '<div class="sub-field"><input type="radio" id="discount_type_percent" name="_dc_bogo_discount_type" value="percent" ' . checked( $discount_type, 'percent', false ) . '><label for="discount_type_percent">' . esc_html__( 'Percentage %', 'dc-bogo-coupons' ) . '</label></div>';
				echo '<div class="sub-field"><input type="radio" id="discount_type_fixed" name="_dc_bogo_discount_type" value="fixed" ' . checked( $discount_type, 'fixed', false ) . '><label for="discount_type_fixed">' . esc_html__( 'Fixed Amount', 'dc-bogo-coupons' ) . '</label></div>';
				echo '<div class="field-container dc-bogo-discount-value-field"><input type="number" name="_dc_bogo_discount_val" value="' . esc_attr( $discount_val ) . '" min="0" step="0.01" style="width: 100px;" /></div>';
			echo '</div></div>';

		echo '</div>'; // .dc-bogo-buy-get-container

		echo '</div>'; // .dc-bogo-metabox
	}

	/**
	 * Render Metabox for Tab 2: Usage Restrictions.
	 */
	public function render_usage_restrictions_metabox( $post ) {
		$apply_mode         = get_post_meta( $post->ID, '_dc_bogo_apply_mode', true ) ?: 'auto';
		$coupon_code        = get_post_meta( $post->ID, '_dc_bogo_coupon_code', true );
		$min_subtotal       = get_post_meta( $post->ID, '_dc_bogo_min_subtotal', true );
		$max_subtotal       = get_post_meta( $post->ID, '_dc_bogo_max_subtotal', true );
		$limit_per_customer = get_post_meta( $post->ID, '_dc_bogo_limit_per_customer', true );
		$limit_total        = get_post_meta( $post->ID, '_dc_bogo_limit_total', true );
		$allowed_roles      = get_post_meta( $post->ID, '_dc_bogo_allowed_roles', true ) ?: [];

		echo '<div class="dc-bogo-metabox">';

		echo '<div class="form-field"><h4>' . esc_html__( 'Coupon Code Requirement', 'dc-bogo-coupons' ) . '</h4>';
		echo '<div class="sub-field-group dc-bogo-apply-mode-selector" style="border-top: none; padding-top: 0; margin-top: 10px;">';
			echo '<div class="sub-field"><input type="radio" id="apply_mode_auto" name="_dc_bogo_apply_mode" value="auto" ' . checked( $apply_mode, 'auto', false ) . '><label for="apply_mode_auto">' . esc_html__( 'Apply Automatically', 'dc-bogo-coupons' ) . ' (No coupon code needed)</label></div>';
			echo '<div class="sub-field"><input type="radio" id="apply_mode_coupon" name="_dc_bogo_apply_mode" value="coupon" ' . checked( $apply_mode, 'coupon', false ) . '><label for="apply_mode_coupon">' . esc_html__( 'Require Coupon Code', 'dc-bogo-coupons' ) . ' (Customer must enter a code)</label></div>';
		echo '</div>';
		echo '<div class="field-container dc-bogo-coupon-code-field" style="display: ' . ( 'coupon' === $apply_mode ? 'block' : 'none' ) . ';">';
			echo '<label>' . esc_html__( 'Coupon Code', 'dc-bogo-coupons' ) . ' (The code customers will enter)</label>';
			echo '<input type="text" name="_dc_bogo_coupon_code" value="' . esc_attr( $coupon_code ) . '" placeholder="' . esc_html__( 'Enter coupon code', 'dc-bogo-coupons' ) . '" style="width: 100%;" />';
		echo '</div>';
		echo '</div>';

		echo '<div class="form-field"><h4>' . esc_html__( 'Cart Subtotal Conditions', 'dc-bogo-coupons' ) . '</h4>';
		echo '<div style="display: flex; gap: 20px;">';
			echo '<div><label>' . esc_html__( 'Minimum Cart Subtotal', 'dc-bogo-coupons' ) . ' (e.g., 50.00)</label>';
			echo '<input type="number" name="_dc_bogo_min_subtotal" value="' . esc_attr( $min_subtotal ) . '" min="0" step="0.01" placeholder="No minimum" style="width: 100%;" /></div>';
			echo '<div><label>' . esc_html__( 'Maximum Cart Subtotal', 'dc-bogo-coupons' ) . ' (e.g., 200.00)</label>';
			echo '<input type="number" name="_dc_bogo_max_subtotal" value="' . esc_attr( $max_subtotal ) . '" min="0" step="0.01" placeholder="No maximum" style="width: 100%;" /></div>';
		echo '</div></div>';

		echo '<div class="form-field"><h4>' . esc_html__( 'Allowed User Roles', 'dc-bogo-coupons' ) . '</h4>';
		$allowed_html_select = [
			'select'   => [
				'id'               => [],
				'name'             => [],
				'class'            => [],
				'multiple'         => [],
				'data-placeholder' => [],
				'style'            => [],
			],
			'option'   => [
				'value'    => [],
				'selected' => [],
			],
		];
		echo wp_kses( dc_bogo_render_user_role_selector( '_dc_bogo_allowed_roles[]', $allowed_roles ), $allowed_html_select );
		echo '<p class="description">' . esc_html__( 'Leave blank to allow all logged-in users and guests. (Select specific roles to restrict usage)', 'dc-bogo-coupons' ) . '</p>';
		echo '</div>';

		echo '<div class="form-field"><h4>' . esc_html__( 'Usage Limits', 'dc-bogo-coupons' ) . '</h4>';
		echo '<div style="display: flex; gap: 20px;">';
			echo '<div><label>' . esc_html__( 'Total Usage Limit', 'dc-bogo-coupons' ) . ' (e.g., 100)</label>';
			echo '<input type="number" name="_dc_bogo_limit_total" value="' . esc_attr( $limit_total ) . '" min="0" step="1" placeholder="Unlimited uses" style="width: 100%;" /></div>';
			echo '<div><label>' . esc_html__( 'Limit Per Customer', 'dc-bogo-coupons' ) . ' (e.g., 1)</label>';
			echo '<input type="number" name="_dc_bogo_limit_per_customer" value="' . esc_attr( $limit_per_customer ) . '" min="0" step="1" placeholder="Unlimited uses per customer" style="width: 100%;" /></div>';
		echo '</div></div>';

		echo '</div>'; // .dc-bogo-metabox
	}

	/**
	 * Render Metabox for Tab 3: Advanced.
	 */
	public function render_advanced_metabox( $post ) {
		echo '<div class="dc-bogo-metabox">';

		echo '<div class="form-field"><h4>' . esc_html__( 'Advanced Application', 'dc-bogo-coupons' ) . '</h4>';
		echo '<div><label class="dc-switch"><input type="checkbox" name="_dc_bogo_apply_cheapest" value="yes" ' . checked( get_post_meta( $post->ID, '_dc_bogo_apply_cheapest', true ), 'yes', false ) . '><span class="dc-slider round"></span></label>';
		echo '<label>' . esc_html__( 'Apply discount to the cheapest item(s) in the cart.', 'dc-bogo-coupons' ) . '</label></div>';
		echo '<div style="margin-top: 15px;"><label class="dc-switch"><input type="checkbox" name="_dc_bogo_apply_multiple" value="yes" ' . checked( get_post_meta( $post->ID, '_dc_bogo_apply_multiple', true ), 'yes', false ) . '><span class="dc-slider round"></span></label>';
		echo '<label>' . esc_html__( 'Allow rule to be applied multiple times (e.g., Buy 1 Get 1, Buy 2 Get 2, etc.).', 'dc-bogo-coupons' ) . '</label></div>';
		echo '</div>';

		echo '<div class="form-field"><h4>' . esc_html__( 'Rule Schedule', 'dc-bogo-coupons' ) . '</h4>';
		echo '<div style="display: flex; gap: 20px;">';
			echo '<div><label>' . esc_html__( 'Start Date', 'dc-bogo-coupons' ) . ' (Optional: When the rule becomes active)</label>';
			echo '<input type="date" name="_dc_bogo_start_date" value="' . esc_attr( get_post_meta( $post->ID, '_dc_bogo_start_date', true ) ) . '" style="width: 100%;" /></div>';
			echo '<div><label>' . esc_html__( 'End Date', 'dc-bogo-coupons' ) . ' (Optional: When the rule expires)</label>';
			echo '<input type="date" name="_dc_bogo_end_date" value="' . esc_attr( get_post_meta( $post->ID, '_dc_bogo_end_date', true ) ) . '" style="width: 100%;" /></div>';
		echo '</div>';
		echo '<p class="description">' . esc_html__( 'You can schedule the rule to be active within a specific date range. Leave blank for no date restrictions.', 'dc-bogo-coupons' ) . '</p>';
		echo '</div>';

		echo '<div class="form-field"><h4>' . esc_html__( 'Rule Priority & Behavior', 'dc-bogo-coupons' ) . '</h4>';

		echo '<div><label class="dc-switch"><input type="checkbox" name="_dc_bogo_exclusive" value="yes" ' . checked( get_post_meta( $post->ID, '_dc_bogo_exclusive', true ), 'yes', false ) . '><span class="dc-slider round"></span></label>';
		echo '<label>' . esc_html__( 'Exclusive Rule', 'dc-bogo-coupons' ) . ' (Cannot be used with other coupons)</label>';
		echo '<p class="description">' . esc_html__( 'If checked, this rule cannot be used with other WooCommerce coupons. If this rule applies, it will remove them.', 'dc-bogo-coupons' ) . '</p>';
		echo '</div>';

		echo '<div><label>' . esc_html__( 'Rule Priority:', 'dc-bogo-coupons' ) . ' (Lower number = higher priority)</label>';
		echo '<input type="number" name="_dc_bogo_priority" value="' . esc_attr( get_post_meta( $post->ID, '_dc_bogo_priority', true ) ?: 10 ) . '" min="1" step="1" style="width:100px;" />';
		echo '<p class="description">' . esc_html__( 'If multiple BOGO rules could apply, the one with the lower number (e.g., 1) runs first. Default is 10.', 'dc-bogo-coupons' ) . '</p>';
		echo '</div>';

		echo '<div class="form-field"><h4>' . esc_html__( 'Cart Reminder', 'dc-bogo-coupons' ) . '</h4>';
		echo '<div><label class="dc-switch"><input type="checkbox" name="_dc_bogo_show_cart_reminder" value="yes" ' . checked( get_post_meta( $post->ID, '_dc_bogo_show_cart_reminder', true ), 'yes', false ) . '><span class="dc-slider round"></span></label>';
		echo '<label>' . esc_html__( 'Show Cart Reminder', 'dc-bogo-coupons' ) . ' (Display a notice if customer is close to qualifying)</label>';
		echo '<p class="description">' . esc_html__( 'For example, if the deal is "Buy 2, Get 1", and the customer has 1 item in the cart, a notice will prompt them to add more.', 'dc-bogo-coupons' ) . '</p>';
		echo '</div>';

		echo '</div>'; // .dc-bogo-metabox
	}

	/**
	 * Save the metabox data.
	 */
	public function save_rule_metaboxes( $post_id ) {
		if ( ! isset( $_POST['dc_bogo_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['dc_bogo_nonce'] ), 'dc_bogo_save' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$is_active = isset( $_POST['_dc_bogo_is_active'] ) ? 'yes' : 'no';
		update_post_meta( $post_id, '_dc_bogo_is_active', $is_active );

		$offer_name = sanitize_text_field( wp_unslash( $_POST['_dc_bogo_offer_name'] ?? '' ) );
		update_post_meta( $post_id, '_dc_bogo_offer_name', $offer_name );
		update_post_meta( $post_id, '_dc_bogo_type', sanitize_text_field( wp_unslash( $_POST['_dc_bogo_type'] ?? 'buy_x_get_y_diff' ) ) );
		
		$buy_qty = isset( $_POST['_dc_bogo_buy_qty'] ) ? absint( wp_unslash( $_POST['_dc_bogo_buy_qty'] ) ) : 1;
		update_post_meta( $post_id, '_dc_bogo_buy_qty', $buy_qty );
		
		update_post_meta( $post_id, '_dc_bogo_buy_match', sanitize_text_field( wp_unslash( $_POST['_dc_bogo_buy_match'] ?? 'products' ) ) );

		$buy_products = isset( $_POST['_dc_bogo_buy_products'] ) ? array_map( 'absint', wp_unslash( $_POST['_dc_bogo_buy_products'] ) ) : [];
		$this->save_sanitized_array( $post_id, '_dc_bogo_buy_products', $buy_products );
		
		$buy_categories = isset( $_POST['_dc_bogo_buy_categories'] ) ? array_map( 'absint', wp_unslash( $_POST['_dc_bogo_buy_categories'] ) ) : [];
		$this->save_sanitized_array( $post_id, '_dc_bogo_buy_categories', $buy_categories );

		$get_qty = isset( $_POST['_dc_bogo_get_qty'] ) ? absint( wp_unslash( $_POST['_dc_bogo_get_qty'] ) ) : 1;
		update_post_meta( $post_id, '_dc_bogo_get_qty', $get_qty );
		
		update_post_meta( $post_id, '_dc_bogo_get_match', sanitize_text_field( wp_unslash( $_POST['_dc_bogo_get_match'] ?? 'products' ) ) );

		$get_products = isset( $_POST['_dc_bogo_get_products'] ) ? array_map( 'absint', wp_unslash( $_POST['_dc_bogo_get_products'] ) ) : [];
		$this->save_sanitized_array( $post_id, '_dc_bogo_get_products', $get_products );
		
		$get_categories = isset( $_POST['_dc_bogo_get_categories'] ) ? array_map( 'absint', wp_unslash( $_POST['_dc_bogo_get_categories'] ) ) : [];
		$this->save_sanitized_array( $post_id, '_dc_bogo_get_categories', $get_categories );

		update_post_meta( $post_id, '_dc_bogo_discount_type', sanitize_text_field( wp_unslash( $_POST['_dc_bogo_discount_type'] ?? 'free' ) ) );
		
		$discount_val_raw = isset( $_POST['_dc_bogo_discount_val'] ) ? floatval( wp_unslash( $_POST['_dc_bogo_discount_val'] ) ) : 100.0;
		update_post_meta( $post_id, '_dc_bogo_discount_val', $discount_val_raw );
		
		update_post_meta( $post_id, '_dc_bogo_auto_add_get_product', isset( $_POST['_dc_bogo_auto_add_get_product'] ) ? 'yes' : 'no' );
		update_post_meta( $post_id, '_dc_bogo_apply_cheapest', isset( $_POST['_dc_bogo_apply_cheapest'] ) ? 'yes' : 'no' );
		update_post_meta( $post_id, '_dc_bogo_apply_multiple', isset( $_POST['_dc_bogo_apply_multiple'] ) ? 'yes' : 'no' );

		update_post_meta( $post_id, '_dc_bogo_apply_mode', sanitize_text_field( wp_unslash( $_POST['_dc_bogo_apply_mode'] ?? 'auto' ) ) );
		update_post_meta( $post_id, '_dc_bogo_coupon_code', sanitize_text_field( wp_unslash( $_POST['_dc_bogo_coupon_code'] ?? '' ) ) );
		
		update_post_meta( $post_id, '_dc_bogo_min_subtotal', sanitize_text_field( wp_unslash( $_POST['_dc_bogo_min_subtotal'] ?? '' ) ) );
		update_post_meta( $post_id, '_dc_bogo_max_subtotal', sanitize_text_field( wp_unslash( $_POST['_dc_bogo_max_subtotal'] ?? '' ) ) );

		$allowed_roles = isset( $_POST['_dc_bogo_allowed_roles'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['_dc_bogo_allowed_roles'] ) ) : [];
		$this->save_sanitized_array( $post_id, '_dc_bogo_allowed_roles', $allowed_roles, 'sanitize_text_field' );

		update_post_meta( $post_id, '_dc_bogo_limit_per_customer', sanitize_text_field( wp_unslash( $_POST['_dc_bogo_limit_per_customer'] ?? '' ) ) );
		update_post_meta( $post_id, '_dc_bogo_limit_total', sanitize_text_field( wp_unslash( $_POST['_dc_bogo_limit_total'] ?? '' ) ) );

		update_post_meta( $post_id, '_dc_bogo_start_date', sanitize_text_field( wp_unslash( $_POST['_dc_bogo_start_date'] ?? '' ) ) );
		update_post_meta( $post_id, '_dc_bogo_end_date', sanitize_text_field( wp_unslash( $_POST['_dc_bogo_end_date'] ?? '' ) ) );
		update_post_meta( $post_id, '_dc_bogo_exclusive', isset( $_POST['_dc_bogo_exclusive'] ) ? 'yes' : 'no' );
		
		$priority = isset( $_POST['_dc_bogo_priority'] ) ? absint( wp_unslash( $_POST['_dc_bogo_priority'] ) ) : 10;
		update_post_meta( $post_id, '_dc_bogo_priority', $priority );
		
		update_post_meta( $post_id, '_dc_bogo_show_cart_reminder', isset( $_POST['_dc_bogo_show_cart_reminder'] ) ? 'yes' : 'no' );

		if ( ! empty( $offer_name ) ) {
			remove_action( 'save_post_dc_bogo_rule', [ $this, 'save_rule_metaboxes' ] );
			wp_update_post(
				[
					'ID'         => $post_id,
					'post_title' => $offer_name,
				]
			);
			add_action( 'save_post_dc_bogo_rule', [ $this, 'save_rule_metaboxes' ] );
		}

		$apply_mode  = get_post_meta( $post_id, '_dc_bogo_apply_mode', true );
		$coupon_code = get_post_meta( $post_id, '_dc_bogo_coupon_code', true );
		$this->sync_woocommerce_coupon( $post_id, $apply_mode, $coupon_code, $is_active );

		delete_transient( 'dc_bogo_active_rules_ids' );
	}
	
	/**
	 * Automatically create/update/delete a real WooCommerce coupon to mirror the BOGO rule.
	 */
	public function sync_woocommerce_coupon( $rule_id, $apply_mode, $coupon_code, $is_active ) {
		$existing_coupons = get_posts(
			[
				'post_type'      => 'shop_coupon',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					[
						'key'   => '_dc_bogo_rule_id',
						'value' => $rule_id,
					],
				],
			]
		);
		$linked_coupon    = ! empty( $existing_coupons ) ? $existing_coupons[0] : null;


		if ( 'yes' !== $is_active ) {
			if ( $linked_coupon && 'draft' !== $linked_coupon->post_status ) {
				wp_update_post(
					[
						'ID'          => $linked_coupon->ID,
						'post_status' => 'draft',
					]
				);
			}
			return;
		}

		if ( 'coupon' !== $apply_mode || empty( $coupon_code ) ) {
			if ( $linked_coupon ) {
				wp_delete_post( $linked_coupon->ID, true );
			}
			return;
		}

		$post_data = [
			'post_title'   => $coupon_code,
			'post_name'    => sanitize_title( $coupon_code ),
			'post_status'  => 'publish', // Ensure it's published
		];

		if ( $linked_coupon ) {
			$post_data['ID'] = $linked_coupon->ID;
			wp_update_post( $post_data );
		} else {
			$post_data['post_type'] = 'shop_coupon';
			$new_coupon_id          = wp_insert_post( $post_data );

			if ( $new_coupon_id ) {
				update_post_meta( $new_coupon_id, '_dc_bogo_rule_id', $rule_id );
				update_post_meta( $new_coupon_id, 'discount_type', 'fixed_cart' );
				update_post_meta( $new_coupon_id, 'coupon_amount', '0' );
			}
		}
	}

	/**
	 * Helper function to save sanitized array data.
	 */
	private function save_sanitized_array( $post_id, $meta_key, $data, $sanitize_callback = 'intval' ) {
		$sanitized_data = [];
		if ( is_array( $data ) ) {
			foreach ( $data as $value ) {
				$sanitized_data[] = call_user_func( $sanitize_callback, $value );
			}
		}
		update_post_meta( $post_id, $meta_key, $sanitized_data );
	}

	/**
	 * Customize the CPT updated messages.
	 */
	public function customize_cpt_updated_messages( $messages ) {
		global $post;

		if ( ! is_object( $post ) || ! isset( $post->ID ) ) {
			return $messages;
		}

		$messages['dc_bogo_rule'] = [
			0  => '', // Unused. Messages start at index 1.
			1  => __( 'BOGO coupon updated.', 'dc-bogo-coupons' ),
			2  => __( 'Custom field updated.', 'dc-bogo-coupons' ),
			3  => __( 'Custom field deleted.', 'dc-bogo-coupons' ),
			4  => __( 'BOGO coupon updated.', 'dc-bogo-coupons' ),
			/* translators: %s: Revision date */
			5  => isset( $_GET['revision'] ) ? sprintf( __( 'BOGO coupon restored to revision from %s', 'dc-bogo-coupons' ), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false, // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			6  => __( 'BOGO coupon published.', 'dc-bogo-coupons' ),
			7  => __( 'BOGO coupon saved.', 'dc-bogo-coupons' ),
			8  => __( 'BOGO coupon submitted.', 'dc-bogo-coupons' ),
			/* translators: %1$s: Scheduled date */
			9  => sprintf(
				/* translators: %1$s: Formatted scheduled date and time */
				__( 'BOGO coupon scheduled for: <strong>%1$s</strong>.', 'dc-bogo-coupons' ),
				/* translators: Date format for scheduled posts */
				date_i18n( __( 'M j, Y @ G:i', 'dc-bogo-coupons' ), strtotime( $post->post_date ) )
			),
			10 => __( 'BOGO coupon draft updated.', 'dc-bogo-coupons' ),
		];

		return $messages;
	}

	/**
	 * Add custom columns to the CPT list screen.
	 */
	public function set_custom_cpt_columns( $columns ) {
		// Build a simplified columns set: remove redundant Status column (we have a toggle)
		// and rename Progress header to "Progress Bar" to match UI language.
		$new_columns = [];
		$new_columns['cb']                 = $columns['cb'];
		$new_columns['title']              = __( 'Rule Name', 'dc-bogo-coupons' );
		$new_columns['dc_bogo_progress']   = __( 'Progress Bar', 'dc-bogo-coupons' );
		$new_columns['dc_bogo_offer_type'] = __( 'Offer Type', 'dc-bogo-coupons' );
		$new_columns['dc_bogo_applies']    = __( 'Applies To', 'dc-bogo-coupons' );
		$new_columns['dc_bogo_uses']       = __( 'Uses', 'dc-bogo-coupons' );
		$new_columns['dc_bogo_toggle']     = __( 'Active', 'dc-bogo-coupons' );
		$new_columns['date']               = $columns['date'];

		return $new_columns;
	}

	/**
	 * Render the custom column data.
	 */
	public function render_custom_cpt_columns( $column, $post_id ) {
		switch ( $column ) {

		case 'dc_bogo_progress':
			$show = get_post_meta( $post_id, '_dc_bogo_show_cart_reminder', true );
			$checked = 'yes' === $show ? 'checked' : '';
			// Toggle control. Admin JS will handle AJAX toggle.
			echo '<label class="dc-switch dc-bogo-progress-toggle" data-rule-id="' . esc_attr( $post_id ) . '">';
			echo '<input type="checkbox" ' . esc_attr( $checked ) . ' />';
			echo '<span class="dc-slider round"></span>';
			echo '</label>';
			break;



			case 'dc_bogo_offer_type':
				$buy_qty       = get_post_meta( $post_id, '_dc_bogo_buy_qty', true ) ?: 1;
				$get_qty       = get_post_meta( $post_id, '_dc_bogo_get_qty', true ) ?: 1;
				$discount_type = get_post_meta( $post_id, '_dc_bogo_discount_type', true ) ?: 'free';

				$offer_text = "Buy {$buy_qty}, Get {$get_qty} ";
				if ( 'free' === $discount_type ) {
					$offer_text .= 'Free';
				} else {
					$val         = get_post_meta( $post_id, '_dc_bogo_discount_val', true );
					$offer_text .= "at {$val}" . ( 'percent' === $discount_type ? '%' : '$' ) . ' off';
				}
				echo esc_html( $offer_text );
				break;

			case 'dc_bogo_applies':
				$buy_match = get_post_meta( $post_id, '_dc_bogo_buy_match', true ) ?: 'products';
				$buy_ids   = get_post_meta( $post_id, "_dc_bogo_buy_{$buy_match}", true ) ?: [];

				if ( empty( $buy_ids ) ) {
					echo esc_html__( 'All Products', 'dc-bogo-coupons' );
					break;
				}

				$items = [];
				switch ( $buy_match ) {
					case 'products':
						foreach ( $buy_ids as $pid ) {
							$p = wc_get_product( $pid );
							if ( $p ) {
								$items[] = $p->get_name();
							}
						}
						break;
					case 'categories':
						foreach ( $buy_ids as $term_id ) {
							$term = get_term( $term_id, 'product_cat' );
							if ( $term && ! is_wp_error( $term ) ) {
								$items[] = $term->name;
							}
						}
						break;
				}

				if ( empty( $items ) ) {
					echo esc_html__( 'Selected (no items)', 'dc-bogo-coupons' );
				} else {
					echo esc_html( wp_trim_words( implode( ', ', $items ), 12, '...' ) );
				}
				break;

			case 'dc_bogo_uses':
				$uses  = get_post_meta( $post_id, '_dc_bogo_total_uses_count', true ) ?: 0; // Use a different meta key for usage
				$limit = get_post_meta( $post_id, '_dc_bogo_limit_total', true );
				echo esc_html( $uses );
				if ( ! empty( $limit ) ) {
					echo ' / ' . esc_html( $limit );
				}
				break;

			case 'dc_bogo_toggle':
				$is_active = get_post_meta( $post_id, '_dc_bogo_is_active', true );
				echo '<label class="dc-switch dc-bogo-status-toggle" data-rule-id="' . esc_attr( $post_id ) . '" title="Toggle rule status">';
				echo '<input type="checkbox" ' . checked( $is_active, 'yes', false ) . '>';
				echo '<span class="dc-slider round"></span>';
				echo '</label>';
				break;
		}
	}

	/**
	 * Render Developer Support & Custom Work sidebar metabox on rule edit screen.
	 */
	public function render_developer_support_metabox() {
		?>
		<div class="dc-bogo-dev-sidebar-box" style="padding: 4px 2px;">
			<div style="margin-bottom: 15px; padding-bottom: 12px; border-bottom: 1px solid #e0e0e0;">
				<strong style="display: block; margin-bottom: 5px; color: #1d2327;">💼 <?php esc_html_e( 'Need Custom Features?', 'dc-bogo-coupons' ); ?></strong>
				<p style="margin: 0 0 10px; font-size: 12px; color: #646970; line-height: 1.4;">
					<?php esc_html_e( 'Need bespoke BOGO logic, custom discount rules, or WooCommerce store development? Hire the creator of DC BOGO directly.', 'dc-bogo-coupons' ); ?>
				</p>
				<a href="https://www.ramira.in/contact/" target="_blank" class="button button-secondary" style="width: 100%; text-align: center; font-weight: 500;">
					<?php esc_html_e( 'Contact / Hire Developer', 'dc-bogo-coupons' ); ?> &rarr;
				</a>
			</div>
			<div style="margin-bottom: 15px; padding-bottom: 12px; border-bottom: 1px solid #e0e0e0;">
				<strong style="display: block; margin-bottom: 5px; color: #1d2327;">☕ <?php esc_html_e( 'Support Free Development', 'dc-bogo-coupons' ); ?></strong>
				<p style="margin: 0 0 10px; font-size: 12px; color: #646970; line-height: 1.4;">
					<?php esc_html_e( 'DC BOGO is 100% free with zero paywalls. If it helps your store grow, consider supporting ongoing development.', 'dc-bogo-coupons' ); ?>
				</p>
				<a href="https://www.ramira.in/support/" target="_blank" class="button button-secondary" style="width: 100%; text-align: center; font-weight: 500;">
					<?php esc_html_e( 'Support & Donate', 'dc-bogo-coupons' ); ?> ❤️
				</a>
			</div>
			<div>
				<strong style="display: block; margin-bottom: 5px; color: #1d2327;">⭐ <?php esc_html_e( 'Love this Plugin?', 'dc-bogo-coupons' ); ?></strong>
				<p style="margin: 0 0 8px; font-size: 12px; color: #646970; line-height: 1.4;">
					<?php esc_html_e( 'A 5-star review on WordPress.org helps an indie developer tremendously!', 'dc-bogo-coupons' ); ?>
				</p>
				<a href="https://wordpress.org/support/plugin/dc-bogo-coupons/reviews/#new-post" target="_blank" style="font-size: 12px; text-decoration: none; font-weight: 600; color: #2271b1; display: inline-block;">
					<?php esc_html_e( 'Leave a 5-Star Rating ★★★★★', 'dc-bogo-coupons' ); ?>
				</a>
			</div>
		</div>
		<?php
	}

	/**
	 * Handle dismissing or snoozing the review notice safely.
	 */
	public function handle_review_notice_actions() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		if ( isset( $_GET['dc_bogo_dismiss_review'] ) && check_admin_referer( 'dc_bogo_dismiss_review_action' ) ) {
			update_option( 'dc_bogo_review_dismissed', 'yes' );
			wp_safe_redirect( remove_query_arg( [ 'dc_bogo_dismiss_review', '_wpnonce' ] ) );
			exit;
		}

		if ( isset( $_GET['dc_bogo_snooze_review'] ) && check_admin_referer( 'dc_bogo_snooze_review_action' ) ) {
			set_transient( 'dc_bogo_review_snooze_' . get_current_user_id(), 'yes', 14 * DAY_IN_SECONDS );
			wp_safe_redirect( remove_query_arg( [ 'dc_bogo_snooze_review', '_wpnonce' ] ) );
			exit;
		}
	}

	/**
	 * Render the review notice if criteria are met:
	 * - Only for store administrators.
	 * - Only after at least 7 days since activation.
	 * - Only after at least 1 BOGO rule exists.
	 * - Not permanently dismissed and not snoozed.
	 */
	public function render_review_notice() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		// Don't show if permanently dismissed.
		if ( 'yes' === get_option( 'dc_bogo_review_dismissed' ) ) {
			return;
		}

		// Don't show if snoozed.
		if ( get_transient( 'dc_bogo_review_snooze_' . get_current_user_id() ) ) {
			return;
		}

		// Check installation time (minimum 7 days).
		$first_activated = get_option( 'dc_bogo_first_activated' );
		if ( ! $first_activated ) {
			update_option( 'dc_bogo_first_activated', time() );
			return;
		}

		if ( ( time() - $first_activated ) < ( 7 * DAY_IN_SECONDS ) ) {
			return;
		}

		// Ensure at least 1 rule has been created.
		$count_rules = wp_count_posts( 'dc_bogo_rule' );
		$has_rules   = ( ! empty( $count_rules->publish ) && $count_rules->publish > 0 ) || ( ! empty( $count_rules->draft ) && $count_rules->draft > 0 );
		if ( ! $has_rules ) {
			return;
		}

		$dismiss_url = wp_nonce_url( add_query_arg( 'dc_bogo_dismiss_review', 'yes' ), 'dc_bogo_dismiss_review_action' );
		$snooze_url  = wp_nonce_url( add_query_arg( 'dc_bogo_snooze_review', 'yes' ), 'dc_bogo_snooze_review_action' );
		?>
		<div class="notice notice-info dc-bogo-review-notice" style="padding: 14px 18px; border-left-color: #ffb900; background: #fff;">
			<div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
				<div style="max-width: 760px;">
					<h3 style="margin: 0 0 6px 0; font-size: 14px; font-weight: 600; color: #1d2327;">
						🌟 <?php esc_html_e( 'Loving DC BOGO Coupons?', 'dc-bogo-coupons' ); ?>
					</h3>
					<p style="margin: 0; font-size: 13px; line-height: 1.5; color: #50575e;">
						<?php esc_html_e( 'Thank you for using DC BOGO Coupons! If this plugin has helped your WooCommerce store run BOGO deals and free gifts, please consider leaving a 5-star review on WordPress.org. Your feedback means the world to an independent developer!', 'dc-bogo-coupons' ); ?>
					</p>
				</div>
				<div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
					<a href="https://wordpress.org/support/plugin/dc-bogo-coupons/reviews/#new-post" target="_blank" class="button button-primary" style="background: #ffb900; border-color: #e0a300; color: #000; font-weight: 600;" onclick="window.location.href='<?php echo esc_url( $dismiss_url ); ?>';">
						<?php esc_html_e( 'Leave a 5-Star Review ★★★★★', 'dc-bogo-coupons' ); ?>
					</a>
					<a href="<?php echo esc_url( $snooze_url ); ?>" class="button button-secondary">
						<?php esc_html_e( 'Remind Me Later', 'dc-bogo-coupons' ); ?>
					</a>
					<a href="<?php echo esc_url( $dismiss_url ); ?>" class="button-link" style="color: #646970; text-decoration: none; padding: 4px 8px;">
						<?php esc_html_e( 'I Already Did', 'dc-bogo-coupons' ); ?>
					</a>
				</div>
			</div>
		</div>
		<?php
	}
}
