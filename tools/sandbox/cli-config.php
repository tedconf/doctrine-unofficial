<?php
#
# This configuration file is loaded by the Doctrine CLI whenever you execute
# a task. A CLI configuration file usually initializes two local variables:
#
# $em - An EntityManager instance that the CLI tasks should use.
# $args - An array of default command line arguments that take effect when an
#         argument is not specifically set on the command line.
#
# You can create several CLI configuration files with different names, for different databases.
# Every CLI task recognizes the --config=<path> option where you can specify the configuration
# file to use for a particular task. If this option is not given, the CLI looks for a file
# named "cli-config.php" (this one) in the same directory and uses that by default.
#

require_once __DIR__ . '/../../lib/Doctrine/Common/IsolatedClassLoader.php';

$classLoader = new \Doctrine\Common\IsolatedClassLoader('Entities');
$classLoader->setBasePath(__DIR__);
$classLoader->register();

$config = new \Doctrine\ORM\Configuration();
$config->setMetadataCacheImpl(new \Doctrine\Common\Cache\ArrayCache);

$connectionOptions = array(
    'driver' => 'pdo_sqlite',
    'path' => 'database.sqlite'
);

$em = \Doctrine\ORM\EntityManager::create($connectionOptions, $config);
$args = array(
    'classdir' => './Entities'
);