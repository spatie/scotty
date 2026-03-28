<?php

namespace App\Providers;

use App\Services\ScottyDescriber;
use Illuminate\Support\ServiceProvider;
use NunoMaduro\LaravelConsoleSummary\Contracts\DescriberContract;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->singleton(DescriberContract::class, ScottyDescriber::class);
    }
}
