<?php

    chdir( dirname( __FILE__ ) );

    // include library
    if (file_exists( './helperFunctions.php' )) {
        include './helperFunctions.php';
    }

    // include main function
    if (file_exists( '../vendor/autoload.php' )) {
        include '../vendor/autoload.php';
    }

?>
