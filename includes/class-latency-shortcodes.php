<?php
/**
 * Latency Global Shortcodes
 *
 * @package Latency_Global
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shortcodes class
 */
class Latency_Global_Shortcodes {

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

        // Register shortcodes
        add_shortcode('latency_uptime', [$this, 'uptime_badge']);
        add_shortcode('latency_status', [$this, 'status_indicator']);
        add_shortcode('latency_stats', [$this, 'stats_display']);

        // Enqueue frontend styles
        add_action('wp_enqueue_scripts', [$this, 'maybe_enqueue_styles']);

        // Footer badge
        add_action('wp_footer', [$this, 'maybe_show_footer_badge']);
    }

    /**
     * Maybe enqueue frontend styles
     */
    public function maybe_enqueue_styles() {
        global $post;

        // Check if we need to load styles
        $load_styles = false;

        if (is_a($post, 'WP_Post')) {
            if (has_shortcode($post->post_content, 'latency_uptime') ||
                has_shortcode($post->post_content, 'latency_status') ||
                has_shortcode($post->post_content, 'latency_stats')) {
                $load_styles = true;
            }
        }

        // Also load if footer badge is enabled
        if (get_option('latency_global_show_badge')) {
            $load_styles = true;
        }

        if ($load_styles) {
            wp_enqueue_style(
                'latency-global-frontend',
                LATENCY_GLOBAL_PLUGIN_URL . 'assets/css/frontend.css',
                [],
                LATENCY_GLOBAL_VERSION
            );
        }
    }

    /**
     * Get cached stats
     *
     * @return array|null Stats array or null.
     */
    private function get_stats() {
        $monitor_id = get_option('latency_global_monitor_id');

        if (!$monitor_id) {
            return null;
        }

        $stats = get_transient('latency_global_stats');

        if (!$stats) {
            $stats = $this->api->get_monitor_stats($monitor_id, 7);

            if (!is_wp_error($stats)) {
                set_transient('latency_global_stats', $stats, 5 * MINUTE_IN_SECONDS);
            } else {
                return null;
            }
        }

        return $stats;
    }

    /**
     * Uptime badge shortcode
     * Usage: [latency_uptime style="minimal|badge|detailed"]
     *
     * @param array $atts Shortcode attributes.
     * @return string HTML output.
     */
    public function uptime_badge($atts) {
        $atts = shortcode_atts([
            'style' => 'badge',
            'days'  => 7,
        ], $atts, 'latency_uptime');

        $stats = $this->get_stats();

        if (!$stats || !isset($stats['stats'])) {
            return '';
        }

        $uptime = $stats['stats']['uptime_percentage'] ?? 0;
        $status_class = $uptime >= 99 ? 'good' : ($uptime >= 95 ? 'warning' : 'bad');

        ob_start();

        switch ($atts['style']) {
            case 'minimal':
                ?>
                <span class="latency-uptime-minimal <?php echo esc_attr($status_class); ?>">
                    <?php echo esc_html(number_format($uptime, 2)); ?>% uptime
                </span>
                <?php
                break;

            case 'detailed':
                $avg_latency = $stats['stats']['avg_latency'] ?? 0;
                ?>
                <div class="latency-uptime-detailed">
                    <div class="latency-stat">
                        <span class="value <?php echo esc_attr($status_class); ?>">
                            <?php echo esc_html(number_format($uptime, 2)); ?>%
                        </span>
                        <span class="label"><?php esc_html_e('Uptime', 'latency-global'); ?></span>
                    </div>
                    <div class="latency-stat">
                        <span class="value"><?php echo esc_html(number_format($avg_latency)); ?>ms</span>
                        <span class="label"><?php esc_html_e('Response', 'latency-global'); ?></span>
                    </div>
                </div>
                <?php
                break;

            case 'badge':
            default:
                ?>
                <a href="https://latency.global" target="_blank" rel="noopener" class="latency-uptime-badge <?php echo esc_attr($status_class); ?>">
                    <span class="latency-badge-icon">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 12h-4l-3 9L9 3l-3 9H2"/>
                        </svg>
                    </span>
                    <span class="latency-badge-text">
                        <?php echo esc_html(number_format($uptime, 1)); ?>% uptime
                    </span>
                    <span class="latency-badge-brand">Latency Global</span>
                </a>
                <?php
                break;
        }

        return ob_get_clean();
    }

    /**
     * Status indicator shortcode
     * Usage: [latency_status]
     *
     * @param array $atts Shortcode attributes.
     * @return string HTML output.
     */
    public function status_indicator($atts) {
        $atts = shortcode_atts([
            'show_text' => 'yes',
        ], $atts, 'latency_status');

        $status = get_transient('latency_global_status');
        $is_up = $status && isset($status['is_up']) ? $status['is_up'] : true;

        ob_start();
        ?>
        <span class="latency-status-indicator <?php echo $is_up ? 'status-up' : 'status-down'; ?>">
            <span class="indicator-dot"></span>
            <?php if ('yes' === $atts['show_text']) : ?>
                <span class="indicator-text">
                    <?php echo $is_up ? esc_html__('Online', 'latency-global') : esc_html__('Offline', 'latency-global'); ?>
                </span>
            <?php endif; ?>
        </span>
        <?php
        return ob_get_clean();
    }

    /**
     * Stats display shortcode
     * Usage: [latency_stats]
     *
     * @param array $atts Shortcode attributes.
     * @return string HTML output.
     */
    public function stats_display($atts) {
        $atts = shortcode_atts([
            'layout' => 'horizontal', // horizontal, vertical, grid
        ], $atts, 'latency_stats');

        $stats = $this->get_stats();

        if (!$stats || !isset($stats['stats'])) {
            return '';
        }

        $uptime = $stats['stats']['uptime_percentage'] ?? 0;
        $avg_latency = $stats['stats']['avg_latency'] ?? 0;
        $min_latency = $stats['stats']['min_latency'] ?? 0;
        $max_latency = $stats['stats']['max_latency'] ?? 0;
        $total_checks = $stats['stats']['total_checks'] ?? 0;

        ob_start();
        ?>
        <div class="latency-stats-display layout-<?php echo esc_attr($atts['layout']); ?>">
            <div class="latency-stat-item">
                <span class="stat-value uptime"><?php echo esc_html(number_format($uptime, 2)); ?>%</span>
                <span class="stat-label"><?php esc_html_e('Uptime', 'latency-global'); ?></span>
            </div>
            <div class="latency-stat-item">
                <span class="stat-value"><?php echo esc_html(number_format($avg_latency)); ?>ms</span>
                <span class="stat-label"><?php esc_html_e('Avg Response', 'latency-global'); ?></span>
            </div>
            <div class="latency-stat-item">
                <span class="stat-value"><?php echo esc_html(number_format($min_latency)); ?>ms</span>
                <span class="stat-label"><?php esc_html_e('Min', 'latency-global'); ?></span>
            </div>
            <div class="latency-stat-item">
                <span class="stat-value"><?php echo esc_html(number_format($max_latency)); ?>ms</span>
                <span class="stat-label"><?php esc_html_e('Max', 'latency-global'); ?></span>
            </div>
            <div class="latency-stat-item">
                <span class="stat-value"><?php echo esc_html(number_format($total_checks)); ?></span>
                <span class="stat-label"><?php esc_html_e('Checks', 'latency-global'); ?></span>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Maybe show footer badge
     */
    public function maybe_show_footer_badge() {
        if (!get_option('latency_global_show_badge')) {
            return;
        }

        if (!get_option('latency_global_monitor_id')) {
            return;
        }

        echo '<div class="latency-footer-badge">';
        echo $this->uptime_badge(['style' => 'badge']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '</div>';
    }
}

