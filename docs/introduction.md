---
title: Introduction
weight: 1
---

Scotty is a tool for executing common tasks on your remote servers. You define tasks in a file, and Scotty handles connecting to your servers, running the scripts, and showing you what's happening.

If you've used [Laravel Envoy](https://laravel.com/docs/envoy), you'll feel right at home. Scotty can read your existing `Envoy.blade.php` files out of the box, so you can try it without changing anything.

## What Scotty adds over Envoy

### A pure bash file format

Envoy uses Blade templates for shell scripts. That means no syntax highlighting for the shell parts, and a templating language designed for HTML views being used for bash commands. Scotty introduces a `Scotty.sh` format that is plain bash with annotation comments. Your editor understands it perfectly.

![Scotty.sh in an editor](https://github.com/spatie/scotty/blob/main/docs/images/scotty-file-editor.png?raw=true)
<!-- SCREENSHOT NEEDED: Scotty.sh file open in an editor showing syntax highlighting -->

### Better output

Envoy streams raw output with minimal formatting. Scotty shows each task with its name, a step counter, elapsed time, and the command currently being executed. When the deploy finishes, you get a summary table with timing.

![Deploy output](https://github.com/spatie/scotty/blob/main/docs/images/scotty-run-deploy.png?raw=true)
<!-- SCREENSHOT NEEDED: Full `scotty run deploy` output showing a real deploy -->

### Pause and resume

Press `p` during a deploy to pause after the current task. Press `Enter` to continue.

![Pause and resume](https://github.com/spatie/scotty/blob/main/docs/images/scotty-pause.png?raw=true)
<!-- SCREENSHOT NEEDED: Deploy paused between tasks -->

### Doctor command

Run `scotty doctor` to validate your file, check SSH connectivity to all servers, and verify that tools like PHP, Composer, and Git are installed on each remote machine.

![scotty doctor](https://github.com/spatie/scotty/blob/main/docs/images/scotty-doctor.png?raw=true)
<!-- SCREENSHOT NEEDED: `scotty doctor` output with all checks passing -->

### Envoy compatibility

Scotty reads existing `Envoy.blade.php` files. You can drop it into any project that already uses Envoy and run `scotty run deploy` right away.
