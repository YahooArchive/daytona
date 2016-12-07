<?php
require('lib/auth.php');
if ($userId){
  if(isset($_POST["filename"])) {
      $filename = $_POST["filename"];
  }
  $output = ""; 
  $fileptr = fopen($filename, "r");
  if ($fileptr) {
        while (($line = fgets($fileptr)) !== false) {
          $output .= $line ;
        }
        fclose($fileptr);
        echo $output;
  }else{
    echo http_response_code(404);
  }
}
?>
