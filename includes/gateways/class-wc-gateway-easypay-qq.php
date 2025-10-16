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
        // 【关键修复】: 不再依赖 $order->get_total()，而是自己精确计算
        // 订单的 "子总计" + "运费" + "税费" = 无手续费的基准金额
        $order_base_amount = $order->get_subtotal() + $order->get_shipping_total() + $order->get_total_tax();

        $manager = ITCRY_WOOPAY_Easypay_Manager::get_instance();
        $manager->refresh_settings();
        // 使用基准金额来选择接口，这是最准确的
        $selected_interface = $manager->select_available_interface( $order_base_amount );

        if ( ! $selected_interface ) {
            $order->add_order_note( __( '易支付错误：当前没有可用的支付接口或所有接口均已达到限额。', 'itcry-woo-pay' ) );
            wc_add_notice(__( '抱歉，支付服务暂时不可用，请稍后再试或联系网站管理员。', 'itcry-woo-pay' ), 'error');
            return '';
        }

        $api_url  = $selected_interface['url'];
        $pid      = $selected_interface['id'];
        $key      = $selected_interface['key'];
        $index    = $selected_interface['index'];

        if ( empty( $api_url ) || empty( $pid ) || empty( $key ) ) {
            $order->add_order_note( sprintf( __( '易支付接口 #%d 配置不完整，无法发起支付。', 'itcry-woo-pay' ), $index + 1 ) );
            wc_add_notice(__( '支付配置错误，请联系网站管理员。', 'itcry-woo-pay' ), 'error');
            return '';
        }
        
        // 【关键修复】: 再次计算手续费金额，确保100%准确
        $fee_rate = $this->get_current_fee_rate($selected_interface);
        $fee_amount = 0;
        if ($fee_rate > 0) {
            $fee_amount = round($order_base_amount * ($fee_rate / 100), 2);
        }

        // 【关键修复】: 最终支付金额 = 基准金额 + 手续费
        $final_payment_amount = $order_base_amount + $fee_amount;

        // 如果计算出的最终金额与订单记录的总额差异过大，则记录一条日志，以防万一
        if (abs($final_payment_amount - (float)$order->get_total()) > 0.01) {
             $order->add_order_note(sprintf(
                 '支付金额校对：插件计算总额为 %.2f，订单记录总额为 %.2f。将以插件计算总额为准发起支付。',
                 $final_payment_amount,
                 $order->get_total()
             ));
        }

        $product_name = $this->get_product_name( $order );
        $type = 'qqpay';

        $return_url = WC()->api_request_url('itcry_woo_pay_easypay_return');
        if (strpos($return_url, '?') === false) {
            $return_url .= '?out_trade_no=' . $order->get_id();
        } else {
            $return_url .= '&out_trade_no=' . $order->get_id();
        }

        $params = array(
            'pid'          => (int)$pid,
            'type'         => $type,
            'out_trade_no' => $order->get_id(),
            'notify_url'   => WC()->api_request_url('itcry_woo_pay_easypay_notify'),
            'return_url'   => $return_url,
            'name'         => $product_name,
            'money'        => $final_payment_amount, // <-- 使用我们精确计算的最终金额
            'sign_type'    => 'MD5',
            'param'        => $index . '_' . uniqid()
        );

        $params['sign'] = $this->generate_easypay_sign( $params, $key );

        $pay_url = rtrim( $api_url, '/' ) . '/submit.php?' . http_build_query( $params );

        $order->add_order_note( sprintf( __( '用户选择了 %s 发起支付 (使用接口 #%d)，应付金额 %.2f（含手续费 %.2f）。', 'itcry-woo-pay' ), $this->method_title, $index + 1, $final_payment_amount, $fee_amount ) );
        
        WC()->cart->empty_cart();

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
        echo '<script type="text/javascript">window.location.replace("' . esc_url_raw( $final_url ) . '");</script>';
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
