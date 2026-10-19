<?php

namespace App\Updater;

use Exception;

class SignatureVerificationFailed extends Exception
{
    public static function sodiumExtensionMissing(): self
    {
        return new self('Cannot verify the release signature: the PHP sodium extension is not available. Install ext-sodium, or download the new version manually from https://github.com/spatie/scotty/releases.');
    }

    public static function noPublicKeyInBuild(): self
    {
        return new self('Cannot verify the release signature: this build of Scotty does not contain a release signing key. Download the new version manually from https://github.com/spatie/scotty/releases.');
    }

    public static function malformedPublicKeyInBuild(): self
    {
        return new self('Cannot verify the release signature: the release signing key in this build of Scotty is malformed.');
    }

    public static function malformedSignature(): self
    {
        return new self('The downloaded release signature is malformed. Refusing to install an unverified update.');
    }

    public static function signatureDoesNotMatch(string $version): self
    {
        return new self("The downloaded phar for {$version} was not signed by Spatie's release signing key. The download may have been tampered with. Refusing to install it.");
    }
}
