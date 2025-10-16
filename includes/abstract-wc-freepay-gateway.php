<?php
/**
 * ITCRY WOOPAY 抽象支付网关类
 *
 * @package ITCRY-WOOPAY/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

abstract class ITCRY_WOOPAY_Abstract_Gateway extends WC_Payment_Gateway {

    /**
     * 网关类型 (codepay 或 easypay)
     * @var string
     */
    protected $gateway_type;

    /**
     * 交易手续费金额
     * @var float
     */
    protected $fee_amount = 0;

    /**
     * 构造函数
     */
    public function __construct() {
        $this->has_fields = false;
        
        $this->init_form_fields();
        $this->init_settings();

        $this->title       = $this->get_option( 'title' );
        $this->description = $this->get_option( 'description' );
        
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );

        // 为易支付网关添加计算手续费的钩子
        if ($this->gateway_type === 'easypay') {
            add_action('woocommerce_cart_calculate_fees', array($this, 'add_payment_fee'));
        }
        
        add_filter('woocommerce_gateway_title', array($this, 'modify_gateway_title'), 20, 2);
    }
    
    /**
     * 动态修改支付网关标题以显示手续费率
     */
    public function modify_gateway_title($title, $gateway_id) {
        if ($this->id !== $gateway_id || $this->gateway_type !== 'easypay') {
            return $title;
        }

        if ( ! is_checkout() || ! WC()->cart || WC()->cart->is_empty() ) {
            return $title;
        }
        
        // 计算基准金额
        $base_amount = $this->get_cart_base_amount();

        $manager = ITCRY_WOOPAY_Easypay_Manager::get_instance();
        $selected_interface = $manager->select_available_interface($base_amount);
        
        if (!$selected_interface) return $title;

        $fee_rate = $this->get_current_fee_rate($selected_interface);
        
        if ($fee_rate > 0) {
            $formatted_rate = wc_format_decimal($fee_rate, 2); 
            $fee_text = sprintf(__(' (手续费: %s%%)', 'itcry-woo-pay'), $formatted_rate);
            $title .= $fee_text;
        }

        return $title;
    }


    /**
     * 为结账页面加载必要的内联JavaScript
     */
    public function payment_scripts() {
        if (!is_checkout() || get_query_var( 'order-pay' )) {
            return;
        }
        
        wp_add_inline_script(
            'wc-checkout', 
            "jQuery( function( $ ) {
                $( 'form.checkout' ).on( 'change', 'input[name=\"payment_method\"]', function() {
                    $( 'body' ).trigger( 'update_checkout' );
                });
            });"
        );
    }

    /**
     * 计算并添加支付手续费到购物车
     */
    public function add_payment_fee($cart) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
            return;
        }

        $chosen_gateway = WC()->session->get('chosen_payment_method');
         if (isset($_POST['payment_method'])) {
            $chosen_gateway = sanitize_text_field($_POST['payment_method']);
        }
        
        if ($chosen_gateway !== $this->id) {
            return;
        }
        
        $base_amount = $this->get_cart_base_amount($cart);

        $manager = ITCRY_WOOPAY_Easypay_Manager::get_instance();
        $selected_interface = $manager->select_available_interface($base_amount);
        
        if (!$selected_interface) {
            return;
        }

        $fee_rate = $this->get_current_fee_rate($selected_interface);

        if ($fee_rate > 0) {
            $this->fee_amount = round( $base_amount * ( $fee_rate / 100 ), wc_get_price_decimals() );
            $cart->add_fee(sprintf(__('支付手续费 (%s)', 'itcry-woo-pay'), $this->get_option('title')), $this->fee_amount);
        }
    }

    /**
     * 【重构】获取购物车用于计算手续费的基准金额
     * @param WC_Cart|null $cart
     * @return float
     */
    protected function get_cart_base_amount($cart = null) {
        if (null === $cart) {
            $cart = WC()->cart;
        }
         // 商品总价 + 运费 + 税费
        return $cart->get_cart_contents_total() + $cart->get_shipping_total() + $cart->get_taxes_total(false, false);
    }
    
    /**
     * 【重构】获取当前支付方式和接口的手续费率
     * @param array $interface
     * @return float
     */
    protected function get_current_fee_rate($interface) {
        $fee_rate_key = $this->get_fee_rate_key();
        if (empty($fee_rate_key) || !isset($interface[$fee_rate_key])) {
            return 0.0;
        }
        return (float) $interface[$fee_rate_key];
    }
    
    /**
     * 根据网关ID获取费率字段名
     */
    private function get_fee_rate_key() {
        if (strpos($this->id, '_zfb') !== false) return 'fee_alipay';
        if (strpos($this->id, '_wx') !== false) return 'fee_wxpay';
        if (strpos($this->id, '_qq') !== false) return 'fee_qqpay';
        return '';
    }


    /**
     * 初始化后台设置的表单字段
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled'     => array(
                'title'   => __( 'Enable/Disable', 'woocommerce' ),
                'type'    => 'checkbox',
                'label'   => sprintf( __( '启用 %s', 'itcry-woo-pay' ), $this->method_title ),
                'default' => 'no',
            ),
            'title'       => array(
                'title'       => __( 'Title', 'woocommerce' ),
                'type'        => 'text',
                'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
                'default'     => $this->method_title,
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => __( 'Description', 'woocommerce' ),
                'type'        => 'textarea',
                'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce' ),
                'default'     => sprintf( __( '使用 %s 付款。', 'itcry-woo-pay' ), $this->method_title ),
                'desc_tip'    => true,
            ),
        );
    }

    /**
     * 处理支付的核心方法
     */
    public function process_payment( $order_id ) {
        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            wc_add_notice( __( '无法找到订单，支付失败。', 'itcry-woo-pay' ), 'error' );
            return array( 'result' => 'failure' );
        }
        
        $redirect_url = $this->generate_payment_url( $order );

        if ( empty( $redirect_url ) ) {
            return array( 'result' => 'failure' );
        }
        
        // 清空购物车必须在重定向 URL 成功生成后执行
        // WC()->cart->empty_cart();
        
        return array(
            'result'   => 'success',
            'redirect' => $redirect_url,
        );
    }

    /**
     * 生成支付链接 (抽象方法)
     */
    protected abstract function generate_payment_url( $order );
}
