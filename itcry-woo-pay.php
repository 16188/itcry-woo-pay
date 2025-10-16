<?php
/**
 * Plugin Name:         ITCRY WOOPAY
 * Plugin URI:          https://itcry.com/
 * Description:         A refactored and secure payment gateway collection for WooCommerce, supporting Codepay and Easypay with advanced features.
 * Author:              ITCRY
 * Version:             1.2.0
 * Author URI:          https://itcry.com/
 * Text Domain:         itcry-woo-pay
 * Domain Path:         /languages
 * WC requires at least: 5.0
 * WC tested up to:      8.9
 * Tested up to:         6.5
 */

// 防止该文件直接被访问，增强安全性
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// HPOS 兼容性声明
add_action( 'before_woocommerce_init', function() {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
} );

/**
 * 主插件类，使用单例模式确保全局只有一个实例
 */
final class ITCRY_WOOPAY_Plugin {

    /**
     * 插件的唯一实例
     * @var ITCRY_WOOPAY_Plugin
     */
    private static $_instance = null;

    /**
     * 插件版本号
     * @var string
     */
    public $version = '1.2.0';

    /**
     * 插件的路径和URL常量
     */
    private function define_constants() {
        define( 'ITCRY_WOOPAY_FILE', __FILE__ );
        define( 'ITCRY_WOOPAY_PATH', plugin_dir_path( ITCRY_WOOPAY_FILE ) );
        define( 'ITCRY_WOOPAY_URL', plugin_dir_url( ITCRY_WOOPAY_FILE ) );
        define( 'ITCRY_WOOPAY_VERSION', $this->version );
    }

    /**
     * 构造函数 (私有，防止直接创建)
     */
    private function __construct() {
        $this->define_constants();
        $this->init_hooks();
    }

    /**
     * 初始化WordPress钩子
     */
    private function init_hooks() {
        add_action( 'plugins_loaded', array( $this, 'on_plugins_loaded' ), 10 );
    }

    /**
     * `plugins_loaded` 钩子回调，初始化所有功能
     */
    public function on_plugins_loaded() {
        $this->includes();

        // 加载插件的文本域，为国际化做准备
        load_plugin_textdomain( 'itcry-woo-pay', false, dirname( plugin_basename( ITCRY_WOOPAY_FILE ) ) . '/languages/' );

        // 注册支付网关
        add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateways' ) );

        // 初始化后台设置页面和通知处理器
        ITCRY_WOOPAY_Settings::get_instance();
        ITCRY_WOOPAY_Notify_Handler::get_instance();
        // 初始化易支付接口管理器
        ITCRY_WOOPAY_Easypay_Manager::get_instance();
    }

    /**
     * 包含所需文件
     */
    public function includes() {
        require_once ITCRY_WOOPAY_PATH . 'includes/class-wc-freepay-settings.php';
        require_once ITCRY_WOOPAY_PATH . 'includes/abstract-wc-freepay-gateway.php';
        require_once ITCRY_WOOPAY_PATH . 'includes/class-wc-freepay-notify-handler.php';
        // 新增: 加载易支付接口管理器
        require_once ITCRY_WOOPAY_PATH . 'includes/class-wc-easypay-manager.php';


        // 包含支付网关的具体实现文件
        foreach ( glob( ITCRY_WOOPAY_PATH . 'includes/gateways/*.php' ) as $gateway_file ) {
            require_once $gateway_file;
        }
    }

    /**
     * 向WooCommerce添加我们的支付网关
     *
     * @param array $gateways
     * @return array
     */
    public function add_gateways( $gateways ) {
        $itcry_woo_pay_gateways = array(
            'ITCRY_WOOPAY_Gateway_Codepay_ZFB',
            'ITCRY_WOOPAY_Gateway_Codepay_QQ',
            'ITCRY_WOOPAY_Gateway_Codepay_WX',
            'ITCRY_WOOPAY_Gateway_Easypay_ZFB',
            'ITCRY_WOOPAY_Gateway_Easypay_QQ',
            'ITCRY_WOOPAY_Gateway_Easypay_WX',
        );

        foreach ( $itcry_woo_pay_gateways as $gateway_class ) {
            if ( class_exists( $gateway_class ) ) {
                $gateways[] = $gateway_class;
            }
        }
        
        return $gateways;
    }

    /**
     * 单例模式入口
     *
     * @return ITCRY_WOOPAY_Plugin
     */
    public static function get_instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }
}

/**
 * 插件主函数
 * @return ITCRY_WOOPAY_Plugin
 */
function itcry_woo_pay_plugin() {
    // 增加一个检查，确保WooCommerce处于激活状态，避免不必要的错误
    if ( class_exists( 'WooCommerce' ) ) {
        return ITCRY_WOOPAY_Plugin::get_instance();
    }
}

// 启动插件
add_action( 'plugins_loaded', 'itcry_woo_pay_plugin', 0 );
