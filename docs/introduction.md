---
title: Introduction
weight: 1
---

Scotty is a tool for executing common tasks on your remote servers. You define tasks in a `Scotty.sh` file, and Scotty handles connecting to your servers, running the scripts, and showing you what's happening.

## Features

### A pure bash file format

Tasks are defined in a `Scotty.sh` file that is plain bash with annotation comments. Your editor highlights it correctly and you get full shell support (linting, syntax checking, autocompletion).

![Scotty.sh in an editor](https://github.com/spatie/scotty/blob/main/docs/images/scotty-file-editor.png?raw=true)

### Rich output

Scotty shows each task with its name, a step counter, elapsed time, and the command currently being executed. When all tasks finish, you get a summary table with timing.

![Deploy output](https://github.com/spatie/scotty/blob/main/docs/images/scotty-run-deploy.png?raw=true)

### Pause and resume

Press `p` during a deploy to pause after the current task. Press `Enter` to continue.

![Pause and resume](https://github.com/spatie/scotty/blob/main/docs/images/scotty-pause.png?raw=true)

### Doctor command

Run `scotty doctor` to validate your file, check SSH connectivity to all servers, and verify that tools like PHP, Composer, and Git are installed on each remote machine.

![scotty doctor](https://github.com/spatie/scotty/blob/main/docs/images/scotty-doctor.png?raw=true)

### Envoy compatibility

Already using Laravel Envoy? Scotty reads existing `Envoy.blade.php` files out of the box. See the [Envoy compatibility](/docs/scotty/v1/advanced-usage/envoy-compatibility) page for details.
