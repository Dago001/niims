<?php
/**
 * Formats an employee's present posting for display, expanding
 * "Service Headquarters" into "SHQ-<Directorate|Unit|Other>" when one was
 * recorded, so views show the exact location entered on the enrolment form
 * rather than just "Service Headquarters" for every SHQ staff member.
 */
function formatPresentPosting($presentPosting, $directorate = '', $unit = '', $postingOther = '') {
    $presentPosting = trim((string) $presentPosting);

    if ($presentPosting === 'Service Headquarters') {
        $directorate = trim((string) $directorate);
        $unit = trim((string) $unit);
        $postingOther = trim((string) $postingOther);

        if ($directorate !== '' && $directorate !== 'Other') {
            return 'SHQ-' . $directorate;
        }
        if ($unit !== '' && $unit !== 'Other') {
            return 'SHQ-' . $unit;
        }
        if ($postingOther !== '') {
            return 'SHQ-' . $postingOther;
        }
    }

    return $presentPosting;
}
