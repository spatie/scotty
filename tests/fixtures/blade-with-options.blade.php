@servers(['local' => '127.0.0.1'])

@option('staging')
@option('branch=main')
@option('tag=')

@task('deploy', ['on' => 'local'])
    echo "branch={{ $branch }}"
@endtask
