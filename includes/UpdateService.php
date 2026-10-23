<?php
/** Private updater for the installed Iceberg Custom Elements plugin. */
namespace IcebergCustomElements;

class UpdateService {
    private string $plugin_basename;
    private string $current_version;
    private string $server_url;
    private string $cache_key;
    private const SLUG = 'iceberg-custom-elements';

    public function __construct(string $plugin_file, string $current_version, string $server_url) {
        $this->plugin_basename = plugin_basename($plugin_file);
        $this->current_version = $current_version;
        $this->server_url = rtrim($server_url, '/');
        $this->cache_key = 'iceberg_custom_elements_update_' . md5($this->plugin_basename);
        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_for_updates']);
        add_filter('plugins_api', [$this, 'plugin_info'], 20, 3);
        add_action('upgrader_process_complete', [$this, 'clear_cache'], 10, 2);
        add_filter('upgrader_pre_download', [$this, 'refresh_package_download'], 10, 4);
    }

    public function check_for_updates($transient) {
        if (!is_object($transient) || !isset($transient->checked) || !is_array($transient->checked)
            || !isset($transient->checked[$this->plugin_basename])) {
            return $transient;
        }
        $data = get_transient($this->cache_key);
        if (!is_array($data) || !$this->valid_update_data($data)) {
            $data = $this->fetch_update_data();
            if ($data !== false) {
                set_transient($this->cache_key, $data, 43200);
            }
        }
        if ($data === false || !version_compare($data['new_version'], $this->current_version, '>')) {
            return $transient;
        }
        if (!isset($transient->response) || !is_array($transient->response)) {
            $transient->response = [];
        }
        $transient->response[$this->plugin_basename] = (object) [
            'slug' => self::SLUG,
            'plugin' => $this->plugin_basename,
            'new_version' => $data['new_version'],
            'url' => $data['url'] ?? '',
            'package' => $data['package'],
            'requires' => $data['requires'] ?? '6.0',
            'tested' => $data['tested'] ?? '',
            'requires_php' => $data['requires_php'] ?? '8.0',
        ];
        return $transient;
    }

    public function plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information' || !is_object($args) || ($args->slug ?? null) !== self::SLUG) {
            return $result;
        }
        $response = $this->request(['action' => 'plugin_info', 'plugin' => self::SLUG]);
        return is_array($response) && !isset($response['error']) ? (object) $response : $result;
    }

    public function clear_cache($upgrader, $options): void {
        if (!is_array($options) || ($options['action'] ?? null) !== 'update' || ($options['type'] ?? null) !== 'plugin') {
            return;
        }
        if (in_array($this->plugin_basename, $options['plugins'] ?? [], true)
            || ($options['plugin'] ?? null) === $this->plugin_basename) {
            delete_transient($this->cache_key);
        }
    }

    /** Download a newly signed package, never the stale update-transient URL. */
    public function refresh_package_download($reply, $package, $upgrader, $hook_extra) {
        if ($reply !== false || !is_array($hook_extra)
            || ($hook_extra['plugin'] ?? null) !== $this->plugin_basename) {
            return $reply;
        }
        if (!is_string($package) || !$this->is_trusted_package_url($package)) {
            return new \WP_Error('iceberg_custom_elements_unsafe_package', 'The update package URL is not trusted.');
        }
        // Bypass the 12-hour update cache: download tokens expire after 15 minutes.
        $data = $this->fetch_update_data();
        if ($data === false || !version_compare($data['new_version'], $this->current_version, '>')) {
            return new \WP_Error('iceberg_custom_elements_refresh_failed', 'A fresh update package is unavailable. Please retry the update.');
        }
        return download_url($data['package']);
    }

    private function fetch_update_data(): array|false {
        $data = $this->request([
            'action' => 'version_check',
            'plugin' => self::SLUG,
            'version' => $this->current_version,
            'site_url' => home_url(),
            'wp_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
        ]);
        return is_array($data) && $this->valid_update_data($data) ? $data : false;
    }

    private function request(array $params): array|false {
        $response = wp_remote_get(add_query_arg($params, $this->server_url), [
            'timeout' => 15,
            'headers' => ['User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url()],
        ]);
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return false;
        }
        $data = json_decode(wp_remote_retrieve_body($response), true);
        return is_array($data) && !isset($data['error']) ? $data : false;
    }

    private function valid_update_data(array $data): bool {
        return isset($data['new_version'], $data['package']) && is_string($data['new_version'])
            && is_string($data['package']) && (bool) preg_match('/^[0-9]+\.[0-9]+\.[0-9]+(?:[-+.][a-zA-Z0-9.-]+)?$/', $data['new_version'])
            && $this->is_trusted_package_url($data['package']);
    }

    private function is_trusted_package_url(string $url): bool {
        $parts = parse_url($url);
        if (!is_array($parts) || ($parts['scheme'] ?? null) !== 'https'
            || ($parts['host'] ?? null) !== 'plugin.icebergwebdesign.com'
            || isset($parts['user']) || isset($parts['pass'])
            || (isset($parts['port']) && $parts['port'] !== 443)) {
            return false;
        }
        parse_str($parts['query'] ?? '', $query);
        return ($query['action'] ?? null) === 'download'
            && ($query['plugin'] ?? null) === self::SLUG
            && is_string($query['token'] ?? null) && $query['token'] !== '';
    }
}
