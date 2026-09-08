<?php

use App\Updater\ReleaseSigningKey;
use App\Updater\SignatureVerifier;

/**
 * Signs a built phar with the release signing key and writes the detached signature next to it.
 *
 *     php .github/scripts/sign-phar.php builds/scotty 1.5.0
 *
 * The secret key is read from the `SCOTTY_RELEASE_SIGNING_KEY` environment variable. The signature
 * is verified against the public key baked into this build before it is written, so a key mismatch
 * fails the release instead of shipping a phar nobody can install.
 */
require __DIR__.'/../../vendor/autoload.php';

$pharPath = $argv[1] ?? null;
$version = $argv[2] ?? null;

if (! is_string($pharPath) || ! is_string($version) || $version === '') {
    fwrite(STDERR, "Usage: php .github/scripts/sign-phar.php <phar-path> <version>\n");

    exit(1);
}

if (! is_file($pharPath)) {
    fwrite(STDERR, "No phar found at {$pharPath}.\n");

    exit(1);
}

$base64SecretKey = trim((string) getenv('SCOTTY_RELEASE_SIGNING_KEY'));

if ($base64SecretKey === '') {
    fwrite(STDERR, "The SCOTTY_RELEASE_SIGNING_KEY environment variable is empty.\n");

    exit(1);
}

$secretKey = base64_decode($base64SecretKey, true);

if ($secretKey === false || strlen($secretKey) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
    fwrite(STDERR, "The SCOTTY_RELEASE_SIGNING_KEY environment variable is not a base64 encoded ed25519 secret key.\n");

    exit(1);
}

$pharContents = (string) file_get_contents($pharPath);

$signature = base64_encode(sodium_crypto_sign_detached(
    SignatureVerifier::signedMessage($version, $pharContents),
    $secretKey,
));

try {
    (new SignatureVerifier(ReleaseSigningKey::PUBLIC_KEY))->verify($version, $pharContents, $signature);
} catch (Throwable $exception) {
    fwrite(STDERR, "{$exception->getMessage()}\n");
    fwrite(STDERR, "Refusing to publish a phar this build cannot verify. Check the SCOTTY_RELEASE_SIGNING_KEY secret and ReleaseSigningKey::PUBLIC_KEY.\n");

    exit(1);
}

file_put_contents("{$pharPath}.sig", "{$signature}\n");

echo "Signed {$pharPath} for version {$version}.\n";
