<?php

function recent_visit_summary_report($pid, $encounter, $cols, $id)
{
    $form_data = formFetch("form_recent_visit_summary", $id);

    if ($form_data) {
        echo nl2br(htmlspecialchars($form_data['summary_text']));
    }
}