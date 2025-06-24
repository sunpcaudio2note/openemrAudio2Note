<?php
/**
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @author    Sun PC Solutions LLC
 * @copyright Copyright (c) 2025 Sun PC Solutions LLC
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

require_once __DIR__ . "/../../globals.php";
require_once __DIR__ . "/../../../library/api.inc";
require_once __DIR__ . "/../../../library/forms.inc.php";
require_once __DIR__ . "/../../../library/formdata.inc.php";
require_once __DIR__ . "/../../../library/options.inc.php";

use OpenEMR\Modules\OpenemrAudio2Note\Services\ConfigurationService;

// Include the module's configuration
$configFilePath = __DIR__ . '/../../modules/custom_modules/openemrAudio2Note/config.php';
if (file_exists($configFilePath)) {
    require_once $configFilePath;
} else {
    // Display error message to user if config is missing
    echo "<div class='alert alert-danger'>Configuration file not found. Please ensure the module is correctly installed.</div>";
    exit;
}

require_once __DIR__ . '/../../modules/custom_modules/openemrAudio2Note/src/Services/ConfigurationService.php';
$configurationService = new ConfigurationService($openemrAudio2NoteConfig);


// Get default transcription parameters from config
$defaultParams = $configurationService->get('transcription_params', []);

// Determine encounter ID from common OpenEMR sources
$encounter_id = null;
if (isset($encounter) && is_numeric($encounter)) {
    $encounter_id = $encounter;
} elseif (isset($_SESSION['encounter']) && is_numeric($_SESSION['encounter'])) {
    $encounter_id = $_SESSION['encounter'];
} elseif (isset($_REQUEST['encounter']) && is_numeric($_REQUEST['encounter'])) {
    $encounter_id = $_REQUEST['encounter'];
}

if ($encounter_id === null) {
    $error = "Encounter ID is missing or invalid.";
    echo "<div class='alert alert-danger'>" . htmlspecialchars($error) . "</div>";
    exit;
}

// Determine patient ID from session or request
$patient_id = $_SESSION['pid'] ?? $_REQUEST['pid'] ?? null;
if (!$patient_id) {
    $error = "Patient ID is missing.";
    echo "<div class='alert alert-danger'>" . htmlspecialchars($error) . "</div>";
    exit;
}

// Form rendering

?>
<html>
<head>
    <title><?php echo xlt('Audio2Note'); ?></title>
    <link rel="stylesheet" href="<?php echo $css_header; ?>" type="text/css">
</head>
<body class="body_top">

<h3><?php echo xlt('Audio2Note Transcription'); ?></h3>

<form method="post" action="<?php echo $GLOBALS['webroot']; ?>/interface/forms/audio_to_note/save.php?mode=new" name="audio_to_note_form" enctype="multipart/form-data" onsubmit="return top.restoreSession()">
    <input type="hidden" name="pid" value="<?php echo htmlspecialchars($patient_id); ?>">
    <input type="hidden" name="encounter" value="<?php echo htmlspecialchars($encounter_id); ?>">
    <input type="hidden" name="process" value="true"> <!-- Flag for save.php -->

    <div class="container">
        <div class="row">
            <div class="col-sm-12" id="audio_file_container">
                <label for="audio_file"><?php echo xlt('Audio File'); ?>:</label>
                <input type="file" name="audio_file" id="audio_file" required accept="audio/*">
            </div>
        </div>

        <div class="row" style="margin-top: 10px;">
            <div class="col-sm-12">
                <label for="note_type"><?php echo xlt('Note Type'); ?>:</label>
                <select name="note_type" id="note_type" class="form-control">
                    <option value="soap"><?php echo xlt('SOAP Note'); ?></option>
                    <option value="history_physical"><?php echo xlt('History and Physical Note'); ?></option>
                    <option value="summary"><?php echo xlt('Recent Visit Summary'); ?></option>
                </select>
            </div>
        </div>


        <div id="transcription_parameters_container">
            <div class="row" style="margin-top: 10px;">
                <div class="col-sm-12">
                    <h4><?php echo xlt('Transcription Parameters (Optional)'); ?></h4>
                </div>
            </div>

            <div class="row">
                <div class="col-sm-6">
                    <label for="min_speakers"><?php echo xlt('Min Speakers'); ?>:</label>
                    <input type="number" name="min_speakers" id="min_speakers" class="form-control" value="<?php echo htmlspecialchars($defaultParams['min_speakers'] ?? 1); ?>" min="1">
                </div>
                <div class="col-sm-6">
                    <label for="max_speakers"><?php echo xlt('Max Speakers'); ?>:</label>
                    <input type="number" name="max_speakers" id="max_speakers" class="form-control" value="<?php echo htmlspecialchars($defaultParams['max_speakers'] ?? 2); ?>" min="1">
                </div>
            </div>
        </div>

        <!-- Output format is fixed to JSON in config for MVP, so not shown as an option -->
        <!-- <div class="row">
            <div class="col-sm-6">
                <label for="output_format"><?php echo xlt('Output Format'); ?>:</label>
                <select name="output_format" id="output_format" class="form-control">
                    <option value="json" <?php echo (($defaultParams['output_format'] ?? 'json') == 'json') ? 'selected' : ''; ?>>JSON</option>
                    <option value="txt" <?php echo (($defaultParams['output_format'] ?? 'json') == 'txt') ? 'selected' : ''; ?>>Text</option>
                </select>
            </div>
        </div> -->

        <div class="row" style="margin-top: 20px;">
            <div class="col-sm-12 text-center">
                <button type="submit" id="submit_button" class="btn btn-primary"><?php echo xlt('Upload and Transcribe'); ?></button>
                <button type="button" class="btn btn-secondary" onclick="top.restoreSession()"><?php echo xlt('Cancel'); ?></button>
            </div>
        </div>
    </div>
</form>

<script>
   document.addEventListener('DOMContentLoaded', function() {
       const noteTypeSelect = document.getElementById('note_type');
       const audioFileContainer = document.getElementById('audio_file_container');
       const audioFileInput = document.getElementById('audio_file');
       const transcriptionParametersContainer = document.getElementById('transcription_parameters_container');
       const submitButton = document.getElementById('submit_button');

       function toggleElements() {
           if (noteTypeSelect.value === 'summary') {
               audioFileContainer.style.display = 'none';
               audioFileInput.removeAttribute('required');
               transcriptionParametersContainer.style.display = 'none';
               submitButton.textContent = '<?php echo xlt('Summarize'); ?>';
           } else {
               audioFileContainer.style.display = 'block';
               audioFileInput.setAttribute('required', 'required');
               transcriptionParametersContainer.style.display = 'block';
               submitButton.textContent = '<?php echo xlt('Upload and Transcribe'); ?>';
           }
       }

       noteTypeSelect.addEventListener('change', toggleElements);
       // Initial check
       toggleElements();
   });
</script>

</body>
</html>
