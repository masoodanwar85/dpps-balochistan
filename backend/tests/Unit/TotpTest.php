<?php

use App\Services\Auth\Totp;

it('accepts the current authenticator code and rejects a wrong one', function () {
    $totp = new Totp;
    $secret = $totp->generateSecret();

    expect($totp->verify($secret, $totp->currentCode($secret)))->toBeTrue()
        ->and($totp->verify($secret, '000000'))->toBeFalse()
        ->and($totp->provisioningUri('user@example.com', $secret))->toContain('secret='.$secret);
});
