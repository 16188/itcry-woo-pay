<?php
/**
 * ITCRY WOOPAY - Codepay QQ Gateway
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class ITCRY_WOOPAY_Gateway_Codepay_QQ extends ITCRY_WOOPAY_Abstract_Gateway {

    /**
     * 构造函数
     */
    public function __construct() {
        $this->id                 = 'itcry_woo_pay_codepay_qq';
        $this->gateway_type       = 'codepay';
        $this->icon               = ITCRY_WOOPAY_URL . 'assets/logo/qq-logo.jpg';
        $this->method_title       = __( '码支付 - QQ钱包', 'itcry-woo-pay' );
        $this->method_description = __( '使用码支付提供的QQ钱包接口进行付款。', 'itcry-woo-pay' );

        parent::__construct();
    }

    /**
     * 实现父类中定义的抽象方法，用于生成支付URL
     *
     * @param WC_Order $order
     * @return string
     */
    protected function generate_payment_url( $order ) {
        $options = get_option( 'itcry_woo_pay_codepay_settings', array() );
        
        $codepay_id  = isset( $options['codepay_id'] ) ? $options['codepay_id'] : '';
        $codepay_key = isset( $options['codepay_key'] ) ? $options['codepay_key'] : '';

        if ( empty( $codepay_id ) || empty( $codepay_key ) ) {
            $order->add_order_note( __( '码支付设置不完整，无法发起支付。', 'itcry-woo-pay' ) );
            return '';
        }
        
        $type = 2;

        // 使用专用的中间API端点来处理重定向并清理URL。
        $return_url = WC()->api_request_url('itcry_woo_pay_codepay_return');

        $params = array(
            'id'         => (int) $codepay_id,
            'pay_id'     => $order->get_id(),
            'type'       => $type,
            'price'      => (float) $order->get_total(),
            'param'      => '',
            'notify_url' => WC()->api_request_url('itcry_woo_pay_codepay_notify'),
            'return_url' => $return_url,
        );

        ksort($params);
        reset($params);

        $sign_str = '';
        $url_str = '';

        foreach ($params as $key => $val) {
            if ($val == '' || $key == 'sign') continue;
            if ($sign_str != '') {
                $sign_str .= "&";
                $url_str  .= "&";
            }
            $sign_str .= "$key=$val";
            $url_str  .= "$key=" . urlencode($val);
        }

        $sign = md5( $sign_str . $codepay_key );
        $query = $url_str . '&sign=' . $sign;
        
        $api_host = 'http://api2.fateqq.com:52888/creat_order/?';
        $pay_url = $api_host . $query;

        $order->add_order_note( sprintf( __( '用户选择了 %s 发起支付。', 'itcry-woo-pay' ), $this->method_title ) );

        return $pay_url;
    }
}