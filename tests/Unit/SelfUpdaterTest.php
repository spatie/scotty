<?php

use App\Updater\SelfUpdater;

beforeEach(function () {
    $this->workingDirectory = sys_get_temp_dir().'/scotty-self-update-'.uniqid();
    mkdir($this->workingDirectory, 0755, true);

    $this->pharPath = $this->workingDirectory.'/scotty';
    file_put_contents($this->pharPath, str_repeat('OLD', 1024));
    chmod($this->pharPath, 0755);
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
        downloader: fn (): string => $payload,
    );

    $result = $updater->update('1.4.0', $this->pharPath);

    expect($result->succeeded)->toBeTrue()
        ->and(file_get_contents($this->pharPath))->toBe($payload);
});

it('substitutes the version into the download URL template', function () {
    $receivedUrl = null;

    $updater = new SelfUpdater(
        downloadUrlTemplate: 'https://example.test/scotty-{version}.phar',
        downloader: function (string $url) use (&$receivedUrl): string {
            $receivedUrl = $url;

            return str_repeat('X', SelfUpdater::MIN_PHAR_SIZE_BYTES);
        },
    );

    $updater->update('1.4.0', $this->pharPath);

    expect($receivedUrl)->toBe('https://example.test/scotty-1.4.0.phar');
});

it('returns failure when the download is suspiciously small', function () {
    $updater = new SelfUpdater(
        downloader: fn (): string => 'tiny',
    );

    $result = $updater->update('1.4.0', $this->pharPath);

    expect($result->succeeded)->toBeFalse()
        ->and($result->error)->toContain('suspiciously small');
});

it('returns failure when the downloader returns null', function () {
    $updater = new SelfUpdater(
        downloader: fn (): ?string => null,
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
    );

    $result = $updater->update('1.4.0', $this->pharPath);

    expect($result->succeeded)->toBeFalse()
        ->and($result->error)->toContain('boom');
});

it('refuses to write when the parent directory is not writable', function () {
    chmod($this->workingDirectory, 0555);

    $updater = new SelfUpdater(
        downloader: fn (): string => str_repeat('X', SelfUpdater::MIN_PHAR_SIZE_BYTES),
    );

    $result = $updater->update('1.4.0', $this->pharPath);

    expect($result->succeeded)->toBeFalse()
        ->and($result->error)->toContain('Cannot write');
});
