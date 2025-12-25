<?php
/**
 * Latency Global Admin
 *
 * @package Latency_Global
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin functionality
 */
class Latency_Global_Admin {

    /**
     * API instance
     *
     * @var Latency_Global_API
     */
    private $api;

    /**
     * Constructor
     */
    public function __construct() {
        $this->api = new Latency_Global_API();

        // Admin menu
        add_action('admin_menu', [$this, 'add_admin_menu']);

        // Register settings
        add_action('admin_init', [$this, 'register_settings']);

        // Enqueue assets
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);

        // Admin notices
        add_action('admin_notices', [$this, 'show_notices']);

        // AJAX handlers
        add_action('wp_ajax_latency_global_create_monitor', [$this, 'ajax_create_monitor']);
        add_action('wp_ajax_latency_global_delete_monitor', [$this, 'ajax_delete_monitor']);
        add_action('wp_ajax_latency_global_refresh_stats', [$this, 'ajax_refresh_stats']);
        add_action('wp_ajax_latency_global_verify_api_key', [$this, 'ajax_verify_api_key']);

        // Network tools AJAX handlers
        add_action('wp_ajax_latency_global_run_ping', [$this, 'ajax_run_ping']);
        add_action('wp_ajax_latency_global_run_http', [$this, 'ajax_run_http']);
        add_action('wp_ajax_latency_global_run_dns', [$this, 'ajax_run_dns']);

