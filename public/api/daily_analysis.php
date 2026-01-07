<?php
header('Content-Type: application/json');
require_once '../../includes/db.php';

// Mock Data matching the user's screenshot
$data = [
    'highs' => [
        [
            'symbol' => 'ZEC',
            'name' => 'Zcash',
            'price' => 709.50,
            'description' => 'Rise from $7 to $709',
            'analysis' => 'Potential Short Opportunity',
            'action' => 'Consider Shorting',
            'badge' => 'New ATH',
            'color' => 'yellow' // For icon/theme
        ],
        [
            'symbol' => 'SOL',
            'name' => 'Solana',
            'price' => 256.30,
            'description' => 'Surged from $20 to $256',
            'analysis' => 'Strong Overbought Zone',
            'action' => 'Consider Shorting',
            'badge' => 'New ATH',
            'color' => 'purple'
        ],
        [
            'symbol' => 'AVAX',
            'name' => 'Avalanche',
            'price' => 145.60, // Fixed price from image
            'description' => 'Climbed from $10 to $145',
            'analysis' => 'Possible Pullback Expected',
            'action' => 'Consider Shorting',
            'badge' => 'New ATH',
            'color' => 'red'
        ]
    ],
    'lows' => [
        [
            'symbol' => 'FIL',
            'name' => 'Filecoin',
            'price' => 3.20,
            'description' => 'Dropped from $120 to $3',
            'analysis' => 'Potential Rebound Opportunity',
            'action' => 'Consider Buying', // "Consider Rebound" or similar, using Buying for logic
            'badge' => 'New ATL',
            'color' => 'blue'
        ],
        [
            'symbol' => 'VET',
            'name' => 'VeChain',
            'price' => 0.015,
            'description' => 'Fell from $0.12 to $0.015',
            'analysis' => 'Deeply Undervalued',
            'action' => 'Consider Buying',
            'badge' => 'New ATL',
            'color' => 'blue'
        ],
        [
            'symbol' => 'FTM',
            'name' => 'Fantom',
            'price' => 0.18,
            'description' => 'Plunged from $3 to $0.18',
            'analysis' => 'Oversold & Reversal Potential',
            'action' => 'Consider Buying',
            'badge' => 'New ATL',
            'color' => 'teal'
        ]
    ],
    'insights' => [
        [
            'type' => 'hot', // Red
            'title' => 'Overheated Market:',
            'text' => 'Zcash has skyrocketed from $7 to $709, a strong shorting candidate.'
        ],
        [
            'type' => 'cold', // Green/Teal
            'title' => 'Bargain Opportunity:',
            'text' => 'Filecoin has dropped to $3, a potential rebound play.'
        ]
    ]
];

echo json_encode($data);
