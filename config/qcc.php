<?php

return [

    'steps' => [
        /*
        | Whether a step can only be submitted after the one before it is approved.
        | The BRD has not decided this yet, so the default shows the steps in order
        | but does not force it.
        */
        'require_sequence' => (bool) env('QCC_STEPS_REQUIRE_SEQUENCE', false),
    ],

    'audit' => [
        /*
        | Days an audit entry stays in the audit_logs table. Older entries are written to
        | storage/app/private/audit-archive first and then removed by `qcc:audit:prune`.
        | The BRD has not decided audit log retention yet.
        */
        'retention_days' => (int) env('QCC_AUDIT_RETENTION_DAYS', 30),
    ],

    'biweekly' => [
        /*
        | DEC-08 is open: does every bi-weekly report need approval? Until it is
        | decided a submitted report waits for a reviewer. Turn this off to accept
        | submitted reports straight away.
        */
        'require_review' => (bool) env('QCC_BIWEEKLY_REQUIRE_REVIEW', true),
    ],

];
