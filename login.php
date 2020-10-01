<?php
 require('dataBase.php');
 $json = file_get_contents("php://input");
 if(isset($json)){
    $obj = json_decode($json,true);
    $name = $obj['name'];
    
    $password = $obj['password'];
    
    

    

    if(isset($name ,$password)){
       
            $queryIsCheck = mysqli_query($mysqli,"SELECT * FROM users WHERE password='$password' AND username='$name'");

                if($queryIsCheck){
                    $numrows = mysqli_num_rows($queryIsCheck);
                    if($numrows == 1){
                            $mass = ['true'];
                            $gotuser = mysqli_query($mysqli,"SELECT * FROM users WHERE password='$password' AND username='$name'");
                            $mass [] = mysqli_fetch_assoc($gotuser);
                            
                            echo json_encode($mass);
                        }
                        elseif($numrows == 0){
                        $mass = ['false'];
                        echo json_encode($mass);}      
                         
            }       
        
    }
}
    
mysqli_close($mysqli);
 
 