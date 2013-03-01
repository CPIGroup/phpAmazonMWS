<?php
/**
 * Contains helper functions needed for the unit tests
 */

/**
* Resets log for next test
*/
function resetLog(){
   file_put_contents('log.txt','');
}

/**
* gets the log contents
*/
function getLog(){
   return file_get_contents('log.txt');
}

/**
* gets log and returns messages in an array
* @param string $s pre-fetched log contents
* @return array list of message strings
*/
function parseLog($s = null){
   if (!$s){
       $s = getLog();
   }
   $temp = explode("\n",$s);
   array_pop($temp);

   $return = array();
   foreach($temp as $x){
       if ($x != ''){
           $tempo = explode('] ',$x);
           if (isset($tempo[1]))
           $return[] = trim($tempo[1]);
       }
   }
   return $return;
}
?>
