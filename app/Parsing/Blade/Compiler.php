<?php

namespace App\Parsing\Blade;

class Compiler
{
    /** @var array<string> */
    protected array $serverCompilers = [
        'Servers',
    ];

    /** @var array<string> */
    protected array $compilers = [
        'Imports',
        'Sets',
        'Comments',
        'Echos',
        'Openings',
        'Closings',
        'Else',
        'Unless',
        'EndUnless',
        'SetupStart',
        'SetupStop',
        'Include',
        'Servers',
        'MacroStart',
        'MacroStop',
        'TaskStart',
        'TaskStop',
        'Before',
        'BeforeStop',
        'After',
        'AfterStop',
        'Finished',
        'FinishedStop',
        'Success',
        'SuccessStop',
        'Error',
        'ErrorStop',
    ];

    /** @var array{string, string} */
    protected array $contentTags = ['{{', '}}'];

    public function compile(string $value, bool $serversOnly = false): string
    {
        $compilers = $serversOnly ? $this->serverCompilers : $this->compilers;

        $value = $this->initializeVariables($value);

        foreach ($compilers as $compiler) {
            $value = $this->{"compile{$compiler}"}($value);
        }

        return $value;
    }

    protected function compileSets(string $value): string
    {
        return preg_replace('/\\@set\(\'(.*?)\'\,\s*(.*)\)/', '<?php $$1 = $2; ?>', $value);
    }

    protected function compileImports(string $value): string
    {
        $pattern = $this->createOpenMatcher('import');

        return preg_replace($pattern, '$1<?php $__container->import$2, get_defined_vars()); ?>', $value);
    }

    protected function compileComments(string $value): string
    {
        $pattern = sprintf('/%s--((.|\s)*?)--%s/', $this->contentTags[0], $this->contentTags[1]);

        return preg_replace($pattern, '<?php /*$1*/ ?>', $value);
    }

    protected function compileEchos(string $value): string
    {
        return $this->compileRegularEchos($value);
    }

    protected function compileRegularEchos(string $value): string
    {
        $pattern = sprintf('/(@)?%s\s*(.+?)\s*%s(\r?\n)?/s', '{{', '}}');

        $callback = function (array $matches): string {
            $whitespace = empty($matches[3]) ? '' : $matches[3].$matches[3];

            $wrapped = sprintf('%s', $this->compileEchoDefaults($matches[2]));

            if ($matches[1]) {
                return substr($matches[0], 1);
            }

            return '<?php echo '.$wrapped.'; ?>'.$whitespace;
        };

        return preg_replace_callback($pattern, $callback, $value);
    }

    public function compileEchoDefaults(string $value): string
    {
        return preg_replace('/^(?=\$)(.+?)(?:\s+or\s+)(.+?)$/s', 'isset($1) ? $1 : $2', $value);
    }

    protected function compileOpenings(string $value): string
    {
        $pattern = '/(?(R)\((?:[^\(\)]|(?R))*\)|(?<!\w)(\s*)@(if|elseif|foreach|for|while)(\s*(?R)+))/';

        return preg_replace($pattern, '$1<?php $2$3: ?>', $value);
    }

    protected function compileClosings(string $value): string
    {
        $pattern = '/(\s*)@(endif|endforeach|endfor|endwhile)(\s*)/';

        return preg_replace($pattern, '$1<?php $2; ?>$3', $value);
    }

    protected function compileElse(string $value): string
    {
        $pattern = $this->createPlainMatcher('else');

        return preg_replace($pattern, '$1<?php else: ?>$2', $value);
    }

    protected function compileUnless(string $value): string
    {
        $pattern = $this->createMatcher('unless');

        return preg_replace($pattern, '$1<?php if ( !$2): ?>', $value);
    }

    protected function compileEndUnless(string $value): string
    {
        $pattern = $this->createPlainMatcher('endunless');

        return preg_replace($pattern, '$1<?php endif; ?>$2', $value);
    }

    public function compileSetupStart(string $value): string
    {
        $value = preg_replace('/(\s*)@setup(\s*)/', '$1<?php$2', $value);

        return preg_replace('/(\s*)@php(\s*)/', '$1<?php$2', $value);
    }

    public function compileSetupStop(string $value): string
    {
        $value = preg_replace('/(\s*)@endsetup(\s*)/', '$1?>$2', $value);

        return preg_replace('/(\s*)@endphp(\s*)/', '$1?>$2', $value);
    }

    public function compileInclude(string $value): string
    {
        $pattern = $this->createMatcher('include');

        return preg_replace($pattern, '$1 <?php require_once$2; ?>', $value);
    }

