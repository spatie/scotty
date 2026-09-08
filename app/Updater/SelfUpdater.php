<?php

namespace App\Updater;

use Throwable;

class SelfUpdater
{
    public const MIN_PHAR_SIZE_BYTES = 100_000;

    /** @var callable(string): ?string */
    protected $downloader;

    protected SignatureVerifier $signatureVerifier;

    public function __construct(
        protected string $downloadUrlTemplate = 'https://github.com/spatie/scotty/releases/download/{version}/scotty',
        ?callable $downloader = null,
        ?SignatureVerifier $signatureVerifier = null,
    ) {
        $this->downloader = $downloader ?? fn (string $url): ?string => $this->defaultDownloader($url);
        $this->signatureVerifier = $signatureVerifier ?? new SignatureVerifier;
    }

    /**
     * @param  ?callable(): void  $beforeCommit  Invoked once the new phar is on disk and validated, immediately before it replaces the running phar. Use this to flush user-facing messages while the running phar is still intact.
     */
    public function update(string $version, string $pharPath, ?callable $beforeCommit = null): UpdateResult
    {
        if (! is_writable(dirname($pharPath))) {
            return UpdateResult::failed("Cannot write to {$pharPath}. Re-run with sudo.");
        }

        $downloadUrl = str_replace('{version}', $version, $this->downloadUrlTemplate);

        try {
            $contents = ($this->downloader)($downloadUrl);
        } catch (Throwable $exception) {
            return UpdateResult::failed("Download failed: {$exception->getMessage()}");
        }

        if (! is_string($contents) || $contents === '') {
            return UpdateResult::failed('Download failed.');
        }

        if (strlen($contents) < self::MIN_PHAR_SIZE_BYTES) {
            return UpdateResult::failed('Downloaded phar is suspiciously small, refusing to replace.');
        }

        $signatureUrl = "{$downloadUrl}.sig";

        try {
            $signature = ($this->downloader)($signatureUrl);
        } catch (Throwable $exception) {
            return UpdateResult::failed("Could not download the release signature from {$signatureUrl}: {$exception->getMessage()}. Refusing to install an unverified update.");
        }

        if (! is_string($signature) || trim($signature) === '') {
            return UpdateResult::failed("Could not download the release signature from {$signatureUrl}. Refusing to install an unverified update.");
        }

        try {
            $this->signatureVerifier->verify($version, $contents, $signature);
        } catch (SignatureVerificationFailed $exception) {
            return UpdateResult::failed($exception->getMessage());
        }

        $tempPath = "{$pharPath}.new";

        if (@file_put_contents($tempPath, $contents) === false) {
            return UpdateResult::failed("Failed to write temporary file at {$tempPath}.");
        }

        @chmod($tempPath, 0755);

        if ($beforeCommit !== null) {
            $beforeCommit();
        }

        if (! @rename($tempPath, $pharPath)) {
            @unlink($tempPath);

            return UpdateResult::failed("Failed to replace {$pharPath}.");
        }

        return UpdateResult::success();
    }

    protected function defaultDownloader(string $url): ?string
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => 60,
                'header' => "User-Agent: scotty-self-update\r\n",
                'follow_location' => 1,
                'max_redirects' => 5,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);

        return $body === false ? null : $body;
    }
}
