<?php

use App\Support\Money;

it('converts provider decimal amounts to exact integer cents', function (mixed $amount, int $expected): void {
    expect(Money::cents($amount))->toBe($expected);
})->with([['450.00', 45000], ['79.50', 7950], ['1.005', 101], ['-1.005', -101], [0, 0], ['1000000', 100000000]]);
it('rejects unreadable and out of range provider amounts', function (mixed $amount): void {
    expect(fn () => Money::cents($amount))->toThrow(InvalidArgumentException::class);
})->with(['NaN', '1e9', '1000000.01', null]);
