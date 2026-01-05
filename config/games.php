<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Tongkrongan Games Configuration
    |--------------------------------------------------------------------------
    */

    // Room Settings
    'room' => [
        'code_length' => 6,
        'max_players' => [
            'uno' => 10,
            'undercover' => 10,
            'last_letter' => 8,
            'abc_dasar' => 10,
        ],
        'min_players' => [
            'uno' => 2,
            'undercover' => 3,
            'last_letter' => 2,
            'abc_dasar' => 2,
        ],
    ],

    // Game Settings
    'games' => [
        'uno' => [
            'starting_cards' => 7,
            'turn_time_limit' => 30, // detik
            'draw_stack_limit' => 4,
        ],
        'undercover' => [
            'discussion_time' => 60, // detik per ronde
            'voting_time' => 30,
            'undercover_ratio' => 0.2, // 20% pemain jadi undercover
        ],
        'last_letter' => [
            'turn_time_limit' => 15, // detik
            'min_word_length' => 2,
        ],
        'abc_dasar' => [
            'answer_time' => 10, // detik
            'voting_time' => 15,
            'rounds' => 5,
        ],
    ],

    // KBBI API
    'kbbi' => [
        'base_url' => env('KBBI_API_URL', 'https://new-kbbi-api.herokuapp.com'),
        'timeout' => 5, // detik
    ],
];
