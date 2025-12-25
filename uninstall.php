<?php
/**
 * Uninstall script for Latency Global plugin
 *
 * This file runs when the plugin is deleted from WordPress.
 * It cleans up all plugin data from the database.
 *
 * @package Latency_Global
 */

// Exit if uninstall not called from WordPress
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Clean up plugin data
 */
function latency_global_uninstall() {
    // Delete options
    delete_option('latency_global_api_key');
    delete_option('latency_global_monitor_id');
    delete_option('latency_global_auto_created');
    delete_option('latency_global_show_badge');

    // Delete transients
    delete_transient('latency_global_status');
    delete_transient('latency_global_stats');

    // Clear scheduled events
    wp_clear_scheduled_hook('latency_global_sync_status');

    // For multisite, clean up each site
    if (is_multisite()) {
        $sites = get_sites(['fields' => 'ids']);

        foreach ($sites as $site_id) {
            switch_to_blog($site_id);

            delete_option('latency_global_api_key');
            delete_option('latency_global_monitor_id');
            delete_option('latency_global_auto_created');
            delete_option('latency_global_show_badge');

            delete_transient('latency_global_status');
            delete_transient('latency_global_stats');

            wp_clear_scheduled_hook('latency_global_sync_status');

            restore_current_blog();
        }
    }
}

// Run uninstall
latency_global_uninstall();

