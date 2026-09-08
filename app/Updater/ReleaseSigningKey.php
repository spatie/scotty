<?php

namespace App\Updater;

class ReleaseSigningKey
{
    /**
     * The base64 encoded ed25519 public key that Spatie's release workflow signs every phar with.
     *
     * Generate a keypair with `php .github/scripts/generate-signing-key.php`, store the printed
     * secret key in the `SCOTTY_RELEASE_SIGNING_KEY` repository secret, and paste the printed
     * public key here.
     */
    public const PUBLIC_KEY = '';
}
