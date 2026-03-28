<?php

namespace App\Services;

use App\Commands\Concerns\RendersBanner;
use Illuminate\Console\Application;
use NunoMaduro\LaravelConsoleSummary\Contracts\DescriberContract;
use NunoMaduro\LaravelConsoleSummary\Describer;
use Symfony\Component\Console\Output\OutputInterface;

class ScottyDescriber extends Describer
{
    use RendersBanner;

    protected function describeTitle(Application $application, OutputInterface $output): DescriberContract
    {
        $this->renderBanner($output);

        return parent::describeTitle($application, $output);
    }
}
