<?php
/**
 * ITCRY WOOPAY 统一支付回调处理器
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class ITCRY_WOOPAY_Notify_Handler {

    /**
     * 类的唯一实例
     * @var ITCRY_WOOPAY_Notify_Handler
     */
    private static $_instance = null;

    /**
     * 构造函数
     */
    private function __construct() {
        // 添加WooCommerce API端点，用于接收支付网关的异步通知
        add_action( 'woocommerce_api_itcry_woo_pay_codepay_notify', array( $this, 'handle_codepay_notify' ) );
        add_action( 'woocommerce_api_itcry_woo_pay_easypay_notify', array( $this, 'handle_easypay_notify' ) );
        
        // 添加操作以处理Codepay网关的清洁重定向
        add_action('woocommerce_api_itcry_woo_pay_codepay_return', array($this, 'handle_codepay_return_url_and_redirect'));
    }

    /**
     * 处理来自Codepay的返回。
     * 将用户重定向到其最终的、干净的URL，以防止404错误。
     * 如果自定义返回URL为空，则将重定向到订单接收页面。
     */
    public function handle_codepay_return_url_and_redirect() {
        $options = get_option( 'itcry_woo_pay_codepay_settings', array() );
        $final_url = '';

        // 如果设置了自定义URL，请使用它。
        if ( ! empty( $options['codepay_return_url'] ) ) {
            $final_url = esc_url_raw( $options['codepay_return_url'] );
        } else {
            // 否则，获取WooCommerce标准的订单接收URL。
            // 对于Codepay，订单ID参数是“pay_id”。
            $order_id = isset( $_GET['pay_id'] ) ? absint( $_GET['pay_id'] ) : 0;
            if ( $order_id > 0 ) {
                $order = wc_get_order( $order_id );
                if ( $order ) {
                    $final_url = $order->get_checkout_order_received_url();
                }
            }
        }

        // 如果一切都失败了，回到主页。
        if ( empty( $final_url ) ) {
            $final_url = home_url();
        }

        // 使用JavaScript进行客户端重定向，以确保浏览器地址栏中的URL是干净的。
        echo '<!DOCTYPE html><html><head><title>Redirecting...</title>';
        echo '<script type="text/javascript">window.location.replace("' . $final_url . '");</script>';
        echo '</head><body><p>Payment successful, redirecting...</p></body></html>';
        exit;
    }

    /**
     * 获取单例
     * @return ITCRY_WOOPAY_Notify_Handler
     */
    public static function get_instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * 处理码支付的异步通知
     */
    public function handle_codepay_notify() {
        $options = get_option( 'itcry_woo_pay_codepay_settings', array() );
        $key = isset( $options['codepay_key'] ) ? $options['codepay_key'] : '';

        if ( empty( $key ) ) {
            $this->log_and_die( 'Codepay key is not configured.' );
        }
        
        $params = wp_unslash( $_GET );
        
        // 验证签名
        if ( ! $this->verify_codepay_sign( $params, $key ) ) {
            $this->log_and_die( 'Codepay sign verification failed.' );
        }

        // 处理业务逻辑
        $order_id = isset( $params['pay_id'] ) ? intval( $params['pay_id'] ) : 0;
        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            $this->log_and_die( 'Order not found for ID: ' . $order_id );
        }

        // 检查订单状态，防止重复处理
        if ( ! $order->has_status( 'pending' ) ) {
            echo 'success';
            exit;
        }
        
        $transaction_id = isset( $params['pay_no'] ) ? sanitize_text_field( $params['pay_no'] ) : '';
        $money_received = isset( $params['money'] ) ? (float) $params['money'] : 0;
        $order_total = (float) $order->get_total();

        if ( ( $order_total - $money_received ) > 0.01 ) {
             $order->add_order_note( sprintf( '支付确认失败：金额不足。订单总额: %s, 实际支付: %s。交易号: %s', $order_total, $money_received, $transaction_id ) );
             $this->log_and_die( 'Payment amount (' . $money_received . ') is less than order total (' . $order_total . ').' );
        }

        $order->add_order_note( sprintf('支付成功！交易号: %s', $transaction_id) );
        $order->payment_complete( $transaction_id );

        if ($this->is_all_virtual($order)) {
            $order->update_status('completed');
        }

        echo 'success';
        exit;
    }

    /**
     * 处理易支付的异步通知
     */
    public function handle_easypay_notify() {
        $options = get_option( 'itcry_woo_pay_easypay_settings', array() );
        $key = isset( $options['easypay_key'] ) ? $options['easypay_key'] : '';
        
        if ( empty( $key ) ) {
            $this->log_and_die( 'Easypay key is not configured.' );
        }

        $params = wp_unslash( $_GET );
        
        if ( ! $this->verify_easypay_sign( $params, $key ) ) {
            $this->log_and_die( 'Easypay sign verification failed.' );
        }

        if ( ! isset( $params['trade_status'] ) || $params['trade_status'] !== 'TRADE_SUCCESS' ) {
            $this->log_and_die( 'Trade status is not TRADE_SUCCESS.' );
        }

        $order_id = isset( $params['out_trade_no'] ) ? intval( $params['out_trade_no'] ) : 0;
        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            $this->log_and_die( 'Order not found for ID: ' . $order_id );
        }

        if ( ! $order->has_status( 'pending' ) ) {
            echo 'success';
            exit;
        }

        $transaction_id = isset( $params['trade_no'] ) ? sanitize_text_field( $params['trade_no'] ) : '';
        
        $order->add_order_note( sprintf('支付成功！交易号: %s', $transaction_id) );
        $order->payment_complete( $transaction_id );

        if ($this->is_all_virtual($order)) {
            $order->update_status('completed');
        }

        echo 'success';
        exit;
    }

    private function verify_codepay_sign( $params, $key ) {
        if ( ! isset( $params['sign'] ) ) return false;
        
        $sign = $params['sign'];
        ksort($params);
        reset($params);
        
        $sign_str = '';
        foreach ($params as $k => $v) {
            if ($k == 'sign' || $v === '') continue;
            $sign_str .= $k . '=' . $v . '&';
        }
        $sign_str = rtrim($sign_str, '&');

        return md5( $sign_str . $key ) === $sign;
    }

    private function verify_easypay_sign( $params, $key ) {
        if ( ! isset( $params['sign'] ) ) return false;

        $sign = $params['sign'];
        
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
        
        return md5( $prestr . $key ) === $sign;
    }

    private function is_all_virtual( $order ) {
        foreach ($order->get_items() as $item) {
            if ( 'line_item' == $item->get_type() ) {
                $product = $item->get_product();
                if ($product && !$product->is_virtual()) {
                    return false;
                }
            }
        }
        return true;
    }

    private function log_and_die( $message ) {
        wp_die( 'fail', 'Notify Verification', array( 'response' => 403 ) );
    }
}