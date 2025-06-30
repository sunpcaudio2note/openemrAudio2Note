<?php
/**
 * Single-execution installer for the OpenEMR Audio to Note module.
 *
 * This script follows the OpenEMR patch script pattern. It should be placed
 * in the OpenEMR webroot and run once.
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @author    Sun PC Solutions LLC
 * @copyright Copyright (c) 2025 Sun PC Solutions LLC
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

// --- Bootstrap OpenEMR Environment ---
$ignoreAuth = true; // This skips login authentication.
$GLOBALS["enable_auditlog"] = 0; // Disables audit logging for this script.

require_once(__DIR__ . '/interface/globals.php');
require_once(__DIR__ . '/library/sql_upgrade_fx.php');
require_once(__DIR__ . '/library/sql.inc.php');
require_once(__DIR__ . '/interface/modules/custom_modules/openemrAudio2Note/src/Logic/EncryptionKeyManager.php');
require_once(__DIR__ . '/interface/modules/custom_modules/openemrAudio2Note/src/Logic/EncryptionService.php');

use OpenEMR\Services\VersionService;
use OpenEMR\Modules\OpenemrAudio2Note\Logic\EncryptionKeyManager;
use OpenEMR\Modules\OpenemrAudio2Note\Logic\EncryptionService;

// --- Installer Configuration ---
$moduleName = 'openemrAudio2Note';
$formAudioToNote = 'audio_to_note';
$formHistoryPhysical = 'history_physical';
$formRecentVisitSummary = 'recent_visit_summary';
$formSoapAudio = 'soap_audio';

$EMRversion = trim(preg_replace('/\s*\([^)]*\)/', '', (new VersionService())->asString()));

?>
<html>
<head>
    <title>OpenEMR Audio2Note Module Installer</title>
    <link rel="shortcut icon" href="public/images/favicon.ico" />
</head>
<body style="color:green;">

<div style="box-shadow: 3px 3px 5px 6px #ccc; border-radius: 20px; padding: 10px 40px;background-color:#EFEFEF; width:500px; margin:40px auto">

  <p style="font-weight:bold; font-size:1.8em; text-align:center">OpenEMR Audio2Note Module Installer</p>

<?php
try {
    // Installation process is now silent to provide a cleaner user experience.

    // 1. Create the module's configuration table directly.
    $sql = "CREATE TABLE IF NOT EXISTS `audio2note_config` (
        `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `openemr_internal_random_uuid` VARCHAR(36) DEFAULT NULL,
        `effective_instance_identifier` VARCHAR(255) DEFAULT NULL,
        `encrypted_license_key` TEXT DEFAULT NULL,
        `encrypted_license_consumer_key` TEXT DEFAULT NULL,
        `encrypted_license_consumer_secret` TEXT DEFAULT NULL,
        `encrypted_dlm_activation_token` TEXT DEFAULT NULL,
        `encrypted_backend_audio_process_base_url` TEXT DEFAULT NULL,
        `encrypted_wc_api_base_url` TEXT DEFAULT NULL,
        `encryption_key_raw` TEXT DEFAULT NULL,
        `license_status` VARCHAR(50) DEFAULT NULL,
        `license_expires_at` DATETIME DEFAULT NULL,
        `last_validation_timestamp` DATETIME DEFAULT NULL,
        `site_id` VARCHAR(255) DEFAULT NULL,
        `created_at` DATETIME DEFAULT NULL,
        `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    sqlStatement($sql);
    // 1.1. Generate the master encryption key and store it immediately upon installation.
    // This ensures the key exists before any encryption/decryption operations are attempted.
    // The ON DUPLICATE KEY UPDATE clause makes this operation idempotent and safe to re-run.
    // It will only set the key if one does not already exist, preserving the key if the installer is run again.
    $encryptionKey = random_bytes(32); // SODIUM_CRYPTO_SECRETBOX_KEYBYTES
    sqlStatement(
        "INSERT INTO `audio2note_config` (id, encryption_key_raw, created_at) VALUES (1, ?, NOW())
        ON DUPLICATE KEY UPDATE encryption_key_raw = IF(encryption_key_raw IS NULL, VALUES(encryption_key_raw), encryption_key_raw)",
        [$encryptionKey]
    );

    // 2. Create form tables from their respective table.sql files
    $formSqlPaths = [
        __DIR__ . '/interface/forms/audio_to_note/table.sql',
        __DIR__ . '/interface/forms/history_physical/table.sql',
        __DIR__ . '/interface/forms/recent_visit_summary/table.sql',
        __DIR__ . '/interface/forms/soap_audio/table.sql'
    ];

    foreach ($formSqlPaths as $path) {
        if (file_exists($path)) {
            $sql = file_get_contents($path);
            sqlStatement($sql);
            // Silent execution.
        } else {
            // Silent execution.
        }
    }

    // 3. Manually register forms in the registry table
    sqlStatement("INSERT INTO `registry` (`name`, `state`, `directory`, `sql_run`, `unpackaged`, `date`, `priority`, `category`, `nickname`) VALUES (?, 'enabled', ?, 1, 1, NOW(), 0, 'Clinical', 'Audio2Note') ON DUPLICATE KEY UPDATE `state`='enabled'", [$formAudioToNote, $formAudioToNote]);
    sqlStatement("INSERT INTO `registry` (`name`, `state`, `directory`, `sql_run`, `unpackaged`, `date`, `priority`, `category`, `nickname`) VALUES (?, 'enabled', ?, 1, 1, NOW(), 0, 'Clinical', 'Audio2Note History and Physical') ON DUPLICATE KEY UPDATE `nickname`='Audio2Note History and Physical', `state`='enabled'", [$formHistoryPhysical, $formHistoryPhysical]);
    sqlStatement("INSERT INTO `registry` (`name`, `state`, `directory`, `sql_run`, `unpackaged`, `date`, `priority`, `category`, `nickname`) VALUES (?, 'enabled', ?, 1, 1, NOW(), 0, 'Clinical', 'Audio2Note Summary') ON DUPLICATE KEY UPDATE `nickname`='Audio2Note Summary', `state`='enabled'", [$formRecentVisitSummary, $formRecentVisitSummary]);
    sqlStatement("INSERT INTO `registry` (`name`, `state`, `directory`, `sql_run`, `unpackaged`, `date`, `priority`, `category`, `nickname`) VALUES (?, 'enabled', ?, 1, 1, NOW(), 0, 'Clinical', 'Audio2Note SOAP') ON DUPLICATE KEY UPDATE `nickname`='Audio2Note SOAP', `state`='enabled'", [$formSoapAudio, $formSoapAudio]);
 
    // 4. Register the background polling service
    sqlStatement("INSERT IGNORE INTO `background_services` (`name`, `title`, `active`, `running`, `next_run`, `execute_interval`, `function`, `require_once`, `sort_order`) VALUES ('AudioToNote_Polling', 'Audio To Note Transcription Polling', 1, 0, NOW(), 5, 'runAudioToNotePolling', '/interface/modules/custom_modules/openemrAudio2Note/cron_runner.php', 110)");

     echo "<h3 class='text-success mt-4'>Module Prepared for Installation!</h3>";
    echo "<hr>";
    echo "<h4>Next Steps</h4>";
    echo "<p>The module's database tables have been created. Please complete the following steps to make it operational:</p>";
    echo "<ol style='line-height: 1.6;'>";
    echo "<li><strong>Delete Installer:</strong> For security, please delete this installer file (<code>install.php</code>) from your OpenEMR web directory.</li>";
    echo "<li><strong>Install and Enable the Module:</strong>";
    echo "<ul>";
    echo "<li>Navigate to: <strong>Modules &rarr; Manage Modules</strong>.</li>";
    echo "<li>Find <strong>openemrAudio2Note</strong> in the list and click the <strong>Install</strong> button.</li>";
    echo "<li>After installation, click the <strong>Enable</strong> button.</li>";
    echo "</ul>";
    echo "</li>";
    echo "<li><strong>Enable Module Forms:</strong>";
    echo "<ul>";
    echo "<li>Navigate to: <strong>Administration &rarr; Forms &rarr; Forms Administration</strong>.</li>";
    echo "<li>Enable the following forms: <strong>audio_to_note</strong>, <strong>history_physical</strong>, <strong>recent_visit_summary</strong>, and <strong>soap_audio</strong>.</li>";
    echo "<li>After enabling the forms, press <strong>Save</strong>.</li>";
    echo "</ul>";
    echo "</li>";
    echo "<li><strong>Configure the Module:</strong>";
    echo "<ul>";
    echo "<li>Navigate back to <strong>Modules &rarr; Manage Modules</strong>.</li>";
    echo "<li>Click the <strong>Configure</strong> button (the gear icon) for the <strong>openemrAudio2Note</strong> module.</li>";
    echo "<li>Enter your <strong>License Key</strong>, <strong>API Consumer Key</strong>, and <strong>API Consumer Secret</strong>, then save the configuration.</li>";
    echo "</ul>";
    echo "</li>";
    echo "</ol>";
    echo "<p>Once these steps are completed, the module will be fully operational.</p>";

} catch (Exception $e) {
    echo "<h3 class='text-danger'>An Error Occurred</h3>";
    echo "<p class='text-danger'>" . htmlspecialchars($e->getMessage()) . "</p>";
    error_log("Audio2Note Installer Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
}

echo '<p><a style="border-radius: 10px; padding:5px; width:200px; margin:0 auto; background-color:green; color:white; font-weight:bold; display:block; text-align:center;" href="index.php?site=',attr($_SESSION['site_id']) . '">',xlt('Log in'),'</a></p>';

?>
</div>
</body>
</html>