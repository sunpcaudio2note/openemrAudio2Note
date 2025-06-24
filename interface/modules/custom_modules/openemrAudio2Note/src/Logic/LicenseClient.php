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

// It's good practice to use a proper HTTP client library like Guzzle,
// but for simplicity in this example, we might outline with basic cURL or file_get_contents.
// However, for production, a robust HTTP client is recommended.

use OpenEMR\Modules\OpenemrAudio2Note\Logic\EncryptionService;

class LicenseClient
{
    private $apiBaseUrl;
    private $encryptionService;

    public function __construct(EncryptionService $encryptionService)
    {
        $this->encryptionService = $encryptionService;
        $this->apiBaseUrl = 'https://www.audio2note.org';
    }

    /**
     * Sends an HTTP GET request using cURL.
     *
     * @param string $url The URL to request.
     * @param string $actionName A descriptive name for the action (e.g., "activate", "validate") for logging.
     * @return array|false The API response as an associative array, or false on failure/error.
     */
    private function _sendRequest(string $url, string $actionName, ?string $consumerKey = null, ?string $consumerSecret = null): array|false
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        if ($consumerKey !== null && $consumerSecret !== null) {
            curl_setopt($ch, CURLOPT_USERPWD, $consumerKey . ":" . $consumerSecret);
        }
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Follow redirects
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HEADER, 1); // Include header in the output
        curl_setopt($ch, CURLOPT_USERAGENT, 'OpenEMR-Audio2Note-Module/1.0');
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErrorNum = curl_errno($ch);
        $curlErrorMsg = curl_error($ch);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        if ($curlErrorNum) {
            error_log("LicenseClient: cURL Error on " . $actionName . " (" . $url . "): [" . $curlErrorNum . "] " . $curlErrorMsg);
            return false;
        }

        $headerStr = substr($response, 0, $headerSize);
        $responseBody = substr($response, $headerSize);

        $headers = [];
        $headerLines = explode("\r\n", $headerStr);
        foreach ($headerLines as $line) {
            if (strpos($line, ':') !== false) {
                list($key, $value) = explode(':', $line, 2);
                $headers[strtolower(trim($key))] = trim($value);
            }
        }

        $contentType = $headers['content-type'] ?? '';

        if (strpos($contentType, 'application/json') === false) {
            error_log("LicenseClient: Unexpected Content-Type on " . $actionName . " (" . $url . "). Expected 'application/json', got '" . $contentType . "'. Response body (first 500 chars): " . substr($responseBody, 0, 500));
            return false;
        }

        $decodedResponse = json_decode($responseBody, true);

        if ($httpCode >= 200 && $httpCode < 300) {
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("LicenseClient: JSON Decode Error on " . $actionName . " success (" . $httpCode . ") for URL (" . $url . "). Response: " . $responseBody);
                return false;
            }
            // Successful response, log might be too verbose for every call.
            return $decodedResponse;
        } else {
            error_log("LicenseClient: HTTP Error on " . $actionName . " (" . $httpCode . ") for URL (" . $url . "). Response: " . $responseBody);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decodedResponse)) {
                return $decodedResponse; // Return API's error structure if available
            }
            return false;
        }
    }

    /**
     * Activates a license key with the Licensing Service.
     *
     * @param string $licenseKey The license key to activate.
     * @param string $effectiveInstanceIdentifier The unique identifier for this OpenEMR instance.
     * @return array|false The API response as an associative array, or false on failure.
     */
    public function activateLicense(string $licenseKey, string $effectiveInstanceIdentifier, string $consumerKey, string $consumerSecret): array|false
    {
        $endpoint = $this->apiBaseUrl . '/wp-json/dlm/v1/licenses/activate/' . rawurlencode($licenseKey) . '/';
        $queryParams = http_build_query([
            'label' => $effectiveInstanceIdentifier,
        ]);
        $url = $endpoint . '?' . $queryParams;

        return $this->_sendRequest($url, 'activate', $consumerKey, $consumerSecret);
    }

    /**
     * Validates an activation token with the Licensing Service.
     *
     * @param string $activationToken The activation token to validate.
     * @return array|false The API response as an associative array, or false on failure.
     */
    public function validateLicense(string $activationToken): array|false
    {
        $credentials = $this->loadCredentialsFromDb();
        $endpoint = $this->apiBaseUrl . '/wp-json/dlm/v1/licenses/validate/' . rawurlencode($activationToken) . '/';
        return $this->_sendRequest($endpoint, 'validate', $credentials['consumerKey'], $credentials['consumerSecret']);
    }

    /**
     * Deactivates an activation token with the Licensing Service.
     *
     * @param string $activationToken The activation token to deactivate.
     * @return array|false The API response as an associative array, or false on failure.
     */
    public function deactivateLicense(string $activationToken): array|false
    {
        $credentials = $this->loadCredentialsFromDb();
        $endpoint = $this->apiBaseUrl . '/wp-json/dlm/v1/licenses/deactivate/' . rawurlencode($activationToken) . '/';
        return $this->_sendRequest($endpoint, 'deactivate', $credentials['consumerKey'], $credentials['consumerSecret']);
    }

    private function loadCredentialsFromDb(): array
    {
        $config = sqlQuery("SELECT encrypted_license_consumer_key, encrypted_license_consumer_secret FROM audio2note_config LIMIT 1");
        if ($config) {
            $consumerKey = $this->encryptionService->decrypt($config['encrypted_license_consumer_key']);
            $consumerSecret = $this->encryptionService->decrypt($config['encrypted_license_consumer_secret']);
            return ['consumerKey' => $consumerKey, 'consumerSecret' => $consumerSecret];
        }
        return ['consumerKey' => null, 'consumerSecret' => null];
    }
}