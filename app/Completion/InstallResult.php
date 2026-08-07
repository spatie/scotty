<?php

namespace App\Completion;

enum InstallResult: string
{
    case Installed = 'installed';
    case Already = 'already';
    case Failed = 'failed';
}
