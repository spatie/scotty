@setup
$web = ['forge@1.1.1.1', 'forge@2.2.2.2'];
$cli = ['forge@3.3.3.3'];
@endsetup

@servers([
    'local' => '127.0.0.1',
    'web' => $web,
    'cli' => $cli,
])

@task('deploy', ['on' => 'web', 'parallel' => true])
    echo "deploying"
@endtask

@task('restart-workers', ['on' => 'cli'])
    echo "restarting"
@endtask

@story('full-deploy')
deploy
restart-workers
@endstory
