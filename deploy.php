<?php

namespace Deployer;

require 'recipe/symfony3.php';

task('deploy', [
    'deploy:prepare',
    'deploy:lock',
    'deploy:release',
    'deploy:update_code',
    'deploy:create_cache_dir',
    'deploy:shared',
    'deploy:assets',
    'deploy:vendors',
    'deploy:assets:install',
    'deploy:assetic:dump',
    'deploy:cache:warmup',
    'deploy:writable',
    'deploy:clear_paths', // This has moved down the line
    'deploy:symlink',
    'deploy:unlock',
    'cleanup',
]);

// Configuration
serverList('deploy/servers.yml');

set('env_vars', 'SYMFONY_ENV=prod');
set('ssh_type', 'native');
set('ssh_multiplexing', true);
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
  './composer.*',
]);

// Tasks
desc('Deploy production parameters');
task('deploy:parameters', function () {
    upload('./deploy/parameters.{{env}}.yml', '{{deploy_path}}/shared/app/config/parameters.yml');
});

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
after('deploy:update_code', 'deploy:parameters');
after('deploy:failed', 'deploy:unlock');
