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

    'biweekly' => [
        /*
        | DEC-08 is open: does every bi-weekly report need approval? Until it is
        | decided a submitted report waits for a reviewer. Turn this off to accept
        | submitted reports straight away.
        */
        'require_review' => (bool) env('QCC_BIWEEKLY_REQUIRE_REVIEW', true),
    ],

];
