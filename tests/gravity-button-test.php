<?php
/** Standalone boundary test for the legacy Gravity Forms button styling hook. */
namespace Breakdance\Util {
    function getDirectoryPathRelativeToPluginFolder($directory) { return 'iceberg-custom-elements'; }
}
namespace {
    error_reporting(E_ALL);
    set_error_handler(static function ($severity, $message) { throw new \ErrorException($message, 0, $severity); });

    $GLOBALS['test_hooks'] = [];
    function add_filter($name, $callback, $priority = 10, $accepted_args = 1) {
        $GLOBALS['test_hooks'][$name][] = [$callback, $priority, $accepted_args];
    }
    function add_action($name, $callback, $priority = 10, $accepted_args = 1) {
        add_filter($name, $callback, $priority, $accepted_args);
    }
    function plugin_basename($path) { return 'iceberg-custom-elements/plugin.php'; }

    // Models the HTML Processor's opening-tag/class contract; the test exercises
    // the actual plugin registration and callback, without bootstrapping WP.
    class WP_HTML_Processor {
        private string $html;
        private bool $valid = false;
        private function __construct(string $html) { $this->html = $html; }
        public static function create_fragment(string $html): self { return new self($html); }
        public function next_token(): bool {
            $this->valid = (bool) preg_match('/^\s*<(?:button|input)\b[^>]*>/i', $this->html);
            return $this->valid;
        }
        public function add_class(string $name): bool {
            if (!$this->valid) { return false; }
            $this->html = preg_replace_callback('/^\s*<(?:button|input)\b[^>]*>/i', static function ($match) use ($name) {
                $tag = $match[0];
                if (preg_match('/\bclass=(\'|\")([^\'\"]*)\1/i', $tag, $classes)) {
                    if (in_array($name, preg_split('/\s+/', trim($classes[2])), true)) { return $tag; }
                    return str_replace($classes[0], 'class=' . $classes[1] . $classes[2] . ' ' . $name . $classes[1], $tag);
                }
                return preg_replace('/\s*\/?\>$/', ' class="' . $name . '">', $tag);
            }, $this->html, 1);
            return true;
        }
        public function get_updated_html(): string { return $this->html; }
    }

    require dirname(__DIR__) . '/plugin.php';
    $hooks = $GLOBALS['test_hooks']['gform_submit_button'] ?? [];
    if (count($hooks) !== 1 || $hooks[0][1] !== 10 || $hooks[0][2] !== 2) {
        throw new \RuntimeException('Gravity Forms submit filter is not registered once with two arguments');
    }
    $callback = $hooks[0][0];
    $input = '<button type="submit" id="gform_submit_button_1" class="gform_button button">Send</button>';
    $result = $callback($input, ['id' => 1]);
    if (!preg_match('/\bclass="([^"]+)"/', $result, $match)) {
        throw new \RuntimeException('Button class attribute missing');
    }
    $classes = explode(' ', $match[1]);
    foreach (['gform_button', 'button', 'button-atom', 'button-atom--primary', 'bde-button__button'] as $class) {
        if (count(array_keys($classes, $class, true)) !== 1) {
            throw new \RuntimeException('Button class must occur exactly once: ' . $class);
        }
    }
    if (!str_contains($result, 'id="gform_submit_button_1"') || !str_contains($result, '>Send</button>')) {
        throw new \RuntimeException('Button identity or content changed');
    }
    if ($callback($result, ['id' => 1]) !== $result) {
        throw new \RuntimeException('Repeated filtering must not duplicate classes');
    }
    $legacy = '<input type="submit" id="gform_submit_button_2" class="gform_button button">';
    if (!str_contains($callback($legacy, ['id' => 2]), 'bde-button__button')) {
        throw new \RuntimeException('Legacy input submit button not styled');
    }
    if ($callback('', ['id' => 3]) !== '') {
        throw new \RuntimeException('Empty button HTML must be returned unchanged');
    }
    echo "PASS Gravity Forms submit button retains original markup and gains Breakdance classes\n";
}
