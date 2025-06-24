<?php

namespace OpenEMR\Modules\openemrAudio2Note\Services;

use OpenEMR\Modules\OpenemrAudio2Note\Logic\EncryptionService;
use OpenEMR\Modules\OpenemrAudio2Note\Logic\EncryptionKeyManager;

class ConfigurationService
{
    private $config = [];
    private $encryptionService;

    public function __construct()
    {
        $this->loadConfigFromFile();
        $this->loadConfigFromDb();
        $this->initializeEncryptionService();
    }

    private function loadConfigFromFile()
    {
        $configFilePath = dirname(__DIR__, 2) . '/config.php';
        if (file_exists($configFilePath)) {
            // The file returns the $openemrAudio2NoteConfig array
            include $configFilePath;
            if (isset($openemrAudio2NoteConfig) && is_array($openemrAudio2NoteConfig)) {
                $this->config = $openemrAudio2NoteConfig;
            }
        }
    }

    private function loadConfigFromDb()
    {
        // Assuming a single row configuration with id = 1
        $dbConfig = sqlQuery("SELECT * FROM `audio2note_config` WHERE id = 1 LIMIT 1");
        if ($dbConfig) {
            // Merge database config over file config
            $this->config = array_merge($this->config, $dbConfig);
        }
    }

    private function initializeEncryptionService()
    {
        try {
            $keyManager = new EncryptionKeyManager();
            $masterKey = $keyManager->getKey();
            if ($masterKey) {
                $this->encryptionService = new EncryptionService($masterKey);
            }
        } catch (\Exception $e) {
            error_log("ConfigurationService: Failed to initialize EncryptionService: " . $e->getMessage());
            $this->encryptionService = null;
        }
    }

    public function get(string $key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }

    public function getDecrypted(string $key, $default = null)
    {
        $encryptedValue = $this->get($key);
        if ($encryptedValue && $this->encryptionService) {
            try {
                $decrypted = $this->encryptionService->decrypt($encryptedValue);
                return $decrypted !== false ? $decrypted : $default;
            } catch (\Exception $e) {
                error_log("ConfigurationService: Failed to decrypt key '$key': " . $e->getMessage());
                return $default;
            }
        }
        return $default;
    }
}