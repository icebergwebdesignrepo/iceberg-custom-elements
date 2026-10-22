<?php
/** Standalone WordPress boundary harness; no WordPress/Breakdance installation required. */
namespace Breakdance\Util {
    function getDirectoryPathRelativeToPluginFolder($directory) { return 'iceberg-custom-elements'; }
}
namespace Breakdance\ElementStudio {
    function registerSaveLocation(...$args) { $GLOBALS['save_locations'][] = $args; }
}
namespace {
    error_reporting(E_ALL);
    set_error_handler(static function ($severity, $message) { throw new \ErrorException($message, 0, $severity); });
    $GLOBALS['hooks'] = []; $GLOBALS['save_locations'] = []; $GLOBALS['transients'] = [];
    $GLOBALS['remote'] = null; $GLOBALS['requests'] = [];
    $GLOBALS['downloads'] = []; $GLOBALS['download_result'] = '/tmp/new-package.zip';
    function same($want, $got, $message = '') {
        if ($want !== $got) { throw new \RuntimeException(($message ? "$message: " : '') . 'expected ' . var_export($want, true) . ', got ' . var_export($got, true)); }
    }
    function truth($ok, $message) { if (!$ok) { throw new \RuntimeException($message); } }
    function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {
        $GLOBALS['hooks'][$hook][] = [$callback, $priority, $accepted_args]; return true;
    }
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {
        return add_filter($hook, $callback, $priority, $accepted_args);
    }
    function plugin_basename($path) { return 'iceberg-custom-elements/plugin.php'; }
    function home_url() { return 'https://example.test/'; }
    function get_bloginfo($field) { return '6.8'; }
    function add_query_arg($args, $url) { return $url . '?' . http_build_query($args); }
    function wp_remote_get($url, $args = []) {
        $GLOBALS['requests'][] = $url;
        return $GLOBALS['remote'] ?? ['response' => ['code' => 200], 'body' => '{}'];
    }
    function wp_remote_retrieve_response_code($response) { return $response['response']['code'] ?? 0; }
    function wp_remote_retrieve_body($response) { return $response['body'] ?? ''; }
    function is_wp_error($value) { return $value instanceof WP_Error; }
    function get_transient($key) { return $GLOBALS['transients'][$key] ?? false; }
    function set_transient($key, $value, $ttl) { $GLOBALS['transients'][$key] = $value; $GLOBALS['cache_ttl'] = $ttl; return true; }
    function delete_transient($key) { unset($GLOBALS['transients'][$key]); $GLOBALS['deleted'][] = $key; return true; }
    function download_url($url) { $GLOBALS['downloads'][] = $url; return $GLOBALS['download_result']; }
    class WP_Error {
        private $code;
        public function __construct($code = 'error', $message = '') { $this->code = $code; }
        public function get_error_code() { return $this->code; }
    }
    function offer($version = '3.0.0', $package = null) {
        return ['new_version' => $version, 'package' => $package ?? 'https://plugin.icebergwebdesign.com/?action=download&plugin=iceberg-custom-elements&token=sample', 'requires' => '6.0', 'tested' => '7.1.3', 'requires_php' => '8.0', 'url' => 'https://icebergwebdesign.com/'];
    }
    function respond($value, $status = 200) { $GLOBALS['remote'] = ['response' => ['code' => $status], 'body' => json_encode($value)]; }
    function check($name, $callback) { $callback(); echo "PASS $name\n"; }

