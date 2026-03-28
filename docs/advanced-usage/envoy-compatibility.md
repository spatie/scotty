---
title: Envoy compatibility
weight: 4
---

Scotty can read existing [Laravel Envoy](https://laravel.com/docs/envoy) files out of the box. If your project has an `Envoy.blade.php`, you can run it with Scotty without changing anything.

This page documents the Blade file format that Envoy uses, so you can understand your existing file before deciding whether to [migrate to the Scotty.sh format](/docs/scotty/v1/basic-usage/bash-format#migrating-from-blade).

All tasks should be defined in an `Envoy.blade.php` file at the root of your project.

## Defining servers

An array of `@servers` is defined at the top of the file. You can reference these servers in your task declarations:

```blade
@servers(['web' => ['user@192.168.1.1'], 'workers' => ['user@192.168.1.2']])
```

The `@servers` declaration should always be placed on a single line.

## Defining tasks

Tasks are the basic building block. They define the shell commands that should execute on your remote servers:

```blade
@servers(['web' => ['user@192.168.1.1']])

@task('deploy', ['on' => 'web'])
    cd /home/user/example.com
    git pull origin main
    php artisan migrate --force
@endtask
```

Within your `@task` declarations, place the shell commands that should execute on your servers when the task is invoked.

### Local tasks

You can force a script to run on your local computer by specifying the server's IP address as `127.0.0.1`:

```blade
@servers(['localhost' => '127.0.0.1'])
```

### Multiple servers

You can run a task across multiple servers. First, add additional servers to your `@servers` declaration. Then list each server in the task's `on` array:

```blade
@servers(['web-1' => '192.168.1.1', 'web-2' => '192.168.1.2'])

@task('deploy', ['on' => ['web-1', 'web-2']])
    cd /home/user/example.com
    git pull origin {{ $branch }}
    php artisan migrate --force
@endtask
```

By default, tasks execute on each server serially. A task will finish running on the first server before proceeding to the second.

### Parallel execution

If you would like to run a task across multiple servers in parallel, add the `parallel` option:

```blade
@task('deploy', ['on' => ['web-1', 'web-2'], 'parallel' => true])
    cd /home/user/example.com
    git pull origin {{ $branch }}
@endtask
```

### Task confirmation

Add the `confirm` option to prompt for confirmation before running a task:

```blade
@task('deploy', ['on' => 'web', 'confirm' => true])
    cd /home/user/example.com
    git pull origin main
@endtask
```

## Setup

Sometimes you need to execute PHP code before running your tasks. Use the `@setup` directive:

```blade
@setup
    $now = new DateTime;
    $branch = 'main';
    $releaseName = $now->format('YmdHis');
@endsetup
```

If you need to require other PHP files, use the `@include` directive at the top of your file:

```blade
@include('vendor/autoload.php')
```

## Variables

You can pass arguments to tasks from the command line:

```bash
scotty run deploy --branch=master
```

Access the options within your tasks using Blade's echo syntax. You can also use `@if` statements and loops:

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

Stories group a set of tasks under a single name:

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

When tasks and stories run, a number of hooks are executed. All hook code is interpreted as PHP and executed locally.

### @before

Runs before each task. Receives the name of the task that will be executed:

```blade
@before
    if ($task === 'deploy') {
        // ...
    }
@endbefore
```

### @after

Runs after each task. Receives the name of the task that was executed:

```blade
@after
    if ($task === 'deploy') {
        // ...
    }
@endafter
```

### @error

Runs after any task failure (exit code greater than 0):

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

Runs after all tasks, regardless of exit status. Receives the exit code:

```blade
@finished
    if ($exitCode > 0) {
        // ...
    }
@endfinished
```

## Importing tasks

You can import other Envoy files so their stories and tasks are added to yours:

```blade
@import('vendor/package/Envoy.blade.php')
```

For the full Blade format reference, see the [Laravel Envoy documentation](https://laravel.com/docs/envoy).
