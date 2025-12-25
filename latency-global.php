<?php
/**
 * Plugin Name: Latency Global - Uptime Monitoring
 * Plugin URI: https://latency.global
 * Description: Monitor your WordPress site's uptime and performance from 110+ global locations. Get instant alerts, detailed latency analytics, and SSL monitoring.
 * Version: 1.0.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: Latency Global
 * Author URI: https://latency.global
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: latency-global
 * Domain Path: /languages
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('LATENCY_GLOBAL_VERSION', '1.0.0');
define('LATENCY_GLOBAL_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('LATENCY_GLOBAL_PLUGIN_URL', plugin_dir_url(__FILE__));
define('LATENCY_GLOBAL_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('LATENCY_GLOBAL_API_URL', 'https://latency.global/api/v1');

/**
 * Main plugin class
 */
final class Latency_Global {

    /**
     * Plugin instance
     */
    private static $instance = null;

    /**
     * API instance
     */
    public $api;

    /**
     * Get plugin instance
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->includes();
        $this->init_hooks();
    }

    /**
     * Include required files
     */
    private function includes() {
        require_once LATENCY_GLOBAL_PLUGIN_DIR . 'includes/class-latency-api.php';
        require_once LATENCY_GLOBAL_PLUGIN_DIR . 'includes/class-latency-admin.php';
        require_once LATENCY_GLOBAL_PLUGIN_DIR . 'includes/class-latency-widget.php';
        require_once LATENCY_GLOBAL_PLUGIN_DIR . 'includes/class-latency-shortcodes.php';
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Load translations
        add_action('plugins_loaded', [$this, 'load_textdomain']);

        // Initialize components
        add_action('plugins_loaded', [$this, 'init']);

        // Admin bar status indicator
        add_action('admin_bar_menu', [$this, 'admin_bar_status'], 100);

        // Schedule status sync
        add_action('latency_global_sync_status', [$this, 'sync_status']);
    }

    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'latency-global',
            false,
            dirname(LATENCY_GLOBAL_PLUGIN_BASENAME) . '/languages'
        );
    }

    /**
     * Initialize plugin components
     */
    public function init() {
        $this->api = new Latency_Global_API();

        if (is_admin()) {
            new Latency_Global_Admin();
        }

        new Latency_Global_Widget();
        new Latency_Global_Shortcodes();
    }

    /**
     * Add status to admin bar
     */
    public function admin_bar_status($wp_admin_bar) {
        if (!current_user_can('manage_options')) {
            return;
        }

        $api_key = get_option('latency_global_api_key');
        $monitor_id = get_option('latency_global_monitor_id');

        if (!$api_key || !$monitor_id) {
            return;
        }

        // Get cached status
        $status = get_transient('latency_global_status');
        $status_class = 'latency-status-unknown';
        $status_text = __('Unknown', 'latency-global');

        if ($status) {
            if ($status['is_up']) {
                $status_class = 'latency-status-up';
                $status_text = sprintf(__('Up (%dms)', 'latency-global'), $status['latency']);
            } else {
                $status_class = 'latency-status-down';
                $status_text = __('Down', 'latency-global');
            }
        }

        $wp_admin_bar->add_node([
            'id'    => 'latency-global-status',
            'title' => '<span class="ab-icon ' . esc_attr($status_class) . '"></span>' .
                       '<span class="ab-label">' . esc_html($status_text) . '</span>',
            'href'  => admin_url('admin.php?page=latency-global'),
            'meta'  => [
                'title' => __('Latency Global Status', 'latency-global'),
            ],
        ]);
    }

    /**
     * Sync status from API (scheduled task)
     */
    public function sync_status() {
        $monitor_id = get_option('latency_global_monitor_id');

        if (!$monitor_id) {
            return;
        }

        $api = new Latency_Global_API();
        $result = $api->get_monitor($monitor_id);

        if (!is_wp_error($result) && isset($result['data'])) {
            $monitor = $result['data'];
            set_transient('latency_global_status', [
                'is_up'   => $monitor['is_up'] ?? true,
                'latency' => $monitor['last_latency'] ?? 0,
            ], 5 * MINUTE_IN_SECONDS);
        }
    }
}

/**
 * Activation hook
 */
function latency_global_activate() {
    // Set default options
    add_option('latency_global_api_key', '');
    add_option('latency_global_monitor_id', '');
    add_option('latency_global_auto_created', false);
    add_option('latency_global_show_badge', false);

    // Schedule status sync
    if (!wp_next_scheduled('latency_global_sync_status')) {
        wp_schedule_event(time(), 'five_minutes', 'latency_global_sync_status');
    }

    // Add custom cron schedule
    add_filter('cron_schedules', 'latency_global_cron_schedules');
}
register_activation_hook(__FILE__, 'latency_global_activate');

/**
 * Add custom cron schedules
 */
function latency_global_cron_schedules($schedules) {
    $schedules['five_minutes'] = [
        'interval' => 5 * MINUTE_IN_SECONDS,
        'display'  => __('Every Five Minutes', 'latency-global'),
    ];
    return $schedules;
}
add_filter('cron_schedules', 'latency_global_cron_schedules');

/**
 * Deactivation hook
 */
function latency_global_deactivate() {
    // Clear scheduled events
    wp_clear_scheduled_hook('latency_global_sync_status');

    // Clear transients
    delete_transient('latency_global_status');
    delete_transient('latency_global_stats');
}
register_deactivation_hook(__FILE__, 'latency_global_deactivate');

/**
 * Get plugin instance
 */
function latency_global() {
    return Latency_Global::instance();
}

// Initialize the plugin
latency_global();

