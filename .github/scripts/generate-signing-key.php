<?php

/**
 * Generates the ed25519 keypair that Scotty releases are signed with.
 *
 * Run it once, on a trusted machine:
 *
 *     php .github/scripts/generate-signing-key.php
 *
 * Store the secret key in the `SCOTTY_RELEASE_SIGNING_KEY` repository secret and commit the public
 * key in `App\Updater\ReleaseSigningKey::PUBLIC_KEY`. Never commit the secret key.
 */
if (! function_exists('sodium_crypto_sign_keypair')) {
    fwrite(STDERR, "The PHP sodium extension is required to generate a signing key.\n");

    exit(1);
}

$keypair = sodium_crypto_sign_keypair();

$secretKey = base64_encode(sodium_crypto_sign_secretkey($keypair));
$publicKey = base64_encode(sodium_crypto_sign_publickey($keypair));

echo "Secret key (store in the SCOTTY_RELEASE_SIGNING_KEY repository secret):\n{$secretKey}\n\n";
echo "Public key (paste into App\\Updater\\ReleaseSigningKey::PUBLIC_KEY):\n{$publicKey}\n";
