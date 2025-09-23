<?php
/**
 * Plugin Name: FPD Quantity Restore
 * Description: Restore quantity selector for Fancy Product Designer products. Per-product & global toggle + modern admin UI with layout controls (Breakdance). Styles apply only when FPD is present.
 * Version: 1.3.1
 * Author: eMarketing Cyprus
 * Requires at least: 5.8
 * Requires PHP: 7.2
 * License: GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: fpd-qty-restore
 */

if (!defined('ABSPATH')) exit;

if (!class_exists('FPD_Qty_Restore')) :

final class FPD_Qty_Restore {
    const META_KEY   = '_fpd_allow_qty';
    const OPT_GLOBAL = 'fpd_qr_global_enable';
    const OPT_UI     = 'fpd_qr_ui_options';

    public function __construct() {
        $this->plugin_url  = plugin_dir_url(__FILE__);
        $this->plugin_path = plugin_dir_path(__FILE__);

        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        //add_action('init', [$this, 'init']);
        
        // Admin product checkbox
        add_action('woocommerce_product_options_inventory_product_data', [$this, 'add_product_field']);
        add_action('woocommerce_process_product_meta', [$this, 'save_product_field']);

        // Admin menu + settings
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);

        // Frontend qty logic
        add_filter('woocommerce_is_sold_individually', [$this, 'filter_sold_individually'], 10, 2);
        add_filter('woocommerce_quantity_input_args', [$this, 'filter_quantity_args'], 20, 2);
        add_action('woocommerce_add_to_cart', [$this, 'after_add_to_cart_update_qty'], 10, 6);

        // Frontend assets
        add_action('wp_enqueue_scripts', [$this, 'frontend_assets'], 20);
        
        if (!$this->is_breakdance_active()) {
            add_action('admin_notices', [$this, 'breakdance_not_active_notice']);
        }
    }
