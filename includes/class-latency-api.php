<?php
/**
 * Latency Global API Wrapper
 *
 * @package Latency_Global
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * API wrapper class for Latency Global API
 */
class Latency_Global_API {

    /**
     * API Key
     *
     * @var string
     */
    private $api_key;

    /**
     * API Base URL
     *
     * @var string
     */
    private $api_url;

    /**
     * Constructor
     */
    public function __construct() {
        $this->api_key = get_option('latency_global_api_key', '');
        $this->api_url = LATENCY_GLOBAL_API_URL;
    }

    /**
     * Make API request
     *
     * @param string $endpoint API endpoint.
     * @param string $method   HTTP method.
     * @param array  $body     Request body.
     * @return array|WP_Error Response data or error.
     */
    private function request($endpoint, $method = 'GET', $body = null) {
        if (empty($this->api_key)) {
            return new WP_Error(
                'no_api_key',
                __('API key not configured. Please add your Latency Global API key in settings.', 'latency-global')
            );
        }

        $args = [
            'method'  => $method,
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
                'User-Agent'    => 'LatencyGlobal-WordPress/' . LATENCY_GLOBAL_VERSION,
            ],
        ];

        if ($body && in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $args['body'] = wp_json_encode($body);
        }

        $url = $this->api_url . $endpoint;
        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        // Handle errors
        if ($code >= 400) {
            $message = isset($data['message']) ? $data['message'] : __('API request failed', 'latency-global');

            return new WP_Error(
                'api_error',
                $message,
                ['status' => $code, 'response' => $data]
            );
        }

        return $data;
    }

    /**
     * Check if API key is configured
     *
     * @return bool
     */
    public function has_api_key() {
        return !empty($this->api_key);
    }

    /**
     * Verify API key is valid
     *
     * @return bool|WP_Error True if valid, WP_Error otherwise.
     */
    public function verify_api_key() {
        $result = $this->request('/monitors?per_page=1');

        if (is_wp_error($result)) {
            return $result;
        }

        return true;
    }

    /**
     * List all monitors
     *
     * @param array $params Query parameters.
     * @return array|WP_Error
     */
    public function list_monitors($params = []) {
        $query = http_build_query($params);
        $endpoint = '/monitors' . ($query ? '?' . $query : '');

        return $this->request($endpoint);
    }

    /**
     * Get single monitor
     *
     * @param int $id Monitor ID.
     * @return array|WP_Error
     */
    public function get_monitor($id) {
        return $this->request('/monitors/' . intval($id));
    }

    /**
     * Create a new monitor
     *
     * @param array $data Monitor data.
     * @return array|WP_Error
     */
    public function create_monitor($data) {
        $defaults = [
            'name'     => get_bloginfo('name') ?: 'WordPress Site',
            'type'     => 'https',
            'url'      => home_url('/'),
            'interval' => 60,
        ];

        $data = wp_parse_args($data, $defaults);

        return $this->request('/monitors', 'POST', $data);
    }

    /**
     * Update a monitor
     *
     * @param int   $id   Monitor ID.
     * @param array $data Monitor data.
     * @return array|WP_Error
     */
    public function update_monitor($id, $data) {
        return $this->request('/monitors/' . intval($id), 'PUT', $data);
    }

    /**
     * Delete a monitor
     *
     * @param int $id Monitor ID.
     * @return array|WP_Error
     */
    public function delete_monitor($id) {
        return $this->request('/monitors/' . intval($id), 'DELETE');
    }

    /**
     * Get monitor results
     *
     * @param int   $id     Monitor ID.
     * @param array $params Query parameters.
     * @return array|WP_Error
     */
    public function get_monitor_results($id, $params = []) {
        $query = http_build_query($params);
        $endpoint = '/monitors/' . intval($id) . '/results' . ($query ? '?' . $query : '');

        return $this->request($endpoint);
    }

    /**
     * Get monitor statistics
     *
     * @param int $id   Monitor ID.
     * @param int $days Number of days.
     * @return array|WP_Error
     */
    public function get_monitor_stats($id, $days = 7) {
        return $this->request('/monitors/' . intval($id) . '/stats?days=' . intval($days));
    }

    /**
     * List available PoPs
     *
     * @param array $params Query parameters.
     * @return array|WP_Error
     */
    public function list_pops($params = []) {
        $query = http_build_query($params);
        $endpoint = '/pops' . ($query ? '?' . $query : '');

        return $this->request($endpoint);
    }

    /**
     * Run a ping probe
     *
     * @param string $target Target IP or hostname.
     * @param array  $params Additional parameters.
     * @return array|WP_Error
     */
    public function probe_ping($target, $params = []) {
        $data = array_merge(['target' => $target], $params);

        return $this->request('/probe/ping', 'POST', $data);
    }

    /**
     * Run an HTTP/HTTPS probe
     *
     * @param string $url    Target URL.
     * @param array  $params Additional parameters.
     * @return array|WP_Error
     */
    public function probe_http($url, $params = []) {
        $data = array_merge(['url' => $url], $params);
        $endpoint = strpos($url, 'https://') === 0 ? '/probe/https-get' : '/probe/http-get';

        return $this->request($endpoint, 'POST', $data);
    }

    /**
     * Run a DNS probe
     *
     * @param string $name   Domain name.
     * @param string $type   Record type.
     * @param array  $params Additional parameters.
     * @return array|WP_Error
     */
    public function probe_dns($name, $type = 'A', $params = []) {
        $data = array_merge([
            'name' => $name,
            'type' => $type,
        ], $params);

        return $this->request('/probe/dns', 'POST', $data);
    }

    /**
     * Create monitor for current WordPress site
     *
     * @param array $overrides Optional overrides.
     * @return array|WP_Error
     */
    public function create_site_monitor($overrides = []) {
        $site_url = home_url('/');
        $site_name = get_bloginfo('name');

        // Ensure HTTPS if available
        if (is_ssl()) {
            $site_url = set_url_scheme($site_url, 'https');
        }

        $data = [
            'name'     => $site_name ?: wp_parse_url($site_url, PHP_URL_HOST),
            'type'     => is_ssl() ? 'https' : 'http',
            'url'      => $site_url,
            'interval' => 60,
        ];

        $data = wp_parse_args($overrides, $data);

        return $this->create_monitor($data);
    }
}

