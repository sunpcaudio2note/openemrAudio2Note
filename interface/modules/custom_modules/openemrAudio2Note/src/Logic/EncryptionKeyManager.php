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

class EncryptionKeyManager
{
    public function __construct()
    {
        // The constructor is empty.
    }

    /**
     * Retrieves the encryption key from the database using direct SQL queries.
     * If the key does not exist, it generates a new one, stores it, and returns it.
     *
     * @return string The encryption key.
     * @throws \Exception If key generation or database operations fail.
     */
    public function getKey(): string
    {
        // We assume a single configuration row with id=1, as created by the installer
        $configId = 1;

        // Attempt to retrieve the existing key from the module's dedicated config table.
        // This logic is based on the resolution from debughistory.md (2025-06-20)
        $row = sqlQuery("SELECT `encryption_key_raw` FROM `audio2note_config` WHERE `id` = ?", [$configId]);
        $key = $row['encryption_key_raw'] ?? null;

        if (!empty($key)) {
        } else {
            try {
                $key = random_bytes(32); // SODIUM_CRYPTO_SECRETBOX_KEYBYTES is 32

                // Store the new key in the database.
                $updateQuery = "UPDATE `audio2note_config` SET `encryption_key_raw` = ? WHERE `id` = ?";
                sqlStatement($updateQuery, [$key, $configId]);
            } catch (\Exception $e) {
                error_log("OpenemrAudio2Note EncryptionKeyManager: CRITICAL - Failed to generate or store new encryption key: " . $e->getMessage());
                throw $e;
            }
        }

        return $key;
    }
}