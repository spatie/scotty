@servers(['local' => '127.0.0.1'])

@task('deploy', ['on' => 'local'])
    echo "deploying"
@endtask
