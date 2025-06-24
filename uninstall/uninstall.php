<?php
/**
 * Uninstaller for the OpenEMR Audio to Note module.
 *
 * This script removes all database artifacts and configurations created by the module.
 */

// --- Bootstrap OpenEMR Environment ---
$ignoreAuth = true;
$GLOBALS["enable_auditlog"] = 0;

// The script is in a subdirectory, so we need to adjust the path to globals.php
require_once(__DIR__ . '/interface/globals.php');
require_once(__DIR__ . '/library/sql.inc.php');

// --- Uninstaller Configuration ---
$moduleName = 'openemrAudio2Note';
$formAudioToNote = 'audio_to_note';
$formHistoryPhysical = 'history_physical';
$formRecentVisitSummary = 'recent_visit_summary';

?>
<html>
<head>
    <title>OpenEMR Audio2Note Module Uninstaller</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; padding: 20px; }
        h1, h3, h4 { color: #333; }
        p { color: #555; }
        code { background-color: #f4f4f4; padding: 2px 6px; border-radius: 4px; font-family: monospace; }
        .success { color: green; }
        .error { color: red; }
        .manual-cleanup { border: 1px solid #ddd; padding: 15px; margin-top: 20px; border-radius: 5px; }
    </style>
</head>
<body>
    <h1>OpenEMR Audio2Note Module Uninstaller</h1>
<?php
try {
    // Step 1: Remove Background Service
    sqlStatement("DELETE FROM `background_services` WHERE `name` = 'AudioToNote_Polling'");
    echo "<p class='success'>Background polling service removed.</p>";

    // Step 2: Unregister Forms
    // We are preserving the notes, so we will not unregister the forms.
    // sqlStatement("DELETE FROM `registry` WHERE `directory` = ?", [$formAudioToNote]);
    // sqlStatement("DELETE FROM `registry` WHERE `directory` = ?", [$formHistoryPhysical]);
    // sqlStatement("DELETE FROM `registry` WHERE `directory` = ?", [$formRecentVisitSummary]);
    echo "<p class='success'>Module forms registration preserved to maintain access to notes.</p>";

    // Step 3: Unregister the Module
    sqlStatement("DELETE FROM `modules` WHERE `mod_name` = ?", [$moduleName]);
    echo "<p class='success'>Main module unregistered.</p>";

    // Step 4: Drop Database Tables
    sqlStatement("DROP TABLE IF EXISTS `audio2note_config`");
    // sqlStatement("DROP TABLE IF EXISTS `form_audio_to_note`");
    // sqlStatement("DROP TABLE IF EXISTS `form_history_physical`");
    // sqlStatement("DROP TABLE IF EXISTS `form_recent_visit_summary`");
    echo "<p class='success'>Note-related database tables preserved.</p>";

    echo "<h3>Module database artifacts removed successfully.</h3>";

    // Step 5: Display Manual Cleanup Instructions
    ?>
    <div class="manual-cleanup">
        <h4>Final Manual Cleanup Steps</h4>
        <p>The automated uninstallation is complete. To fully remove the module, please manually delete the following files and directories from your OpenEMR installation:</p>
        <ol>
            <li><strong>This Uninstaller Script:</strong>
                <ul>
                    <li><code>/path/to/openemr/uninstall/uninstall.php</code></li>
                </ul>
            </li>
            <li><strong>Module Source Directory:</strong>
                <ul>
                    <li><code>/path/to/openemr/interface/modules/custom_modules/openemrAudio2Note/</code></li>
                </ul>
            </li>
            <li style="color:red;"><strong>IMPORTANT: Do NOT delete the form directories below if you want to keep the notes created by this module.</strong>
                <ul>
                    <li><code>/path/to/openemr/interface/forms/audio_to_note/</code></li>
                    <li><code>/path/to/openemr/interface/forms/history_physical/</code></li>
                    <li><code>/path/to/openemr/interface/forms/recent_visit_summary/</code></li>
                </ul>
            </li>
        </ol>
        <p><strong>Note:</strong> By preserving the form directories and the associated database tables, you will be able to access all notes and forms created by the Audio2Note module even after uninstallation.</p>
    </div>
    <?php

} catch (Exception $e) {
    echo "<h3 class='error'>An error occurred during uninstallation.</h3>";
    echo "<p class='error'>" . htmlspecialchars($e->getMessage()) . "</p>";
    error_log("Audio2Note Uninstaller Error: " . $e->getMessage());
}
?>
</body>
</html>