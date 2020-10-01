<?php
// if(isset($_POST['avatar'])){
    // $avatar = $_POST['avatar'];
    // $json = file_get_contents("php://input");

    // if(isset($json)){

    // if(is_array($obj = json_decode($json,true))){
        if(isset($_GET['key'])){
            // if(array_key_exists('avatar',$obj)){
                // $avatar = $obj['avatar'];
                $avatar = $_GET['key'];
                header('Content-type: image/jpg');
                $bytes = file_get_contents("./uploads/" . $avatar);
                echo $bytes;
            }else exit(0);
        // }
        
    // }
   
   
    
// }