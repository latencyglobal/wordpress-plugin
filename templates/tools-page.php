<?php
/**
 * Tools page template
 *
 * @package Latency_Global
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$api = new Latency_Global_API();
$has_api_key = $api->has_api_key();
?>
<div class="wrap latency-global-tools">
    <h1>
        <span class="dashicons dashicons-admin-tools"></span>
        <?php esc_html_e('Network Tools', 'latency-global'); ?>
    </h1>

    <?php if (!$has_api_key) : ?>
        <div class="notice notice-warning">
            <p>
                <?php printf(
                    /* translators: %1$s: opening link tag, %2$s: closing link tag */
                    esc_html__('Please %1$sconfigure your API key%2$s first to use network tools.', 'latency-global'),
                    '<a href="' . esc_url(admin_url('admin.php?page=latency-global')) . '">',
                    '</a>'
                ); ?>
            </p>
        </div>
    <?php else : ?>

    <p class="description">
        <?php esc_html_e('Run network diagnostics from Latency Global\'s worldwide probe network.', 'latency-global'); ?>
    </p>

    <div class="latency-tools-container">
        <!-- Ping Tool -->
        <div class="latency-card latency-tool-card">
            <h2>
                <span class="dashicons dashicons-admin-site"></span>
                <?php esc_html_e('Ping Test', 'latency-global'); ?>
            </h2>
            <p><?php esc_html_e('Test connectivity to a host from global locations.', 'latency-global'); ?></p>
            <form id="latency-ping-form" class="latency-tool-form">
                <div class="form-row">
                    <label for="ping-target"><?php esc_html_e('Target (IP or hostname)', 'latency-global'); ?></label>
                    <input type="text" id="ping-target" name="target" value="<?php echo esc_attr(wp_parse_url(home_url(), PHP_URL_HOST)); ?>" required>
                </div>
                <div class="form-row">
                    <label for="ping-packets"><?php esc_html_e('Packets', 'latency-global'); ?></label>
                    <select id="ping-packets" name="packets">
                        <option value="3">3</option>
                        <option value="5">5</option>
                        <option value="10">10</option>
                    </select>
                </div>
                <button type="submit" class="button button-primary">
                    <?php esc_html_e('Run Ping', 'latency-global'); ?>
                </button>
            </form>
            <div class="latency-tool-output" id="ping-output"></div>
        </div>

        <!-- HTTP Test -->
        <div class="latency-card latency-tool-card">
            <h2>
                <span class="dashicons dashicons-admin-links"></span>
                <?php esc_html_e('HTTP/HTTPS Test', 'latency-global'); ?>
            </h2>
            <p><?php esc_html_e('Test HTTP response and get detailed timing breakdown.', 'latency-global'); ?></p>
            <form id="latency-http-form" class="latency-tool-form">
                <div class="form-row">
                    <label for="http-url"><?php esc_html_e('URL', 'latency-global'); ?></label>
                    <input type="url" id="http-url" name="url" value="<?php echo esc_attr(home_url('/')); ?>" required>
                </div>
                <button type="submit" class="button button-primary">
                    <?php esc_html_e('Run Test', 'latency-global'); ?>
                </button>
            </form>
            <div class="latency-tool-output" id="http-output"></div>
        </div>

        <!-- DNS Test -->
        <div class="latency-card latency-tool-card">
            <h2>
                <span class="dashicons dashicons-networking"></span>
                <?php esc_html_e('DNS Lookup', 'latency-global'); ?>
            </h2>
            <p><?php esc_html_e('Query DNS records from different locations.', 'latency-global'); ?></p>
            <form id="latency-dns-form" class="latency-tool-form">
                <div class="form-row">
                    <label for="dns-name"><?php esc_html_e('Domain', 'latency-global'); ?></label>
                    <input type="text" id="dns-name" name="name" value="<?php echo esc_attr(wp_parse_url(home_url(), PHP_URL_HOST)); ?>" required>
                </div>
                <div class="form-row">
                    <label for="dns-type"><?php esc_html_e('Record Type', 'latency-global'); ?></label>
                    <select id="dns-type" name="type">
                        <option value="A">A</option>
                        <option value="AAAA">AAAA</option>
                        <option value="CNAME">CNAME</option>
                        <option value="MX">MX</option>
                        <option value="TXT">TXT</option>
                        <option value="NS">NS</option>
                        <option value="SOA">SOA</option>
                    </select>
                </div>
                <button type="submit" class="button button-primary">
                    <?php esc_html_e('Lookup', 'latency-global'); ?>
                </button>
            </form>
            <div class="latency-tool-output" id="dns-output"></div>
        </div>
    </div>

    <?php endif; ?>
</div>

