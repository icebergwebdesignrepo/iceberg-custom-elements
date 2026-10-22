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
 * Version: 3.0.0
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Update URI: https://plugin.icebergwebdesign.com/iceberg-custom-elements
 */

namespace IcebergCustomElements;

use function Breakdance\Util\getDirectoryPathRelativeToPluginFolder;

require_once __DIR__ . '/includes/UpdateService.php';
new UpdateService(__FILE__, '3.0.0', 'https://plugin.icebergwebdesign.com');

add_action('breakdance_loaded', function () {
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
