<?php
/** Check that the legacy top bar script is enqueued only on the frontend. */
namespace Breakdance\Util {
    function getDirectoryPathRelativeToPluginFolder($directory) { return 'iceberg-custom-elements'; }
}
namespace {
    error_reporting(E_ALL);
    set_error_handler(static function ($severity, $message) { throw new \ErrorException($message, 0, $severity); });

    $GLOBALS['test_hooks'] = [];
    $GLOBALS['enqueued_scripts'] = [];
    function add_action($name, $callback, $priority = 10, $accepted_args = 1) {
        $GLOBALS['test_hooks'][$name][] = $callback;
    }
    function add_filter($name, $callback, $priority = 10, $accepted_args = 1) {
        add_action($name, $callback, $priority, $accepted_args);
    }
    function plugin_basename($path) { return 'iceberg-custom-elements/plugin.php'; }
    function plugin_dir_url($path) { return 'https://example.test/wp-content/plugins/iceberg-custom-elements/'; }
    function wp_enqueue_script($handle, $src, $deps, $ver, $in_footer) {
        $GLOBALS['enqueued_scripts'][] = [$handle, $src, $deps, $ver, $in_footer];
    }

    require dirname(__DIR__) . '/plugin.php';
    if ($GLOBALS['enqueued_scripts'] !== []) {
        throw new \RuntimeException('Script was enqueued during plugin load instead of the frontend hook');
    }
    foreach ($GLOBALS['test_hooks']['wp_enqueue_scripts'] ?? [] as $callback) {
        $callback();
    }
    $expected = [[
        'topbar-hide',
        'https://example.test/wp-content/plugins/iceberg-custom-elements/js/topbar-hide.js',
        [],
        '3.0.3',
        true,
    ]];
    if ($GLOBALS['enqueued_scripts'] !== $expected) {
        throw new \RuntimeException('Legacy top bar script was not enqueued with the expected URL, version, and footer flag: ' . var_export($GLOBALS['enqueued_scripts'], true));
    }
    echo "PASS legacy top bar script enqueued on frontend\n";
}
