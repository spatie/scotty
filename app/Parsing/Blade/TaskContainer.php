<?php

namespace App\Parsing\Blade;

use Closure;
use Exception;
use InvalidArgumentException;

class TaskContainer
{
    /** @var array<string, string|array<string>> */
    protected array $servers = [];

    /** @var array<string, mixed> */
    protected array $sharedData = [];

    /** @var array<string, array<string>> */
    protected array $macros = [];

    /** @var array<string, string> */
    protected array $tasks = [];

    /** @var array<Closure> */
    protected array $success = [];

    /** @var array<Closure> */
    protected array $error = [];

    /** @var array<Closure> */
    protected array $before = [];

    /** @var array<Closure> */
    protected array $after = [];

    /** @var array<Closure> */
    protected array $finished = [];

    /** @var array<string, array<string, mixed>> */
    protected array $taskOptions = [];

    /** @var array<string> */
    protected array $taskStack = [];

    /** @var array<string> */
    protected array $macroStack = [];

    /** @var array<string, array<string, mixed>> */
    protected array $macroOptions = [];

    public function loadServers(string $path, Compiler $compiler): void
    {
        $this->load($path, $compiler, [], __serversOnly: true);
    }

    public function load(string $__path, Compiler $__compiler, array $__data = [], bool $__serversOnly = false): void
    {
        $__envoyPath = $this->writeCompiledEnvoyFile($__compiler, $__path, $__serversOnly);

        $__container = $this;

        ob_start() && extract($__data);

        include $__envoyPath;

        @unlink($__envoyPath);

        $this->replaceSubTasks();

        ob_end_clean();
    }

    protected function writeCompiledEnvoyFile(Compiler $compiler, string $path, bool $serversOnly): string
    {
        $envoyPath = sys_get_temp_dir().'/Envoy'.md5_file($path).'.php';

        $compiled = $compiler->compile(file_get_contents($path), $serversOnly);

        // Replace __DIR__ with the actual source directory, since the compiled
        // file is written to a temp directory where __DIR__ would resolve incorrectly.
        $sourceDir = dirname(realpath($path));
        $compiled = str_replace('__DIR__', "'".addslashes($sourceDir)."'", $compiled);

        file_put_contents($envoyPath, $compiled);

        return $envoyPath;
    }

    protected function replaceSubTasks(): void
    {
        foreach ($this->tasks as $name => &$script) {
            $callback = function (array $match): string {
                $taskContent = $this->tasks[$match[2]];

                return "{$match[1]}{$taskContent}";
            };

            $script = $this->trimSpaces(
                preg_replace_callback("/(\s*)@run\('(.*)'\)/", $callback, $script)
            );
        }
    }

    /** @param array<string, string> $servers */
    public function servers(array $servers): void
    {
        $this->servers = $servers;
    }

    public function getServer(string $server): string|array
    {
        if (! array_key_exists($server, $this->servers)) {
            throw new Exception("Server [{$server}] is not defined.");
        }

        return $this->servers[$server];
    }

    /** @return array<string, string|array<string>> */
    public function getServers(): array
    {
        return $this->servers;
    }

    public function hasOneServer(): bool
    {
        return count($this->servers) === 1;
    }

    public function getFirstServer(): string
    {
        return reset($this->servers);
    }

    /** @param array<string, mixed> $data */
    public function import(string $file, array $data = []): void
    {
        $keysToExclude = [
            '__path', '__dir', '__compiler', '__data', '__serversOnly',
            '__envoyPath', '__container', 'this',
        ];

        $data = array_diff_key($data, array_flip($keysToExclude));

        $path = $this->resolveImportPath($file);

        if ($path === false) {
            throw new InvalidArgumentException("Unable to locate file: [{$file}].");
        }

        $this->load($path, new Compiler, $data);
    }

    protected function resolveImportPath(string $file): string|false
    {
        $realPath = realpath($file);

        if ($realPath !== false) {
            return $realPath;
        }

        $bladeRealPath = realpath("{$file}.blade.php");

        if ($bladeRealPath !== false) {
            return $bladeRealPath;
        }

        $vendorRealPath = realpath(getcwd()."/vendor/{$file}/Envoy.blade.php");

        if ($vendorRealPath !== false) {
            return $vendorRealPath;
        }

        return false;
    }

    public function share(string $key, mixed $value): void
    {
        $this->sharedData[$key] = $value;
    }

    /** @return array<string, array<string>> */
    public function getMacros(): array
    {
        return $this->macros;
    }

    /** @return array<string>|null */
    public function getMacro(string $macro): ?array
    {
        return $this->macros[$macro] ?? null;
    }

    /** @return array<string, mixed> */
    public function getMacroOptions(string $macro): array
    {
        return $this->macroOptions[$macro] ?? [];
    }

    /** @return array<string, string> */
    public function getTasks(): array
    {
        return $this->tasks;
    }

    /** @return array<string, mixed> */
    public function getTaskOptions(string $task): array
    {
        return $this->taskOptions[$task] ?? [];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string>
     */
    public function resolveServers(array $options): array
    {
        $on = $options['on'] ?? [];

        $serverNames = is_array($on) ? $on : [$on];

        return array_map(fn (string $name) => $this->getServer($name), $serverNames);
    }

    /** @param array<string, mixed> $options */
    public function startMacro(string $macro, array $options = []): void
    {
        ob_start() && $this->macroStack[] = $macro;

        $this->macroOptions[$macro] = $options;
    }

    public function endMacro(): void
    {
        $macro = array_map('trim', preg_split('/\n|\r\n?/', $this->trimSpaces(trim(ob_get_clean()))));

        $this->macros[array_pop($this->macroStack)] = $macro;
    }

    /** @param array<string, mixed> $options */
    public function startTask(string $task, array $options = []): void
    {
        ob_start() && $this->taskStack[] = $task;

        $this->taskOptions[$task] = $this->mergeDefaultOptions($options);
    }

    /** @return array<string, mixed> */
    protected function mergeDefaultOptions(array $options): array
    {
        return array_merge(['as' => null, 'on' => array_keys($this->servers)], $options);
    }

    public function endTask(): void
    {
        $name = array_pop($this->taskStack);

        $contents = trim(ob_get_clean());

        if (isset($this->tasks[$name])) {
            $this->tasks[$name] = str_replace('@parent', $this->tasks[$name], $contents);

            return;
        }

        $this->tasks[$name] = $contents;
    }

    public function before(Closure $callback): void
    {
        $this->before[] = $callback;
    }

    /** @return array<Closure> */
    public function getBeforeCallbacks(): array
    {
        return $this->before;
    }

    public function after(Closure $callback): void
    {
        $this->after[] = $callback;
    }

    /** @return array<Closure> */
    public function getAfterCallbacks(): array
    {
        return $this->after;
    }

    public function finished(Closure $callback): void
    {
        $this->finished[] = $callback;
    }

    /** @return array<Closure> */
    public function getFinishedCallbacks(): array
    {
        return $this->finished;
    }

    public function success(Closure $callback): void
    {
        $this->success[] = $callback;
    }

    /** @return array<Closure> */
    public function getSuccessCallbacks(): array
    {
        return $this->success;
    }

    public function error(Closure $callback): void
    {
        $this->error[] = $callback;
    }

    /** @return array<Closure> */
    public function getErrorCallbacks(): array
    {
        return $this->error;
    }

    protected function trimSpaces(string $value): string
    {
        return implode(PHP_EOL, array_map('trim', explode(PHP_EOL, $value)));
    }
}
