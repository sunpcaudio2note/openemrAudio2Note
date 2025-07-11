<?php
/**
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @author    Sun PC Solutions LLC
 * @copyright Copyright (c) 2025 Sun PC Solutions LLC
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace OpenEMR\Modules\OpenemrAudio2Note\Services;

// Autoloading should handle all namespaced classes via composer.
// Ensure the entry point (e.g., cron_runner.php or OpenEMR's task scheduler) includes openemr.bootstrap.php.
// Removed direct require_once for namespaced classes:
// require_once __DIR__ . '/../../Logic/TranscriptionServiceClient.php'; // Incorrect path & not needed with autoload
// require_once __DIR__ . '/../../Logic/Manager/OpenEMRSoapNoteManager.php'; // Incorrect path & not needed with autoload
// require_once __DIR__ . '/../../Logic/Manager/OpenEMRHistoryPhysicalNoteManager.php'; // Incorrect path & not needed with autoload

use OpenEMR\Modules\OpenemrAudio2Note\Logic\TranscriptionServiceClient;
use OpenEMR\Modules\OpenemrAudio2Note\Logic\Manager\OpenEMRAudioSoapNoteManager;
use OpenEMR\Modules\OpenemrAudio2Note\Logic\Manager\OpenEMRHistoryPhysicalNoteManager;
use OpenEMR\Modules\OpenemrAudio2Note\Logic\Manager\OpenEMRSummaryNoteManager;
use OpenEMR\Modules\OpenemrAudio2Note\Logic\EncryptionService;
use OpenEMR\Modules\OpenemrAudio2Note\Logic\EncryptionKeyManager;
use OpenEMR\Common\Database\Database; // For database access
use OpenEMR\Common\Logging\SystemLogger; // For logging

// Assuming a simple execute method is called by the scheduler.

class TranscriptionPollingService
{
    private $db; // ADODB connection
    private $transcriptionServiceClient;
    private $systemLog;

    public function __construct()
    {
        $this->db = $GLOBALS['adodb']['db'];
        $this->systemLog = new SystemLogger();
        // Constructor now only initializes basic dependencies.
        // The service client is initialized in the execute method.
    }

    /**
     * Main method called by the OpenEMR task scheduler.
     */
    public function execute()
    {
        $this->systemLog->info("TranscriptionPollingService: Polling service execution started.");

        // Initialize client and decrypt credentials here, only when the service is executed.
        $this->systemLog->info("TranscriptionPollingService: Preparing to decrypt credentials for polling.");
        // Fetch the master key ONCE.
        $keyManager = new EncryptionKeyManager();
        $masterKey = $keyManager->getKey();
        if (!$masterKey) {
            $this->systemLog->error("TranscriptionPollingService: Aborting polling. CRITICAL - Could not retrieve master encryption key.");
            return;
        }
        $encryptionService = new EncryptionService($masterKey);
        $config = sqlQuery("SELECT encrypted_license_key, encrypted_license_consumer_key, encrypted_license_consumer_secret FROM audio2note_config LIMIT 1");

        if (!$config || empty($config['encrypted_license_key'])) {
            $this->systemLog->info("TranscriptionPollingService: Aborting polling. Module is not yet configured with a license key.");
            return;
        }

        $this->licenseKey = $encryptionService->decrypt($config['encrypted_license_key']);
        if (!$this->licenseKey) {
            $this->systemLog->error("TranscriptionPollingService: Aborting polling. License key could not be decrypted.");
            return;
        }

        try {
            $this->transcriptionServiceClient = new TranscriptionServiceClient();
        } catch (\Throwable $e) {
            $this->systemLog->error("TranscriptionPollingService: Aborting polling, CRITICAL - Failed to initialize TranscriptionServiceClient: " . $e->getMessage());
            return;
        }

        $pendingJobs = $this->getPendingTranscriptionJobs();

        if (empty($pendingJobs)) {
            $this->systemLog->info("TranscriptionPollingService: No pending transcription jobs found.");
            $this->systemLog->info("TranscriptionPollingService: Polling service execution finished.");
            return;
        }

        $this->systemLog->info("TranscriptionPollingService: Found " . count($pendingJobs) . " pending jobs to process.");

        foreach ($pendingJobs as $job) {
            $jobId = $job['transcription_job_id'];
            $formId = $job['id'];
            // $currentDbStatus = $job['status']; // Not directly used in this loop's logic flow after fetching

            $this->systemLog->info("TranscriptionPollingService: Polling backend for job_id: " . $jobId . " (form_id: " . $formId . ")");

            try {
                $backendResponse = $this->transcriptionServiceClient->getTranscriptionStatus($this->licenseKey, $jobId);
                $this->systemLog->info("TranscriptionPollingService: Raw backendResponse for job_id {$jobId}: " . print_r($backendResponse, true));

                if ($backendResponse && isset($backendResponse['status'])) {
                    $backendAudioProcessStatus = $backendResponse['status'];
                    $this->systemLog->info("TranscriptionPollingService: Received status '{$backendAudioProcessStatus}' for job_id: " . $jobId);

                    if (strpos($backendAudioProcessStatus, 'error_') === 0) {
                        $errorMessage = $backendResponse['error_message'] ?? 'Unknown client error during polling.';
                        $this->systemLog->error("TranscriptionPollingService: Client error for job_id {$jobId}. Status: {$backendAudioProcessStatus}. Message: {$errorMessage}.");
                        $this->updateJobStatus($formId, 'error_polling_client_issue', null, "Client Error: " . $backendAudioProcessStatus . " - " . $errorMessage);
                        continue;
                    }

                    switch ($backendAudioProcessStatus) {
                        case 'completed':
                            $rawTranscriptPayload = $backendResponse['transcript'] ?? null;
                            $this->systemLog->info("PollingService Debug: Raw transcript payload for job_id {$jobId}: " . print_r($rawTranscriptPayload, true));
                            $transcriptionResults = null;

                            if (is_array($rawTranscriptPayload) || is_object($rawTranscriptPayload)) {
                                $transcriptionResults = (array) $rawTranscriptPayload;
                            } elseif (!empty($rawTranscriptPayload) && is_string($rawTranscriptPayload)) {
                                $decoded = json_decode($rawTranscriptPayload, true);
                                if (json_last_error() === JSON_ERROR_NONE) {
                                    // It's a JSON string, use the decoded version
                                    $transcriptionResults = $decoded;
                                } else {
                                    // It's a plain text string, wrap it in the expected structure
                                    $transcriptionResults = ['full_note_text' => $rawTranscriptPayload];
                                }
                            }
 
                            // Prepare payload for database storage (must be string or null)
                            $stringifiedPayload = null;
                            if ($rawTranscriptPayload !== null) {
                                if (is_string($rawTranscriptPayload)) {
                                    // If it's a string, use it as is.
                                    // It's assumed to be either valid JSON or a plain string intended for storage.
                                    $stringifiedPayload = $rawTranscriptPayload;
                                } else { // is_array or is_object
                                    $stringifiedPayload = json_encode($rawTranscriptPayload);
                                    if ($stringifiedPayload === false) {
                                        $this->systemLog->error("PollingService: Failed to json_encode rawTranscriptPayload for job_id: " . $jobId . ". Type was: " . gettype($rawTranscriptPayload));
                                        // Store an error JSON if encoding fails
                                        $stringifiedPayload = json_encode(['error' => 'Failed to encode transcript payload for storage.']);
                                    }
                                }
                            }
                            $this->systemLog->info("PollingService Debug: stringifiedPayload for job_id {$jobId}: " . substr($stringifiedPayload, 0, 500) . (strlen($stringifiedPayload) > 500 ? '...' : ''));
                            $this->systemLog->info("PollingService Debug: transcriptionResults (after decoding) for job_id {$jobId}: " . print_r($transcriptionResults, true));
 
                            if (empty($transcriptionResults) || !is_array($transcriptionResults)) {
                                $this->systemLog->error("PollingService: Backend reported completed but no valid/usable results for job_id: " . $jobId);
                                $this->updateJobStatus($formId, 'completed_no_results', $stringifiedPayload, "Backend reported completed but no valid/usable results.");
                                continue 2; // Use continue 2 to correctly skip to the next iteration of the outer foreach loop.
                            }
 
                            $this->systemLog->info("TranscriptionPollingService: Job completed, processing results for job_id: " . $jobId);
                            $this->updateJobStatus($formId, 'completed', $stringifiedPayload); // Use the stringified payload
 
                            $noteType = $job['note_type'];
                            $pid = (int)$job['pid'];
                            $encounterId = (int)$job['encounter'];
                            $userIdForNote = (int)$job['user'];
                            $linkedFormsId = (int)$job['linked_forms_id'];

                            $noteManager = null;
                            if ($noteType === 'soap_audio' || $noteType === 'soap') {
                                $noteManager = new OpenEMRAudioSoapNoteManager($pid, $encounterId, $formId, $userIdForNote);
                            } elseif ($noteType === 'history_physical') {
                                $noteManager = new OpenEMRHistoryPhysicalNoteManager($pid, $encounterId, $formId, $userIdForNote);
                            } elseif ($noteType === 'summary') {
                                $noteManager = new OpenEMRSummaryNoteManager($pid, $encounterId, $formId, $userIdForNote);
                            }

                            if ($noteManager) {
                                try {
                                    $this->systemLog->info("PollingService: Calling saveNoteData for job_id {$jobId}. Note Type: {$noteType}.");
                                    // The manager's constructor now has all necessary context.
                                    // The saveNoteData method only needs the results payload.
                                    $noteDataToSave = $transcriptionResults;
                                    // For summaries, the actual text is in the 'full_note_text' key of the decoded results.
                                    if ($noteType === 'summary' && isset($transcriptionResults['full_note_text'])) {
                                        $noteDataToSave = ['anp_content' => $transcriptionResults['full_note_text']];
                                    }
                                    $noteManager->saveNoteData($noteDataToSave);
                                    $this->systemLog->info("PollingService: Note updated successfully for job_id: " . $jobId);
                                    $this->updateJobStatus($formId, 'note_updated');
                                    $this->updateJobStatus($formId, 'note_updated');

                                    // After successfully updating the note, purge the job data from the backend.
                                    try {
                                        $this->systemLog->info("PollingService: Attempting to purge job data for job_id: " . $jobId);
                                        $purgeSuccess = $this->transcriptionServiceClient->purgeJobData($this->licenseKey, $jobId);
                                        if ($purgeSuccess) {
                                            $this->systemLog->info("PollingService: Successfully purged job data for job_id: " . $jobId);
                                        } else {
                                            $this->systemLog->warning("PollingService: Failed to purge job data for job_id: " . $jobId . ". This will be retried on the next UI poll for this job.");
                                        }
                                    } catch (\Throwable $purgeException) {
                                        $this->systemLog->error("PollingService: Exception during data purge for job_id {$jobId}: " . $purgeException->getMessage());
                                        // Do not re-throw or change job status; logging is sufficient.
                                    }
                                } catch (\Throwable $noteUpdateException) {
                                    $this->systemLog->error("TranscriptionPollingService: Error updating note for job_id {$jobId}: " . $noteUpdateException->getMessage());
                                    $this->updateJobStatus($formId, 'completed_error_note_update', null, "Error updating note: " . $noteUpdateException->getMessage());
                                }
                            } else {
                                $this->systemLog->error("TranscriptionPollingService: Unknown note_type '{$noteType}' for form_id {$formId}, job_id {$jobId}. Cannot update note.");
                                $this->updateJobStatus($formId, 'completed_error_note_update', null, "Unknown note type for update.");
                            }
                            break;

                        case 'failed':
                            $errorMessage = $backendResponse['error_message'] ?? 'Transcription failed with an unknown error.';
                            $this->systemLog->error("TranscriptionPollingService: Job failed for job_id: " . $jobId . " - " . $errorMessage);
                            $this->updateJobStatus($formId, 'failed', null, $errorMessage);
                            break;

                        case 'processing':
                            $this->systemLog->info("TranscriptionPollingService: Job still processing for job_id: " . $jobId);
                            break;
                            
                        case 'not_found':
                             $this->systemLog->error("TranscriptionPollingService: Job not found on backend service for job_id: " . $jobId);
                             $this->updateJobStatus($formId, 'error_job_not_found', null, "Job not found on backend service.");
                             break;

                        case 'unknown':
                            $this->systemLog->warning("TranscriptionPollingService: Received 'unknown' status for job_id: " . $jobId . ". This may be a temporary state. Will re-poll.");
                            // No status update, just log and wait for the next polling cycle.
                            break;

                        default:
                            $this->systemLog->error("TranscriptionPollingService: Received unknown status '{$backendAudioProcessStatus}' from backend for job_id: " . $jobId);
                            $this->updateJobStatus($formId, 'error_unknown_backend_audio_process_status', null, "Unknown status from backend: " . $backendAudioProcessStatus);
                            break;
                    }
                } else {
                    $this->systemLog->error("TranscriptionPollingService: Invalid, empty, or unexpected response structure from TranscriptionServiceClient for job_id: " . $jobId . ". Response: " . print_r($backendResponse, true));
                    $this->updateJobStatus($formId, 'error_client_response_unexpected', null, "Unexpected response structure from client for job_id: " . $jobId);
                }
            } catch (\Throwable $e) {
                $this->systemLog->error("TranscriptionPollingService: Exception during polling for job_id {$jobId}: " . $e->getMessage() . "\n" . $e->getTraceAsString());
                $this->updateJobStatus($formId, 'error_polling', null, "Polling error: " . $e->getMessage());
            }
        }
        
        $this->systemLog->info("TranscriptionPollingService: Polling service execution finished.");
    }

    /**
     * Retrieves pending transcription jobs from the database.
     * @return array Array of database rows.
     */
    private function getPendingTranscriptionJobs(): array
    {
        $sql = "SELECT id, pid, encounter, user, transcription_job_id, note_type, status, linked_forms_id
                FROM form_audio_to_note
                WHERE status IN (?, ?)
                AND transcription_job_id IS NOT NULL";
        $bindings = ['processing', 'pending_upload'];
        
        $result = $this->db->Execute($sql, $bindings);

        if ($result === false) {
            $dbError = $this->db->ErrorMsg();
            $logMessage = "TranscriptionPollingService: Database error retrieving pending jobs.";
            if (!empty($dbError)) {
                $logMessage .= " DB Error: " . (string)$dbError;
            }
            $this->systemLog->error($logMessage);
            return [];
        }
        
        if (is_object($result) && method_exists($result, 'RecordCount')) {
            $numRows = $result->RecordCount();
            if ($numRows > 0) {
                $allRows = $result->GetAll();
                $result->Close();
                return $allRows ?: [];
            } else {
                $result->Close();
                return [];
            }
        } else {
            $this->systemLog->error("getPendingTranscriptionJobs: Query result is not a valid ADODB RecordSet object or RecordCount method missing.");
            return [];
        }
    }

    /**
     * Updates the status and optionally results/error message for a job in the database.
     * @param int $formId The ID of the form_audio_to_note record.
     * @param string $newStatus The new status to set.
     * @param string|null $resultsJson JSON string of results.
     * @param string|null $errorMessage Error message.
     */
    private function updateJobStatus(int $formId, string $newStatus, ?string $resultsJson = null, ?string $errorMessage = null): void
    {
        $sql = "UPDATE form_audio_to_note SET status = ?";
        $bindings = [$newStatus];

        if ($resultsJson !== null) {
            $sql .= ", transcription_service_response = ?";
            $bindings[] = $resultsJson;
        } elseif ($errorMessage !== null) {
             $sql .= ", transcription_service_response = ?"; // Store error in the same field for simplicity
             $bindings[] = json_encode(['error' => $errorMessage]);
        }
        
        $sql .= " WHERE id = ?";
        $bindings[] = $formId;

        if ($this->db->Execute($sql, $bindings) === false) {
            $this->systemLog->error("TranscriptionPollingService: Database error updating status for form_id {$formId} to '{$newStatus}': " . $this->db->ErrorMsg());
        } else {
        }
    }

    /**
     * Triggers the note creation/update logic after successful transcription.
     * This method is now effectively integrated into the main execute loop.
     * @param int $formId The ID of the form_audio_to_note record.
     * @param string $noteType The type of note (SOAP/H&P).
     * @param int $pid Patient ID.
     * @param int $encounterId Encounter ID.
     * @param int $userIdForNote User ID to associate with the note.
     * @param array $transcriptionResults The structured transcription results.
     */
    private function processCompletedTranscription(int $formId, string $noteType, int $pid, int $encounterId, int $userIdForNote, array $transcriptionResults): void
    {
        // This method's logic has been moved into the main execute() loop's 'completed' case
        // for better flow and to avoid redundant RestConfig checks if possible.
        // Kept as a placeholder or for future refactoring if needed.
        $this->systemLog->warning("TranscriptionPollingService: processCompletedTranscription was called, but its logic is now in execute(). This indicates a potential refactoring need or old call path.");
    }
}

?>
