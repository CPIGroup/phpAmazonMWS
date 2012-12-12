<?php

if (!defined("DB_PHPUNIT"))
    define("DB_PHPUNIT", true);

if (!defined("SKIP_DB_PHPUNIT"))
    define("SKIP_DB_PHPUNIT", true);

//if (!defined("PHPUNIT_DEBUG"))
//    define("PHPUNIT_DEBUG", true);

if (!defined("PHPUNIT_FILE"))
    define("PHPUNIT_FILE", __FILE__);

chdir(dirname(__FILE__));

// dependencies
// run all dependencies
if (file_exists('./../PlugIn.ini')) {
    $ini = parse_ini_file('./../PlugIn.ini');
    if ($ini and is_array($ini) and isset($ini['Dependencies']))
        include ('./../' . $ini['Dependencies'] . 'test-cases/bootstrap.php');
    chdir(dirname(__FILE__));
}

// include library
if (file_exists('./functions.php'))
    include './functions.php';

// include main function
if (file_exists('./../includes/includes.php'))
    include './../includes/includes.php';

// include extends
if (file_exists('./extends.php'))
    include './extends.php';

// check for correct phpunit test files
if (PHPUNIT_FILE == __FILE__ and !checkPHPUnitFiles('./*/*.php')) {
    die('Bad phpunit file(s)!');
}
?>
