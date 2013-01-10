<?php
/**
 * Autoload Amazon Classes * 
 */
function autoloadAmazonClasses($className) {
	$file = dirname(__FILE__).'/classes/' . $className. '.php';
	if (file_exists($file)){
		include($file);
	}else{
		//Commented out because there's a strange crash bug when this runs in a test case
		//if(function_exists('myLog'))
			 //myLog('Could not include:'.$file,LOG_ALERT);
	}
}
spl_autoload_register('autoloadAmazonClasses');
?>
