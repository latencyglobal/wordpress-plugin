<?php
/**
 * Admin page template
 *
 * @package Latency_Global
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap latency-global-admin">
    <h1>
        <span class="dashicons dashicons-chart-line"></span>
        <?php esc_html_e('Latency Global', 'latency-global'); ?>
    </h1>

    <div class="latency-admin-container">
        <!-- Left Column: Settings -->
        <div class="latency-admin-main">
            <!-- API Settings Card -->
            <div class="latency-card">
                <form method="post" action="options.php">
                    <?php
                    settings_fields('latency_global_settings');
                    do_settings_sections('latency-global');
                    submit_button(__('Save Settings', 'latency-global'));
                    ?>
                </form>
            </div>

            <?php if ($api_key) : ?>
            <!-- Monitor Card -->
            <div class="latency-card">
                <h2><?php esc_html_e('Site Monitor', 'latency-global'); ?></h2>

                <?php if ($monitor_id && $monitor) : ?>
                    <!-- Monitor exists -->
                    <div class="latency-monitor-info">
                        <div class="monitor-header">
                            <div class="monitor-status <?php echo ($monitor['is_up'] ?? true) ? 'status-up' : 'status-down'; ?>">
                                <span class="status-dot"></span>
                                <span class="status-text">
                                    <?php echo ($monitor['is_up'] ?? true) ? esc_html__('Online', 'latency-global') : esc_html__('Offline', 'latency-global'); ?>
                                </span>
                            </div>
                            <div class="monitor-actions">
                                <button type="button" id="latency-refresh-stats" class="button button-small">
                                    <span class="dashicons dashicons-update"></span>
                                    <?php esc_html_e('Refresh', 'latency-global'); ?>
                                </button>
                                <a href="https://latency.global/dashboard/monitors/<?php echo esc_attr($monitor_id); ?>"
                                   target="_blank"
                                   class="button button-primary button-small">
                                    <?php esc_html_e('View Dashboard', 'latency-global'); ?>
                                    <span class="dashicons dashicons-external"></span>
                                </a>
                            </div>
                        </div>

                        <table class="latency-monitor-details">
                            <tr>
                                <th><?php esc_html_e('Name', 'latency-global'); ?></th>
                                <td><?php echo esc_html($monitor['name'] ?? ''); ?></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('URL', 'latency-global'); ?></th>
                                <td><code><?php echo esc_html($monitor['url'] ?? ''); ?></code></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Type', 'latency-global'); ?></th>
                                <td><?php echo esc_html(strtoupper($monitor['type'] ?? 'HTTPS')); ?></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Interval', 'latency-global'); ?></th>
                                <td><?php echo esc_html(($monitor['interval'] ?? 60) . ' ' . __('seconds', 'latency-global')); ?></td>
                            </tr>
                        </table>
                    </div>

                    <?php if ($stats && !is_wp_error($stats) && isset($stats['stats'])) : ?>
                    <!-- Stats -->
                    <div class="latency-stats-grid" id="latency-stats-container">
                        <div class="latency-stat-box">
                            <span class="stat-value <?php echo ($stats['stats']['uptime_percentage'] ?? 0) >= 99 ? 'good' : 'warning'; ?>">
                                <?php echo esc_html(number_format($stats['stats']['uptime_percentage'] ?? 0, 2)); ?>%
                            </span>
                            <span class="stat-label"><?php esc_html_e('Uptime (7 days)', 'latency-global'); ?></span>
                        </div>
                        <div class="latency-stat-box">
                            <span class="stat-value">
                                <?php echo esc_html(number_format($stats['stats']['avg_latency'] ?? 0)); ?>ms
                            </span>
                            <span class="stat-label"><?php esc_html_e('Avg Latency', 'latency-global'); ?></span>
                        </div>
                        <div class="latency-stat-box">
                            <span class="stat-value">
                                <?php echo esc_html(number_format($stats['stats']['min_latency'] ?? 0)); ?>ms
                            </span>
                            <span class="stat-label"><?php esc_html_e('Min Latency', 'latency-global'); ?></span>
                        </div>
                        <div class="latency-stat-box">
                            <span class="stat-value">
                                <?php echo esc_html(number_format($stats['stats']['max_latency'] ?? 0)); ?>ms
                            </span>
                            <span class="stat-label"><?php esc_html_e('Max Latency', 'latency-global'); ?></span>
                        </div>
                        <div class="latency-stat-box">
                            <span class="stat-value">
                                <?php echo esc_html(number_format($stats['stats']['total_checks'] ?? 0)); ?>
                            </span>
                            <span class="stat-label"><?php esc_html_e('Total Checks', 'latency-global'); ?></span>
                        </div>
                        <div class="latency-stat-box">
                            <span class="stat-value">
                                <?php echo esc_html(number_format($stats['stats']['successful_checks'] ?? 0)); ?>
                            </span>
                            <span class="stat-label"><?php esc_html_e('Successful', 'latency-global'); ?></span>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Delete Monitor -->
                    <div class="latency-danger-zone">
                        <h4><?php esc_html_e('Danger Zone', 'latency-global'); ?></h4>
                        <p class="description">
                            <?php esc_html_e('Remove this monitor from your Latency Global account.', 'latency-global'); ?>
                        </p>
                        <button type="button" id="latency-delete-monitor" class="button button-link-delete">
                            <?php esc_html_e('Delete Monitor', 'latency-global'); ?>
                        </button>
                    </div>

                <?php else : ?>
                    <!-- No monitor yet -->
                    <div class="latency-no-monitor">
                        <p>
                            <?php esc_html_e('No monitor configured for this site yet.', 'latency-global'); ?>
                        </p>
                        <p class="description">
                            <?php printf(
                                /* translators: %s: site URL */
                                esc_html__('Click the button below to create an HTTPS monitor for %s', 'latency-global'),
                                '<code>' . esc_html(home_url('/')) . '</code>'
                            ); ?>
                        </p>
                        <button type="button" id="latency-create-monitor" class="button button-primary button-hero">
                            <span class="dashicons dashicons-plus-alt2"></span>
                            <?php esc_html_e('Create Monitor for This Site', 'latency-global'); ?>
                        </button>
                    </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Right Column: Sidebar -->
        <div class="latency-admin-sidebar">
            <!-- Quick Links -->
            <div class="latency-card">
                <h3><?php esc_html_e('Quick Links', 'latency-global'); ?></h3>
                <ul class="latency-quick-links">
                    <li>
                        <a href="https://latency.global/dashboard" target="_blank">
                            <span class="dashicons dashicons-dashboard"></span>
                            <?php esc_html_e('Full Dashboard', 'latency-global'); ?>
                        </a>
                    </li>
                    <li>
                        <a href="https://latency.global/api-tokens" target="_blank">
                            <span class="dashicons dashicons-admin-network"></span>
                            <?php esc_html_e('Manage API Keys', 'latency-global'); ?>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=latency-global-tools')); ?>">
                            <span class="dashicons dashicons-admin-tools"></span>
                            <?php esc_html_e('Network Tools', 'latency-global'); ?>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Shortcodes -->
            <div class="latency-card">
                <h3><?php esc_html_e('Shortcodes', 'latency-global'); ?></h3>
                <p class="description">
                    <?php esc_html_e('Display uptime information on your site:', 'latency-global'); ?>
                </p>
                <div class="latency-shortcode-list">
                    <div class="shortcode-item">
                        <code>[latency_uptime]</code>
                        <span class="shortcode-desc"><?php esc_html_e('Uptime badge', 'latency-global'); ?></span>
                    </div>
                    <div class="shortcode-item">
                        <code>[latency_status]</code>
                        <span class="shortcode-desc"><?php esc_html_e('Status indicator', 'latency-global'); ?></span>
                    </div>
                    <div class="shortcode-item">
                        <code>[latency_stats]</code>
                        <span class="shortcode-desc"><?php esc_html_e('Full stats display', 'latency-global'); ?></span>
                    </div>
                </div>
            </div>

            <!-- Support -->
            <div class="latency-card">
                <h3><?php esc_html_e('Need Help?', 'latency-global'); ?></h3>
                <p>
                    <?php esc_html_e('Have questions or need assistance? Get in touch with us.', 'latency-global'); ?>
                </p>
                <a href="https://latency.global/contact" target="_blank" class="button button-primary">
                    <?php esc_html_e('Contact Support', 'latency-global'); ?>
                </a>
            </div>
        </div>
    </div>
</div>

