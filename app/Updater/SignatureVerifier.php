<?php

namespace App\Updater;

class SignatureVerifier
{
    public function __construct(
        protected string $base64PublicKey = ReleaseSigningKey::PUBLIC_KEY,
    ) {}

    /**
     * The exact bytes that the release workflow signs. It covers the phar and the version it was
     * published as, so a genuine phar cannot be served in the place of another version.
     */
    public static function signedMessage(string $version, string $pharContents): string
    {
        return "scotty\n{$version}\n".hash('sha256', $pharContents);
    }

    /** @throws SignatureVerificationFailed */
    public function verify(string $version, string $pharContents, string $base64Signature): void
    {
        if (! function_exists('sodium_crypto_sign_verify_detached')) {
            throw SignatureVerificationFailed::sodiumExtensionMissing();
        }

        $publicKey = $this->publicKey();

        $signature = $this->decodeSignature($base64Signature);

        $signatureMatches = sodium_crypto_sign_verify_detached(
            $signature,
            self::signedMessage($version, $pharContents),
            $publicKey,
        );

        if (! $signatureMatches) {
            throw SignatureVerificationFailed::signatureDoesNotMatch($version);
        }
    }

    /** @throws SignatureVerificationFailed */
    protected function publicKey(): string
    {
        $base64PublicKey = trim($this->base64PublicKey);

        if ($base64PublicKey === '') {
            throw SignatureVerificationFailed::noPublicKeyInBuild();
        }

        $publicKey = base64_decode($base64PublicKey, true);

        if ($publicKey === false) {
            throw SignatureVerificationFailed::malformedPublicKeyInBuild();
        }

        if (strlen($publicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            throw SignatureVerificationFailed::malformedPublicKeyInBuild();
        }

        return $publicKey;
    }

    /** @throws SignatureVerificationFailed */
    protected function decodeSignature(string $base64Signature): string
    {
        $signature = base64_decode(trim($base64Signature), true);

        if ($signature === false) {
            throw SignatureVerificationFailed::malformedSignature();
        }

        if (strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES) {
            throw SignatureVerificationFailed::malformedSignature();
        }

        return $signature;
    }
}
