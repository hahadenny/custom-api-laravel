<?php
/**
 * Deployer recipe file containing definitions for hosts and tasks
 * @see https://deployer.org/docs/7.x/getting-started
 */

namespace Deployer;

/** @see https://deployer.org/docs/7.x/recipe/laravel */
require 'recipe/laravel.php';

/**
 * @see https://deployer.org/docs/7.x/contrib/php-fpm
 *      https://deployer.org/docs/7.x/avoid-php-fpm-reloading
 */
require 'contrib/php-fpm.php';

/** @see https://deployer.org/docs/7.x/contrib/npm */
require 'contrib/npm.php';

///////////////////////////////////////////////////////////////////////////
// ENV VARS
//
$disguise_info = [
    'repo'           => 'git@github.com:polygonlabs-devs/porta-api.git',
    'dev_branch'     => 'dev',
    'staging_branch' => 'staging',
    'prod_branch'    => 'main',
];

$greenice_info = [
    'repo'           => 'git@bitbucket.org:greenice/porta-api.git',
    'dev_branch'     => 'dev',     // ??
    'staging_branch' => 'staging', // ??
    'prod_branch'    => 'master',
];

/** Things that may change */
// Repos
$repo            = $disguise_info['repo'];
// Branch names
$dev_branch     = $disguise_info['dev_branch'];
$staging_branch = $disguise_info['staging_branch'];
$prod_branch    = $disguise_info['prod_branch'];
// Server hostnames
$dev_hostname     = 'devapi.porta.solutions';
$staging_hostname = 'stagingapi.porta.solutions';
$prod_hostname    = 'api.porta.solutions';

///////////////////////////////////////////////////////////////////////////

/** define global configs */
set('shared_dirs', ['storage']);
set('application', 'porta');
set('repository', $repo);
set('php_fpm_version', '8.1');
set('http_user', 'www-data');
set('worker_name', 'porta-worker');
set('writable_dirs', [
    'bootstrap/cache',
    'storage',
    'storage/app',
    'storage/app/public',
    'storage/framework',
    'storage/framework/cache',
    'storage/framework/cache/data',
    'storage/framework/sessions',
    'storage/framework/views',
    'storage/logs',
]);
set('writable_chmod_mode', '0775');

/**
 * DEV SERVER
 */
host('dev')
    ->set('remote_user', 'developer')
    ->set('hostname', $dev_hostname)
    ->set('deploy_path', '/var/www/html/porta-api')
    ->set('branch', $dev_branch)
    ->set('composer_options', '--verbose --prefer-dist --no-progress --no-interaction --optimize-autoloader');

/**
 * STAGING SERVER
 */
host('staging')
    ->set('remote_user', 'developer')
    ->set('hostname', $staging_hostname)
    ->set('deploy_path', '/var/www/html/porta-api')
    ->set('branch', $staging_branch)
    ->set('composer_options', '--verbose --prefer-dist --no-progress --no-interaction --optimize-autoloader');

/**
 * PRODUCTION SERVER
 */
host('prod')
    ->set('remote_user', 'developer')
    ->set('hostname', $prod_hostname)
    ->set('deploy_path', '/var/www/html/porta-api')
    ->set('branch', $prod_branch)
    ->set('composer_options', '--verbose --prefer-dist --no-progress --no-interaction --optimize-autoloader');

/**
 * Run Laravel deployment tasks
 *
 * @see https://deployer.org/docs/7.x/recipe/laravel#how-to-deploy-a-laravel-project-with-zero-downtime
 */
task('deploy', [
    // Prepare host for deployment
    // @see https://deployer.org/docs/7.x/recipe/common#deployprepare
    'deploy:prepare',
    // Installs vendors
    'deploy:vendors',
    // Creates the symbolic links configured for the application.
    'artisan:storage:link',
    // Compile all the application's Blade templates
    'artisan:view:cache',
    // Create a cache file for faster configuration loading
    'artisan:config:cache',
    // Run the database migrations
    'artisan:migrate',
    // Initialize permissions in the database
    'artisan:seed:permissions',
    // Publishes the release
    // @see https://deployer.org/docs/7.x/recipe/common#deploypublish
    'deploy:publish',
    // Generate the API Scribe docs
    'artisan:docs:generate',
    // restart workers/service
    'supervisor:restart',
]);

task('supervisor:restart', function () {
    run('echo "" | sudo -S /usr/bin/supervisorctl restart {{worker_name}}:*');
});

task('artisan:docs:generate', function () {
    run('cd {{release_or_current_path}} && {{bin/php}} artisan scribe:generate');
});

// ensure permissions exist in the database -- is idempotent
task('artisan:seed:permissions', function () {
    run('cd {{release_or_current_path}} && {{bin/php}} artisan db:seed --class=PermissionSeeder');
});

after('deploy:failed', 'deploy:unlock');


