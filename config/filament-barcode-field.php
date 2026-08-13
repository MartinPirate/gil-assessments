<?php

use Filament\Support\Enums\Width;

return [
    'asset_js' => 'https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js',
    /*
     | Narrowed to the symbologies that actually appear on a KRA ETR / eTIMS
     | receipt. The retail formats (EAN, UPC) are for products on a shelf, and
     | leaving them on only gives the scanner more ways to misread a creased
     | receipt.
     */
    'formats' => [
        'CODE_128',
        'CODE_39',
    ],
    'modal' => [
        'width' => Width::Large,
    ],
    'reader' => [
        'width' => '600px',
        'height' => '600px',
    ],
    'scanner' => [
        'fps' => 10,
        'width' => 300,
        'height' => 150,
    ],
];