        // Plugin action links
        add_filter('plugin_action_links_' . LATENCY_GLOBAL_PLUGIN_BASENAME, [$this, 'plugin_action_links']);
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Latency Global', 'latency-global'),
            __('Latency Global', 'latency-global'),
            'manage_options',
            'latency-global',
            [$this, 'render_admin_page'],
            'dashicons-chart-line',
            80
        );

        add_submenu_page(
            'latency-global',
            __('Settings', 'latency-global'),
            __('Settings', 'latency-global'),
            'manage_options',
            'latency-global',
            [$this, 'render_admin_page']
        );

        add_submenu_page(
            'latency-global',
            __('Tools', 'latency-global'),
            __('Tools', 'latency-global'),
            'manage_options',
            'latency-global-tools',
            [$this, 'render_tools_page']
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('latency_global_settings', 'latency_global_api_key', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ]);

        register_setting('latency_global_settings', 'latency_global_show_badge', [
            'type'              => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default'           => false,
        ]);

        // Settings sections
        add_settings_section(
            'latency_global_api_section',
            __('API Configuration', 'latency-global'),
            [$this, 'render_api_section'],
            'latency-global'
        );

        add_settings_section(
            'latency_global_display_section',
            __('Display Settings', 'latency-global'),
            [$this, 'render_display_section'],
            'latency-global'
        );

        // Settings fields
        add_settings_field(
            'latency_global_api_key',
            __('API Key', 'latency-global'),
            [$this, 'render_api_key_field'],
            'latency-global',
            'latency_global_api_section'
        );

        add_settings_field(
            'latency_global_show_badge',
            __('Show Uptime Badge', 'latency-global'),
            [$this, 'render_show_badge_field'],
            'latency-global',
            'latency_global_display_section'
        );
    }

    /**
     * Render API section description
     */
    public function render_api_section() {
        echo '<p>' . sprintf(
            /* translators: %1$s: opening link tag, %2$s: closing link tag */
            esc_html__('Enter your Latency Global API key. You can get one from your %1$sdashboard%2$s.', 'latency-global'),
            '<a href="https://latency.global/api-tokens" target="_blank">',
            '</a>'
        ) . '</p>';
    }

    /**
     * Render display section description
     */
    public function render_display_section() {
        echo '<p>' . esc_html__('Configure how monitoring information is displayed on your site.', 'latency-global') . '</p>';
    }

    /**
     * Render API key field
     */
    public function render_api_key_field() {
        $api_key = get_option('latency_global_api_key', '');
        $is_valid = !empty($api_key);
        ?>
        <div class="latency-api-key-field">
            <input type="password"
                   id="latency_global_api_key"
                   name="latency_global_api_key"
                   value="<?php echo esc_attr($api_key); ?>"
                   class="regular-text"
                   autocomplete="off">
            <button type="button" class="button" id="latency-toggle-api-key">
                <?php esc_html_e('Show', 'latency-global'); ?>
            </button>
            <?php if ($is_valid) : ?>
                <button type="button" class="button" id="latency-verify-api-key">
                    <?php esc_html_e('Verify', 'latency-global'); ?>
                </button>
            <?php endif; ?>
            <span id="latency-api-key-status"></span>
        </div>
        <p class="description">
            <?php printf(
                /* translators: %s: API key prefix */
                esc_html__('Your API key starts with %s', 'latency-global'),
                '<code>lat_</code>'
            ); ?>
        </p>
        <?php
    }

    /**
     * Render show badge field
     */
    public function render_show_badge_field() {
        $show_badge = get_option('latency_global_show_badge', false);
        ?>
        <label>
            <input type="checkbox"
                   name="latency_global_show_badge"
                   value="1"
                   <?php checked($show_badge, true); ?>>
            <?php esc_html_e('Display uptime badge in site footer', 'latency-global'); ?>
        </label>
        <p class="description">
            <?php esc_html_e('You can also use the shortcode [latency_uptime] to display the badge anywhere.', 'latency-global'); ?>
        </p>
        <?php
    }

    /**
     * Enqueue admin assets
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_assets($hook) {
        // Only load on our plugin pages
        if (strpos($hook, 'latency-global') === false) {
            return;
        }

        wp_enqueue_style(
            'latency-global-admin',
            LATENCY_GLOBAL_PLUGIN_URL . 'assets/css/admin.css',
            [],
            LATENCY_GLOBAL_VERSION
        );

        wp_enqueue_script(
            'latency-global-admin',
            LATENCY_GLOBAL_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            LATENCY_GLOBAL_VERSION,
            true
        );

        wp_localize_script('latency-global-admin', 'latencyGlobal', [
            'ajaxUrl'  => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('latency_global_nonce'),
            'siteUrl'  => home_url('/'),
            'siteName' => get_bloginfo('name'),
            'i18n'     => [
                'creating'      => __('Creating monitor...', 'latency-global'),
                'created'       => __('Monitor created successfully!', 'latency-global'),
                'deleting'      => __('Deleting monitor...', 'latency-global'),
                'deleted'       => __('Monitor deleted.', 'latency-global'),
                'verifying'     => __('Verifying...', 'latency-global'),
                'valid'         => __('Valid!', 'latency-global'),
                'invalid'       => __('Invalid API key', 'latency-global'),
                'error'         => __('An error occurred', 'latency-global'),
                'confirmDelete' => __('Are you sure you want to delete this monitor?', 'latency-global'),
            ],
        ]);
    }

    /**
     * Show admin notices
     */
    public function show_notices() {
        // Only show to admins
        if (!current_user_can('manage_options')) {
            return;
        }

        $api_key = get_option('latency_global_api_key');
        $screen = get_current_screen();

        // Don't show on our own settings page
        if ($screen && strpos($screen->id, 'latency-global') !== false) {
            return;
        }

        // Show setup notice if API key not configured
        if (empty($api_key)) {
            $settings_url = admin_url('admin.php?page=latency-global');
            ?>
            <div class="notice notice-warning is-dismissible">
                <p>
                    <strong><?php esc_html_e('Latency Global:', 'latency-global'); ?></strong>
                    <?php printf(
                        /* translators: %1$s: opening link tag, %2$s: closing link tag */
                        esc_html__('Please %1$sconfigure your API key%2$s to start monitoring your site.', 'latency-global'),
                        '<a href="' . esc_url($settings_url) . '">',
                        '</a>'
                    ); ?>
                </p>
            </div>
            <?php
        }
    }

    /**
     * Render main admin page
     */
    public function render_admin_page() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'latency-global'));
        }

        $api_key = get_option('latency_global_api_key');
        $monitor_id = get_option('latency_global_monitor_id');
        $stats = null;
        $monitor = null;

        if ($api_key && $monitor_id) {
            // Try to get cached stats first
            $stats = get_transient('latency_global_stats');

            if (!$stats) {
                $stats = $this->api->get_monitor_stats($monitor_id, 7);

                if (!is_wp_error($stats)) {
                    set_transient('latency_global_stats', $stats, 5 * MINUTE_IN_SECONDS);
                }
            }

            // Get monitor details
            $monitor_response = $this->api->get_monitor($monitor_id);
            if (!is_wp_error($monitor_response) && isset($monitor_response['data'])) {
                $monitor = $monitor_response['data'];
            }
        }

        include LATENCY_GLOBAL_PLUGIN_DIR . 'templates/admin-page.php';
    }

    /**
     * Render tools page
     */
    public function render_tools_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'latency-global'));
        }

        include LATENCY_GLOBAL_PLUGIN_DIR . 'templates/tools-page.php';
    }

    /**
     * AJAX: Create monitor
     */
    public function ajax_create_monitor() {
        check_ajax_referer('latency_global_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'latency-global')]);
        }

        $result = $this->api->create_site_monitor();

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        if (isset($result['data']['id'])) {
            update_option('latency_global_monitor_id', $result['data']['id']);
            update_option('latency_global_auto_created', true);

            // Clear any cached stats
            delete_transient('latency_global_stats');
            delete_transient('latency_global_status');

            wp_send_json_success([
                'message'    => __('Monitor created successfully!', 'latency-global'),
                'monitor_id' => $result['data']['id'],
                'monitor'    => $result['data'],
            ]);
        }

        wp_send_json_error(['message' => __('Failed to create monitor', 'latency-global')]);
    }

    /**
     * AJAX: Delete monitor
     */
    public function ajax_delete_monitor() {
        check_ajax_referer('latency_global_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'latency-global')]);
        }

        $monitor_id = get_option('latency_global_monitor_id');

        if (!$monitor_id) {
            wp_send_json_error(['message' => __('No monitor configured', 'latency-global')]);
        }

        $result = $this->api->delete_monitor($monitor_id);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        // Clear options
        delete_option('latency_global_monitor_id');
        delete_option('latency_global_auto_created');
        delete_transient('latency_global_stats');
        delete_transient('latency_global_status');

        wp_send_json_success(['message' => __('Monitor deleted', 'latency-global')]);
    }

    /**
     * AJAX: Refresh stats
     */
    public function ajax_refresh_stats() {
        check_ajax_referer('latency_global_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'latency-global')]);
        }

        $monitor_id = get_option('latency_global_monitor_id');

        if (!$monitor_id) {
            wp_send_json_error(['message' => __('No monitor configured', 'latency-global')]);
        }

        // Clear cache and get fresh stats
        delete_transient('latency_global_stats');

        $stats = $this->api->get_monitor_stats($monitor_id, 7);

        if (is_wp_error($stats)) {
            wp_send_json_error(['message' => $stats->get_error_message()]);
        }

        set_transient('latency_global_stats', $stats, 5 * MINUTE_IN_SECONDS);

        wp_send_json_success($stats);
    }

    /**
     * AJAX: Verify API key
     */
    public function ajax_verify_api_key() {
        check_ajax_referer('latency_global_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'latency-global')]);
        }

        $result = $this->api->verify_api_key();

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success(['message' => __('API key is valid!', 'latency-global')]);
    }

    /**
     * AJAX: Run ping tool
     */
    public function ajax_run_ping() {
        check_ajax_referer('latency_global_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'latency-global')]);
        }

        $params = isset($_POST['params']) ? array_map('sanitize_text_field', $_POST['params']) : [];

        if (empty($params['target'])) {
            wp_send_json_error(['message' => __('Target is required', 'latency-global')]);
        }

        $result = $this->api->probe_ping($params['target'], [
            'packets' => isset($params['packets']) ? intval($params['packets']) : 3,
        ]);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success($result);
    }

    /**
     * AJAX: Run HTTP/HTTPS tool
     */
    public function ajax_run_http() {
        check_ajax_referer('latency_global_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'latency-global')]);
        }

        $params = isset($_POST['params']) ? array_map('sanitize_text_field', $_POST['params']) : [];

        if (empty($params['url'])) {
            wp_send_json_error(['message' => __('URL is required', 'latency-global')]);
        }

        $url = esc_url_raw($params['url']);

        $result = $this->api->probe_http($url);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        // Format output for display
        $output = '';
        if (isset($result['meta'])) {
            $meta = $result['meta'];
            $output .= "Status: " . ($meta['status'] ?? 'N/A') . "\n";
            $output .= "Total Latency: " . ($meta['latency_ms'] ?? 'N/A') . " ms\n";
            $output .= "\n--- Timing Breakdown ---\n";
            $output .= "DNS Lookup: " . ($meta['t_dns_ms'] ?? 'N/A') . " ms\n";
            $output .= "TCP Connect: " . ($meta['t_connect_ms'] ?? 'N/A') . " ms\n";
            $output .= "TLS Handshake: " . ($meta['t_tls_ms'] ?? 'N/A') . " ms\n";
            $output .= "Time to First Byte: " . ($meta['t_ttfb_ms'] ?? 'N/A') . " ms\n";

            if (isset($meta['tls_version'])) {
                $output .= "\n--- TLS Info ---\n";
                $output .= "TLS Version: " . ($meta['tls_version'] ?? 'N/A') . "\n";
                $output .= "Cipher: " . ($meta['tls_cipher'] ?? 'N/A') . "\n";
            }

            $output .= "\nBody Length: " . ($meta['body_len'] ?? 'N/A') . " bytes\n";
        }

        $result['output'] = $output ?: ($result['stdout'] ?? json_encode($result, JSON_PRETTY_PRINT));

        wp_send_json_success($result);
    }

    /**
     * AJAX: Run DNS tool
     */
    public function ajax_run_dns() {
        check_ajax_referer('latency_global_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'latency-global')]);
        }

        $params = isset($_POST['params']) ? array_map('sanitize_text_field', $_POST['params']) : [];

        if (empty($params['name'])) {
            wp_send_json_error(['message' => __('Domain name is required', 'latency-global')]);
        }

        $result = $this->api->probe_dns(
            sanitize_text_field($params['name']),
            isset($params['type']) ? strtoupper(sanitize_text_field($params['type'])) : 'A'
        );

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success($result);
    }

    /**
     * Add plugin action links
     *
     * @param array $links Existing links.
     * @return array Modified links.
     */
    public function plugin_action_links($links) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url('admin.php?page=latency-global'),
            __('Settings', 'latency-global')
        );

        array_unshift($links, $settings_link);

        return $links;
    }
}

