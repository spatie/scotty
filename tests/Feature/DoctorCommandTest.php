<?php

use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    $this->fixturePath = realpath(__DIR__.'/../fixtures');
});

it('does not flag macros that reference other macros as undefined', function () {
    Artisan::call('doctor', [
        '--conf' => $this->fixturePath.'/nested-macros.sh',
    ]);

    $output = Artisan::output();

    expect($output)
        ->toContain('All macro references resolve')
        ->not->toContain('undefined task or macro');
});
