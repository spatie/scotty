<?php

use App\Execution\TaskResult;

it('returns true for succeeded when exit code is 0', function () {
    $result = new TaskResult(exitCode: 0);

    expect($result->succeeded())->toBeTrue();
});

it('returns false for succeeded when exit code is greater than 0', function () {
    $result = new TaskResult(exitCode: 1);

    expect($result->succeeded())->toBeFalse();
});

it('returns false for succeeded with various non-zero exit codes', function (int $code) {
    $result = new TaskResult(exitCode: $code);

    expect($result->succeeded())->toBeFalse();
})->with([1, 2, 127, 255]);
