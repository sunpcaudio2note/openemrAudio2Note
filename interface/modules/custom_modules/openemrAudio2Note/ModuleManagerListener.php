<?php

/**
 * ModuleManagerListener for openemrAudio2Note module.
 * Handles actions from OpenEMR's Module Manager.
 */

// Ensure globals.php is loaded if necessary, though AbstractModuleActionListener might handle it.
// require_once dirname(__FILE__, 4) . '/globals.php'; // May not be needed here

use OpenEMR\Core\AbstractModuleActionListener;
use OpenEMR\Modules\OpenemrAudio2Note\Setup; 
// Import the Setup class
// If you create a service class in src/ for your module's core logic, you might use it here.
// use OpenEMR\Modules\OpenemrAudio2Note\AudioNoteService; 

// It's crucial that this class is in the global namespace.

class ModuleManagerListener extends AbstractModuleActionListener
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Required static method for OpenEMR's Module Manager to get the module's primary namespace.
     */
    public static function getModuleNamespace(): string
    {
        return 'OpenEMR\\Modules\\OpenemrAudio2Note\\';
    }

    /**
     * Required static method for OpenEMR's Module Manager to instantiate this listener.
     */
    public static function initListenerSelf(): ModuleManagerListener
    {
        return new self();
    }

    /**
     * Entry point called by OpenEMR's Module Manager.
     */
    public function moduleManagerAction($methodName, $modId, string $currentActionStatus = 'Success'): string
    {

        $moduleRegistry = $this->getModuleRegistry($modId, 'mod_directory');
        $moduleDirectoryName = $moduleRegistry['mod_directory'] ?? null;

        if ($moduleDirectoryName !== 'openemrAudio2Note') {
            return $currentActionStatus;
        }


        if (method_exists($this, $methodName)) {
            return $this->$methodName($modId, $currentActionStatus);
        } else {
            return $currentActionStatus;
        }
    }

    private function enable($modId, $currentActionStatus): string
    {
        try {
            $updateSql = "UPDATE background_services SET execute_interval = 5, active = 1 WHERE name = 'AudioToNote_Polling'";
            sqlStatement($updateSql);
        } catch (\Throwable $e) {
            error_log("OpenemrAudio2Note Listener: Failed to update AudioToNote_Polling interval/active status on enable: " . $e->getMessage());
        }
        return $currentActionStatus;
    }

    private function postEnable($modId, $currentActionStatus): string
    {
        // After enabling, clear the module manager cache to ensure the autoloader picks up new classes.
        // This is a common workaround for race conditions in the module loader.
        if (class_exists('OpenEMR\Core\ModulesApplication')) {
            \OpenEMR\Core\ModulesApplication::clearModuleCache();
        }
        return $currentActionStatus;
    }

    private function disable($modId, $currentActionStatus): string
    {
        return $currentActionStatus;
    }

    private function unregister($modId, $currentActionStatus): string
    {
        // Logic for removing instance_uuid was moved to reset_module, which is typically called during uninstall.
        return $currentActionStatus;
    }
    
    private function reset_module($modId, $currentActionStatus): string
    {
        try {
            $setup = new Setup();
            $setup->reset_module();
        } catch (\Throwable $e) {
            error_log("OpenemrAudio2Note Listener: FAILED to call Setup::reset_module(): " . $e->getMessage());
        }
        return $currentActionStatus;
    }

    private function install_sql($modId, $currentActionStatus): string
    {
        return $currentActionStatus;
    }

    private function upgrade_sql($modId, $currentActionStatus): string
    {
        return $currentActionStatus;
    }
    
    private function help_requested($modId, $currentActionStatus): string
    {
        return $currentActionStatus;
    }

    private function preenable($modId, $currentActionStatus): string
    {
        return $currentActionStatus;
    }

    /**
     * Handles pre-installation tasks for the module.
     * This includes running SQL scripts and calling the module's Setup::install method.
     */
    private function preinstall($modId, $currentActionStatus): string
    {

        $modulePath = $GLOBALS['fileroot'] . "/" . $GLOBALS['baseModDir'] . "custom_modules/openemrAudio2Note";
        $sqlFile = $modulePath . '/sql/install.sql';

        if (file_exists($sqlFile)) {
            $sqlContent = file_get_contents($sqlFile);

            if ($sqlContent) {
                // Remove comments before executing
                $sqlContent = preg_replace('/#IfNotRow.*?#EndIf/s', '', $sqlContent);
                $sqlContent = preg_replace('/#.*/', '', $sqlContent);
                $sqlContent = preg_replace('/--.*/', '', $sqlContent);

                $sqlStatements = array_filter(array_map('trim', explode(';', $sqlContent)));

                if (!empty($sqlStatements)) {
                    foreach ($sqlStatements as $statement) {
                        if (!empty($statement)) {
                            try {
                                sqlStatement($statement);
                            } catch (\Throwable $e) {
                                error_log("OpenemrAudio2Note Listener: Failed to execute SQL statement - " . $e->getMessage() . "\nStatement: " . $statement);
                            }
                        }
                    }
                } else {
                }
            } else {
            }
        } else {
             error_log("OpenemrAudio2Note Listener: install.sql not found at " . $sqlFile);
        }

        try {
            $setup = new Setup();
            $setup->install();
        } catch (\Throwable $e) {
            error_log("OpenemrAudio2Note Listener: FAILED to instantiate Setup class or execute install() method: " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
        }

        try {
            $updateSql = "UPDATE background_services SET execute_interval = 5, active = 1 WHERE name = 'AudioToNote_Polling'";
            sqlStatement($updateSql);
        } catch (\Throwable $e) {
            error_log("OpenemrAudio2Note Listener: Failed to update AudioToNote_Polling interval/active status post-preinstall: " . $e->getMessage());
        }

        return $currentActionStatus;
    }

    private function install($modId, $currentActionStatus): string
    {
        // All installation logic should be in preinstall.
        return $currentActionStatus;
    }
}