    protected function compileServers(string $value): string
    {
        $value = preg_replace_callback('/@servers\(\[(.*?)\]\)/s', function (array $matches): string {
            return '@servers(['.trim(preg_replace('/\s+/', ' ', $matches[1])).'])';
        }, $value);

        $pattern = $this->createMatcher('servers');

        return preg_replace($pattern, '$1<?php $__container->servers$2; ?>', $value);
    }

    protected function compileMacroStart(string $value): string
    {
        $pattern = $this->createMatcher('macro');

        $value = preg_replace($pattern, '$1<?php $__container->startMacro$2; ?>', $value);

        $pattern = $this->createMatcher('story');

        return preg_replace($pattern, '$1<?php $__container->startMacro$2; ?>', $value);
    }

    protected function compileMacroStop(string $value): string
    {
        $pattern = $this->createPlainMatcher('endmacro');

        $value = preg_replace($pattern, '$1<?php $__container->endMacro(); ?>$2', $value);

        $pattern = $this->createPlainMatcher('endstory');

        return preg_replace($pattern, '$1<?php $__container->endMacro(); ?>$2', $value);
    }

    protected function compileTaskStart(string $value): string
    {
        $pattern = $this->createMatcher('task');

        return preg_replace($pattern, '$1<?php $__container->startTask$2; ?>', $value);
    }

    protected function compileTaskStop(string $value): string
    {
        $pattern = $this->createPlainMatcher('endtask');

        return preg_replace($pattern, '$1<?php $__container->endTask(); ?>$2', $value);
    }

    protected function compileBefore(string $value): string
    {
        $pattern = $this->createPlainMatcher('before');

        return preg_replace($pattern, '$1<?php $_vars = get_defined_vars(); $__container->before(function($task) use ($_vars) { extract($_vars, EXTR_SKIP)  ; $2', $value);
    }

    protected function compileBeforeStop(string $value): string
    {
        return preg_replace($this->createPlainMatcher('endbefore'), '$1}); ?>$2', $value);
    }

    protected function compileAfter(string $value): string
    {
        $pattern = $this->createPlainMatcher('after');

        return preg_replace($pattern, '$1<?php $_vars = get_defined_vars(); $__container->after(function($task) use ($_vars) { extract($_vars, EXTR_SKIP)  ; $2', $value);
    }

    protected function compileAfterStop(string $value): string
    {
        return preg_replace($this->createPlainMatcher('endafter'), '$1}); ?>$2', $value);
    }

    protected function compileFinished(string $value): string
    {
        $pattern = $this->createPlainMatcher('finished');

        return preg_replace($pattern, '$1<?php $_vars = get_defined_vars(); $__container->finished(function($exitCode = null) use ($_vars) { extract($_vars); $2', $value);
    }

    protected function compileFinishedStop(string $value): string
    {
        return preg_replace($this->createPlainMatcher('endfinished'), '$1}); ?>$2', $value);
    }

    protected function compileSuccess(string $value): string
    {
        $pattern = $this->createPlainMatcher('success');

        return preg_replace($pattern, '$1<?php $_vars = get_defined_vars(); $__container->success(function() use ($_vars) { extract($_vars); $2', $value);
    }

    protected function compileSuccessStop(string $value): string
    {
        return preg_replace($this->createPlainMatcher('endsuccess'), '$1}); ?>$2', $value);
    }

    protected function compileError(string $value): string
    {
        $pattern = $this->createPlainMatcher('error');

        return preg_replace($pattern, '$1<?php $_vars = get_defined_vars(); $__container->error(function($task) use ($_vars) { extract($_vars, EXTR_SKIP); $2', $value);
    }

    protected function compileErrorStop(string $value): string
    {
        return preg_replace($this->createPlainMatcher('enderror'), '$1}); ?>$2', $value);
    }

    private function initializeVariables(string $value): string
    {
        preg_match_all('/\$([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)/', $value, $matches);

        foreach (array_unique($matches[0]) as $variable) {
            $value = "<?php {$variable} = isset({$variable}) ? {$variable} : null; ?>\n".$value;
        }

        return $value;
    }

    public function createMatcher(string $function): string
    {
        return '/(?<!\w)(\s*)@'.$function.'(\s*\(.*\))/';
    }

    public function createOpenMatcher(string $function): string
    {
        return '/(?<!\w)(\s*)@'.$function.'(\s*\(.*)\)/';
    }

    public function createPlainMatcher(string $function): string
    {
        return '/(?<!\w)(\s*)@'.$function.'(\s*)/';
    }
}
