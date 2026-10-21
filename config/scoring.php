<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Evidence gate
    |--------------------------------------------------------------------------
    |
    | Minimum number of ready evidence items a project needs before AI scoring
    | may run. The official minimum per scoring type is still an open decision
    | (DEC-10), so this is a single configurable floor for now.
    |
    */

    'minimum_evidence' => (int) env('SCORING_MINIMUM_EVIDENCE', 1),

    'queue' => env('SCORING_QUEUE', 'ai'),

    'prompt_version' => 'v1',

    /*
    |--------------------------------------------------------------------------
    | OpenAI
    |--------------------------------------------------------------------------
    |
    | Called from the backend only. The model is deliberately not defaulted:
    | pin one that supports structured output in the environment.
    |
    */

    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_SCORING_MODEL'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'timeout' => (int) env('OPENAI_SCORING_TIMEOUT', 120),
    ],

];
