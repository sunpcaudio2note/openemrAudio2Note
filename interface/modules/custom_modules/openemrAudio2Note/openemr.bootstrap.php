<?php

/**
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @author    Sun PC Solutions LLC
 * @copyright Copyright (c) 2025 Sun PC Solutions LLC
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

// It is critical to include the module's own autoloader to make its dependencies
// (like Guzzle) available to the main OpenEMR application.
$moduleVendorAutoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($moduleVendorAutoload)) {
    require_once $moduleVendorAutoload;
} else {
    // Log an error if the vendor autoloader is missing, as it's essential for the module to function.
    error_log("OpenemrAudio2Note Bootstrap Error: The module's vendor/autoload.php file is missing. Please run 'composer install' in the module directory.");
}

/**
 * @global OpenEMR\Core\ModulesClassLoader $classLoader
 */
// Register the module's own namespace for its source files.
$classLoader->registerNamespaceIfNotExists('OpenEMR\\Modules\\OpenemrAudio2Note\\', __DIR__ . DIRECTORY_SEPARATOR . 'src');
