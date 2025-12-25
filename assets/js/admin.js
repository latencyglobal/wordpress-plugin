/**
 * Latency Global - Admin JavaScript
 *
 * @package Latency_Global
 */

(function($) {
    'use strict';

    var LatencyGlobalAdmin = {
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Toggle API key visibility
            $('#latency-toggle-api-key').on('click', this.toggleApiKey);

            // Verify API key
            $('#latency-verify-api-key').on('click', this.verifyApiKey);

            // Create monitor
            $('#latency-create-monitor').on('click', this.createMonitor);

            // Delete monitor
            $('#latency-delete-monitor').on('click', this.deleteMonitor);

            // Refresh stats
            $('#latency-refresh-stats').on('click', this.refreshStats);

            // Tools forms
            $('#latency-ping-form').on('submit', this.handlePingForm);
            $('#latency-http-form').on('submit', this.handleHttpForm);
            $('#latency-dns-form').on('submit', this.handleDnsForm);
        },

        /**
         * Toggle API key visibility
         */
        toggleApiKey: function(e) {
            e.preventDefault();
            var $input = $('#latency_global_api_key');
            var $button = $(this);

            if ($input.attr('type') === 'password') {
                $input.attr('type', 'text');
                $button.text(latencyGlobal.i18n.hide || 'Hide');
            } else {
                $input.attr('type', 'password');
                $button.text(latencyGlobal.i18n.show || 'Show');
            }
        },

        /**
         * Verify API key
         */
        verifyApiKey: function(e) {
            e.preventDefault();
            var $button = $(this);
            var $status = $('#latency-api-key-status');

            $button.prop('disabled', true);
            $status.removeClass('valid invalid').text(latencyGlobal.i18n.verifying);

            $.ajax({
                url: latencyGlobal.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'latency_global_verify_api_key',
                    nonce: latencyGlobal.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $status.addClass('valid').text(latencyGlobal.i18n.valid);
                    } else {
                        $status.addClass('invalid').text(response.data.message || latencyGlobal.i18n.invalid);
                    }
                },
                error: function() {
                    $status.addClass('invalid').text(latencyGlobal.i18n.error);
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        },

        /**
         * Create monitor
         */
        createMonitor: function(e) {
            e.preventDefault();
            var $button = $(this);
            var originalText = $button.html();

            $button.prop('disabled', true).html(
                '<span class="dashicons dashicons-update spin"></span> ' + 
                latencyGlobal.i18n.creating
            );

            $.ajax({
                url: latencyGlobal.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'latency_global_create_monitor',
                    nonce: latencyGlobal.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Reload page to show new monitor
                        location.reload();
                    } else {
                        alert(response.data.message || latencyGlobal.i18n.error);
                        $button.prop('disabled', false).html(originalText);
                    }
                },
                error: function() {
                    alert(latencyGlobal.i18n.error);
                    $button.prop('disabled', false).html(originalText);
                }
            });
        },

        /**
         * Delete monitor
         */
        deleteMonitor: function(e) {
            e.preventDefault();

            if (!confirm(latencyGlobal.i18n.confirmDelete)) {
                return;
            }

            var $button = $(this);
            $button.prop('disabled', true).text(latencyGlobal.i18n.deleting);

            $.ajax({
                url: latencyGlobal.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'latency_global_delete_monitor',
                    nonce: latencyGlobal.nonce
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data.message || latencyGlobal.i18n.error);
                        $button.prop('disabled', false).text(latencyGlobal.i18n.delete || 'Delete Monitor');
                    }
                },
                error: function() {
                    alert(latencyGlobal.i18n.error);
                    $button.prop('disabled', false);
                }
            });
        },

        /**
         * Refresh stats
         */
        refreshStats: function(e) {
            e.preventDefault();
            var $button = $(this);
            var $container = $('#latency-stats-container');

            $button.prop('disabled', true);
            $button.find('.dashicons').addClass('spin');
            $container.addClass('latency-loading');

            $.ajax({
                url: latencyGlobal.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'latency_global_refresh_stats',
                    nonce: latencyGlobal.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Reload to show new stats
                        location.reload();
                    } else {
                        alert(response.data.message || latencyGlobal.i18n.error);
                    }
                },
                error: function() {
                    alert(latencyGlobal.i18n.error);
                },
                complete: function() {
                    $button.prop('disabled', false);
                    $button.find('.dashicons').removeClass('spin');
                    $container.removeClass('latency-loading');
                }
            });
        },

        /**
         * Handle ping form
         */
        handlePingForm: function(e) {
            e.preventDefault();
            var $form = $(this);
            var $output = $('#ping-output');
            var $button = $form.find('button[type="submit"]');

            LatencyGlobalAdmin.runTool($form, $output, $button, 'ping');
        },

        /**
         * Handle HTTP form
         */
        handleHttpForm: function(e) {
            e.preventDefault();
            var $form = $(this);
            var $output = $('#http-output');
            var $button = $form.find('button[type="submit"]');

            LatencyGlobalAdmin.runTool($form, $output, $button, 'http');
        },

        /**
         * Handle DNS form
         */
        handleDnsForm: function(e) {
            e.preventDefault();
            var $form = $(this);
            var $output = $('#dns-output');
            var $button = $form.find('button[type="submit"]');

            LatencyGlobalAdmin.runTool($form, $output, $button, 'dns');
        },

        /**
         * Run network tool
         */
        runTool: function($form, $output, $button, tool) {
            var originalText = $button.text();
            $button.prop('disabled', true).text('Running...');
            $output.removeClass('visible error').empty();

            var data = $form.serializeArray();
            var params = {};
            $.each(data, function(i, field) {
                params[field.name] = field.value;
            });

            $.ajax({
                url: latencyGlobal.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'latency_global_run_' + tool,
                    nonce: latencyGlobal.nonce,
                    params: params
                },
                success: function(response) {
                    if (response.success) {
                        var output = response.data.stdout || response.data.output || JSON.stringify(response.data, null, 2);
                        $output.text(output).addClass('visible');
                    } else {
                        $output.text(response.data.message || 'Error running tool').addClass('visible error');
                    }
                },
                error: function(xhr) {
                    var message = 'Request failed';
                    try {
                        var response = JSON.parse(xhr.responseText);
                        message = response.data.message || message;
                    } catch(e) {}
                    $output.text(message).addClass('visible error');
                },
                complete: function() {
                    $button.prop('disabled', false).text(originalText);
                }
            });
        }
    };

    // Add spin animation
    $('<style>')
        .text('.dashicons.spin { animation: latency-spin 1s linear infinite; }')
        .appendTo('head');
    $('<style>')
        .text('@keyframes latency-spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }')
        .appendTo('head');

    // Initialize on document ready
    $(document).ready(function() {
        LatencyGlobalAdmin.init();
    });

})(jQuery);

