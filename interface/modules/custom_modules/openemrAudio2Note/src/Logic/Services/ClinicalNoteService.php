<?php

namespace OpenEMR\Modules\OpenemrAudio2Note\Logic\Services;

use OpenEMR\Modules\OpenemrAudio2Note\Logic\Repositories\AudioNoteRepository;

class ClinicalNoteService
{
    private $audioNoteRepository;

    public function __construct(AudioNoteRepository $audioNoteRepository)
    {
        $this->audioNoteRepository = $audioNoteRepository;
    }

    /**
     * Gets the details needed to create a clinical note form.
     *
     * @param string $noteType The type of note ('soap_audio', 'history_physical', 'summary').
     * @return array An array containing the 'formTitle' and 'formName'.
     */
    public function getNoteCreationDetails(string $noteType): array
    {
        return [
            'formTitle' => $this->getFormTitle($noteType),
            'formName' => $this->getFormName($noteType),
        ];
    }

    /**
     * Creates a placeholder record for a summary note.
     * This is kept in the service as it's a specific database interaction.
     *
     * @param int $formsId The ID from the 'forms' table.
     * @param int $patientId The patient ID.
     * @param int $encounterId The encounter ID.
     */
    public function createSummaryPlaceholder(int $formsId, int $patientId, int $encounterId): void
    {
        $sql = "INSERT INTO `form_recent_visit_summary` (`id`, `pid`, `encounter`) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE id=id";
        sqlStatement($sql, [$formsId, $patientId, $encounterId]);
    }

    private function getFormTitle(string $noteType): string
    {
        switch ($noteType) {
            case 'history_physical':
                return "History and Physical Note";
            case 'summary':
                return "Recent Visit Summary";
            default:
                return "Audio2Note SOAP";
        }
    }

    private function getFormName(string $noteType): string
    {
        switch ($noteType) {
            case 'history_physical':
                return "history_physical";
            case 'summary':
                return "recent_visit_summary";
            default:
                return "soap_audio";
        }
    }

}