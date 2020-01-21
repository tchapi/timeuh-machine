<?php

namespace Deployer;

require 'recipe/symfony4.php';

set('ssh_type', 'native');
set('ssh_multiplexing', true);

// Configuration
inventory('deploy/servers.yml');

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
set('writable_mode', "chmod");

desc('Restart PHP-FPM service');
task('php-fpm:restart', function () {
    // The user must have rights for restart service
    // /etc/sudoers: username ALL=NOPASSWD:/bin/systemctl restart php-fpm.service
    run('sudo systemctl restart php7.4-fpm.service');
});

// Command crontab
/*
/!\ A.env file must exist with the following syntax :

    export APP_ENV=prod
    export ..

in the deployment directory
*/
desc('Add crontab for fetch-tracks');
task('deploy:crontab', function () {
    cd('/etc/cron.d/');
    run('echo -e \'# Fetching tracks\n*/8 * * * * {{user}} . {{deploy_path}}/.env && {{bin/php}} {{current_path}}/bin/console timeuh-machine:fetch-tracks --env=prod >> /dev/null 2>&1\n# Updating archives\n42 4 * * * {{user}} . {{deploy_path}}/.env && {{bin/php}} {{current_path}}/bin/console timeuh-machine:update-archives --env=prod >> /dev/null 2>&1\' | sudo tee timeuh-machine');
    writeln('<info>Wrote /etc/cron.d/timeuh-machine</info>');
});

// Hooks
after('deploy:symlink', 'php-fpm:restart');
after('deploy:symlink', 'deploy:crontab');
after('deploy:failed', 'deploy:unlock');
