<?php
/**
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @author    Sun PC Solutions LLC
 * @copyright Copyright (c) 2025 Sun PC Solutions LLC
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

// Core OpenEMR globals and authentication
require_once dirname(__DIR__, 2) . '/globals.php';
require_once __DIR__ . "/../../../library/auth.inc.php";
require_once __DIR__ . '/../../../_rest_config.php';

// Module bootstrap for autoloading
require_once __DIR__ . "/../../modules/custom_modules/openemrAudio2Note/openemr.bootstrap.php";

// Manually include forms.inc.php as globals.php does not always include it in this context.
require_once $GLOBALS['fileroot'] . '/library/forms.inc.php';

require_once $GLOBALS['fileroot'] . '/library/forms.inc.php';

// Use statements for new service-oriented architecture
use OpenEMR\Modules\OpenemrAudio2Note\Logic\Repositories\AudioNoteRepository;
use OpenEMR\Modules\OpenemrAudio2Note\Logic\Services\ClinicalNoteService;
use OpenEMR\Modules\OpenemrAudio2Note\Logic\Services\FileUploadService;
use OpenEMR\Modules\OpenemrAudio2Note\Services\ConfigurationService;
use OpenEMR\Modules\OpenemrAudio2Note\Logic\Services\LicensingService;
use OpenEMR\Modules\OpenemrAudio2Note\Logic\Services\PatientHistoryService;
use OpenEMR\Modules\OpenemrAudio2Note\Logic\TranscriptionServiceClient;

// Set non-REST context for API functions
\RestConfig::setNotRestCall();

// --- Input Validation ---
if (($_POST['process'] ?? null) !== "true") {
    header("Location: " . $GLOBALS['webroot'] . "/interface/patient_file/encounter/encounter_top.php");
    exit;
}

$patient_id = $_POST['pid'] ?? $_SESSION['pid'] ?? null;
$encounter_id = $_POST['encounter'] ?? null;

if (!$patient_id || !$encounter_id) {
    $error = "Patient ID or Encounter ID missing in submission.";
    $redirectUrl = $GLOBALS['webroot'] . "/interface/patient_file/encounter/encounter_top.php?set_encounter=" . urlencode($_POST['encounter'] ?? '') . "&audio_note_error=" . urlencode($error);
    header("Location: " . $redirectUrl);
    exit;
}

// --- Service Instantiation ---
$configurationService = new ConfigurationService();
$fileUploadService = new FileUploadService();
$licensingService = new LicensingService();
$audioNoteRepository = new AudioNoteRepository();
$clinicalNoteService = new ClinicalNoteService($audioNoteRepository);
$patientHistoryService = new PatientHistoryService();
$transcriptionServiceClient = new TranscriptionServiceClient();

// --- Main Processing Logic ---
$successMessage = null;
$errorMessage = null;
$tempFilePath = null;
$form_id_to_link = $_POST['id'] ?? null;

try {
    $selectedNoteType = $_POST['note_type'] ?? 'soap_audio';
    if (!in_array($selectedNoteType, ['soap', 'soap_audio', 'history_physical', 'summary'])) {
        $selectedNoteType = 'soap_audio';
    }

    // 1. Handle File Upload
    $uploadResult = $fileUploadService->handleUpload($_FILES['audio_file'] ?? null, $selectedNoteType);
    $tempFilePath = $uploadResult['tempFilePath'];
    $originalFilename = $uploadResult['originalFilename'];

    // 2. Check License
    $licenseKey = $licensingService->checkAndGetKey();

    // 3. Prepare and Save Initial Record
    $transcriptionParams = $configurationService->get('transcription_params', []);
    if (isset($_POST['min_speakers']) && is_numeric($_POST['min_speakers'])) {
        $transcriptionParams['min_speakers'] = (int)$_POST['min_speakers'];
    }
    if (isset($_POST['max_speakers']) && is_numeric($_POST['max_speakers'])) {
        $transcriptionParams['max_speakers'] = (int)$_POST['max_speakers'];
    }

    $formSaveData = [
        'pid' => $patient_id,
        'encounter' => $encounter_id,
        'user' => $GLOBALS['authUserID'] ?? $_SESSION['authId'] ?? $_SESSION['userauthorized'] ?? null,
        'groupname' => $_SESSION['authProvider'] ?? $GLOBALS['authGroup'] ?? null,
        'authorized' => 1,
        'activity' => 1,
        'date' => date('Y-m-d H:i:s'),
        'audio_filename' => $originalFilename,
        'transcription_params' => json_encode($transcriptionParams),
        'status' => 'pending_upload',
        'note_type' => $selectedNoteType,
    ];
    $new_form_id = $audioNoteRepository->createInitialRecord($formSaveData);
    $form_id_to_link = $new_form_id;

    // 4. Create and Link Clinical Note
    // 4. Create and Link Clinical Note
    $noteDetails = $clinicalNoteService->getNoteCreationDetails($selectedNoteType);
    $userId = $GLOBALS['authUserID'] ?? $_SESSION['authId'] ?? $_SESSION['userauthorized'] ?? null;
    $addFormResult = addForm($encounter_id, $noteDetails['formTitle'], 0, $noteDetails['formName'], $patient_id, $userId);

    if (empty($addFormResult) || !is_numeric($addFormResult) || $addFormResult <= 0) {
        $audioNoteRepository->updateStatus($new_form_id, 'link_error', "Failed to create/link clinical note form shell.");
        error_log("save.php CRITICAL: addForm for target clinical note did not return a valid forms.id. Returned: " . print_r($addFormResult, true));
        throw new \Exception(xlt("Failed to create or link the underlying clinical note form."));
    }

    $targetClinicalNoteFormsId = (int)$addFormResult;

    // If this is a summary note, create the placeholder record in its specific table.
    if ($selectedNoteType === 'summary') {
        $clinicalNoteService->createSummaryPlaceholder($targetClinicalNoteFormsId, (int)$patient_id, (int)$encounter_id);
    }

    $audioNoteRepository->linkToClinicalNote($new_form_id, $targetClinicalNoteFormsId);

    // 5. Initiate Transcription
    $config_row = sqlQuery("SELECT openemr_internal_random_uuid FROM audio2note_config ORDER BY id ASC LIMIT 1");
    $openemrInstanceId = $config_row['openemr_internal_random_uuid'] ?? 'uuid_not_found';

    $job_id = $transcriptionServiceClient->initiateTranscription(
        $licenseKey,
        $tempFilePath,
        $originalFilename,
        $selectedNoteType,
        (int)$patient_id,
        (int)$encounter_id,
        $new_form_id,
        (int)($formSaveData['user'] ?? null),
        (string)$openemrInstanceId,
        $transcriptionParams,
        $patientHistoryService, // Pass the service itself
        $configurationService // Pass the service
    );

    if (!$job_id) {
        throw new \Exception(xlt("Failed to submit audio for transcription. The service did not return a Job ID."));
    }

    // 6. Update record with Job ID
    $audioNoteRepository->updateWithJobId($new_form_id, $job_id);
    $successMessage = xlt("Audio transcription request submitted successfully. Job ID: ") . htmlspecialchars($job_id);

} catch (\Throwable $e) {
    error_log("Error in audio_to_note/save.php: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    $errorMessage = xlt("An error occurred during processing:") . " " . htmlspecialchars($e->getMessage());
    // If a record was created, mark it as failed
    if (!empty($new_form_id)) {
        $audioNoteRepository->updateStatus($new_form_id, 'error', $e->getMessage());
    }
} finally {
    // Clean up temporary file
    if ($tempFilePath && file_exists($tempFilePath)) {
        unlink($tempFilePath);
    }
}

// --- Redirect ---
if ($errorMessage) {
    $_SESSION['form_error'] = $errorMessage;
} else {
    $_SESSION['form_success'] = $successMessage;
}
formJump("view.php?id=" . ($form_id_to_link ?? '') . "&encounter=" . urlencode($encounter_id));
exit;
