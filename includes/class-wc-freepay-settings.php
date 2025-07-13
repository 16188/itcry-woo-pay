<?php
/**
 * ITCRY WOOPAY 后台设置页面
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class ITCRY_WOOPAY_Settings {

    private static $_instance = null;
    private $tabs = array();
    private $option_groups = array();

    private function __construct() {
        $this->tabs = array(
            'codepay' => '码支付 (Codepay)',
            'easypay' => '易支付 (Easypay)',
        );

        $this->option_groups = array(
            'codepay' => 'itcry_woo_pay_codepay_settings',
            'easypay' => 'itcry_woo_pay_easypay_settings'
        );

        add_action( 'admin_menu', array( $this, 'admin_menu' ) );
        add_action( 'admin_init', array( $this, 'settings_init' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
    }

    public static function get_instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function admin_menu() {
        add_menu_page(
            __( 'ITCRY WOOPAY Settings', 'itcry-woo-pay' ),
            __( 'ITCRY支付', 'itcry-woo-pay' ),
            'manage_woocommerce',
            'itcry-woo-pay-settings',
            array( $this, 'settings_page_html' ),
            ITCRY_WOOPAY_URL . 'assets/logo/codepay.jpg'
        );
    }

    public function settings_page_html() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        $current_tab = isset( $_GET['tab'] ) && array_key_exists( $_GET['tab'], $this->tabs ) ? sanitize_key( $_GET['tab'] ) : 'codepay';
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <nav class="nav-tab-wrapper">
                <?php
                foreach ( $this->tabs as $tab_key => $tab_name ) {
                    $active = ( $current_tab === $tab_key ) ? 'nav-tab-active' : '';
                    echo '<a href="' . esc_url( admin_url( 'admin.php?page=itcry-woo-pay-settings&tab=' . $tab_key ) ) . '" class="nav-tab ' . esc_attr( $active ) . '">' . esc_html( $tab_name ) . '</a>';
                }
                ?>
            </nav>
            <form action="options.php" method="post">
                <?php
                settings_fields( $this->option_groups[$current_tab] );
                do_settings_sections( 'itcry-woo-pay-settings-' . $current_tab );
                submit_button( __( 'Save Settings', 'itcry-woo-pay' ) );
                ?>
            </form>
        </div>
        <?php
    }

    public function settings_init() {
        // 注册Codepay设置
        $codepay_option_group = $this->option_groups['codepay'];
        register_setting( $codepay_option_group, $codepay_option_group, array($this, 'sanitize_codepay_settings') );
        add_settings_section( 'itcry_woo_pay_codepay_section', __( 'Codepay API Settings', 'itcry-woo-pay' ), array( $this, 'codepay_section_callback' ), 'itcry-woo-pay-settings-codepay' );
        add_settings_field( 'codepay_id', __( '码支付ID', 'itcry-woo-pay' ), array( $this, 'render_text_field' ), 'itcry-woo-pay-settings-codepay', 'itcry_woo_pay_codepay_section', [ 'option_group' => $codepay_option_group, 'id' => 'codepay_id', 'placeholder' => '请输入您的码支付ID' ] );
        add_settings_field( 'codepay_key', __( '通讯密钥', 'itcry-woo-pay' ), array( $this, 'render_text_field' ), 'itcry-woo-pay-settings-codepay', 'itcry_woo_pay_codepay_section', [ 'option_group' => $codepay_option_group, 'id' => 'codepay_key', 'placeholder' => '请输入您的码支付通讯密钥' ] );
        add_settings_field( 'codepay_return_url', __( '自定义同步跳转地址', 'itcry-woo-pay' ), array( $this, 'render_text_field' ), 'itcry-woo-pay-settings-codepay', 'itcry_woo_pay_codepay_section', [ 'option_group' => $codepay_option_group, 'id' => 'codepay_return_url', 'placeholder' => '留空则跳转到WooCommerce默认感谢页面', 'desc' => '（选填）支付成功后，用户浏览器将跳转到此地址。' ] );

        // 注册Easypay设置
        $easypay_option_group = $this->option_groups['easypay'];
        register_setting( $easypay_option_group, $easypay_option_group, array($this, 'sanitize_easypay_settings') );
        add_settings_section( 'itcry_woo_pay_easypay_section', __( 'Easypay API Settings', 'itcry-woo-pay' ), array( $this, 'easypay_section_callback' ), 'itcry-woo-pay-settings-easypay' );
        add_settings_field( 'easypay_api_url', __( '支付网关地址', 'itcry-woo-pay' ), array( $this, 'render_text_field' ), 'itcry-woo-pay-settings-easypay', 'itcry_woo_pay_easypay_section', [ 'option_group' => $easypay_option_group, 'id' => 'easypay_api_url', 'placeholder' => '例如: http://your.domain.com/', 'desc' => '请以 http:// 或 https:// 开头，并以 / 结尾' ] );
        add_settings_field( 'easypay_id', __( '商户ID', 'itcry-woo-pay' ), array( $this, 'render_text_field' ), 'itcry-woo-pay-settings-easypay', 'itcry_woo_pay_easypay_section', [ 'option_group' => $easypay_option_group, 'id' => 'easypay_id', 'placeholder' => '请输入您的易支付商户ID' ] );
        add_settings_field( 'easypay_key', __( '商户密钥', 'itcry-woo-pay' ), array( $this, 'render_text_field' ), 'itcry-woo-pay-settings-easypay', 'itcry_woo_pay_easypay_section', [ 'option_group' => $easypay_option_group, 'id' => 'easypay_key', 'placeholder' => '请输入您的易支付商户密钥' ] );
        add_settings_field( 'easypay_return_url', __( '自定义同步跳转地址', 'itcry-woo-pay' ), array( $this, 'render_text_field' ), 'itcry-woo-pay-settings-easypay', 'itcry_woo_pay_easypay_section', [ 'option_group' => $easypay_option_group, 'id' => 'easypay_return_url', 'placeholder' => '留空则跳转到WooCommerce默认感谢页面', 'desc' => '（选填）支付成功后，用户浏览器将跳转到此地址。' ] );
    }

    public function render_text_field( $args ) {
        $options = get_option( $args['option_group'] );
        $value = isset( $options[ $args['id'] ] ) ? $options[ $args['id'] ] : '';
        echo '<input type="text" id="' . esc_attr( $args['id'] ) . '" name="' . esc_attr( $args['option_group'] . '[' . $args['id'] . ']' ) . '" value="' . esc_attr( $value ) . '" class="regular-text" placeholder="' . esc_attr( $args['placeholder'] ) . '">';
        if ( ! empty( $args['desc'] ) ) {
            echo '<p class="description">' . esc_html( $args['desc'] ) . '</p>';
        }
    }
    
    public function codepay_section_callback() {
        echo '<p>' . __( '请输入您的码支付账户信息。', 'itcry-woo-pay' ) . '</p>';
        echo '<p><strong>' . __( '通知地址 (Notify URL):', 'itcry-woo-pay' ) . '</strong> <code>' . esc_url( home_url( '/wc-api/itcry_woo_pay_codepay_notify/' ) ) . '</code><br><small>'.__( '请将此地址复制并粘贴到码支付后台的通知地址设置中。', 'itcry-woo-pay' ).'</small></p>';
    }

    public function easypay_section_callback() {
        echo '<p>' . __( '请输入您的易支付账户信息。', 'itcry-woo-pay' ) . '</p>';
        echo '<p><strong>' . __( '通知地址 (Notify URL):', 'itcry-woo-pay' ) . '</strong> <code>' . esc_url( home_url( '/wc-api/itcry_woo_pay_easypay_notify/' ) ) . '</code><br><small>'.__( '此地址将自动提交给易支付，无需手动设置。', 'itcry-woo-pay' ).'</small></p>';
    }

    public function sanitize_codepay_settings($input) {
        $new_input = array();
        if ( isset( $input['codepay_id'] ) ) $new_input['codepay_id'] = sanitize_text_field( $input['codepay_id'] );
        if ( isset( $input['codepay_key'] ) ) $new_input['codepay_key'] = sanitize_text_field( $input['codepay_key'] );
        if ( isset( $input['codepay_return_url'] ) ) $new_input['codepay_return_url'] = esc_url_raw( $input['codepay_return_url'] );
        return $new_input;
    }

    public function sanitize_easypay_settings($input) {
        $new_input = array();
        if ( isset( $input['easypay_api_url'] ) ) $new_input['easypay_api_url'] = esc_url_raw( $input['easypay_api_url'] );
        if ( isset( $input['easypay_id'] ) ) $new_input['easypay_id'] = sanitize_text_field( $input['easypay_id'] );
        if ( isset( $input['easypay_key'] ) ) $new_input['easypay_key'] = sanitize_text_field( $input['easypay_key'] );
        if ( isset( $input['easypay_return_url'] ) ) $new_input['easypay_return_url'] = esc_url_raw( $input['easypay_return_url'] );
        return $new_input;
    }

    public function admin_enqueue_scripts( $hook ) {
        if ( 'toplevel_page_itcry-woo-pay-settings' !== $hook ) return;
        wp_enqueue_style( 'itcry-woo-pay-layui-css', ITCRY_WOOPAY_URL . 'assets/lay/css/layui.css', array(), ITCRY_WOOPAY_VERSION );
    }
}