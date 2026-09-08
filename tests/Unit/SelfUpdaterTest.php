<?php

use App\Updater\SelfUpdater;
use App\Updater\SignatureVerifier;

beforeEach(function () {
    $this->workingDirectory = sys_get_temp_dir().'/scotty-self-update-'.uniqid();
    mkdir($this->workingDirectory, 0755, true);

    $this->pharPath = $this->workingDirectory.'/scotty';
    file_put_contents($this->pharPath, str_repeat('OLD', 1024));
    chmod($this->pharPath, 0755);

    $keypair = sodium_crypto_sign_keypair();
    $secretKey = sodium_crypto_sign_secretkey($keypair);

    $this->verifier = new SignatureVerifier(base64_encode(sodium_crypto_sign_publickey($keypair)));

    $this->sign = fn (string $version, string $contents): string => base64_encode(sodium_crypto_sign_detached(
        SignatureVerifier::signedMessage($version, $contents),
        $secretKey,
    ));

    $this->serve = function (string $phar, ?string $signature = null): callable {
        return fn (string $url): ?string => str_ends_with($url, '.sig') ? $signature : $phar;
    };
});

afterEach(function () {
    @chmod($this->workingDirectory, 0755);

    foreach (glob($this->workingDirectory.'/*') ?: [] as $file) {
        @chmod($file, 0644);
        @unlink($file);
    }

    @rmdir($this->workingDirectory);
});

it('replaces the phar with the freshly downloaded contents', function () {
    $payload = str_repeat('NEW', SelfUpdater::MIN_PHAR_SIZE_BYTES);

    $updater = new SelfUpdater(
        downloader: ($this->serve)($payload, ($this->sign)('1.4.0', $payload)),
        signatureVerifier: $this->verifier,
    );

    $result = $updater->update('1.4.0', $this->pharPath);

    expect($result->succeeded)->toBeTrue()
        ->and(file_get_contents($this->pharPath))->toBe($payload);
});

it('substitutes the version into the download URL template', function () {
    $payload = str_repeat('X', SelfUpdater::MIN_PHAR_SIZE_BYTES);
    $requestedUrls = [];

    $updater = new SelfUpdater(
        downloadUrlTemplate: 'https://example.test/scotty-{version}.phar',
        downloader: function (string $url) use (&$requestedUrls, $payload): string {
            $requestedUrls[] = $url;

            return str_ends_with($url, '.sig') ? ($this->sign)('1.4.0', $payload) : $payload;
        },
        signatureVerifier: $this->verifier,
    );

    $updater->update('1.4.0', $this->pharPath);

    expect($requestedUrls)->toBe([
        'https://example.test/scotty-1.4.0.phar',
        'https://example.test/scotty-1.4.0.phar.sig',
    ]);
});

it('invokes the beforeCommit callback while the original phar is still in place', function () {
    $payload = str_repeat('NEW', SelfUpdater::MIN_PHAR_SIZE_BYTES);

    $updater = new SelfUpdater(
        downloader: ($this->serve)($payload, ($this->sign)('1.4.0', $payload)),
        signatureVerifier: $this->verifier,
    );

    $contentsAtCallback = null;

    $result = $updater->update(
        '1.4.0',
        $this->pharPath,
        beforeCommit: function () use (&$contentsAtCallback) {
            $contentsAtCallback = file_get_contents($this->pharPath);
        },
    );

    expect($result->succeeded)->toBeTrue()
        ->and($contentsAtCallback)->toBe(str_repeat('OLD', 1024))
        ->and(file_get_contents($this->pharPath))->toBe($payload);
});

it('refuses to install a phar that does not match its signature', function () {
    $genuinePayload = str_repeat('NEW', SelfUpdater::MIN_PHAR_SIZE_BYTES);
    $tamperedPayload = str_replace('NEW', 'BAD', $genuinePayload);

    $updater = new SelfUpdater(
        downloader: ($this->serve)($tamperedPayload, ($this->sign)('1.4.0', $genuinePayload)),
        signatureVerifier: $this->verifier,
    );

    $result = $updater->update('1.4.0', $this->pharPath);

    expect($result->succeeded)->toBeFalse()
        ->and($result->error)->toContain('may have been tampered with')
        ->and(file_get_contents($this->pharPath))->toBe(str_repeat('OLD', 1024))
        ->and(is_file($this->pharPath.'.new'))->toBeFalse();
});

it('refuses to install a phar signed for another version', function () {
    $payload = str_repeat('NEW', SelfUpdater::MIN_PHAR_SIZE_BYTES);

    $updater = new SelfUpdater(
        downloader: ($this->serve)($payload, ($this->sign)('1.3.0', $payload)),
        signatureVerifier: $this->verifier,
    );

    $result = $updater->update('1.4.0', $this->pharPath);

    expect($result->succeeded)->toBeFalse()
        ->and($result->error)->toContain('may have been tampered with')
        ->and(file_get_contents($this->pharPath))->toBe(str_repeat('OLD', 1024));
});

