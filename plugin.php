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
 * Version: 2.3.1
 */

namespace IcebergCustomElements;

use function Breakdance\Util\getDirectoryPathRelativeToPluginFolder;

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
