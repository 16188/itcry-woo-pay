<?php
/**
 * ITCRY WOOPAY 易支付接口管理器
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class ITCRY_WOOPAY_Easypay_Manager {

    private static $_instance = null;
    private $settings;

    private function __construct() {
        $this->settings = get_option( 'itcry_woo_pay_easypay_settings', array() );
    }

    public static function get_instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * 刷新设置，当设置更新时可调用
     */
    public function refresh_settings() {
        $this->settings = get_option( 'itcry_woo_pay_easypay_settings', array() );
    }

    /**
     * 选择一个可用的支付接口
     * @param float $amount 订单金额
     * @return array|null 返回可用接口的信息 (包括索引) 或 null
     */
    public function select_available_interface( $amount ) {
        $interfaces = isset($this->settings['interfaces']) ? $this->settings['interfaces'] : array();
        if ( empty( $interfaces ) ) {
            return null;
        }

        // 遍历所有接口，寻找未达到限额的
        foreach ( $interfaces as $index => $interface ) {
            $limit = isset( $interface['limit'] ) ? (float) $interface['limit'] : 0;
            // 如果限额为0或未设置，则视为无限额，直接使用
            if ( $limit <= 0 ) {
                $interface['index'] = $index;
                return $interface;
            }

            $today_total = $this->get_daily_total( $index );
            if ( ( $today_total + $amount ) <= $limit ) {
                $interface['index'] = $index;
                return $interface;
            }
        }

        // 如果所有接口都达到了限额，使用备用接口
        $fallback_index = isset( $this->settings['fallback_index'] ) ? (int) $this->settings['fallback_index'] : -1;
        if ( $fallback_index !== -1 && isset( $interfaces[ $fallback_index ] ) ) {
            $fallback_interface = $interfaces[ $fallback_index ];
            $fallback_interface['index'] = $fallback_index;
            return $fallback_interface;
        }

        // 连备用接口都没有，则返回失败
        return null;
    }

    /**
     * 获取指定接口今天的收款总额
     * @param int $index 接口索引
     * @return float
     */
    public function get_daily_total( $index ) {
        // 使用瞬态数据存储，键名包含日期确保每天重置
        $transient_key = 'itcry_easypay_total_' . $index . '_' . date('Ymd');
        return (float) get_transient( $transient_key );
    }

    /**
     * 增加指定接口今天的收款总额
     * @param int $index 接口索引
     * @param float $amount 金额
     */
    public function add_to_daily_total( $index, $amount ) {
        $transient_key = 'itcry_easypay_total_' . $index . '_' . date('Ymd');
        $current_total = (float) get_transient( $transient_key );
        $new_total = $current_total + (float) $amount;
        // 设置瞬态数据在一天后过期
        set_transient( $transient_key, $new_total, DAY_IN_SECONDS );
    }
}
