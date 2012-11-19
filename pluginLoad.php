<?php
$menu['Admin']['sub']['Plugins']['sub']['New Amazon Plugin'] = array(
    "url" => $this->filePathHTML, 
    "img" => $this->filePathHTML . 'menu-icon.png'
    );

function amazonCronRunDaily(){
    include('test.php');    
    runAmzEnableDisable(dirname(__FILE__));
}

//add_action('cron_hourly', 'amazonCronRunDaily', 10);
?>
