@servers(['local' => '127.0.0.1'])

@task('startDeployment', ['on' => 'local', 'emoji' => '🏃'])
    echo "deploying"
@endtask

@task('noEmoji', ['on' => 'local'])
    echo "no emoji"
@endtask
