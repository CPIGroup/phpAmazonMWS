<?php
include('/var/www/athena/includes/header.php');

include('includes/classesproto.php');

$a = new AmazonOrder('BigKitchen');

$a->genRequest();

include('/var/www/athena/includes/footer.php');

?>