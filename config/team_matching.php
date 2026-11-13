<?php

return [

    'prompt_version' => 'v3',

    /*
    | Seconds a project must wait before it may ask for a new suggestion.
    */
    'cooldown_seconds' => (int) env('TEAM_MATCHING_COOLDOWN', 60),

    'request_timeout_seconds' => (int) env('TEAM_MATCHING_TIMEOUT', 45),

    'queue' => env('TEAM_MATCHING_QUEUE', env('SCORING_QUEUE', 'ai')),

];
