<?php

it('uses western indonesia time for application dates', function () {
    expect(config('app.timezone'))->toBe('Asia/Jakarta')
        ->and(now()->format('P'))->toBe('+07:00');
});
