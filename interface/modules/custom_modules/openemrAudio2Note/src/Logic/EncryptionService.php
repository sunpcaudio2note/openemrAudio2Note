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

class EncryptionService
{
    private string $key;

    public function __construct(string $key)
    {
        if (!extension_loaded('sodium')) {
            throw new \Exception("The 'sodium' PHP extension is required for encryption but is not enabled. Please contact your system administrator to enable it.");
        }
        $this->key = $key;
    }

    /**
     * Encrypts data using sodium_crypto_secretbox.
     * The nonce is prepended to the ciphertext.
     *
     * @param string $data The plaintext data to encrypt.
     * @return string|false The base64 encoded nonce+ciphertext, or false on failure.
     */
    public function encrypt(string $data): string|false
    {
        if (empty($this->key)) {
            error_log("OpenemrAudio2Note EncryptionService: CRITICAL - Encryption key not provided for encryption.");
            return false;
        }

        try {
            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $ciphertext = sodium_crypto_secretbox($data, $nonce, $this->key);
            return base64_encode($nonce . $ciphertext);
        } catch (\Exception $e) {
            error_log("OpenemrAudio2Note EncryptionService: Encryption failed - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Decrypts data encrypted with sodium_crypto_secretbox.
     * Expects the nonce to be prepended to the ciphertext.
     *
     * @param string $base64EncryptedDataWithNonce The base64 encoded nonce+ciphertext.
     * @return string|false The decrypted plaintext data, or false on failure (e.g., bad key, tampered).
     */
    public function decrypt(string $base64EncryptedDataWithNonce): string|false
    {
        if (empty($this->key)) {
            error_log("OpenemrAudio2Note EncryptionService: CRITICAL - Encryption key not provided for decryption.");
            return false;
        }

        $decodedData = base64_decode($base64EncryptedDataWithNonce, true);
        if ($decodedData === false) {
            return false;
        }

        if (strlen($decodedData) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return false;
        }

        $nonce = substr($decodedData, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = substr($decodedData, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        if ($ciphertext === false || $ciphertext === '') {
            return false;
        }

        try {
            $decrypted = sodium_crypto_secretbox_open($ciphertext, $nonce, $this->key);
            if ($decrypted === false) {
            }
            return $decrypted;
        } catch (\Exception $e) {
            error_log("OpenemrAudio2Note EncryptionService: Decryption exception - " . $e->getMessage());
            return false;
        }
    }
}