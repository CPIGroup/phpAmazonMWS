<?php
$menu['Admin']['sub']['Plugins']['sub']['Amazon Plugin'] = array(
    "url" => $this->filePathHTML, 
    "img" => $this->filePathHTML . 'menu-icon.png'
    );

function amazonCronRunDaily(){
    include('amazonFBA-DisableEnableLocal.php');    
    runAmzEnableDisable(dirname(__FILE__));
}

add_action('cron_hourly', 'amazonCronRunDaily', 10);
?>
