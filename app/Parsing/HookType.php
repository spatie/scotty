<?php

namespace App\Parsing;

enum HookType: string
{
    case Before = 'before';
    case After = 'after';
    case Success = 'success';
    case Error = 'error';
    case Finished = 'finished';
}
