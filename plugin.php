<?php

/**
 * Plugin Name: Iceberg Custom Elements
 * Plugin URI: https://www.icebergwebdesign.com/
 * Description: Iceberg's custom elements for Breakdance
 * Author: Iceberg Web Design
 * Author URI: https://www.icebergwebdesign.com/
 * License: GPLv2
 * Text Domain: iceberg
 * Domain Path: /languages/
 * Version: 3.0.3
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Update URI: https://plugin.icebergwebdesign.com/iceberg-custom-elements
 */

namespace IcebergCustomElements;

use function Breakdance\Util\getDirectoryPathRelativeToPluginFolder;

require_once __DIR__ . '/includes/UpdateService.php';
new UpdateService(__FILE__, '3.0.3', 'https://plugin.icebergwebdesign.com');

require_once __DIR__ . '/includes/gravity-form-settings.php';

// Keep the legacy #iwd-top-bar behavior on sites that still use it.
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_script(
        'topbar-hide',
        plugin_dir_url(__FILE__) . 'js/topbar-hide.js',
        [],
        '3.0.3',
        true
    );
});

add_action('breakdance_loaded', function () {
    \Breakdance\Elements\registerCategory('iceberg-web-design', 'IcebergWebDesign');

    \Breakdance\ElementStudio\registerSaveLocation(
        getDirectoryPathRelativeToPluginFolder(__DIR__) . '/elements',
        'IcebergCustomElements',
        'element',
        'Iceberg Custom Elements',
        false
    );

    \Breakdance\ElementStudio\registerSaveLocation(
        getDirectoryPathRelativeToPluginFolder(__DIR__) . '/macros',
        'IcebergCustomElements',
        'macro',
        'Iceberg Custom Macros',
        false,
    );

    \Breakdance\ElementStudio\registerSaveLocation(
        getDirectoryPathRelativeToPluginFolder(__DIR__) . '/presets',
        'IcebergCustomElements',
        'preset',
        'Iceberg Custom Presets',
        false,
    );
},
    // register elements before loading them
    9
);