    $file = dirname(__DIR__) . '/plugin.php';
    require $file;
    $service = null;
    foreach ($GLOBALS['hooks']['pre_set_site_transient_update_plugins'] ?? [] as $entry) {
        if (is_array($entry[0]) && $entry[0][0] instanceof \IcebergCustomElements\UpdateService) { $service = $entry[0][0]; }
    }
    $installed = $service;
    $service = new \IcebergCustomElements\UpdateService($file, '2.3.1', 'https://plugin.icebergwebdesign.com');
    $mode = $argv[1] ?? 'all';
    if ($mode === 'discovery' || $mode === 'all') {
        check('plugin metadata', static function () use ($file) {
            $text = file_get_contents($file);
            truth((bool) preg_match('/\* Version: 3\.0\.0\b/', $text), '3.0.0 version missing');
            truth((bool) preg_match('/\* Requires at least: 6\.0\b/', $text), 'WP 6.0 requirement missing');
            truth((bool) preg_match('/\* Requires PHP: 8\.0\b/', $text), 'PHP 8.0 requirement missing');
        });
        check('Breakdance callbacks and independent hooks', static function () use ($service) {
            truth($service instanceof \IcebergCustomElements\UpdateService, 'updater not bootstrapped without Breakdance');
            same(9, $GLOBALS['hooks']['breakdance_loaded'][0][1]);
            ($GLOBALS['hooks']['breakdance_loaded'][0][0])();
            same(['element', 'macro', 'preset'], array_column($GLOBALS['save_locations'], 2));
            truth(!isset($GLOBALS['hooks']['auto_update_plugin']), 'must not force auto-update');
            truth(isset($GLOBALS['hooks']['plugins_api']), 'plugin info hook missing');
            truth(isset($GLOBALS['hooks']['upgrader_process_complete']), 'cache hook missing');
        });
        check('new signed update offer and request contract', static function () use ($service) {
            respond(offer()); $GLOBALS['requests'] = []; $GLOBALS['transients'] = [];
            $value = $service->check_for_updates((object) ['checked' => ['iceberg-custom-elements/plugin.php' => '2.3.1'], 'response' => []]);
            $got = $value->response['iceberg-custom-elements/plugin.php'] ?? null;
            truth(is_object($got), 'target update missing');
            same('iceberg-custom-elements', $got->slug); same('iceberg-custom-elements/plugin.php', $got->plugin);
            same('3.0.0', $got->new_version); same(offer()['package'], $got->package);
            same(43200, $GLOBALS['cache_ttl']);
            parse_str(parse_url($GLOBALS['requests'][0], PHP_URL_QUERY), $query);
            same('version_check', $query['action']); same('iceberg-custom-elements', $query['plugin']);
            same('2.3.1', $query['version']); same('https://example.test/', $query['site_url']);
            same('6.8', $query['wp_version']); same(PHP_VERSION, $query['php_version']);
            $service->check_for_updates((object) ['checked' => ['iceberg-custom-elements/plugin.php' => '2.3.1'], 'response' => []]);
            same(1, count($GLOBALS['requests']), 'valid cache not reused');
        });
        check('equal, older and malformed transients remain unchanged', static function () use ($service) {
            $GLOBALS['transients'] = [];
            foreach (['2.3.1', '2.0.0'] as $version) {
                respond(offer($version));
                $value = (object) ['checked' => ['iceberg-custom-elements/plugin.php' => '2.3.1'], 'response' => []];
                same($value, $service->check_for_updates($value));
                truth(!isset($value->response['iceberg-custom-elements/plugin.php']), 'nonnewer update advertised');
                $GLOBALS['transients'] = [];
            }
            foreach ([false, null, (object) [], (object) ['checked' => 'bad'], (object) ['checked' => []]] as $bad) {
                same($bad, $service->check_for_updates($bad));
            }
        });
        check('invalid responses never advertise update or cache', static function () use ($service) {
            foreach ([
                [offer('3.0.0', 'http://plugin.icebergwebdesign.com/?action=download&plugin=iceberg-custom-elements&token=x'), 200],
                [offer('3.0.0', 'https://evil.test/?action=download&plugin=iceberg-custom-elements&token=x'), 200],
                [offer('3.0.0', 'https://plugin.icebergwebdesign.com/?action=download&plugin=other&token=x'), 200],
                [['new_version' => '3.0.0'], 200],
                [offer(), 503],
            ] as [$body, $status]) {
                $GLOBALS['transients'] = []; respond($body, $status);
                $value = (object) ['checked' => ['iceberg-custom-elements/plugin.php' => '2.3.1'], 'response' => []];
                $service->check_for_updates($value);
                truth(empty($value->response), 'invalid offer advertised');
                same([], $GLOBALS['transients'], 'invalid offer cached');
            }
            $GLOBALS['remote'] = ['response' => ['code' => 200], 'body' => '{bad json'];
            $value = (object) ['checked' => ['iceberg-custom-elements/plugin.php' => '2.3.1'], 'response' => []];
            $service->check_for_updates($value); truth(empty($value->response), 'malformed JSON offered');
            $GLOBALS['remote'] = new WP_Error('network');
            $service->check_for_updates($value); truth(empty($value->response), 'network error offered');
        });
        check('plugin info scoped to slug/action; cache clear scoped to target', static function () use ($service) {
            $sentinel = new \stdClass(); $GLOBALS['requests'] = [];
            same($sentinel, $service->plugin_info($sentinel, 'query_plugins', (object) ['slug' => 'iceberg-custom-elements']));
            same($sentinel, $service->plugin_info($sentinel, 'plugin_information', (object) ['slug' => 'other']));
            same($sentinel, $service->plugin_info($sentinel, 'plugin_information', (object) []));
            same(0, count($GLOBALS['requests']));
            $GLOBALS['remote'] = ['response' => ['code' => 200], 'body' => '{"name":"Iceberg Custom Elements","version":"3.0.0"}'];
            $info = $service->plugin_info($sentinel, 'plugin_information', (object) ['slug' => 'iceberg-custom-elements']);
            same('Iceberg Custom Elements', $info->name);
            $GLOBALS['deleted'] = []; $GLOBALS['transients'] = ['iceberg_custom_elements_update' => offer()];
            $service->clear_cache(null, ['action' => 'update', 'type' => 'plugin', 'plugins' => ['other/plugin.php']]);
            same([], $GLOBALS['deleted']);
            $service->clear_cache(null, ['action' => 'update', 'type' => 'plugin', 'plugins' => ['iceberg-custom-elements/plugin.php']]);
            truth(count($GLOBALS['deleted']) === 1, 'target cache not deleted');
        });
    }
    if ($mode === 'refresh' || $mode === 'all') {
        // Task 2 adds refresh-specific tests here.
    }
}