/** Breakdance present? */
    private function is_breakdance_active() {
        return class_exists('Breakdance\PluginAPI\PluginAPI') || is_plugin_active('breakdance/plugin.php');
    }

    /** Notice when Breakdance is missing */
    public function breakdance_not_active_notice() { ?>
        <div class="notice notice-warning is-dismissible">
            <p><?php echo esc_html__('Breakdance Menu Builder Fix requires the Breakdance plugin to be installed and activated.', 'breakdance-menu-fix'); ?></p>
        </div>
    <?php }

    
    /* -------------------- Helpers -------------------- */

    public static function product_allows_qty($product_id) : bool {
        if (get_post_meta($product_id, self::META_KEY, true)) return true;
        if (get_option(self::OPT_GLOBAL) === 'yes') return self::is_fpd_product($product_id);
        return false;
    }

    public static function is_fpd_product($product_id) : bool {
        $maybe_keys = ['_fpd_product','_fpd_enabled','fpd_products','fpd_product_settings'];
        $detected = false;
        foreach ($maybe_keys as $k) {
            $v = get_post_meta($product_id, $k, true);
            if (!empty($v)) { $detected = true; break; }
        }
        return (bool) apply_filters('fpd_qr_is_fpd_product', $detected, $product_id);
    }

    public static function get_ui_options() : array {
        $defaults = [
            'cart_flex_wrap' => 'wrap',
            'cart_flex_wrap_important' => false,
            'btn_width'   => '',
            'btn_width_important' => false,
            'btn_margin'  => '',
            'btn_margin_important' => false,
            'btn_display' => '',
            'btn_display_important' => false,
            'price_margin'  => '',
            'price_margin_important' => false,
            'price_padding' => '',
            'price_padding_important' => false,
        ];
        $opts = get_option(self::OPT_UI, []);
        if (!is_array($opts)) $opts = [];
        return array_merge($defaults, $opts);
    }

    /* ---------------- Product checkbox ---------------- */

    public function add_product_field() {
        echo '<div class="options_group">';
        woocommerce_wp_checkbox([
            'id'          => self::META_KEY,
            'label'       => __('Allow quantity with Fancy Product Designer', 'fpd-qty-restore'),
            'desc_tip'    => true,
            'description' => __('Show quantity selector on product page and allow more than 1 unit of the same design.', 'fpd-qty-restore'),
        ]);
        echo '</div>';
    }

    public function save_product_field($post_id) {
        $val = isset($_POST[self::META_KEY]) ? 'yes' : '';
        update_post_meta($post_id, self::META_KEY, $val);
    }

    /* ---------------- Admin settings/UI ---------------- */
     
    public function add_settings_page() {
        //The icon in Base64 format
    $icon_base64 = 'PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI1MTIiIGhlaWdodD0iNTEyIiB2aWV3Qm94PSIwIDAgNjAgNTciPjxnIGZpbGw9IiMwMDAiIGZpbGwtcnVsZT0ibm9uemVybyI+PHBhdGggZD0iTTU3IDI0aC05Ljc4NkEyLjk4MSAyLjk4MSAwIDAgMCA0OCAyMlYzYTMgMyAwIDAgMC0zLTNIMjZjLS43NjQgMC0xLjQ5OS4yOTMtMi4wNTMuODE5bC05LjIxMiA2LjQ3QTMgMyAwIDAgMCAxMyAxMHYxNGMtLjc2NCAwLTEuNDk5LjI5My0yLjA1My44MTlsLTkuMjEyIDYuNDdBMyAzIDAgMCAwIDAgMzR2MjBhMyAzIDAgMCAwIDMgM2gyMWEyLjk4MSAyLjk4MSAwIDAgMCAyLS43OGMuNTQ3LjUgMS4yNi43NzcgMiAuNzhoMjFjLjgxMSAwIDEuNTg4LS4zMzEgMi4xNS0uOTE2bDcuOTU4LTcuOTU4Yy41Ny0uNTYuODkyLTEuMzI2Ljg5Mi0yLjEyNlYyN2EzLjAxNSAzLjAxNSAwIDAgMC0zLTN6bS02LjY2NiA3LjMyN0EyLjk2NCAyLjk2NCAwIDAgMCA0OSAzMWgtOC43NjZsNS01aDExLjE4OHpNMjQgMzFoLThhMSAxIDAgMCAxLTEtMVYxMGExIDEgMCAwIDEgMS0xaDIxYTEgMSAwIDAgMSAxIDF2MjBhLjk5NC45OTQgMCAwIDEtLjI4NS43bC0uMDIuMDJBLjk5NC45OTQgMCAwIDEgMzcgMzF6TTM5LjcyNyA4Ljc2NiA0NiAzLjI3N1YyMmEuOTc5Ljk3OSAwIDAgMS0uMy43TDQwIDI4LjQwOVYxMGEyLjk2MyAyLjk2MyAwIDAgMC0uMjczLTEuMjM0em0tMS4zOTMtMS40MzlBMi45NjQgMi45NjQgMCAwIDAgMzcgN2gtNy4wODVsNi40MjgtNWg4LjA3OXpNMjUuMTY1IDIuNDA4YTEgMSAwIDAgMCAuMTQxLS4xMkEuOTcxLjk3MSAwIDAgMSAyNiAyaDcuMDg1bC02LjQyOCA1aC04LjAzem0tMTMgMjRhMSAxIDAgMCAwIC4xNDEtLjEyQS45NzEuOTcxIDAgMCAxIDEzIDI2djRjLjAwMy4zNDEuMDY2LjY4LjE4NCAxSDUuNjI3ek0zIDU1YTEgMSAwIDAgMS0xLTFWMzRhMSAxIDAgMCAxIDEtMWgyMWExIDEgMCAwIDEgMSAxdjIwYTEgMSAwIDAgMS0uMjg2LjdsLS4wMTguMDE3QS45OTMuOTkzIDAgMCAxIDI0IDU1em0yNSAwYTEgMSAwIDAgMS0xLTFWMzRhMSAxIDAgMCAxIDEtMWgyMWExIDEgMCAwIDEgMSAxdjIwYTEgMSAwIDAgMS0uMjc4LjY4NWwtLjAyMi4wMTUtLjAxNC4wMTZBMSAxIDAgMCAxIDQ5IDU1em0yOS43LTguM0w1MiA1Mi40VjM0YTIuOTYzIDIuOTYzIDAgMCAwLS4yNzMtMS4yMzRMNTggMjcuMjc3VjQ2YS45NzkuOTc5IDAgMCAxLS4zLjd6Ii8+PHBhdGggZD0iTTIzIDIzaC00YTIgMiAwIDAgMC0yIDJ2MmEyIDIgMCAwIDAgMiAyaDRhMiAyIDAgMCAwIDItMnYtMmEyIDIgMCAwIDAtMi0yem0tNCA0di0yaDR2MnpNMzUgMjNoLTNhMSAxIDAgMCAwIDAgMmgzYTEgMSAwIDAgMCAwLTJ6TTM1IDI3aC0zYTEgMSAwIDAgMCAwIDJoM2ExIDEgMCAwIDAgMC0yek0xMCA0N0g2YTIgMiAwIDAgMC0yIDJ2MmEyIDIgMCAwIDAgMiAyaDRhMiAyIDAgMCAwIDItMnYtMmEyIDIgMCAwIDAtMi0yem0tNCA0di0yaDR2MnpNMjIgNDdoLTNhMSAxIDAgMCAwIDAgMmgzYTEgMSAwIDAgMCAwLTJ6TTIyIDUxaC0zYTEgMSAwIDAgMCAwIDJoM2ExIDEgMCAwIDAgMC0yek0zNSA0N2gtNGEyIDIgMCAwIDAtMiAydjJhMiAyIDAgMCAwIDIgMmg0YTIgMiAwIDAgMCAyLTJ2LTJhMiAyIDAgMCAwLTItMnptLTQgNHYtMmg0djJ6TTQ3IDQ3aC0zYTEgMSAwIDAgMCAwIDJoM2ExIDEgMCAwIDAgMC0yek00NyA1MWgtM2ExIDEgMCAwIDAgMCAyaDNhMSAxIDAgMCAwIDAtMnoiLz48L2c+PC9zdmc+';
    
    //The icon in the data URI scheme
    $icon_data_uri = 'data:image/svg+xml;base64,' . $icon_base64;
    // Use PNG for the admin menu icon (SVG is used in-page).
        $icon_url = $this->plugin_url . 'assets/img/fpd-qty-icon.svg'; // put your PNG here
        if (!file_exists($this->plugin_path . 'assets/img/fpd-qty-icon.svg')) {
            // fallback to dashicon if PNG missing
            $icon_url = 'dashicons-admin-appearance';
        }
        add_menu_page(
            __('FPD Qty Restore', 'fpd-qty-restore'),
            __('FPD Qty Restore', 'fpd-qty-restore'),
            'manage_woocommerce',
            'fpd-qty-restore',
            [$this, 'render_settings_page'],
            $icon_data_uri,
            58
        );
    }

    public function register_settings() {
        register_setting('fpd_qr_settings', self::OPT_GLOBAL);
        register_setting('fpd_qr_ui', self::OPT_UI);

        add_settings_section('fpd_qr_main', __(''), function(){}, 'fpd_qr_settings_page');
        add_settings_field(self::OPT_GLOBAL, __('Enable site-wide for FPD products', 'fpd-qty-restore'), function(){
            $checked = get_option(self::OPT_GLOBAL) === 'yes' ? 'checked' : '';
            echo '<label class="fpdqr-switch"><input type="checkbox" name="'.esc_attr(self::OPT_GLOBAL).'" value="yes" '.$checked.'> <span>'.esc_html__('Show quantity selector on any product detected as using FPD.', 'fpd-qty-restore').'</span></label>';
        }, 'fpd_qr_settings_page', 'fpd_qr_main');

        add_settings_section('fpd_qr_ui_section', __(''), function(){}, 'fpd_qr_ui_page');
        add_settings_field('cart_flex_wrap', __('form.cart → flex-wrap', 'fpd-qty-restore'), [$this,'field_cart_flex_wrap'], 'fpd_qr_ui_page','fpd_qr_ui_section');
        add_settings_field('btn_block', __('Cart Button (.bde-wooproductcartbutton)', 'fpd-qty-restore'), [$this,'field_btn_block'], 'fpd_qr_ui_page','fpd_qr_ui_section');
        add_settings_field('price_block', __('Price (.bde-wooproductprice)', 'fpd-qty-restore'), [$this,'field_price_block'], 'fpd_qr_ui_page','fpd_qr_ui_section');
    }

    public function admin_assets($hook) {
        if ($hook !== 'toplevel_page_fpd-qty-restore') return;
        wp_enqueue_style('fpdqr-admin', plugins_url('assets/admin.css', __FILE__), [], '1.3.0');
        wp_enqueue_script('fpdqr-admin', plugins_url('assets/admin.js', __FILE__), ['jquery'], '1.3.0', true);
    }

    public function field_cart_flex_wrap() {
        $o = self::get_ui_options();
        ?>
        <div class="fpdqr-card">
            <div class="fpdqr-field">
                <label class="fpdqr-label"><?php esc_html_e('form.cart flex-wrap', 'fpd-qty-restore'); ?></label>
                <div class="fpdqr-row">
                    <select name="<?php echo esc_attr(self::OPT_UI); ?>[cart_flex_wrap]" class="fpdqr-input">
                        <?php foreach (['','nowrap','wrap','wrap-reverse'] as $opt): ?>
                            <option value="<?php echo esc_attr($opt); ?>" <?php selected($o['cart_flex_wrap'], $opt); ?>>
                                <?php echo $opt === '' ? esc_html__('(no change)','fpd-qty-restore') : esc_html($opt); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <label class="fpdqr-checkbox">
                        <input type="checkbox" name="<?php echo esc_attr(self::OPT_UI); ?>[cart_flex_wrap_important]" value="1" <?php checked(!empty($o['cart_flex_wrap_important'])); ?>>
                        <span>!important</span>
                    </label>
                </div>
                <p class="fpdqr-help"><?php esc_html_e('.div.product form.cart → flex-wrap control (FPD pages only).','fpd-qty-restore'); ?></p>
            </div>
        </div>
        <?php
    }

    public function field_btn_block() {
        $o = self::get_ui_options();
        ?>
        <div class="fpdqr-card">
            <div class="fpdqr-grid">
                <div class="fpdqr-field">
                    <label class="fpdqr-label"><?php esc_html_e('Width', 'fpd-qty-restore'); ?> (<?php esc_html_e('e.g., 100% or 280px', 'fpd-qty-restore'); ?>)</label>
                    <div class="fpdqr-row">
                        <input class="fpdqr-input" type="text" name="<?php echo esc_attr(self::OPT_UI); ?>[btn_width]" value="<?php echo esc_attr($o['btn_width']); ?>" placeholder="100% / 280px" />
                        <label class="fpdqr-checkbox"><input type="checkbox" name="<?php echo esc_attr(self::OPT_UI); ?>[btn_width_important]" value="1" <?php checked(!empty($o['btn_width_important'])); ?>><span>!important</span></label>
                    </div>
                </div>
                <div class="fpdqr-field">
                    <label class="fpdqr-label"><?php esc_html_e('Margin (CSS shorthand)', 'fpd-qty-restore'); ?></label>
                    <div class="fpdqr-row">
                        <input class="fpdqr-input" type="text" name="<?php echo esc_attr(self::OPT_UI); ?>[btn_margin]" value="<?php echo esc_attr($o['btn_margin']); ?>" placeholder="0 12px" />
                        <label class="fpdqr-checkbox"><input type="checkbox" name="<?php echo esc_attr(self::OPT_UI); ?>[btn_margin_important]" value="1" <?php checked(!empty($o['btn_margin_important'])); ?>><span>!important</span></label>
                    </div>
                </div>
                <div class="fpdqr-field fpdqr-col-full">
                    <label class="fpdqr-label"><?php esc_html_e('Display', 'fpd-qty-restore'); ?></label>
                    <div class="fpdqr-row">
                        <select name="<?php echo esc_attr(self::OPT_UI); ?>[btn_display]" class="fpdqr-input">
                            <?php foreach (['','block','inline-block','flex'] as $opt): ?>
                                <option value="<?php echo esc_attr($opt); ?>" <?php selected($o['btn_display'], $opt); ?>>
                                    <?php echo $opt === '' ? esc_html__('(no change)','fpd-qty-restore') : esc_html($opt); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <label class="fpdqr-checkbox"><input type="checkbox" name="<?php echo esc_attr(self::OPT_UI); ?>[btn_display_important]" value="1" <?php checked(!empty($o['btn_display_important'])); ?>><span>!important</span></label>
                    </div>
                </div>
            </div>
            <p class="fpdqr-help"><?php esc_html_e('Controls the Breakdance Add to Cart button element.', 'fpd-qty-restore'); ?></p>
        </div>
        <?php
    }

    public function field_price_block() {
        $o = self::get_ui_options();
        ?>
        <div class="fpdqr-card">
            <div class="fpdqr-grid">
                <div class="fpdqr-field">
                    <label class="fpdqr-label"><?php esc_html_e('Margin (CSS shorthand)', 'fpd-qty-restore'); ?></label>
                    <div class="fpdqr-row">
                        <input class="fpdqr-input" type="text" name="<?php echo esc_attr(self::OPT_UI); ?>[price_margin]" value="<?php echo esc_attr($o['price_margin']); ?>" placeholder="0 0 12px 0" />
                        <label class="fpdqr-checkbox"><input type="checkbox" name="<?php echo esc_attr(self::OPT_UI); ?>[price_margin_important]" value="1" <?php checked(!empty($o['price_margin_important'])); ?>><span>!important</span></label>
                    </div>
                </div>
                <div class="fpdqr-field">
                    <label class="fpdqr-label"><?php esc_html_e('Padding (CSS shorthand)', 'fpd-qty-restore'); ?></label>
                    <div class="fpdqr-row">
                        <input class="fpdqr-input" type="text" name="<?php echo esc_attr(self::OPT_UI); ?>[price_padding]" value="<?php echo esc_attr($o['price_padding']); ?>" placeholder="0 0 8px 0" />
                        <label class="fpdqr-checkbox"><input type="checkbox" name="<?php echo esc_attr(self::OPT_UI); ?>[price_padding_important]" value="1" <?php checked(!empty($o['price_padding_important'])); ?>><span>!important</span></label>
                    </div>
                </div>
            </div>
            <p class="fpdqr-help"><?php esc_html_e('Adjust spacing for the Breakdance product price element.', 'fpd-qty-restore'); ?></p>
        </div>
        <?php
    }

    public function render_settings_page() {
        if (!current_user_can('manage_woocommerce')) return;
        ?>
        <div class="wrap fpdqr-wrap">
            <div class="fpdqr-header">
                <div class="fpdqr-header-content">
                    <div class="fpdqr-logo" aria-hidden="true">
                        <!-- Use your uploaded SVG logo -->
                        <img src="<?php echo esc_url($this->plugin_url . 'assets/img/fpd-qty-icon.png'); ?>" alt="" width="40" height="40" />
                    </div>
                    <div class="fpdqr-header-text">
            <h1><?php esc_html_e('FPD Quantity Restore', 'fpd-qty-restore'); ?></h1>
             <p><?php echo esc_html__('Professional WooCommerce quantity on FPD products', 'breakdance-menu-fix'); ?></p>
                    </div>
                </div>
                <div class="fpdqr-version"><span class="fpdqr-version-badge">v1.3.0</span></div>
            </div>
             <?php if (!$this->is_breakdance_active()): ?>
                <div class="fpdqr-alert fpdqr-alert-error">
                    <div class="fpdqr-alert-icon" aria-hidden="true">⚠️</div>
                    <div class="fpdqr-alert-content">
                        <strong><?php echo esc_html__('Breakdance Required', 'breakdance-menu-fix'); ?></strong>
                        <p><?php echo esc_html__('This plugin requires Breakdance to be installed and activated.', 'breakdance-menu-fix'); ?></p>
                    </div>
                </div>
            <?php endif; ?>
            <div class="fpdqr-columns">
                <div class="fpdqr-col">
                    <form method="post" action="options.php" class="fpdqr-panel">
                        <h2><?php esc_html_e('Global Behavior', 'fpd-qty-restore'); ?></h2>
                        <p class="fpdqr-desc"><?php esc_html_e('Enable quantity restore for all detected Fancy Product Designer products. Per-product checkbox still works.', 'fpd-qty-restore'); ?></p>
                        <?php
                            settings_fields('fpd_qr_settings');
                            do_settings_sections('fpd_qr_settings_page');
                            submit_button(__('Save Global Settings', 'fpd-qty-restore'));
                        ?>
                    </form>

                    <form method="post" action="options.php" class="fpdqr-panel">
                        <h2><?php esc_html_e('Breakdance Layout Controls (FPD pages only)', 'fpd-qty-restore'); ?></h2>
                        <p class="fpdqr-desc"><?php esc_html_e('Styles apply only when the FPD designer is present on the page.', 'fpd-qty-restore'); ?></p>
                        <?php
                            settings_fields('fpd_qr_ui');
                            do_settings_sections('fpd_qr_ui_page');
                            submit_button(__('Save Layout Controls', 'fpd-qty-restore'));
                        ?>
                    </form>
                </div>

                <aside class="fpdqr-col fpdqr-col-aside">
                    <div class="fpdqr-info-card">
                            <div class="fpdqr-info-header">
                                <h3><?php echo esc_html__('Plugin Status', 'breakdance-menu-fix'); ?></h3>
                                <span class="fpdqr-status-badge fpdqr-status-active"><?php echo esc_html__('Active', 'breakdance-menu-fix'); ?></span>
                            </div>
                            <div class="fpdqr-info-content">
                                <div class="fpdqr-stat">
                                    <span class="fpdqr-stat-label"><?php echo esc_html__('Breakdance Status', 'breakdance-menu-fix'); ?></span>
                                    <span class="fpdqr-stat-value <?php echo $this->is_breakdance_active() ? 'fpdqr-status-ok' : 'fpdqr-status-error'; ?>">
                                        <?php echo $this->is_breakdance_active() ? esc_html__('Active', 'breakdance-menu-fix') : esc_html__('Inactive', 'breakdance-menu-fix'); ?>
                                    </span>
                                </div>
                            </div>
                            </div>
                        
                    <div class="fpdqr-panel">
                        <h3><?php esc_html_e('How it works', 'fpd-qty-restore'); ?></h3>
                        <ul class="fpdqr-list">
                            <li><?php esc_html_e('Shows WooCommerce quantity on FPD products.', 'fpd-qty-restore'); ?></li>
                            <li><?php esc_html_e('Duplicates the same design N times (like increasing qty in cart).', 'fpd-qty-restore'); ?></li>
                            <li><?php esc_html_e('Layout controls are scoped to FPD pages only.', 'fpd-qty-restore'); ?></li>
                        </ul>
                        <p class="fpdqr-muted"><?php esc_html_e('Author: eMarketing Cyprus', 'fpd-qty-restore'); ?></p>
                    </div>
                </aside>
            </div>
            <div class="fpdqr-footer">
                <p><?php echo wp_kses_post(__('Made with ❤️ by <a href="https://emarketing.cy" target="_blank" rel="noopener">eMarketing Cyprus</a> for the FPD & Breakdance community', 'breakdance-menu-fix')); ?></p>
            </div>
        </div>
        <?php
    }

    /* ---------------- Frontend behavior ---------------- */

    public function filter_sold_individually($sold, $product) {
        if (!$product || !is_a($product, 'WC_Product')) return $sold;
        
        // Only override for FPD products that allow quantity
        if (self::product_allows_qty($product->get_id())) {
            return false; // Allow quantities for FPD products
        }
        
        
        $product_id = $product->get_id();
        
        // Only process if this is an FPD product
        if (!self::is_fpd_product($product_id)) {
            return $sold; // Return original WooCommerce setting for non-FPD products
        }
        
        // For FPD products, check if quantity is allowed
        if (self::product_allows_qty($product_id)) {
            return false; // Allow quantities for FPD products with quantity enabled
        }
        
        // For FPD products without quantity enabled, respect WooCommerce setting
        return $sold;
    }

    public function filter_quantity_args($args, $product) {
        if (!$product || !is_a($product, 'WC_Product')) return $args;
        if (!self::product_allows_qty($product->get_id())) return $args;

        $calcMax = (int) $product->get_max_purchase_quantity();
        if ($calcMax < 2) $calcMax = 10;
        $args['min_value']   = 1;
        $args['max_value']   = $calcMax;
        $args['input_value'] = max(1, min((int)($args['input_value'] ?? 1), $calcMax));
        return $args;
    }

    public function after_add_to_cart_update_qty($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data) {
        if (!self::product_allows_qty($product_id)) return;
        $posted_qty = isset($_POST['quantity']) ? (int) $_POST['quantity'] : 1;
        if ($posted_qty > 1 && $posted_qty !== (int)$quantity && WC()->cart && $cart_item_key) {
            WC()->cart->set_quantity($cart_item_key, $posted_qty, true);
        }
    }

    public function frontend_assets() {
        if (!is_product()) return;

        // JS flag when FPD designer exists + sync qty hidden/visible
        wp_enqueue_script('fpdqr-frontend', plugins_url('assets/frontend.js', __FILE__), [], '1.3.0', true);

        // Quantity reveal + tidy layout when feature is active for product
        global $product;
        $apply_qty = (is_a($product, 'WC_Product') && self::product_allows_qty($product->get_id()));
        if ($apply_qty) {
            wp_enqueue_style('fpdqr-frontend', plugins_url('assets/frontend.css', __FILE__), [], '1.3.0');
        }

        // Dynamic layout CSS for FPD pages
        $o = self::get_ui_options();
        $rules = [];

        if (!empty($o['cart_flex_wrap'])) {
            $imp = !empty($o['cart_flex_wrap_important']) ? ' !important' : '';
            $rules[] = ".fpd-has-designer .div.product form.cart{flex-wrap: {$o['cart_flex_wrap']}{$imp};}";
        }
        $btn = [];
        if (!empty($o['btn_width']))   $btn[] = "width: {$o['btn_width']}".(!empty($o['btn_width_important'])?' !important':'').";";
        if (!empty($o['btn_margin']))  $btn[] = "margin: {$o['btn_margin']}".(!empty($o['btn_margin_important'])?' !important':'').";";
        if (!empty($o['btn_display'])) $btn[] = "display: {$o['btn_display']}".(!empty($o['btn_display_important'])?' !important':'').";";
        if (!empty($btn)) $rules[] = ".fpd-has-designer .bde-wooproductcartbutton{".implode('', $btn)."}";

        $price = [];
        if (!empty($o['price_margin']))  $price[] = "margin: {$o['price_margin']}".(!empty($o['price_margin_important'])?' !important':'').";";
        if (!empty($o['price_padding'])) $price[] = "padding: {$o['price_padding']}".(!empty($o['price_padding_important'])?' !important':'').";";
        if (!empty($price)) $rules[] = ".fpd-has-designer .bde-wooproductprice{".implode('', $price)."}";

        if (!empty($rules)) {
            wp_register_style('fpdqr-layout', false);
            wp_enqueue_style('fpdqr-layout');
            wp_add_inline_style('fpdqr-layout', implode('', $rules));
        }
    }
}

new FPD_Qty_Restore();

endif;
