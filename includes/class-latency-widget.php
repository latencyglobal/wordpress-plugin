<?php
/**
 * Latency Global Dashboard Widget
 *
 * @package Latency_Global
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Dashboard widget class
 */
class Latency_Global_Widget {

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

        // Register dashboard widget
        add_action('wp_dashboard_setup', [$this, 'register_dashboard_widget']);

        // Enqueue dashboard styles
        add_action('admin_enqueue_scripts', [$this, 'enqueue_dashboard_styles']);
    }

    /**
     * Register the dashboard widget
     */
    public function register_dashboard_widget() {
        // Only show if configured
        if (!get_option('latency_global_api_key') || !get_option('latency_global_monitor_id')) {
            return;
        }

        wp_add_dashboard_widget(
            'latency_global_dashboard_widget',
            __('Latency Global - Site Status', 'latency-global'),
            [$this, 'render_widget'],
            null,
            null,
            'side',
            'high'
        );
    }

    /**
     * Enqueue dashboard styles
     *
     * @param string $hook Current page hook.
     */
    public function enqueue_dashboard_styles($hook) {
        if ('index.php' !== $hook) {
            return;
        }

        wp_enqueue_style(
            'latency-global-dashboard',
            LATENCY_GLOBAL_PLUGIN_URL . 'assets/css/dashboard-widget.css',
            [],
            LATENCY_GLOBAL_VERSION
        );
    }

    /**
     * Render the dashboard widget
     */
    public function render_widget() {
        $monitor_id = get_option('latency_global_monitor_id');

        if (!$monitor_id) {
            echo '<p>' . esc_html__('No monitor configured.', 'latency-global') . '</p>';
            return;
        }

        // Get cached stats
        $stats = get_transient('latency_global_stats');

        if (!$stats) {
            $stats = $this->api->get_monitor_stats($monitor_id, 7);

            if (!is_wp_error($stats)) {
                set_transient('latency_global_stats', $stats, 5 * MINUTE_IN_SECONDS);
            }
        }

        // Get current status
        $status = get_transient('latency_global_status');

        if (is_wp_error($stats)) {
            echo '<p class="latency-error">' . esc_html($stats->get_error_message()) . '</p>';
            return;
        }

        $uptime = isset($stats['stats']['uptime_percentage']) ? $stats['stats']['uptime_percentage'] : 0;
        $avg_latency = isset($stats['stats']['avg_latency']) ? $stats['stats']['avg_latency'] : 0;
        $total_checks = isset($stats['stats']['total_checks']) ? $stats['stats']['total_checks'] : 0;
        $is_up = $status && isset($status['is_up']) ? $status['is_up'] : ($uptime >= 99);
        ?>

        <div class="latency-widget">
            <!-- Current Status -->
            <div class="latency-widget-status <?php echo $is_up ? 'status-up' : 'status-down'; ?>">
                <span class="status-indicator"></span>
                <span class="status-text">
                    <?php echo $is_up ? esc_html__('Online', 'latency-global') : esc_html__('Offline', 'latency-global'); ?>
                </span>
            </div>

            <!-- Stats Grid -->
            <div class="latency-widget-stats">
                <div class="stat-item">
                    <span class="stat-value <?php echo $uptime >= 99 ? 'good' : ($uptime >= 95 ? 'warning' : 'bad'); ?>">
                        <?php echo esc_html(number_format($uptime, 2)); ?>%
                    </span>
                    <span class="stat-label"><?php esc_html_e('Uptime', 'latency-global'); ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-value">
                        <?php echo esc_html(number_format($avg_latency)); ?>ms
                    </span>
                    <span class="stat-label"><?php esc_html_e('Avg Latency', 'latency-global'); ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-value">
                        <?php echo esc_html(number_format($total_checks)); ?>
                    </span>
                    <span class="stat-label"><?php esc_html_e('Checks (7d)', 'latency-global'); ?></span>
                </div>
            </div>

            <!-- Actions -->
            <div class="latency-widget-actions">
                <a href="<?php echo esc_url(admin_url('admin.php?page=latency-global')); ?>" class="button button-small">
                    <?php esc_html_e('Settings', 'latency-global'); ?>
                </a>
                <a href="https://latency.global/dashboard/monitors/<?php echo esc_attr($monitor_id); ?>"
                   target="_blank"
                   class="button button-primary button-small">
                    <?php esc_html_e('View Dashboard', 'latency-global'); ?>
                </a>
            </div>
        </div>

        <?php
    }
}

