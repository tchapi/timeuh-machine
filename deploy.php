<?php

namespace Deployer;

require 'recipe/symfony4.php';

set('ssh_type', 'native');
set('ssh_multiplexing', true);

// Configuration
inventory('deploy/servers.yml');

set('env_vars', 'APP_ENV=prod');

set('env', function () {
    return [
        'APP_ENV' => 'prod',
        'TUNEEFY_TOKEN' => 'NA',
        'SPOTIFY_CLIENT_ID' => 'NA',
        'SPOTIFY_CLIENT_SECRET' => 'NA',
        'GA_TRACKER_ID' => '0',
        'DATABASE_URL' => 'null'
    ];
});

set('http_user', 'www-data');
set('default_stage', 'production');
set('repository', 'git@github.com:tchapi/timeuh-machine.git');

set('clear_paths', [
  './README.md',
  './.gitignore',
  './.git',
  './deploy',
  './.php_cs',
  './deploy.php',
  './.env*',
]);

desc('Restart PHP-FPM service');
task('php-fpm:restart', function () {
    // The user must have rights for restart service
    // /etc/sudoers: username ALL=NOPASSWD:/bin/systemctl restart php-fpm.service
    run('sudo systemctl restart php7.1-fpm.service');
});

// Draft crontab
desc('Add crontab for fetch-tracks');
task('deploy:crontab', function () {
    cd('/var/tmp');
    run('echo -n > deploy.crontab');
    run('echo \'*/10 * * * * {{env_vars}} {{bin/php}} {{current_path}}/bin/console timeuh-machine:fetch-tracks --env=prod >> /dev/null 2>&1\' >> deploy.crontab');
    run('cat deploy.crontab | crontab');
    $output = run('crontab -l');
    run('rm deploy.crontab');
    writeln('<info>' . $output . '</info>');
});

// Hooks
after('deploy', 'success');
after('deploy:symlink', 'php-fpm:restart');
after('deploy:symlink', 'deploy:crontab');
after('deploy:failed', 'deploy:unlock');
