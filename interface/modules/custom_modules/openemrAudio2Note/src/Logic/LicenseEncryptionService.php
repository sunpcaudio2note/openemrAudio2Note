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

use OpenEMR\Common\System\System;

class LicenseEncryptionService
{
    private string $key;

    public function __construct(string $key)
    {
        $this->key = $key;
    }

    public function encrypt(string $data): ?string
    {
        if (empty($this->key)) {
            System::logError("Audio2Note LicenseEncryptionService: Encryption failed. Master key not provided.");
            return null;
        }

        try {
            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $encrypted = sodium_crypto_secretbox($data, $nonce, $this->key);

            if ($encrypted === false) {
                 System::logError("Audio2Note LicenseEncryptionService: sodium_crypto_secretbox encryption failed.");
                 return null;
            }
            // Prepend nonce to the ciphertext for decryption.
            return base64_encode($nonce . $encrypted);
        } catch (\Exception $e) {
            System::logError("Audio2Note LicenseEncryptionService: Encryption exception: " . $e->getMessage());
            return null;
        }
    }

    public function decrypt(string $encryptedData): ?string
    {
        if (empty($this->key)) {
            System::logError("Audio2Note LicenseEncryptionService: Decryption failed. Master key not provided.");
            return null;
        }

        $decoded = base64_decode($encryptedData);
        if ($decoded === false) {
             return null;
        }

        // Check if decoded data is long enough to contain nonce and MAC.
        if (strlen($decoded) < (SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES)) {
             return null;
        }

        $nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        if ($ciphertext === false || $ciphertext === '') {
            return null;
        }

        $decrypted = sodium_crypto_secretbox_open($ciphertext, $nonce, $this->key);

        if ($decrypted === false) {
            return null;
        }

        return $decrypted;
    }
}