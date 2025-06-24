<?php

namespace OpenEMR\Modules\OpenemrAudio2Note\Logic\Services;

class FileUploadService
{
    /**
     * Handles the audio file upload process.
     *
     * @param array|null $file The $_FILES['audio_file'] array.
     * @param string $selectedNoteType The type of note being created ('soap', 'summary', etc.).
     * @return array An array containing 'tempFilePath' and 'originalFilename'.
     * @throws \Exception If the upload fails or the file type is invalid.
     */
    public function handleUpload(?array $file, string $selectedNoteType): array
    {
        if ($selectedNoteType === 'summary') {
            return $this->createDummyWavFile();
        }

        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            throw new \Exception("Audio file upload failed. Error code: " . ($file['error'] ?? 'Unknown'));
        }

        $this->validateFileType($file['type']);

        return [
            'tempFilePath' => $file['tmp_name'],
            'originalFilename' => basename($file['name']),
        ];
    }

    /**
     * Validates the MIME type of the uploaded file.
     *
     * @param string $fileType The MIME type of the file.
     * @throws \Exception If the file type is not allowed.
     */
    private function validateFileType(string $fileType): void
    {
        $allowedTypes = ['audio/mpeg', 'audio/wav', 'audio/ogg', 'audio/mp4', 'audio/aac', 'audio/webm'];
        if (!in_array($fileType, $allowedTypes)) {
            throw new \Exception("Invalid audio file type: " . htmlspecialchars($fileType));
        }
    }

    /**
     * Creates a temporary dummy WAV file for summary requests.
     *
     * @return array An array containing 'tempFilePath' and 'originalFilename'.
     */
    private function createDummyWavFile(): array
    {
        $tempFilePath = tempnam(sys_get_temp_dir(), 'dummy_audio_') . '.wav';
        $originalFilename = 'summary_request.wav';

        // Minimal valid WAV header for a silent, short audio clip
        $wavHeader = pack(
            'A4VA4A4VvvVVvvA4V',
            'RIFF',
            36, // Filesize - 8
            'WAVE',
            'fmt ',
            16, // Subchunk1Size
            1,  // AudioFormat (PCM)
            1,  // NumChannels
            8000, // SampleRate
            8000, // ByteRate
            1,  // BlockAlign
            8,  // BitsPerSample
            'data',
            0   // Subchunk2Size (0 data bytes)
        );

        file_put_contents($tempFilePath, $wavHeader);

        return [
            'tempFilePath' => $tempFilePath,
            'originalFilename' => $originalFilename,
        ];
    }
}