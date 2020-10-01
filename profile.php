<?php
 require('dataBase.php');

 $json = file_get_contents("php://input");
 if(isset($json)){
    $obj = json_decode($json,true);
    switch($obj['request']){
        case 'SETPROFILE':
            $name = $obj['name'];
            $avatar = $obj['avatar'];
            $about = $obj['about'];
            setProfile($name,$avatar,$about);
        break;
        case 'GETSETTINGS':
            $name = $obj['name'];
            getSettings($name);
        break;

    }
    
 }

 mysqli_close($mysqli);


 function setProfile($name,$avatar,$about){
     global $mysqli;
    $file_ext = explode('.',$avatar['uri'])[1];
    $unique = uniqid();
    $file_dir = "./uploads/" . $unique . "." . $file_ext;
    $file_location = $unique . "." . $file_ext;
    $bytes = base64_decode($avatar['base64']);
    file_put_contents($file_dir, $bytes);
    $query = mysqli_query($mysqli,"INSERT INTO profiles (name,avatar,about) values ('$name','$file_location','$about')");
    if($query){
        $answer = array("status"=>"true");
        echo json_encode($answer);
    }
    else {
        $answer = array("status"=>"false");
        echo json_encode($answer);
    }
 }
 function getSettings($name){
    global $mysqli; 
    $query = mysqli_query($mysqli,"SELECT * FROM profiles WHERE name='$name'");
    if($query){
        $assoc = mysqli_fetch_assoc($query);
        $answer = array("status"=>"true","avatar"=>$assoc['avatar'],"about"=>$assoc['about']);
        echo json_encode($answer);
    }
    else {
        $answer = array("status"=>"false");
        echo json_encode($answer);
    }
}