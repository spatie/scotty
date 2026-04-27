<?php

namespace App\Updater;

use Throwable;

class UpdateChecker
{
    public const CACHE_TTL_SECONDS = 86_400;

    /** @var callable(string): ?string */
    protected $httpFetcher;

    public function __construct(
        protected string $currentVersion,
        protected string $cacheDirectory,
        protected string $latestReleaseUrl = 'https://api.github.com/repos/spatie/scotty/releases/latest',
        ?callable $httpFetcher = null,
    ) {
        $this->httpFetcher = $httpFetcher ?? fn (string $url): ?string => $this->defaultHttpFetcher($url);
    }

    public function findNewerVersion(): ?string
    {
        $latestVersion = $this->fetchLatestVersion();

        if ($latestVersion === null) {
            return null;
        }

        if (version_compare($latestVersion, $this->currentVersion, '<=')) {
            return null;
        }

        return $latestVersion;
    }

    protected function fetchLatestVersion(): ?string
    {
        $cached = $this->readCache();

        if ($cached !== null) {
            return $cached;
        }

        try {
            $body = ($this->httpFetcher)($this->latestReleaseUrl);
        } catch (Throwable) {
            return null;
        }

        if (! is_string($body) || $body === '') {
            return null;
        }

        $payload = json_decode($body, true);

        if (! is_array($payload)) {
            return null;
        }

        $tagName = $payload['tag_name'] ?? null;

        if (! is_string($tagName) || $tagName === '') {
            return null;
        }

        $version = str_starts_with($tagName, 'v') ? substr($tagName, 1) : $tagName;

        $this->writeCache($version);

        return $version;
    }

    protected function defaultHttpFetcher(string $url): ?string
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => 3,
                'header' => "Accept: application/vnd.github+json\r\nUser-Agent: scotty-self-update\r\n",
            ],
        ]);

        $body = @file_get_contents($url, false, $context);

        return $body === false ? null : $body;
    }

    protected function readCache(): ?string
    {
        $cacheFile = $this->cacheFile();

        if (! is_file($cacheFile)) {
            return null;
        }

        $age = time() - (int) @filemtime($cacheFile);

        if ($age > self::CACHE_TTL_SECONDS) {
            return null;
        }

        $contents = trim((string) @file_get_contents($cacheFile));

        return $contents !== '' ? $contents : null;
    }

    protected function writeCache(string $version): void
    {
        if (! is_dir($this->cacheDirectory)) {
            @mkdir($this->cacheDirectory, 0755, true);
        }

        @file_put_contents($this->cacheFile(), $version);
    }

    protected function cacheFile(): string
    {
        return $this->cacheDirectory.'/update-check';
    }
}
