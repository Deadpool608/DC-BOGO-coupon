<?php
/**
 * DC BOGO Coupons Main Class
 *
 * @package DC_BOGO_Coupons
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Main Plugin Class.
 */
final class DC_BOGO_Coupons {

	/**
	 * The single instance of the class
	 *
	 * @var DC_BOGO_Coupons
	 */
	private static $_instance = null;

	/**
	 * @var DC_BOGO_CPT
	 */
	public $cpt;

	/**
	 * @var DC_BOGO_Admin
	 */
	public $admin;

	/**
	 * @var DC_BOGO_Frontend
	 */
	public $frontend;

	/**
	 * @var DC_BOGO_AJAX
	 */
	public $ajax;

	/**
	 * @var DC_BOGO_Cart
	 */
	public $cart;

	/**
	 * Main DC_BOGO_Coupons Instance.
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->includes();
		$this->init();
	}

	/**
	 * Include required files.
	 */
	private function includes() {
		include_once plugin_dir_path( __FILE__ ) . 'class-dc-bogo-cpt.php';
		include_once plugin_dir_path( __FILE__ ) . 'class-dc-bogo-admin.php';
		include_once plugin_dir_path( __FILE__ ) . 'class-dc-bogo-frontend.php';
		include_once plugin_dir_path( __FILE__ ) . 'class-dc-bogo-ajax.php';
		include_once plugin_dir_path( __FILE__ ) . 'class-dc-bogo-cart.php';
	}

	/**
	 * Initialize the plugin.
	 */
	private function init() {
		$this->cpt      = new DC_BOGO_CPT();
		$this->admin    = new DC_BOGO_Admin();
		$this->frontend = new DC_BOGO_Frontend();
		$this->ajax     = new DC_BOGO_AJAX();
		$this->cart     = new DC_BOGO_Cart();
	}
}
