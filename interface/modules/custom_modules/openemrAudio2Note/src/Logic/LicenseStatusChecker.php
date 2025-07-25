<?php
/**
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @author    Sun PC Solutions LLC
 * @copyright Copyright (c) {{ encodeURIComponent($input.item.binary.audio_file0.fileName || 'audio.mp3') }}2025 Sun PC Solutions LLC
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace OpenEMR\Modules\OpenemrAudio2Note\Logic;

use OpenEMR\Common\Logging\SystemLogger;
use Psr\Log\LoggerInterface;
use OpenEMR\Modules\OpenemrAudio2Note\Logic\EncryptionService; // Corrected
use OpenEMR\Modules\OpenemrAudio2Note\Logic\LicenseClient;

class LicenseStatusChecker
{
    private const CACHE_DURATION_SECONDS = 24 * 60 * 60; // 24 hours
    private LoggerInterface $logger;
    private ?string $masterKey;

    public function __construct(string $masterKey = null)
    {
        $this->logger = new SystemLogger();
        $this->masterKey = $masterKey;
    }

    public function isLicenseActive(): bool
    {
        // Ensure $GLOBALS['site_id'] is set for this context, as encryption/decryption might depend on it.
        if (empty($GLOBALS['site_id'])) {
            if (isset($_SESSION['site_id']) && !empty($_SESSION['site_id'])) {
                $GLOBALS['site_id'] = $_SESSION['site_id'];
            } else {
                // Fallback to 'default' if session also doesn't have it.
                $GLOBALS['site_id'] = 'default';
                $this->logger->warning("Audio2Note: LicenseStatusChecker: \$GLOBALS['site_id'] and \$_SESSION['site_id'] were empty, defaulted \$GLOBALS['site_id'] to 'default'");
            }
        }
        $this->logger->info("Audio2Note: LicenseStatusChecker: Using site_id: " . ($GLOBALS['site_id'] ?? 'NOT SET'));

        // 1. Retrieve current license config from DB
        $config = sqlQuery("SELECT * FROM `audio2note_config` LIMIT 1");
        $this->logger->info("Audio2Note: LicenseStatusChecker: Raw config from DB: " . json_encode($config));


        if (!$config || empty($config['license_status']) || $config['license_status'] === 'not_configured') {
            $this->logger->warning("Audio2Note: LicenseStatusChecker: No config or license_status not configured or empty. Status: " . ($config['license_status'] ?? 'N/A'));
            return false; // No configuration or not configured
        }

        // 2. Check cached status and expiry
        $currentStatus = $config['license_status'];
        $expiresAt = $config['license_expires_at'];
        $lastValidationTimestamp = $config['last_validation_timestamp'];

        $now = new \DateTime();
        $isCacheValid = false;

        if ($lastValidationTimestamp) {
            $lastValidationDateTime = new \DateTime($lastValidationTimestamp);
            $interval = $now->getTimestamp() - $lastValidationDateTime->getTimestamp();
            if ($interval < self::CACHE_DURATION_SECONDS) {
                $isCacheValid = true;
            }
        }

        // If cached status is active and cache is valid, return true
        if ($currentStatus === 'active' && $isCacheValid) {
            // Also check expiry locally if available and not null
            if ($expiresAt) {
                $expiryDateTime = new \DateTime($expiresAt);
                if ($now < $expiryDateTime) {
                    return true; // Active, valid cache, not expired locally
                } else {
                    // License expired locally, force re-validation
                    $this->logger->info("Audio2Note: Local license expiry detected. Forcing re-validation.");
                }
            } else {
                 return true; // Active, valid cache, no expiry date set (e.g., perpetual license)
            }
        }

        // 3. If cache is stale or status is not active, re-validate with Licensing Service API
        $this->logger->info("Audio2Note: License cache stale or status not active. Re-validating with Licensing Service API.");

        // Decrypt sensitive credentials
        // If the key wasn't passed in the constructor, fetch it now.
        // This maintains backward compatibility while allowing for injection.
        if ($this->masterKey === null) {
            $this->logger->warning("Audio2Note: LicenseStatusChecker was not constructed with a master key. Fetching manually.");
            $keyManager = new EncryptionKeyManager();
            $this->masterKey = $keyManager->getKey();
        }

        if (!$this->masterKey) {
            $this->logger->error("Audio2Note: LicenseStatusChecker: CRITICAL - Could not retrieve master encryption key.");
            return false;
        }
        $encryptionService = new EncryptionService($this->masterKey);
        $activationToken = $encryptionService->decrypt($config['encrypted_dlm_activation_token'] ?? '');

        if (empty($activationToken)) {
            $this->logger->error("Audio2Note: LicenseStatusChecker: Critical - Activation token is missing or could not be decrypted.");
            $this->updateLicenseStatus('inactive', null, null, 'Activation token not found.');
            return false;
        }

        try {
            $licenseClient = new LicenseClient($encryptionService);
            $validationResult = $licenseClient->validateLicense($activationToken);

            // 4. Process validation result and update DB
            $newStatus = 'inactive'; // Default to inactive on validation failure
            $newExpiresAt = null;
            $message = xlt('License validation failed.');

            if ($validationResult && isset($validationResult['success']) && $validationResult['success'] === true) {
                $newStatus = 'active';
                $message = xlt('License validated successfully.');
                if (isset($validationResult['data']['license']['expires_at'])) {
                    $newExpiresAt = $validationResult['data']['license']['expires_at'];
                }
            } else {
                 if ($validationResult && isset($validationResult['message'])) {
                    $message .= " " . htmlspecialchars($validationResult['message']);
                } elseif ($validationResult && isset($validationResult['error'])) {
                     $message .= " Error: " . htmlspecialchars($validationResult['error']);
                } else {
                     $message .= " " . xlt('Unknown API response or connection error during validation.');
                }
            }

            $this->updateLicenseStatus($newStatus, $newExpiresAt, $config['encrypted_dlm_activation_token'] ?? null); // Keep existing activation token

            if ($newStatus === 'active') {
                $this->logger->info("Audio2Note: License validation successful. Status: active.");
                return true;
            } else {
                $this->logger->warning("Audio2Note: License validation failed. Status: inactive. Message: " . $message);
                return false;
            }

        } catch (\Exception $e) {
            $this->logger->error("Audio2Note: Exception during license validation: " . $e->getMessage());
            $this->updateLicenseStatus('invalid', null, $config['encrypted_dlm_activation_token'] ?? null); // Preserve existing token on exception
            return false;
        }
    }

    private function updateLicenseStatus(string $status, ?string $expiresAt, ?string $encryptedActivationToken): void
    {
        $now = date('Y-m-d H:i:s');
        sqlStatement(
            "UPDATE `audio2note_config` SET
            `license_status` = ?,
            `license_expires_at` = ?,
            `last_validation_timestamp` = ?,
            `encrypted_dlm_activation_token` = ?,
            `updated_at` = ?
            WHERE `id` = (SELECT id FROM (SELECT id FROM `audio2note_config` LIMIT 1) as temp)", // Use subquery to avoid "You can't specify target table 'audio2note_config' for update in FROM clause"
            [
                $status,
                $expiresAt,
                $now,
                $encryptedActivationToken,
                $now
            ]
        );
    }
}