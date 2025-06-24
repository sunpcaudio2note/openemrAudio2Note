<?php

namespace OpenEMR\Modules\OpenemrAudio2Note\Logic\Services;

class PatientHistoryService
{
    /**
     * Fetches the 3 most recent clinical notes (SOAP or H&P) for a patient
     * and formats them into a single text block.
     *
     * @param int $patientId The patient's ID (pid).
     * @return string The formatted text of recent notes.
     */
    public function getRecentNotesAsText(int $patientId): string
    {
        $historicalNotesText = "";

        $sqlRecentNotes = "
            SELECT f.form_id, f.form_name, f.date
            FROM forms f
            WHERE f.pid = ? AND f.deleted = 0 AND (f.form_name LIKE '%SOAP Note%' OR f.form_name LIKE '%History and Physical%' OR f.form_name LIKE '%Audio2Note SOAP%')
            ORDER BY f.date DESC
            LIMIT 3
        ";
        $recentNotesResult = sqlStatement($sqlRecentNotes, [$patientId]);

        $allNotes = [];
        if ($recentNotesResult) {
            while ($row = sqlFetchArray($recentNotesResult)) {
                $allNotes[] = $row;
            }
        }

        foreach ($allNotes as $noteInfo) {
            $specific_form_id = $noteInfo['form_id'];
            $form_name = $noteInfo['form_name'];

            if (strpos($form_name, 'Audio2Note SOAP') !== false) {
                $historicalNotesText .= $this->getSoapAudioNoteText($specific_form_id);
            } elseif (strpos($form_name, 'SOAP Note') !== false) {
                $historicalNotesText .= $this->getSoapNoteText($specific_form_id);
            } elseif (strpos($form_name, 'History and Physical') !== false) {
                $historicalNotesText .= $this->getHpNoteText($specific_form_id);
            }
        }

        return $historicalNotesText;
    }

    /**
     * Fetches and formats a single SOAP note.
     *
     * @param int $formId The form ID.
     * @return string The formatted SOAP note text.
     */
    private function getSoapNoteText(int $formId): string
    {
        $sqlSoap = "SELECT subjective, objective, assessment FROM form_soap WHERE id = ?";
        $soapRow = sqlQuery($sqlSoap, [$formId]);
        if ($soapRow) {
            return "SOAP Note:\n" .
                   "Subjective: " . ($soapRow['subjective'] ?? '') . "\n" .
                   "Objective: " . ($soapRow['objective'] ?? '') . "\n" .
                   "Assessment: " . ($soapRow['assessment'] ?? '') . "\n---\n";
        }
        return "";
    }

    private function getSoapAudioNoteText(int $formId): string
    {
        $sqlSoap = "SELECT subjective, objective, assessment, plan FROM form_soap_audio WHERE id = ?";
        $soapRow = sqlQuery($sqlSoap, [$formId]);
        if ($soapRow) {
            return "Audio2Note SOAP:\n" .
                   "Subjective: " . ($soapRow['subjective'] ?? '') . "\n" .
                   "Objective: " . ($soapRow['objective'] ?? '') . "\n" .
                   "Assessment: " . ($soapRow['assessment'] ?? '') . "\n" .
                   "Plan: " . ($soapRow['plan'] ?? '') . "\n---\n";
        }
        return "";
    }

    /**
     * Fetches and formats a single History and Physical note.
     *
     * @param int $formId The form ID.
     * @return string The formatted H&P note text.
     */
    private function getHpNoteText(int $formId): string
    {
        $sqlHp = "SELECT history_physical, plan FROM form_history_physical WHERE id = ?";
        $hpRow = sqlQuery($sqlHp, [$formId]);
        if ($hpRow) {
            $noteText = "History and Physical Note:\n" . ($hpRow['history_physical'] ?? '');
            if (!empty($hpRow['plan'])) {
                $noteText .= "\nPlan:\n" . $hpRow['plan'];
            }
            return $noteText . "\n---\n";
        }
        return "";
    }
}