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
     * 构造函数
     */
    public function __construct() {
        $this->has_fields = false;
        
        // 初始化后台设置表单字段
        $this->init_form_fields();

        // 加载此网关自身的基础设置 (enabled, title, description)
        $this->init_settings();

        // 定义用户在结账时看到的属性
        $this->title       = $this->get_option( 'title' );
        $this->description = $this->get_option( 'description' );
        
        // 为后台的保存操作添加钩子
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

        // =================================================================
        //
        // 【核心修复】: 添加下面的钩子和对应的方法。
        // 这段代码是解决“切换支付方式后客户信息丢失”问题的关键。
        // 它能确保当用户选择此支付方式时，正确触发WooCommerce的AJAX更新，而不是刷新整个页面。
        //
        // =================================================================
        add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
    }

    /**
     * 【核心修复新增方法】: 为结账页面加载必要的内联JavaScript
     *
     * 这个方法确保只有在结账页面，并且当我们的支付网关被选择时，
     *才会触发 'update_checkout' 事件，强制WooCommerce通过AJAX更新订单摘要。
     * 这样做可以防止整个页面刷新，从而保留用户已输入的账单和送货信息。
     */
    public function payment_scripts() {
        // 仅在结账页面且未执行支付时执行
        if ( ! is_checkout() || get_query_var( 'order-pay' ) ) {
            return;
        }

        // 使用wp_add_inline_script来添加一小段JS，比单独创建一个JS文件更高效
        // 这段JS的逻辑是：当用户在支付方式中选择了我们这个插件的任何一个支付方式时
        // 就手动触发一次 'update_checkout' 事件。
        wp_add_inline_script(
            'wc-checkout', // 附加到WooCommerce的核心结账脚本上
            "jQuery( function( $ ) {
                $( 'form.checkout' ).on( 'change', 'input[name=\"payment_method\"]', function() {
                    if ( $( this ).val().startsWith( 'itcry_woo_pay_' ) ) {
                        $( 'body' ).trigger( 'update_checkout' );
                    }
                });
            });"
        );
    }

    /**
     * 初始化后台设置的表单字段
     * 这是所有子网关通用的
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
     *
     * @param int $order_id
     * @return array
     */
    public function process_payment( $order_id ) {
        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            wc_add_notice( __( '无法找到订单，支付失败。', 'itcry-woo-pay' ), 'error' );
            return array( 'result' => 'failure' );
        }

        // 调用由子类实现的、用于生成支付URL的抽象方法
        $redirect_url = $this->generate_payment_url( $order );

        if ( empty( $redirect_url ) ) {
            wc_add_notice( __( '生成支付链接失败，请联系网站管理员。', 'itcry-woo-pay' ), 'error' );
            return array( 'result' => 'failure' );
        }
        
        // 清空购物车
        WC()->cart->empty_cart();
        
        // 重定向到支付网关
        return array(
            'result'   => 'success',
            'redirect' => $redirect_url,
        );
    }

    /**
     * 生成支付链接 (抽象方法)
     *
     * 这个方法必须由每一个具体的支付网关子类来实现，
     * 因为Codepay和Easypay的链接生成逻辑不同。
     *
     * @param WC_Order $order
     * @return string
     */
    protected abstract function generate_payment_url( $order );
}
