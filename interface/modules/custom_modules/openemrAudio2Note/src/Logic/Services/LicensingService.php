<?php

namespace OpenEMR\Modules\OpenemrAudio2Note\Logic\Services;

use OpenEMR\Modules\OpenemrAudio2Note\Logic\LicenseStatusChecker;
use OpenEMR\Modules\OpenemrAudio2Note\Logic\EncryptionKeyManager;
use OpenEMR\Modules\OpenemrAudio2Note\Logic\LicenseEncryptionService;

class LicensingService
{
    private $masterKey;

    public function __construct()
    {
        $keyManager = new EncryptionKeyManager();
        $this->masterKey = $keyManager->getKey();
        if (!$this->masterKey) {
            throw new \Exception(xlt('CRITICAL: Could not retrieve the master encryption key. The module cannot proceed.'));
        }
    }

    /**
     * Checks if the license is active and returns the decrypted license key.
     *
     * @return string The decrypted license key.
     * @throws \Exception If the license is inactive or the key cannot be retrieved.
     */
    public function checkAndGetKey(): string
    {
        $licenseChecker = new LicenseStatusChecker($this->masterKey);
        if (!$licenseChecker->isLicenseActive()) {
            throw new \Exception(xlt("Audio transcription feature is not licensed or license is inactive. Please configure your license in the module settings."));
        }

        $configRow = sqlQuery("SELECT encrypted_license_key FROM audio2note_config LIMIT 1");
        $encryptedLicenseKey = $configRow['encrypted_license_key'] ?? null;

        if (empty($encryptedLicenseKey)) {
            throw new \Exception(xlt("The module is not yet configured with a valid license key. Please configure the module in Administration -> Modules -> Manage Modules."));
        }

        $licenseEncryptionService = new LicenseEncryptionService($this->masterKey);
        $licenseKey = $licenseEncryptionService->decrypt($encryptedLicenseKey);

        if (empty($licenseKey)) {
            throw new \Exception(xlt("Failed to decrypt the license key. Please check your module configuration."));
        }

        return $licenseKey;
    }
}