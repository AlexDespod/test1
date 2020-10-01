<?php
require('dataBase.php');
 $json = file_get_contents("php://input");
 if(isset($json)){
    $obj = json_decode($json,true);
    $name = $obj['name'];
    $email = $obj['email'];
    $password = $obj['password'];
    
    

    

    if(isset($name ,$email,$password)){
       
            $queryIsCheck = mysqli_query($mysqli,"SELECT * FROM users WHERE email='$email' OR username='$name'");

                if($queryIsCheck){
                    $numrows = mysqli_num_rows($queryIsCheck);
                    if($numrows == 0){
                        $unique = md5($name);
                        $querySetUser = mysqli_query($mysqli,"INSERT INTO users (username,email,password,chats) VALUES ('$name','$email','$password','$unique')");
                        if(!$querySetUser){
                            echo json_encode("err");
                        }
                        else {
                            $mass = ['true'];
                            $gotuser = mysqli_query($mysqli,"SELECT * FROM users WHERE email='$email' AND username='$name'");
                            $mass [] = mysqli_fetch_assoc($gotuser);
                            tableMaker($unique);
                            echo json_encode($mass);

                        }          
                    }else {
                        $massnot=['false'];
                        echo json_encode($massnot); 
                    } 
               
        }
    }
 }
 function tableMaker($user){
    global $mysqli;
    $createtable = mysqli_query($mysqli,"CREATE TABLE `$user` ( `id` INT(11) AUTO_INCREMENT , `chatid` VARCHAR(100) , `interlocutor` VARCHAR(100)  , PRIMARY KEY (`id`))");
    

}

mysqli_close($mysqli);