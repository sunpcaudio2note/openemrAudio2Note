<?php
/**
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @author    Sun PC Solutions LLC
 * @copyright Copyright (c) 2025 Sun PC Solutions LLC
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace OpenEMR\Modules\OpenemrAudio2Note\Logic;

// Use Guzzle or another HTTP client library. Ensure it's available in OpenEMR or add via Composer.
// Example assumes GuzzleHttp\Client is available.
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Utils; // For file uploads
use GuzzleHttp\Exception\ConnectException;
// Use the module's own EncryptionService, assuming PSR-4 autoloading is configured
use OpenEMR\Modules\OpenemrAudio2Note\Logic\Services\PatientHistoryService;
// The EncryptionService class is located in src/Logic/EncryptionService.php
use OpenEMR\Modules\OpenemrAudio2Note\Logic\EncryptionService;
use OpenEMR\Modules\openemrAudio2Note\Services\ConfigurationService;

class TranscriptionServiceClient
{
    private $audioProcessingServiceBaseUrl;
    private $licenseKey; // This might be a specific API key for the transcription service itself, or the module license key if it gates the audio processing service call
    private $httpClient;
    private $encryptionService;
    private $db;

    public function __construct()
    {
        $this->audioProcessingServiceBaseUrl = 'https://backend.audio2note.org/webhook';
        $this->httpClient = new Client(['timeout' => 30.0]);
        $this->db = $GLOBALS['adodb']['db'];
    }

    /**
     * Fetches encounter details from the database.
     *
     * @param int $encounterId The encounter ID.
     * @return array An associative array of encounter details.
     */
    private function getEncounterDetails(int $encounterId): array
    {
        $details = [
            'visit_category' => null,
            'visit_class' => null,
            'visit_type' => null,
        ];

        if ($encounterId > 0) {
            $sql = "SELECT
                        opc.pc_catname AS visit_category,
                        loclass.title AS visit_class,
                        lotype.title AS visit_type
                    FROM
                        form_encounter AS fe
                    LEFT JOIN
                        openemr_postcalendar_categories AS opc ON opc.pc_catid = fe.pc_catid
                    LEFT JOIN
                        list_options AS loclass ON loclass.list_id = '_ActEncounterCode' AND loclass.option_id = fe.class_code
                    LEFT JOIN
                        list_options AS lotype ON lotype.list_id = 'encounter-types' AND lotype.option_id = fe.encounter_type_code
                    WHERE
                        fe.encounter = ?
                    LIMIT 1";

            $result = $this->db->Execute($sql, [$encounterId]);

            if ($result && !$result->EOF) {
                $row = $result->FetchRow();
                $details['visit_category'] = $row['visit_category'] ?? 'Unknown';
                $details['visit_class'] = $row['visit_class'] ?? 'Unknown';
                $details['visit_type'] = $row['visit_type'] ?? 'Unknown';
            }
        }
        return $details;
    }


    /**
     * Initiates a transcription job with the audio processing service.
     *
     * @param string $audioFilePath Temporary path to the uploaded audio file.
     * @param string $originalFilename Original name of the audio file.
     * @param string $noteType Type of note (e.g., 'SOAP', 'History and Physical').
     * @param int $patientId Patient ID.
     * @param int $encounterId Encounter ID.
     * @param int $formId ID of the form_audio_to_note record.
     * @param int $userId OpenEMR user ID.
     * @param string $openemrInstanceId The OpenEMR instance ID.
     * @param array $encounterDetails An array containing the visit category, class, and type.
     * @param array $params Optional parameters.
     * @return string|null Returns the job_id if successful, null otherwise.
     * @throws \Exception On API communication errors or invalid responses.
     */
    public function initiateTranscription(string $licenseKey, string $audioFilePath, string $originalFilename, string $noteType, int $patientId, int $encounterId, int $formId, int $userId, string $openemrInstanceId, array $params = [], ?PatientHistoryService $patientHistoryService = null, ?ConfigurationService $configurationService = null)
    {
        if (empty($this->audioProcessingServiceBaseUrl) || empty($licenseKey)) {
            throw new \Exception("Transcription service client is not configured (missing URL or license key).");
        }
        if (!file_exists($audioFilePath) || !is_readable($audioFilePath)) {
            throw new \Exception("Audio file not found or not readable: " . $audioFilePath);
        }

        $encounterDetails = $this->getEncounterDetails($encounterId);

        $url = rtrim($this->audioProcessingServiceBaseUrl, '/') . '/initiate_transcription';

        $tier = 1; // Default to Tier 1
        if (preg_match('/^T(\d+)/', $licenseKey, $matches)) {
            $tier = (int)$matches[1];
        }

        $multipartData = [
            [
                'name'     => 'audio_file',
                'contents' => Utils::tryFopen($audioFilePath, 'r'),
                'filename' => $originalFilename
            ],
            ['name' => 'note_type', 'contents' => $noteType],
            ['name' => 'patient_id', 'contents' => (string)$patientId],
            ['name' => 'encounter_id', 'contents' => (string)$encounterId],
            ['name' => 'form_id', 'contents' => (string)$formId],
            ['name' => 'user_id', 'contents' => (string)$userId],
            ['name' => 'openemr_instance_id', 'contents' => (string)$openemrInstanceId],
            ['name' => 'tier', 'contents' => (string)$tier],
            ['name' => 'encounter_details', 'contents' => json_encode($encounterDetails)]
        ];

        // Add dynamic parameters from the form
        foreach ($params as $key => $value) {
            // Avoid adding historical_notes_text and output_format if they exist in $params, as we will handle them explicitly.
            if ($key !== 'historical_notes_text' && $key !== 'output_format') {
                $multipartData[] = ['name' => $key, 'contents' => (string)$value];
            }
        }

        // Explicitly handle historical_notes_text for summary notes
        if ($noteType === 'summary' && $patientHistoryService) {
            $historicalNotes = $patientHistoryService->getRecentNotesAsText($patientId);
            $multipartData[] = ['name' => 'historical_notes_text', 'contents' => $historicalNotes];
        }

        // Explicitly handle output_format from config
        // Explicitly handle output_format from config
        if ($configurationService) {
            $transcriptionParams = $configurationService->get('transcription_params', []);
            if (!empty($transcriptionParams['output_format'])) {
                $multipartData[] = ['name' => 'output_format', 'contents' => $transcriptionParams['output_format']];
            }
        }

        try {
            $response = $this->httpClient->post($url, [
                'multipart' => $multipartData,
                'headers' => [
                    'X-License-Key' => $licenseKey
                ]
            ]);

            $statusCode = $response->getStatusCode();
            $body = $response->getBody()->getContents();

            if ($statusCode >= 200 && $statusCode < 300) {
                $decodedBody = json_decode($body, true);
                if (json_last_error() === JSON_ERROR_NONE && isset($decodedBody['job_id'])) {
                    return $decodedBody['job_id'];
                } else {
                    error_log("TranscriptionServiceClient: Backend service returned non-JSON or missing job_id: " . $body);
                    throw new \Exception("Received invalid response from transcription initiation service.");
                }
            } else {
                error_log("TranscriptionServiceClient: Backend initiation service error: Status " . $statusCode . " - Body: " . $body);
                throw new \Exception("Transcription initiation service returned status code: " . $statusCode);
            }
        } catch (ConnectException $e) {
            $errorMsg = "TranscriptionServiceClient: Connection Error to backend: " . $e->getMessage();
            error_log($errorMsg);
            throw new \Exception("Could not connect to the transcription service. Please check network configuration or if the service is down.");
        } catch (RequestException $e) {
            $errorMsg = "Error calling backendAudioProcess initiation API: " . $e->getMessage();
            if ($e->hasResponse()) {
                $errorMsg .= " | Response body: " . $e->getResponse()->getBody()->getContents();
            }
            error_log($errorMsg);
            throw new \Exception("Failed to communicate with transcription initiation service: " . $e->getMessage());
        } catch (\Throwable $e) {
             error_log("TranscriptionServiceClient: Unexpected error in initiateTranscription: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            throw new \Exception("An unexpected error occurred while initiating transcription.");
        }
        return null;
    }

    /**
     * Gets the status and results of a transcription job from the audio processing service.
     *
     * @param string $jobId The ID of the transcription job.
     * @return array|null Returns an array with job status and results, or null on error.
     * @throws \Exception On API communication errors or invalid responses.
     */
    public function getTranscriptionStatus(string $licenseKey, string $jobId): ?array
    {
        if (empty($this->audioProcessingServiceBaseUrl) || empty($licenseKey)) {
            throw new \Exception("Transcription service client is not configured (missing URL or license key).");
        }
        if (empty($jobId)) {
            throw new \Exception("Job ID cannot be empty for status check.");
        }

        $url = rtrim($this->audioProcessingServiceBaseUrl, '/') . '/get_transcription_status';

        try {
            $response = $this->httpClient->get($url, [
                'query' => ['job_id' => $jobId],
                'headers' => [
                    'X-License-Key' => $licenseKey
                ]
            ]);

            $statusCode = $response->getStatusCode();
            $body = $response->getBody()->getContents();

            if ($statusCode >= 400) {
                error_log("TranscriptionServiceClient: Backend status service error: Status " . $statusCode . " - Body: " . $body);
                return [
                    'status' => 'error_audio_processing_service_response',
                    'http_status_code' => $statusCode,
                    'error_message' => 'Audio processing service returned an error status code.',
                    'raw_response' => $body
                ];
            } elseif ($statusCode >= 200 && $statusCode < 300) {
                $decodedBody = json_decode($body, true);
                if (json_last_error() === JSON_ERROR_NONE && isset($decodedBody['status'])) {
                    return $decodedBody;
                } else {
                    return [
                        'status' => json_last_error() !== JSON_ERROR_NONE ? 'error_invalid_json' : 'error_missing_status_field',
                        'error_message' => json_last_error() !== JSON_ERROR_NONE ? 'Audio processing service returned non-JSON response.' : 'Audio processing service response missing status field.',
                        'raw_response' => $body
                    ];
                 }
            }
        } catch (RequestException $e) {
            $errorMsg = "Error calling audio processing service status API: " . $e->getMessage();
            if ($e->hasResponse()) {
                $errorMsg .= " | Response body: " . $e->getResponse()->getBody()->getContents();
            }
            error_log($errorMsg);
            return [
                'status' => 'error_request_exception',
                'error_message' => "Failed to communicate with transcription status service: " . $e->getMessage(),
                'exception_type' => get_class($e),
                'has_response' => $e->hasResponse(),
                'response_body' => $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : null
            ];
        } catch (\Throwable $e) {
             error_log("TranscriptionServiceClient: Unexpected error in getTranscriptionStatus: " . $e->getMessage());
            throw new \Exception("An unexpected error occurred while checking transcription status.");
        }
        return null;
    }

    /**
     * Requests the purging of sensitive data for a specific transcription job.
     *
     * @param string $jobId The ID of the job to purge.
     * @return bool True on success, false on failure.
     * @throws \Exception On critical configuration errors.
     */
    public function purgeJobData(string $licenseKey, string $jobId): bool
    {
        if (empty($this->audioProcessingServiceBaseUrl) || empty($licenseKey)) {
            throw new \Exception("Transcription service client is not configured (missing URL or license key).");
        }
        if (empty($jobId)) {
            throw new \Exception("Job ID cannot be empty for data purge.");
        }

        $url = rtrim($this->audioProcessingServiceBaseUrl, '/') . '/purge_job_data';

        try {
            $response = $this->httpClient->post($url, [
                'json' => ['job_id' => $jobId],
                'headers' => [
                    'X-License-Key' => $licenseKey,
                    'Content-Type' => 'application/json'
                ]
            ]);

            $statusCode = $response->getStatusCode();
            $body = $response->getBody()->getContents();

            // A 2xx status code indicates success.
            if ($statusCode >= 200 && $statusCode < 300) {
                // Optional: Log success for debugging, but it's generally not needed for successful operations.
                return true;
            }

            // If the status code is not in the 2xx range, it's an error.
            error_log("TranscriptionServiceClient: Failed to purge data for job " . $jobId . ". Status: " . $statusCode . " - Body: " . $body);
            return false;
        } catch (RequestException $e) {
            $errorMsg = "Error calling data purge API for job " . $jobId . ": " . $e->getMessage();
            if ($e->hasResponse()) {
                $errorMsg .= " | Response body: " . $e->getResponse()->getBody()->getContents();
            }
            error_log($errorMsg);
            return false;
        } catch (\Throwable $e) {
            error_log("TranscriptionServiceClient: Unexpected error in purgeJobData for job " . $jobId . ": " . $e->getMessage());
            return false;
        }
    }
}

?>
