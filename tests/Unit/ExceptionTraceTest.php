<?php

use App\Support\ExceptionTrace;

test('it normalizes the client exception trace shape', function () {
    $payload = ExceptionTrace::normalize([
        'trace' => json_encode([
            [
                'file' => 'app/Services/Checkout.php:42',
                'source' => 'App\\Services\\Checkout->charge(string, int)',
                'code' => [
                    '41' => '    $gateway->prepare();',
                    '42' => '    $gateway->charge();',
                ],
            ],
        ], JSON_THROW_ON_ERROR),
    ]);

    expect($payload['stack'])->toHaveCount(1)
        ->and($payload['stack'][0])->toMatchArray([
            'file' => 'app/Services/Checkout.php',
            'line' => 42,
            'class' => 'App\\Services\\Checkout',
            'type' => '->',
            'function' => 'charge',
            'preview' => [
                '41' => '    $gateway->prepare();',
                '42' => '    $gateway->charge();',
            ],
            'snippet' => "    \$gateway->prepare();\n    \$gateway->charge();",
        ]);
});

test('it preserves an already normalized stack', function () {
    $payload = ExceptionTrace::normalize([
        'stack' => [[
            'file' => 'C:\\app\\Handler.php',
            'line' => 17,
            'class' => 'Handler',
            'type' => '::',
            'function' => 'run',
            'preview' => ['17' => 'throw new Exception;'],
            'snippet' => 'throw new Exception;',
        ]],
    ]);

    expect($payload['stack'][0])->toMatchArray([
        'file' => 'C:\\app\\Handler.php',
        'line' => 17,
        'class' => 'Handler',
        'type' => '::',
        'function' => 'run',
        'snippet' => 'throw new Exception;',
    ]);
});

test('it handles malformed traces without breaking the detail page', function () {
    expect(ExceptionTrace::normalize(['trace' => '{not-json}'])['stack'])->toBe([])
        ->and(ExceptionTrace::normalize(['trace' => null])['stack'])->toBe([]);
});
