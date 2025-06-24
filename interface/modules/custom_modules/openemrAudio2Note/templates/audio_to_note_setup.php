<?php
/**
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @author    Sun PC Solutions LLC
 * @copyright Copyright (c) 2025 Sun PC Solutions LLC
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

// globals.php is expected to be loaded by moduleConfig.php, which should set up the environment.

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Core\Header;

// Call Header::setupHeader() early to ensure environment, including session and globals like site_id, is fully initialized.
// This is typically done before any significant logic or HTML output.
// Note: This might output headers early. If issues arise, this might need adjustment
// or a more targeted way to ensure $GLOBALS['site_id'] is populated.
if (class_exists('OpenEMR\Core\Header')) {
    Header::setupHeader();
}
// use OpenEMR\Common\Crypto\CryptoGen;
// Replaced by EncryptionService
use OpenEMR\Modules\OpenemrAudio2Note\Logic\EncryptionService;
use OpenEMR\Modules\OpenemrAudio2Note\Logic\LicenseClient;
use OpenEMR\Modules\OpenemrAudio2Note\Logic\EncryptionKeyManager;
// Assuming this will be created
use OpenEMR\Modules\OpenemrAudio2Note\Setup;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CsrfUtils::verifyCsrfToken($_POST["csrf_token_form"])) {
        die(xlt('CSRF token validation failed. Please try again.'));
    }

    // Sanitize inputs
    $license_key = filter_input(INPUT_POST, 'audio_note_license_key', FILTER_UNSAFE_RAW);
    $consumer_key = filter_input(INPUT_POST, 'audio_note_consumer_key', FILTER_UNSAFE_RAW);
    $consumer_secret = filter_input(INPUT_POST, 'audio_note_consumer_secret', FILTER_UNSAFE_RAW);

    // Adjusted condition as consumer key/secret are now hardcoded
    if (empty($license_key) || empty($consumer_key) || empty($consumer_secret)) {
        echo "<div class='alert alert-danger'>" . xlt('All fields are required.') . "</div>";
    } else {
        try {
            // Fetch the master key ONCE.
            $keyManager = new EncryptionKeyManager();
            $masterKey = $keyManager->getKey();
            if (!$masterKey) {
                throw new \Exception(xlt('CRITICAL: Could not retrieve the master encryption key.'));
            }

            // Initialize Encryption Service with the key.
            $encryptionService = new EncryptionService($masterKey);

            // Encrypt sensitive data
            $encrypted_license_key = $encryptionService->encrypt($license_key);
            $encrypted_consumer_key = $encryptionService->encrypt($consumer_key);
            $encrypted_consumer_secret = $encryptionService->encrypt($consumer_secret);

            if ($encrypted_license_key === false || $encrypted_consumer_key === false || $encrypted_consumer_secret === false) {
                throw new \Exception(xlt('Failed to encrypt credentials. Check OpenEMR logs.'));
            }

            $instanceUuid = Setup::getStoredInstanceUuid();
            if (empty($instanceUuid)) {
                 throw new \Exception(xlt('Failed to retrieve OpenEMR instance UUID. Module might not be installed correctly.'));
            }

            // Compute Effective Instance Identifier
            $site_id_for_hash = $GLOBALS['site_id'] ?? 'default'; // Fallback if somehow not set
            $effective_instance_identifier = hash('sha256', $instanceUuid . $site_id_for_hash);

            $config_row_id = 1; // Assuming a single configuration row
            $now = date('Y-m-d H:i:s');

            $resultForExistingCheck = sqlQuery("SELECT id FROM `audio2note_config` WHERE id = ?", [$config_row_id]);
            $configRowActuallyExists = ($resultForExistingCheck && ( (is_array($resultForExistingCheck) && !empty($resultForExistingCheck['id'])) || (is_object($resultForExistingCheck) && method_exists($resultForExistingCheck, 'RecordCount') && $resultForExistingCheck->RecordCount() > 0) ));

            if ($configRowActuallyExists) {
                sqlStatement(
                    "UPDATE `audio2note_config` SET
                    `openemr_internal_random_uuid` = ?, `effective_instance_identifier` = ?,
                    `encrypted_license_key` = ?,
                    `encrypted_license_consumer_key` = ?, `encrypted_license_consumer_secret` = ?,
                    `site_id` = ?, `updated_at` = ?
                    WHERE `id` = ?",
                    [
                        $instanceUuid, $effective_instance_identifier,
                        $encrypted_license_key,
                        $encrypted_consumer_key, $encrypted_consumer_secret,
                        $GLOBALS['site_id'], $now,
                        $config_row_id
                    ]
                );
            } else {
                // This path indicates an issue if Setup::install() didn't create the initial row.
                error_log("Audio2Note Module Configuration WARNING: audio2note_config row with ID {$config_row_id} not found. Attempting to insert.");
                sqlStatement(
                    "INSERT INTO `audio2note_config` (
                    `id`, `openemr_internal_random_uuid`, `effective_instance_identifier`,
                    `encrypted_license_key`,
                    `encrypted_license_consumer_key`, `encrypted_license_consumer_secret`,
                    `site_id`, `created_at`, `updated_at`
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        $config_row_id, $instanceUuid, $effective_instance_identifier,
                        $encrypted_license_key,
                        $encrypted_consumer_key, $encrypted_consumer_secret,
                        $GLOBALS['site_id'], $now, $now
                    ]
                );
            }
            // Remove old global if it exists
            sqlStatement("DELETE FROM `globals` WHERE `gl_name` = 'audio_note_backend_audio_process_base_url'");
            // Also remove old global for license_api_base_url if it existed
            sqlStatement("DELETE FROM `globals` WHERE `gl_name` = 'audio_note_license_api_base_url'");


            // Call Licensing Service API for Activation
            // LicenseClient constructor now uses its own hardcoded URL and does not need consumer key/secret.
            $licenseClient = new LicenseClient($encryptionService);
            $activationResult = $licenseClient->activateLicense($license_key, $effective_instance_identifier, $consumer_key, $consumer_secret);

            // Handle Activation Result
            $license_status_val = 'inactive';
            $license_expires_at_val = null;
            $encrypted_dlm_token_val = null;
            $message = xlt('License activation failed.');
            $alertClass = 'alert-danger';

            if ($activationResult && isset($activationResult['success']) && $activationResult['success'] === true && isset($activationResult['data'])) {
                $license_status_val = 'active';
                $message = xlt('License activated successfully.');
                if (isset($activationResult['data']['license']['expires_at'])) {
                    $message .= " " . xlt('Expires:') . " " . $activationResult['data']['license']['expires_at'];
                    $license_expires_at_val = $activationResult['data']['license']['expires_at'];
                }
                if (isset($activationResult['data']['token'])) {
                    $encrypted_dlm_token_val = $encryptionService->encrypt($activationResult['data']['token']);
                }
                $alertClass = 'alert-success';
            } else {
                if ($activationResult && isset($activationResult['message'])) {
                    $message .= " " . htmlspecialchars($activationResult['message']);
                } elseif ($activationResult && isset($activationResult['code'])) {
                     $message .= " Error code: " . htmlspecialchars($activationResult['code']);
                     if(isset($activationResult['data']['status'])) {
                         $message .= " Status: " . htmlspecialchars($activationResult['data']['status']);
                     }
                } else {
                     $message .= " " . xlt('Unknown API response or connection error.');
                }
            }
            // Update DB with activation details
            sqlStatement(
                "UPDATE `audio2note_config` SET
                `license_status` = ?, `license_expires_at` = ?, `encrypted_dlm_activation_token` = ?, `updated_at` = ?
                WHERE `id` = ?",
                [$license_status_val, $license_expires_at_val, $encrypted_dlm_token_val, $now, $config_row_id]
            );

            echo "<div class='alert {$alertClass}'>" . $message . "</div>";

        } catch (\Exception $e) {
            echo "<div class='alert alert-danger'>" . xlt('Error processing configuration:') . " " . htmlspecialchars($e->getMessage()) . "</div>";
            error_log("Audio2Note Module Configuration Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
        }
    }
}

// Load current settings for display
$currentConfig = sqlQuery("SELECT * FROM `audio2note_config` WHERE id = 1 LIMIT 1");
// $decrypted_backend_audio_process_base_url = ''; // Removed, URL is static
// $decrypted_license_api_base_url = ''; // Removed, URL is static

// Decryption for display is removed to prevent fatal errors when the full framework is not loaded.
// The form fields will be intentionally left blank for security.
$decrypted_consumer_key = '';
$decrypted_consumer_secret = '';

$license_status_display = $currentConfig['license_status'] ?? xlt('Not Configured');
$license_expires_at_display = $currentConfig['license_expires_at'] ?? xlt('N/A');
if ($license_expires_at_display && $license_expires_at_display !== xlt('N/A')) {
    $license_expires_at_display = oeFormatShortDate($license_expires_at_display) . " " . oeFormatTime($license_expires_at_display);
}

?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo xlt('Audio2Note Configuration'); ?></title>
    <?php Header::setupHeader(); ?>
    <style>
        body { padding: 20px; }
        .container { max-width: 800px; margin: auto; }
        .license-info { margin-top: 15px; padding: 10px; border: 1px solid #eee; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <h3><?php echo xlt('Audio2Note Configuration'); ?></h3>
        <hr>
        <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
            <input type="hidden" name="csrf_token_form" value="<?php echo attr(CsrfUtils::collectCsrfToken()); ?>" />


            <div class="form-group">
                <label for="audio_note_consumer_key"><?php echo xlt('API Consumer Key'); ?></label>
                <input type="password" class="form-control" id="audio_note_consumer_key" name="audio_note_consumer_key" value="<?php echo htmlspecialchars($decrypted_consumer_key); ?>" autocomplete="new-password" required>
                <small class="form-text text-muted"><?php echo xlt('Enter the API Consumer Key for the transcription service.'); ?></small>
            </div>

            <div class="form-group">
                <label for="audio_note_consumer_secret"><?php echo xlt('API Consumer Secret'); ?></label>
                <input type="password" class="form-control" id="audio_note_consumer_secret" name="audio_note_consumer_secret" value="<?php echo htmlspecialchars($decrypted_consumer_secret); ?>" autocomplete="new-password" required>
                <small class="form-text text-muted"><?php echo xlt('Enter the API Consumer Secret for the transcription service.'); ?></small>
            </div>

            <div class="form-group">
                <label for="audio_note_license_key"><?php echo xlt('License Key'); ?></label>
                <input type="password" class="form-.form-control" id="audio_note_license_key" name="audio_note_license_key" value="" autocomplete="new-password" required>
                <small class="form-text text-muted"><?php echo xlt('Enter the License Key provided by the module vendor.'); ?></small>
            </div>

            <button type="submit" class="btn btn-primary"><?php echo xlt('Save and Activate'); ?></button>
        </form>

        <div class="license-info">
            <h4><?php echo xlt('Current License Status'); ?></h4>
            <p><strong><?php echo xlt('Status:'); ?></strong> <span id="license-status-text"><?php echo htmlspecialchars($license_status_display); ?></span></p>
            <p><strong><?php echo xlt('Expires:'); ?></strong> <span id="license-expiry-text"><?php echo htmlspecialchars($license_expires_at_display); ?></span></p>
        </div>
    </div>
</body>
</html>