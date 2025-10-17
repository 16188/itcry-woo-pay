<?php
/**
 * ITCRY WOOPAY 统一支付回调处理器
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class ITCRY_WOOPAY_Notify_Handler {

    private static $_instance = null;

    private function __construct() {
        add_action( 'woocommerce_api_itcry_woo_pay_codepay_notify', array( $this, 'handle_codepay_notify' ) );
        add_action( 'woocommerce_api_itcry_woo_pay_easypay_notify', array( $this, 'handle_easypay_notify' ) );
        add_action('woocommerce_api_itcry_woo_pay_codepay_return', array($this, 'handle_codepay_return_url_and_redirect'));
    }

    public static function get_instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function handle_codepay_return_url_and_redirect() {
        $options = get_option( 'itcry_woo_pay_codepay_settings', array() );
        $final_url = '';

        if ( ! empty( $options['codepay_return_url'] ) ) {
            $final_url = esc_url_raw( $options['codepay_return_url'] );
        } else {
            $order_id = isset( $_GET['pay_id'] ) ? absint( $_GET['pay_id'] ) : 0;
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
        echo '<script type="text/javascript">window.location.replace("' . esc_url_raw( $final_url ) . '");</script>';
        echo '</head><body><p>Payment successful, redirecting...</p></body></html>';
        exit;
    }

    public function handle_codepay_notify() {
        $options = get_option( 'itcry_woo_pay_codepay_settings', array() );
        $key = isset( $options['codepay_key'] ) ? $options['codepay_key'] : '';

        if ( empty( $key ) ) {
            $this->log_and_die( 'Codepay key is not configured.' );
        }
        
        $params = wp_unslash( $_GET );
        
        if ( ! $this->verify_codepay_sign( $params, $key ) ) {
            $this->log_and_die( 'Codepay sign verification failed.' );
        }

        $order_id = isset( $params['pay_id'] ) ? intval( $params['pay_id'] ) : 0;
        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            $this->log_and_die( 'Order not found for ID: ' . $order_id );
        }

        if ( ! $order->has_status( 'pending' ) ) {
            echo 'success';
            exit;
        }
        
        $transaction_id = isset( $params['pay_no'] ) ? sanitize_text_field( $params['pay_no'] ) : '';
        $money_received = isset( $params['money'] ) ? (float) $params['money'] : 0;
        $order_total = (float) $order->get_total();

        // 精确金额校验
        if ( abs($order_total - $money_received) > 0.01 ) {
             $order->add_order_note( sprintf( '支付确认失败：金额不匹配。订单总额: %s, 实际支付: %s。交易号: %s', $order_total, $money_received, $transaction_id ) );
             $this->log_and_die( 'Payment amount (' . $money_received . ') does not match order total (' . $order_total . ').' );
        }

        $order->add_order_note( sprintf('支付成功！交易号: %s', $transaction_id) );
        $order->payment_complete( $transaction_id );

        if ($this->is_all_virtual($order)) {
            $order->update_status('completed');
        }

        echo 'success';
        exit;
    }

    public function handle_easypay_notify() {
        $params = wp_unslash( $_GET );
        
        $param_parts = isset($params['param']) ? explode('_', $params['param']) : array();
        $interface_index = isset($param_parts[0]) && is_numeric($param_parts[0]) ? intval($param_parts[0]) : -1;
        
        if ($interface_index === -1) {
            $this->log_and_die('Easypay notify error: Missing interface index.');
        }

        $settings = get_option('itcry_woo_pay_easypay_settings');
        $interface = isset($settings['interfaces'][$interface_index]) ? $settings['interfaces'][$interface_index] : null;

        if (!$interface || empty($interface['key'])) {
            $this->log_and_die('Easypay key is not configured for interface index: ' . $interface_index);
        }
        $key = $interface['key'];

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
        $money_paid = isset($params['money']) ? (float)$params['money'] : 0.0;

        // 计算“应付金额 = 基准金额 + 手续费”，以兼容开启手续费时的回调校验
        // 基准金额：商品小计 + 运费 + 税费（不含任何支付手续费）
        $order_base_amount = (float)$order->get_subtotal() + (float)$order->get_shipping_total() + (float)$order->get_total_tax();

        // 从请求中判断支付类型，以匹配对应的费率字段
        $pay_type = isset($params['type']) ? sanitize_text_field($params['type']) : '';
        $fee_rate = 0.0;
        if (is_array($interface)) {
            if ($pay_type === 'alipay' && isset($interface['fee_alipay'])) {
                $fee_rate = (float)$interface['fee_alipay'];
            } elseif ($pay_type === 'wxpay' && isset($interface['fee_wxpay'])) {
                $fee_rate = (float)$interface['fee_wxpay'];
            } elseif ($pay_type === 'qqpay' && isset($interface['fee_qqpay'])) {
                $fee_rate = (float)$interface['fee_qqpay'];
            }
        }

        // 以两位小数计算手续费与最终金额，避免浮点误差
        $fee_amount_calc = $fee_rate > 0 ? round($order_base_amount * ($fee_rate / 100), 2) : 0.0;
        $expected_amount = round($order_base_amount + $fee_amount_calc, 2);

        // 允许 0.01 的误差容忍度（单位：元）
        $tolerance = 0.01;

        // 优先按“基准金额+手续费”比对；若未启用手续费或仍不匹配，再回退到订单总额校验
        $order_total = (float)$order->get_total();
        $match_expected = (abs($money_paid - $expected_amount) <= $tolerance);
        $match_order_total = (abs($money_paid - $order_total) <= $tolerance);

        if (!($match_expected || $match_order_total)) {
            $order->add_order_note( sprintf(
                '支付确认失败：金额不匹配。订单总额: %s, 应付(含手续费): %s, 实际支付: %s。交易号: %s',
                wc_format_decimal($order_total, 2),
                wc_format_decimal($expected_amount, 2),
                wc_format_decimal($money_paid, 2),
                $transaction_id
            ) );
            $this->log_and_die('Payment amount (' . $money_paid . ') mismatch. Expected with fee ' . $expected_amount . ', or order total ' . $order_total . '.');
        }

        // 仅在金额校验成功后才更新收款总额
        ITCRY_WOOPAY_Easypay_Manager::get_instance()->add_to_daily_total($interface_index, $money_paid);

        $order->add_order_note( sprintf('支付成功！交易号: %s (接口 #%d)', $transaction_id, $interface_index + 1) );
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
        if (WP_DEBUG === true) {
            error_log('ITCRY_WOOPAY Error: ' . $message);
        }
        wp_die( 'fail', 'Notify Verification', array( 'response' => 403 ) );
    }
}
