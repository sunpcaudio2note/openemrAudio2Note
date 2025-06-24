<?php

require_once(__DIR__ . "/../../globals.php");

use OpenEMR\Common\Csrf\CsrfUtils;

// Get the encounter ID from the global scope.
$encounter_id = $GLOBALS['encounter'];

// Find the id of the 'Recent Visit Summary (from Audio)' form for this encounter.
$form_data = sqlQuery("SELECT id FROM forms WHERE encounter = ? AND form_name = 'Recent Visit Summary (from Audio)' ORDER BY id DESC LIMIT 1", [$encounter_id]);
$form_id = $form_data['id'] ?? null;

if (empty($form_id)) {
    die("Could not find the summary form for this encounter.");
}

// Fetch the summary text from the local database
$summaryData = sqlQuery("SELECT summary_text FROM form_recent_visit_summary WHERE id = ?", [$form_id]);

$summaryText = $summaryData['summary_text'] ?? 'No summary available. (Debug: Query returned no text).';

// Simple HTML output
?>
<!DOCTYPE html>
<html>
<head>
    <title>Recent Visit Summary</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        pre { white-space: pre-wrap; word-wrap: break-word; }
    </style>
</head>
<body>
    <h1>Recent Visit Summary</h1>
    <hr>
    <pre><?php echo htmlspecialchars($summaryText, ENT_QUOTES, 'UTF-8'); ?></pre>
</body>
</html>