<?php

use App\Execution\PreambleEvaluator;

it('returns the preamble unchanged when there are no variable assignments', function () {
    $preamble = "format_date() {\n    date +\"%Y-%m-%d\"\n}";

    $result = (new PreambleEvaluator)->evaluate($preamble);

    expect($result->variables)->toBe([])
        ->and($result->remainingPreamble)->toBe($preamble);
});

it('captures simple string assignments locally', function () {
    $preamble = 'BRANCH="main"';

    $result = (new PreambleEvaluator)->evaluate($preamble);

    expect($result->variables)->toBe(['BRANCH' => 'main'])
        ->and($result->remainingPreamble)->toBe('');
});

it('evaluates command substitutions once and freezes the value', function () {
    $preamble = 'TIMESTAMP=$(date +%s)';

    $evaluator = new PreambleEvaluator;

    $first = $evaluator->evaluate($preamble);
    sleep(1);
    $second = $evaluator->evaluate($preamble);

    expect($first->variables['TIMESTAMP'])->not->toBe($second->variables['TIMESTAMP']);

    expect((int) $first->variables['TIMESTAMP'])
        ->toBeGreaterThan(0)
        ->toBeLessThan((int) $second->variables['TIMESTAMP']);
});

it('captures variables that depend on each other', function () {
    $preamble = "RELEASE_NAME=20260427-145000\nRELEASE_DIR=\"app/\${RELEASE_NAME}\"";

    $result = (new PreambleEvaluator)->evaluate($preamble);

    expect($result->variables)->toBe([
        'RELEASE_NAME' => '20260427-145000',
        'RELEASE_DIR' => 'app/20260427-145000',
    ]);
});

it('keeps helper functions in the remaining preamble', function () {
    $preamble = "BRANCH=main\n\nformat_date() {\n    date +\"%Y-%m-%d\"\n}";

    $result = (new PreambleEvaluator)->evaluate($preamble);

    expect($result->variables)->toBe(['BRANCH' => 'main'])
        ->and($result->remainingPreamble)->toContain('format_date()')
        ->and($result->remainingPreamble)->not->toContain('BRANCH=');
});

it('passes provided env variables to the preamble execution', function () {
    $preamble = 'TARGET="${BRANCH}-build"';

    $result = (new PreambleEvaluator)->evaluate($preamble, ['BRANCH' => 'develop']);

    expect($result->variables)->toBe(['TARGET' => 'develop-build']);
});

it('throws when the preamble exits with a non-zero status', function () {
    $preamble = "VAR=valid\nfalse";

    expect(fn () => (new PreambleEvaluator)->evaluate($preamble))
        ->toThrow(RuntimeException::class, 'Failed to evaluate preamble');
});
