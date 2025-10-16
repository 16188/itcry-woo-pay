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
        add_settings_field( 'easypay_interfaces', __( '接口管理', 'itcry-woo-pay' ), array( $this, 'render_easypay_fields' ), 'itcry-woo-pay-settings-easypay', 'itcry_woo_pay_easypay_section' );
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
        echo '<p>' . __( '在此管理您的易支付接口。您可以通过拖拽调整接口优先级，并为不同支付方式设置费率。', 'itcry-woo-pay' ) . '</p>';
        echo '<p><strong>' . __( '通知地址 (Notify URL):', 'itcry-woo-pay' ) . '</strong> <code>' . esc_url( home_url( '/wc-api/itcry_woo_pay_easypay_notify/' ) ) . '</code><br><small>'.__( '所有接口都使用此同一个通知地址，系统会自动处理。', 'itcry-woo-pay' ).'</small></p>';
    }

    public function render_easypay_fields() {
        $options = get_option('itcry_woo_pay_easypay_settings');
        $interfaces = isset($options['interfaces']) ? $options['interfaces'] : array();
        $fallback_index = isset($options['fallback_index']) ? $options['fallback_index'] : -1;
        $return_url = isset($options['easypay_return_url']) ? $options['easypay_return_url'] : '';

        // 【新增代码】: 获取 Easypay Manager 实例
        $easypay_manager = ITCRY_WOOPAY_Easypay_Manager::get_instance(); 
    ?>
    <div id="easypay-interfaces-wrapper">
        <style>
            #easypay-interfaces-body .interface-row .handle { cursor: move; width: 30px; text-align: center; font-size: 18px; vertical-align: middle; }
            #easypay-interfaces-body .interface-row.ui-sortable-helper { background-color: #f9f9f9; border: 1px dashed #ccc; }
        </style>
        <table class="widefat striped" id="easypay-interfaces-table">
            <thead>
                <tr>
                    <th class="handle">排序</th>
                    <th>支付网关地址 (URL)</th>
                    <th>商户ID</th>
                    <th>商户密钥</th>
                    <th>每日限额 (元)</th>
                    <th>今日已收 (元)</th> <!-- 【修改点 1】: 新增表头 -->
                    <th>支付宝费率 (%)</th>
                    <th>微信费率 (%)</th>
                    <th>QQ钱包费率 (%)</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody id="easypay-interfaces-body">
                <?php
                if (!empty($interfaces)) {
                    foreach ($interfaces as $index => $interface) {
                        ?>
                        <tr class="interface-row">
                            <td class="handle dashicons dashicons-move"></td>
                            <td><input type="text" class="regular-text" name="itcry_woo_pay_easypay_settings[interfaces][<?php echo $index; ?>][url]" value="<?php echo esc_attr($interface['url'] ?? ''); ?>" placeholder="http://your.domain.com/"></td>
                            <td><input type="text" class="regular-text" name="itcry_woo_pay_easypay_settings[interfaces][<?php echo $index; ?>][id]" value="<?php echo esc_attr($interface['id'] ?? ''); ?>"></td>
                            <td><input type="text" class="regular-text" name="itcry_woo_pay_easypay_settings[interfaces][<?php echo $index; ?>][key]" value="<?php echo esc_attr($interface['key'] ?? ''); ?>"></td>
                            <td><input type="number" step="0.01" class="small-text" name="itcry_woo_pay_easypay_settings[interfaces][<?php echo $index; ?>][limit]" value="<?php echo esc_attr($interface['limit'] ?? 0); ?>" placeholder="0为无限制"></td>
                            
                            <!-- 【修改点 2】: 新增显示统计的单元格 -->
                            <td>
                                <?php
                                    $daily_total = $easypay_manager->get_daily_total($index);
                                    $limit = isset($interface['limit']) ? (float)$interface['limit'] : 0;
                                    $percentage = ($limit > 0) ? min(100, ($daily_total / $limit) * 100) : 0;
                                    $bar_color = ($percentage >= 90) ? '#f44336' : (($percentage >= 70) ? '#ffc107' : '#4caf50');
                                ?>
                                <div style="position: relative; height: 22px; background: #f1f1f1; border-radius: 4px; overflow: hidden; border: 1px solid #ccc;">
                                    <div style="width: <?php echo $percentage; ?>%; height: 100%; background: <?php echo $bar_color; ?>;"></div>
                                    <span style="position: absolute; top: 0; left: 5px; line-height: 22px; color: #000; font-weight: 500;">
                                        <?php echo '¥ ' . number_format($daily_total, 2); ?>
                                    </span>
                                </div>
                            </td>
                            
                            <td><input type="number" step="0.01" class="small-text" name="itcry_woo_pay_easypay_settings[interfaces][<?php echo $index; ?>][fee_alipay]" value="<?php echo esc_attr($interface['fee_alipay'] ?? 0); ?>" placeholder="例如: 1.5"></td>
                            <td><input type="number" step="0.01" class="small-text" name="itcry_woo_pay_easypay_settings[interfaces][<?php echo $index; ?>][fee_wxpay]" value="<?php echo esc_attr($interface['fee_wxpay'] ?? 0); ?>" placeholder="例如: 1.5"></td>
                            <td><input type="number" step="0.01" class="small-text" name="itcry_woo_pay_easypay_settings[interfaces][<?php echo $index; ?>][fee_qqpay]" value="<?php echo esc_attr($interface['fee_qqpay'] ?? 0); ?>" placeholder="例如: 1.5"></td>
                            <td><button type="button" class="button button-secondary remove-interface">移除</button></td>
                        </tr>
                        <?php
                    }
                }
                ?>
            </tbody>
        </table>
        <p>
            <button type="button" class="button button-primary" id="add-easypay-interface">添加新接口</button>
        </p>

        <hr>

        <h4><?php _e( '全局设置', 'itcry-woo-pay' ); ?></h4>
        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="easypay_fallback_index"><?php _e( '备用接口', 'itcry-woo-pay' ); ?></label>
                    </th>
                    <td>
                        <select id="easypay_fallback_index" name="itcry_woo_pay_easypay_settings[fallback_index]">
                            <option value="-1"><?php _e( '未设置', 'itcry-woo-pay' ); ?></option>
                            <?php
                            if (!empty($interfaces)) {
                                foreach ($interfaces as $index => $interface) {
                                    printf('<option value="%d" %s>%s (ID: %s)</option>',
                                        $index,
                                        selected($fallback_index, $index, false),
                                        esc_html( empty($interface['url']) ? "接口 ".($index+1) : $interface['url']),
                                        esc_html( empty($interface['id']) ? "未知" : $interface['id'])
                                    );
                                }
                            }
                            ?>
                        </select>
                        <p class="description"><?php _e( '当所有接口都达到每日限额时，将使用此接口进行收款（无论其是否已达限额）。', 'itcry-woo-pay' ); ?></p>
                    </td>
                </tr>
                 <tr>
                    <th scope="row">
                        <label for="easypay_return_url"><?php _e( '自定义同步跳转地址', 'itcry-woo-pay' ); ?></label>
                    </th>
                    <td>
                        <input type="text" id="easypay_return_url" name="itcry_woo_pay_easypay_settings[easypay_return_url]" value="<?php echo esc_attr( $return_url ); ?>" class="regular-text" placeholder="留空则跳转到WooCommerce默认感谢页面">
                        <p class="description">（选填）支付成功后，用户浏览器将跳转到此地址。</p>
                    </td>
                 </tr>
            </tbody>
        </table>
    </div>

    <!-- JS Template for new rows -->
    <script type="text/template" id="easypay-interface-template">
        <tr class="interface-row">
            <td class="handle dashicons dashicons-move"></td>
            <td><input type="text" class="regular-text" name="itcry_woo_pay_easypay_settings[interfaces][{index}][url]" placeholder="http://your.domain.com/"></td>
            <td><input type="text" class="regular-text" name="itcry_woo_pay_easypay_settings[interfaces][{index}][id]"></td>
            <td><input type="text" class="regular-text" name="itcry_woo_pay_easypay_settings[interfaces][{index}][key]"></td>
            <td><input type="number" step="0.01" class="small-text" name="itcry_woo_pay_easypay_settings[interfaces][{index}][limit]" value="0" placeholder="0为无限制"></td>
            <!-- 【修改点 3】: JS模板中也要添加对应的空单元格, 以维持表格结构 -->
            <td><!--
                 This cell is for display only.
                 New row will not have any value until page refresh after save.
            --></td>
            <td><input type="number" step="0.01" class="small-text" name="itcry_woo_pay_easypay_settings[interfaces][{index}][fee_alipay]" value="0" placeholder="例如: 1.5"></td>
            <td><input type="number" step="0.01" class="small-text" name="itcry_woo_pay_easypay_settings[interfaces][{index}][fee_wxpay]" value="0" placeholder="例如: 1.5"></td>
            <td><input type="number" step="0.01" class="small-text" name="itcry_woo_pay_easypay_settings[interfaces][{index}][fee_qqpay]" value="0" placeholder="例如: 1.5"></td>
            <td><button type="button" class="button button-secondary remove-interface">移除</button></td>
        </tr>
    </script>
    <?php
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
        if (isset($input['interfaces']) && is_array($input['interfaces'])) {
            $new_input['interfaces'] = array_map(function($interface) {
                $sanitized = [];
                $sanitized['url'] = isset($interface['url']) ? esc_url_raw(trim($interface['url'])) : '';
                $sanitized['id'] = isset($interface['id']) ? sanitize_text_field($interface['id']) : '';
                $sanitized['key'] = isset($interface['key']) ? sanitize_text_field($interface['key']) : '';
                $sanitized['limit'] = isset($interface['limit']) && is_numeric($interface['limit']) ? floatval($interface['limit']) : 0;
                $sanitized['fee_alipay'] = isset($interface['fee_alipay']) && is_numeric($interface['fee_alipay']) ? floatval($interface['fee_alipay']) : 0;
                $sanitized['fee_wxpay'] = isset($interface['fee_wxpay']) && is_numeric($interface['fee_wxpay']) ? floatval($interface['fee_wxpay']) : 0;
                $sanitized['fee_qqpay'] = isset($interface['fee_qqpay']) && is_numeric($interface['fee_qqpay']) ? floatval($interface['fee_qqpay']) : 0;
                return $sanitized;
            }, array_values($input['interfaces']));
        }
        $new_input['fallback_index'] = isset($input['fallback_index']) ? intval($input['fallback_index']) : -1;
        $new_input['easypay_return_url'] = isset($input['easypay_return_url']) ? esc_url_raw($input['easypay_return_url']) : '';
        return $new_input;
    }

    public function admin_enqueue_scripts( $hook ) {
        if ( 'toplevel_page_itcry-woo-pay-settings' !== $hook ) return;
        
        // **【关键修复】**: 显式加载 WordPress 的 Dashicons 样式表
        wp_enqueue_style('dashicons');

        // 加载 jQuery UI 的 sortable 组件
        wp_enqueue_script('jquery-ui-sortable');
        
        wp_enqueue_style( 'itcry-woo-pay-layui-css', ITCRY_WOOPAY_URL . 'assets/lay/css/layui.css', array(), ITCRY_WOOPAY_VERSION );
        
        wp_add_inline_script('jquery-core', "
            jQuery(document).ready(function($) {
                var wrapper = $('#easypay-interfaces-wrapper');

                function reindexRows() {
                    $('#easypay-interfaces-body .interface-row').each(function(index) {
                        $(this).find('input, select').each(function() {
                            var name = $(this).attr('name');
                            if (name) {
                                var newName = name.replace(/\[interfaces\]\[\d+\]/, '[interfaces][' + index + ']');
                                $(this).attr('name', newName);
                            }
                        });
                    });
                }
                
                function updateFallbackOptions() {
                    var fallbackSelect = $('#easypay_fallback_index');
                    var selectedValue = fallbackSelect.val();
                    fallbackSelect.empty().append('<option value=\"-1\">" . esc_js(__('未设置', 'itcry-woo-pay')) . "</option>');
                    
                    $('#easypay-interfaces-body .interface-row').each(function(index) {
                        var url = $(this).find('input[name*=\"[url]\"]').val();
                        var id = $(this).find('input[name*=\"[id]\"]').val();
                        var optionText = (url ? url : '接口 ' + (index + 1)) + (id ? ' (ID: ' + id + ')' : '');
                        
                        var option = $('<option>', {
                            value: index,
                            text: optionText
                        });

                        if (index == selectedValue) {
                           option.prop('selected', true);
                        }

                        fallbackSelect.append(option);
                    });
                }
                
                $('#add-easypay-interface').on('click', function(e) {
                    e.preventDefault();
                    var newIndex = $('#easypay-interfaces-body .interface-row').length;
                    var template = jQuery('#easypay-interface-template').html().replace(/{index}/g, newIndex);
                    $('#easypay-interfaces-body').append(template);
                    updateFallbackOptions();
                });

                wrapper.on('click', '.remove-interface', function(e) {
                    e.preventDefault();
                    if (confirm('" . esc_js(__('确定要移除这个接口吗？', 'itcry-woo-pay')) . "')) {
                        $(this).closest('.interface-row').remove();
                        reindexRows();
                        updateFallbackOptions();
                    }
                });

                wrapper.on('input', 'input[name*=\"[url]\"], input[name*=\"[id]\"]', function() {
                    updateFallbackOptions();
                });
                
                // 初始化拖拽排序
                $('#easypay-interfaces-body').sortable({
                    handle: '.handle',
                    axis: 'y',
                    update: function(event, ui) {
                        reindexRows();
                        updateFallbackOptions();
                    }
                });

                if($('#easypay-interfaces-body').length) {
                    updateFallbackOptions();
                }
            });
        ");
    }
}

