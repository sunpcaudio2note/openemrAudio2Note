<?php

namespace OpenEMR\Modules\OpenemrAudio2Note\Logic\Manager;

class OpenEMRSummaryNoteManager
{
    private $pid;
    private $encounterId;
    private $formId; // This is the ID from form_audio_to_note
    private $userId; // User performing the action

    public function __construct(int $pid, int $encounterId, int $formId, int $userId)
    {
        $this->pid = $pid;
        $this->encounterId = $encounterId;
        $this->formId = $formId;
        $this->userId = $userId;
    }

    /**
     * Saves the summary text into the form_recent_visit_summary table.
     *
     * @param array $noteData The data containing the summary text.
     * @return bool True on success, false on failure.
     */
    public function saveNoteData(array $noteData): bool
    {
        $audioNoteMetaData = sqlQuery("SELECT linked_forms_id, pid, encounter FROM form_audio_to_note WHERE id = ?", [$this->formId]);
        if (!$audioNoteMetaData || empty($audioNoteMetaData['linked_forms_id'])) {
            error_log("OpenEMRSummaryNoteManager CRITICAL: Could not retrieve linked_forms_id for form_audio_to_note ID {$this->formId}.");
            return false;
        }
        $targetFormsId = (int)$audioNoteMetaData['linked_forms_id'];
        $this->pid = (int)$audioNoteMetaData['pid'];
        $this->encounterId = (int)$audioNoteMetaData['encounter'];

        $summaryText = $noteData['anp_content'] ?? '';

        $userSql = "SELECT username, facility_id FROM users WHERE id = ?";
        $userData = sqlQuery($userSql, [$this->userId]);
        $user = $userData['username'] ?? 'admin';
        $groupname = $GLOBALS['OE_SITE_ID'] ?? ($userData['facility_id'] ?? 'Default');

        $currentDateTime = date('Y-m-d H:i:s');
        $authorized = 1;
        $activity = 1;

        // The placeholder record is created by save.php, so we only ever need to update.
        $updateSql = "UPDATE form_recent_visit_summary SET
                        date = ?, user = ?, groupname = ?, authorized = ?, activity = ?,
                        summary_text = ?
                      WHERE id = ?";
        $bindings = [
            $currentDateTime, $user, $groupname, $authorized, $activity,
            $summaryText,
            $targetFormsId
        ];
        $updateResult = sqlStatement($updateSql, $bindings);
        if ($updateResult === false) {
            error_log("OpenEMRSummaryNoteManager CRITICAL: Failed to update form_recent_visit_summary ID {$targetFormsId}. SQL Error: " . $GLOBALS['adodb']['db']->ErrorMsg());
            return false;
        }

        $updateFormsTableSql = "UPDATE forms SET deleted = 0, formdir = 'recent_visit_summary', form_name = 'Recent Visit Summary (from Audio)', form_id = ? WHERE id = ?";
        $updateFormsResult = sqlStatement($updateFormsTableSql, [$targetFormsId, $targetFormsId]);
        if ($updateFormsResult === false) {
            error_log("OpenEMRSummaryNoteManager WARNING: Failed to update 'forms' table for ID {$targetFormsId}.");
        }

        return true;
    }
}