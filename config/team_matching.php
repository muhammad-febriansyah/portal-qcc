<?php

return [

    'prompt_version' => 'v1',

    /*
    | Seconds a project must wait before it may ask for a new suggestion.
    */
    'cooldown_seconds' => (int) env('TEAM_MATCHING_COOLDOWN', 60),

    'queue' => env('SCORING_QUEUE', 'ai'),

];
