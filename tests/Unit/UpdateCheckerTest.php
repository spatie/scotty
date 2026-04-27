<?php

use App\Updater\UpdateChecker;

beforeEach(function () {
    $this->cacheDirectory = sys_get_temp_dir().'/scotty-update-check-'.uniqid();
});

afterEach(function () {
    if (is_dir($this->cacheDirectory)) {
        array_map('unlink', glob($this->cacheDirectory.'/*') ?: []);
        @rmdir($this->cacheDirectory);
    }
});

it('returns the latest tag when newer than the current version', function () {
    $checker = new UpdateChecker(
        currentVersion: '1.3.0',
        cacheDirectory: $this->cacheDirectory,
        httpFetcher: fn (): string => json_encode(['tag_name' => '1.4.0']),
    );

    expect($checker->findNewerVersion())->toBe('1.4.0');
});

it('returns null when the current version is already the latest', function () {
    $checker = new UpdateChecker(
        currentVersion: '1.3.0',
        cacheDirectory: $this->cacheDirectory,
        httpFetcher: fn (): string => json_encode(['tag_name' => '1.3.0']),
    );

    expect($checker->findNewerVersion())->toBeNull();
});

it('strips a leading v from the tag', function () {
    $checker = new UpdateChecker(
        currentVersion: '1.3.0',
        cacheDirectory: $this->cacheDirectory,
        httpFetcher: fn (): string => json_encode(['tag_name' => 'v1.4.0']),
    );

    expect($checker->findNewerVersion())->toBe('1.4.0');
});

it('returns null silently when the request fails', function () {
    $checker = new UpdateChecker(
        currentVersion: '1.3.0',
        cacheDirectory: $this->cacheDirectory,
        httpFetcher: fn (): ?string => null,
    );

    expect($checker->findNewerVersion())->toBeNull();
});

it('returns null silently when the fetcher throws', function () {
    $checker = new UpdateChecker(
        currentVersion: '1.3.0',
        cacheDirectory: $this->cacheDirectory,
        httpFetcher: function (): string {
            throw new RuntimeException('network down');
        },
    );

    expect($checker->findNewerVersion())->toBeNull();
});

it('returns null when the response is not valid JSON', function () {
    $checker = new UpdateChecker(
        currentVersion: '1.3.0',
        cacheDirectory: $this->cacheDirectory,
        httpFetcher: fn (): string => 'not json at all',
    );

    expect($checker->findNewerVersion())->toBeNull();
});

it('caches the latest version and reuses it on subsequent calls', function () {
    $callCount = 0;

    $checker = new UpdateChecker(
        currentVersion: '1.3.0',
        cacheDirectory: $this->cacheDirectory,
        httpFetcher: function () use (&$callCount): string {
            $callCount++;

            return json_encode(['tag_name' => '1.4.0']);
        },
    );

    $checker->findNewerVersion();
    $checker->findNewerVersion();

    expect($callCount)->toBe(1);
});

it('ignores stale cache entries', function () {
    if (! is_dir($this->cacheDirectory)) {
        mkdir($this->cacheDirectory, 0755, true);
    }

    $cacheFile = $this->cacheDirectory.'/update-check';
    file_put_contents($cacheFile, '1.3.5');
    touch($cacheFile, time() - UpdateChecker::CACHE_TTL_SECONDS - 60);

    $checker = new UpdateChecker(
        currentVersion: '1.3.0',
        cacheDirectory: $this->cacheDirectory,
        httpFetcher: fn (): string => json_encode(['tag_name' => '1.5.0']),
    );

    expect($checker->findNewerVersion())->toBe('1.5.0');
});
