---
title: Envoy compatibility
weight: 4
---

Scotty can read existing [Laravel Envoy](https://laravel.com/docs/envoy) files out of the box. If your project has an `Envoy.blade.php`, you can run it with Scotty without changing anything. Just run `scotty run deploy` (or whatever your task is called) and it works.

This page documents the Blade file format that Envoy uses, so you can understand your existing file before deciding whether to [migrate to the Scotty.sh format](/docs/scotty/v1/basic-usage/bash-format#migrating-from-envoy).

All tasks should be defined in an `Envoy.blade.php` file at the root of your project.

## Defining servers

Define your servers with the `@servers` directive at the top of the file:

```blade
@servers(['web' => ['user@192.168.1.1'], 'workers' => ['user@192.168.1.2']])
```

The `@servers` declaration should always be placed on a single line.

## Defining tasks

Tasks are the basic building block. They contain the shell commands that should execute on your remote servers:

```blade
@servers(['web' => ['user@192.168.1.1']])

@task('deploy', ['on' => 'web'])
    cd /home/user/example.com
    git pull origin main
    php artisan migrate --force
@endtask
```

### Local tasks

To run a script on your local machine, use `127.0.0.1` as the server address:

```blade
@servers(['localhost' => '127.0.0.1'])
```

### Multiple servers

List multiple servers in the `on` array to run a task on each one:

```blade
@servers(['web-1' => '192.168.1.1', 'web-2' => '192.168.1.2'])

@task('deploy', ['on' => ['web-1', 'web-2']])
    cd /home/user/example.com
    git pull origin {{ $branch }}
    php artisan migrate --force
@endtask
```

By default, the task finishes on the first server before starting on the second.

### Parallel execution

To run on all servers at the same time, add the `parallel` option:

```blade
@task('deploy', ['on' => ['web-1', 'web-2'], 'parallel' => true])
    cd /home/user/example.com
    git pull origin {{ $branch }}
@endtask
```

### Task confirmation

Add `confirm` to prompt before running:

```blade
@task('deploy', ['on' => 'web', 'confirm' => true])
    cd /home/user/example.com
    git pull origin main
@endtask
```

## Setup

If you need to execute PHP code before your tasks run, use the `@setup` directive:

```blade
@setup
    $now = new DateTime;
    $branch = 'main';
    $releaseName = $now->format('YmdHis');
@endsetup
```

To require other PHP files, use `@include` at the top of your file:

```blade
@include('vendor/autoload.php')
```

## Variables

You can pass arguments from the command line:

```bash
scotty run deploy --branch=master
```

Access them in your tasks using Blade's echo syntax:

```blade
@task('deploy', ['on' => 'web'])
    cd /home/user/example.com

    @if ($branch)
        git pull origin {{ $branch }}
    @endif

    php artisan migrate --force
@endtask
```

## Stories (macros)

Stories group tasks under a single name:

```blade
@servers(['web' => ['user@192.168.1.1']])

@story('deploy')
    update-code
    install-dependencies
@endstory

@task('update-code')
    cd /home/user/example.com
    git pull origin main
@endtask

@task('install-dependencies')
    cd /home/user/example.com
    composer install
@endtask
```

Run a story the same way you run a task:

```bash
scotty run deploy
```

## Hooks

Hooks run at different points during execution. All hook code is interpreted as PHP and executed locally.

### @before

Runs before each task:

```blade
@before
    if ($task === 'deploy') {
        // ...
    }
@endbefore
```

### @after

Runs after each task:

```blade
@after
    if ($task === 'deploy') {
        // ...
    }
@endafter
```

### @error

Runs when a task fails (exit code greater than 0):

```blade
@error
    if ($task === 'deploy') {
        // ...
    }
@enderror
```

### @success

Runs if all tasks executed without errors:

```blade
@success
    // ...
@endsuccess
```

### @finished

Runs after all tasks, regardless of the outcome:

```blade
@finished
    if ($exitCode > 0) {
        // ...
    }
@endfinished
```

## Importing tasks

You can import other Envoy files:

```blade
@import('vendor/package/Envoy.blade.php')
```

For the full Blade format reference, see the [Laravel Envoy documentation](https://laravel.com/docs/envoy).
