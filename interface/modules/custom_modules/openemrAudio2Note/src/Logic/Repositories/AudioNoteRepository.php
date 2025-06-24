<?php

namespace OpenEMR\Modules\OpenemrAudio2Note\Logic\Repositories;

class AudioNoteRepository
{
    /**
     * Creates the initial record in the form_audio_to_note table.
     *
     * @param array $data The data to be inserted.
     * @return int The ID of the newly created record.
     * @throws \Exception If the insertion fails.
     */
    public function createInitialRecord(array $data): int
    {
        $setClauses = [];
        $sqlBindings = [];
        foreach ($data as $key => $value) {
            $setClauses[] = "`" . $key . "` = ?";
            $sqlBindings[] = $value;
        }
        $sqlSet = implode(', ', $setClauses);

        $new_form_id = sqlInsert("INSERT INTO `form_audio_to_note` SET " . $sqlSet, $sqlBindings);

        if (!$new_form_id) {
            $adodbError = $GLOBALS['adodb']['db'] ? $GLOBALS['adodb']['db']->ErrorMsg() : "ADODB unavailable";
            error_log("AudioNoteRepository: Failed to get new_form_id from sqlInsert. ADODB Error: " . $adodbError);
            throw new \Exception(xlt("Failed to save initial form record."));
        }

        return (int)$new_form_id;
    }

    /**
     * Links the audio note record to the actual clinical note form ID.
     *
     * @param int $audioNoteFormId The ID of the record in form_audio_to_note.
     * @param int $clinicalNoteFormsId The ID of the record in the forms table (forms.id).
     * @throws \Exception If the update fails.
     */
    public function linkToClinicalNote(int $audioNoteFormId, int $clinicalNoteFormsId): void
    {
        $sql = "UPDATE `form_audio_to_note` SET `linked_forms_id` = ? WHERE `id` = ?";
        $result = sqlStatement($sql, [$clinicalNoteFormsId, $audioNoteFormId]);
        $affectedRows = $GLOBALS['adodb']['db']->Affected_Rows();

        if ($result === false || $affectedRows < 1) {
            $adodbError = $GLOBALS['adodb']['db'] ? $GLOBALS['adodb']['db']->ErrorMsg() : "ADODB unavailable";
            $this->updateStatus($audioNoteFormId, 'link_error', "Failed to link to clinical note form.");
            error_log("AudioNoteRepository CRITICAL: Failed to update linked_forms_id for form_audio_to_note ID {$audioNoteFormId} with target forms.id {$clinicalNoteFormsId}. ADODB Error: " . $adodbError);
            throw new \Exception(xlt("Failed to establish link to the clinical note form."));
        }
    }

    /**
     * Updates the record with the transcription job ID and sets the status to 'processing'.
     *
     * @param int $audioNoteFormId The ID of the record in form_audio_to_note.
     * @param string $jobId The job ID received from the transcription service.
     * @throws \Exception If the update fails.
     */
    public function updateWithJobId(int $audioNoteFormId, string $jobId): void
    {
        $updateSql = "UPDATE `form_audio_to_note` SET `transcription_job_id` = ?, `status` = ? WHERE `id` = ?";
        sqlStatement($updateSql, [$jobId, 'processing', $audioNoteFormId]);
        $affectedRows = $GLOBALS['adodb']['db']->Affected_Rows();

        if ($affectedRows < 1) {
            $adodbError = $GLOBALS['adodb']['db'] ? $GLOBALS['adodb']['db']->ErrorMsg() : "ADODB unavailable";
            $this->updateStatus($audioNoteFormId, 'error', "Failed to link audio processing service job_id " . $jobId . " after initiation.");
            error_log("AudioNoteRepository CRITICAL: UPDATE for transcription_job_id affected 0 rows for form_id " . $audioNoteFormId . ". ADODB Error: " . $adodbError);
            throw new \Exception(xlt("Failed to store transcription Job ID after successful initiation. Please contact support."));
        }
    }

    /**
     * Updates the status and optionally an error message for a record.
     *
     * @param int $audioNoteFormId The ID of the record.
     * @param string $status The new status.
     * @param string|null $errorMessage An optional error message.
     */
    public function updateStatus(int $audioNoteFormId, string $status, ?string $errorMessage = null): void
    {
        if ($errorMessage) {
            $sql = "UPDATE `form_audio_to_note` SET `status` = ?, `error_message` = ? WHERE `id` = ?";
            sqlStatement($sql, [$status, $errorMessage, $audioNoteFormId]);
        } else {
            $sql = "UPDATE `form_audio_to_note` SET `status` = ? WHERE `id` = ?";
            sqlStatement($sql, [$status, $audioNoteFormId]);
        }
    }
}