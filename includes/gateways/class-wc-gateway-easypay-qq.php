<?php
/**
 * ITCRY WOOPAY - Easypay QQ Gateway
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class ITCRY_WOOPAY_Gateway_Easypay_QQ extends ITCRY_WOOPAY_Abstract_Gateway {

    /**
     * 构造函数
     */
    public function __construct() {
        $this->id                 = 'itcry_woo_pay_easypay_qq';
        $this->gateway_type       = 'easypay';
        $this->icon               = ITCRY_WOOPAY_URL . 'assets/logo/qq-logo.jpg';
        $this->method_title       = __( '易支付 - QQ钱包', 'itcry-woo-pay' );
        $this->method_description = __( '使用易支付聚合接口进行QQ钱包付款。', 'itcry-woo-pay' );

        parent::__construct();
        
        // 添加操作以处理重定向并清理URL参数。
        add_action('woocommerce_api_itcry_woo_pay_easypay_return', array($this, 'handle_return_url_and_redirect'));
    }

    /**
     * 实现父类中定义的抽象方法，用于生成支付URL
     *
     * @param WC_Order $order
     * @return string
     */
    protected function generate_payment_url( $order ) {
        $options = get_option( 'itcry_woo_pay_easypay_settings', array() );

        $api_url  = isset( $options['easypay_api_url'] ) ? $options['easypay_api_url'] : '';
        $pid      = isset( $options['easypay_id'] ) ? $options['easypay_id'] : '';
        $key      = isset( $options['easypay_key'] ) ? $options['easypay_key'] : '';

        if ( empty( $api_url ) || empty( $pid ) || empty( $key ) ) {
            $order->add_order_note( __( '易支付设置不完整，无法发起支付。', 'itcry-woo-pay' ) );
            return '';
        }

        $product_name = $this->get_product_name( $order );
        $type = 'qqpay';

        // 不向Easypay发送最终URL，而是发送一个自定义API端点URL。
        $return_url = WC()->api_request_url('itcry_woo_pay_easypay_return');

        $params = array(
            'pid'          => (int)$pid,
            'type'         => $type,
            'out_trade_no' => $order->get_id(),
            'notify_url'   => WC()->api_request_url('itcry_woo_pay_easypay_notify'),
            'return_url'   => $return_url,
            'name'         => $product_name,
            'money'        => (float) $order->get_total(),
            'sign_type'    => 'MD5'
        );

        $params['sign'] = $this->generate_easypay_sign( $params, $key );

        $pay_url = rtrim( $api_url, '/' ) . '/submit.php?' . http_build_query( $params );

        $order->add_order_note( sprintf( __( '用户选择了 %s 发起支付。', 'itcry-woo-pay' ), $this->method_title ) );

        return $pay_url;
    }
    
    /**
     * 处理来自Easypay的返回。
     * 如果自定义返回URL为空，则重定向到订单接收页面。
     */
    public function handle_return_url_and_redirect() {
        $options = get_option( 'itcry_woo_pay_easypay_settings', array() );
        $final_url = '';

        if ( ! empty( $options['easypay_return_url'] ) ) {
            $final_url = esc_url_raw( $options['easypay_return_url'] );
        } else {
            // 对于Easypay，订单ID参数是“out_trade_no”。
            $order_id = isset( $_GET['out_trade_no'] ) ? absint( $_GET['out_trade_no'] ) : 0;
            if ( $order_id > 0 ) {
                $order = wc_get_order( $order_id );
                if ( $order ) {
                    $final_url = $order->get_checkout_order_received_url();
                }
            }
        }

        if ( empty( $final_url ) ) {
            $final_url = home_url();
        }
        
        echo '<!DOCTYPE html><html><head><title>Redirecting...</title>';
        echo '<script type="text/javascript">window.location.replace("' . $final_url . '");</script>';
        echo '</head><body><p>Payment successful, redirecting...</p></body></html>';
        exit;
    }

    private function get_product_name( $order ) {
        $items = $order->get_items();
        $item_names = array();
        foreach ( $items as $item ) {
            $item_names[] = $item->get_name();
        }
        $product_name = implode( ', ', $item_names );
        if ( mb_strlen( $product_name ) > 100 ) {
            $product_name = mb_substr( $product_name, 0, 100 ) . '...';
        }
        return $product_name;
    }

    private function generate_easypay_sign( $params, $key ) {
        $para_filter = array();
        foreach ($params as $k => $v) {
            if ( $k == "sign" || $k == "sign_type" || $v === "" ) continue;
            $para_filter[$k] = $v;
        }

        ksort($para_filter);
        reset($para_filter);

        $prestr = '';
        foreach ($para_filter as $k => $v) {
            $prestr .= $k . '=' . $v . '&';
        }
        $prestr = rtrim($prestr, '&');

        return md5( $prestr . $key );
    }
}