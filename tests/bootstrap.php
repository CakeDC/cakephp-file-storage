<?php
declare(strict_types=1);

/**
 * Bootstrap
 */

use Cake\Core\Plugin;
use function Cake\Core\env as env;

$findRoot = function ($root) {
    do {
        $lastRoot = $root;
        $root = dirname($root);
        if (is_dir($root . '/vendor/cakephp/cakephp')) {
            return $root;
        }
    } while ($root !== $lastRoot);
    throw new \Exception('Cannot find the root of the application, unable to run tests');
};

$root = $findRoot(__FILE__);
unset($findRoot);
chdir($root);

require $root . '/vendor/cakephp/cakephp/tests/bootstrap.php';
$loader = require $root . '/vendor/autoload.php';

$loader->setPsr4('Cake\\', './vendor/cakephp/cakephp/src');
$loader->setPsr4('Cake\Test\\', './vendor/cakephp/cakephp/tests');
$loader->setPsr4('Burzum\Imagine\\', './vendor/burzum/cakephp-imagine-plugin/src');

if (env('FIXTURE_SCHEMA_METADATA')) {
    $loader = new \Cake\TestSuite\Fixture\SchemaLoader();
    $loader->loadInternalFile(env('FIXTURE_SCHEMA_METADATA'));
}

$config = [
    'path' => dirname(__FILE__, 2) . DS,
];
Plugin::getCollection()->add(new \Burzum\FileStorage\Plugin($config));