it('refuses to install a phar signed by another key', function () {
    $payload = str_repeat('NEW', SelfUpdater::MIN_PHAR_SIZE_BYTES);

    $attackerSignature = base64_encode(sodium_crypto_sign_detached(
        SignatureVerifier::signedMessage('1.4.0', $payload),
        sodium_crypto_sign_secretkey(sodium_crypto_sign_keypair()),
    ));

    $updater = new SelfUpdater(
        downloader: ($this->serve)($payload, $attackerSignature),
        signatureVerifier: $this->verifier,
    );

    $result = $updater->update('1.4.0', $this->pharPath);

    expect($result->succeeded)->toBeFalse()
        ->and($result->error)->toContain('may have been tampered with')
        ->and(file_get_contents($this->pharPath))->toBe(str_repeat('OLD', 1024));
});

it('refuses to install when the signature cannot be downloaded', function () {
    $payload = str_repeat('NEW', SelfUpdater::MIN_PHAR_SIZE_BYTES);

    $updater = new SelfUpdater(
        downloader: ($this->serve)($payload, null),
        signatureVerifier: $this->verifier,
    );

    $result = $updater->update('1.4.0', $this->pharPath);

    expect($result->succeeded)->toBeFalse()
        ->and($result->error)->toContain('Could not download the release signature')
        ->and($result->error)->toContain('Refusing to install an unverified update')
        ->and(file_get_contents($this->pharPath))->toBe(str_repeat('OLD', 1024));
});

it('refuses to install when the signature download throws', function () {
    $payload = str_repeat('NEW', SelfUpdater::MIN_PHAR_SIZE_BYTES);

    $updater = new SelfUpdater(
        downloader: function (string $url) use ($payload): string {
            if (str_ends_with($url, '.sig')) {
                throw new RuntimeException('network down');
            }

            return $payload;
        },
        signatureVerifier: $this->verifier,
    );

    $result = $updater->update('1.4.0', $this->pharPath);

    expect($result->succeeded)->toBeFalse()
        ->and($result->error)->toContain('network down')
        ->and($result->error)->toContain('Refusing to install an unverified update');
});

it('refuses to install when the build carries no signing key', function () {
    $payload = str_repeat('NEW', SelfUpdater::MIN_PHAR_SIZE_BYTES);

    $updater = new SelfUpdater(
        downloader: ($this->serve)($payload, ($this->sign)('1.4.0', $payload)),
        signatureVerifier: new SignatureVerifier(''),
    );

    $result = $updater->update('1.4.0', $this->pharPath);

    expect($result->succeeded)->toBeFalse()
        ->and($result->error)->toContain('does not contain a release signing key')
        ->and(file_get_contents($this->pharPath))->toBe(str_repeat('OLD', 1024));
});

it('returns failure when the download is suspiciously small', function () {
    $updater = new SelfUpdater(
        downloader: ($this->serve)('tiny', ($this->sign)('1.4.0', 'tiny')),
        signatureVerifier: $this->verifier,
    );

    $result = $updater->update('1.4.0', $this->pharPath);

    expect($result->succeeded)->toBeFalse()
        ->and($result->error)->toContain('suspiciously small');
});

it('returns failure when the downloader returns null', function () {
    $updater = new SelfUpdater(
        downloader: fn (): ?string => null,
        signatureVerifier: $this->verifier,
    );

    $result = $updater->update('1.4.0', $this->pharPath);

    expect($result->succeeded)->toBeFalse()
        ->and($result->error)->toContain('Download failed');
});

it('returns failure when the downloader throws', function () {
    $updater = new SelfUpdater(
        downloader: function (): string {
            throw new RuntimeException('boom');
        },
        signatureVerifier: $this->verifier,
    );

    $result = $updater->update('1.4.0', $this->pharPath);

    expect($result->succeeded)->toBeFalse()
        ->and($result->error)->toContain('boom');
});

it('refuses to write when the parent directory is not writable', function () {
    chmod($this->workingDirectory, 0555);

    $payload = str_repeat('X', SelfUpdater::MIN_PHAR_SIZE_BYTES);

    $updater = new SelfUpdater(
        downloader: ($this->serve)($payload, ($this->sign)('1.4.0', $payload)),
        signatureVerifier: $this->verifier,
    );

    $result = $updater->update('1.4.0', $this->pharPath);

    expect($result->succeeded)->toBeFalse()
        ->and($result->error)->toContain('Cannot write');
});
