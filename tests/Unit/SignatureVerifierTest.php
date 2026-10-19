<?php

use App\Updater\SignatureVerificationFailed;
use App\Updater\SignatureVerifier;

beforeEach(function () {
    $keypair = sodium_crypto_sign_keypair();

    $this->secretKey = sodium_crypto_sign_secretkey($keypair);
    $this->publicKey = base64_encode(sodium_crypto_sign_publickey($keypair));

    $this->sign = fn (string $version, string $contents): string => base64_encode(sodium_crypto_sign_detached(
        SignatureVerifier::signedMessage($version, $contents),
        $this->secretKey,
    ));
});

it('accepts a signature made with the matching secret key', function () {
    $contents = 'the phar bytes';

    $verifier = new SignatureVerifier($this->publicKey);

    $verifier->verify('1.5.0', $contents, ($this->sign)('1.5.0', $contents));
})->throwsNoExceptions();

it('rejects a signature over different contents', function () {
    $signature = ($this->sign)('1.5.0', 'the phar bytes');

    $verifier = new SignatureVerifier($this->publicKey);

    $verifier->verify('1.5.0', 'the tampered phar bytes', $signature);
})->throws(SignatureVerificationFailed::class, 'was not signed by');

it('rejects a signature made for another version', function () {
    $contents = 'the phar bytes';

    $verifier = new SignatureVerifier($this->publicKey);

    $verifier->verify('1.5.0', $contents, ($this->sign)('1.4.0', $contents));
})->throws(SignatureVerificationFailed::class, 'was not signed by');

it('rejects a signature made with another key', function () {
    $otherKeypair = sodium_crypto_sign_keypair();

    $signature = base64_encode(sodium_crypto_sign_detached(
        SignatureVerifier::signedMessage('1.5.0', 'the phar bytes'),
        sodium_crypto_sign_secretkey($otherKeypair),
    ));

    $verifier = new SignatureVerifier($this->publicKey);

    $verifier->verify('1.5.0', 'the phar bytes', $signature);
})->throws(SignatureVerificationFailed::class, 'was not signed by');

it('rejects a signature that is not valid base64', function () {
    $verifier = new SignatureVerifier($this->publicKey);

    $verifier->verify('1.5.0', 'the phar bytes', 'not base64 !!');
})->throws(SignatureVerificationFailed::class, 'malformed');

it('rejects a signature of the wrong length', function () {
    $verifier = new SignatureVerifier($this->publicKey);

    $verifier->verify('1.5.0', 'the phar bytes', base64_encode('too short'));
})->throws(SignatureVerificationFailed::class, 'malformed');

it('refuses to verify when the build carries no public key', function () {
    $contents = 'the phar bytes';

    $verifier = new SignatureVerifier('');

    $verifier->verify('1.5.0', $contents, ($this->sign)('1.5.0', $contents));
})->throws(SignatureVerificationFailed::class, 'does not contain a release signing key');

it('refuses to verify when the public key in the build is malformed', function () {
    $contents = 'the phar bytes';

    $verifier = new SignatureVerifier(base64_encode('too short'));

    $verifier->verify('1.5.0', $contents, ($this->sign)('1.5.0', $contents));
})->throws(SignatureVerificationFailed::class, 'signing key in this build');

it('signs over both the version and a hash of the phar', function () {
    expect(SignatureVerifier::signedMessage('1.5.0', 'the phar bytes'))
        ->toBe("scotty\n1.5.0\n".hash('sha256', 'the phar bytes'));
});
