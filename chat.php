<?php
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Allow-Credentials: true');
 $json = file_get_contents("php://input");
 if(isset($json)){
    $obj = json_decode($json,true);
    $usermess = $obj['query'];
    $user_name = $obj['name'];
    $chatid = $obj['chatid'];
    $interlocutor = $obj['interlocutor'];
    $mysqli = mysqli_connect('127.0.0.1', 'root', '', 'datachat','3306');
    if (!$mysqli) {
        die('Ошибка подключения');
    }
    else {                      
        switch($usermess){
                case 'GETLISTOFOTHETUSERS':
                    getlist($user_name);
                    break;
                case 'GETCHAT':
                    userselfchat($user_name,$interlocutor);
                    break;
                case 'USERCHATS':
                    userchats($user_name);
                    break;
                case 'GOCHAT':
                    gochat($user_name);
                    break;
                case 'GETMESSAGES':
                    getmessages($chatid);
                    break;
                case 'GETFRIENDSLIST':
                    getFriedsList($user_name);
                    break;  
            }
    }
}
    
    function gochat($user_name){
        global $mysqli;
        $table = mysqli_query($mysqli,"SELECT * FROM `$user_name`");
        $numrows = mysqli_num_rows($table);              
        $mass = [];
        while($numrows>0){
            $mass [] = mysqli_fetch_assoc($table); 
            $numrows--;
        }
                           
        echo json_encode($mass);

    }

    function userchats($name){
        global $mysqli;
        $query = mysqli_query($mysqli,"SELECT chats FROM users WHERE username = '$name'");
        $assoc = mysqli_fetch_assoc($query);
        $tablename = $assoc['chats'];
        
        $table = mysqli_query($mysqli,"SELECT * FROM `$tablename`");
        $numrows = mysqli_num_rows($table);              
        $mass = [];
        while($numrows>0){
            $mass [] = mysqli_fetch_assoc($table); 
            $numrows--;
        }
                           
        echo json_encode($mass);

    }

    function userselfchat($user_name,$interlocutor){
        global $mysqli;
        $queryIsCheck = mysqli_query($mysqli,"SELECT chats FROM users WHERE username = '$user_name'");
        if($queryIsCheck){
            $assoc = mysqli_fetch_assoc($queryIsCheck);
            $tablename = $assoc['chats'];
           
            $isExist = mysqli_query($mysqli,"SELECT * FROM `$tablename` WHERE interlocutor = '$interlocutor'");
            $num_rows = mysqli_num_rows($isExist);
            if($num_rows<1){
                $random_num = mt_rand(100, 9999);
                $chat_id = $tablename . $random_num;
                $set_id_of_table = mysqli_query($mysqli,"INSERT INTO `$tablename` (chatid,interlocutor) VALUES ('$chat_id','$interlocutor')");
                $interlocTable = mysqli_query($mysqli,"SELECT chats FROM users WHERE username = '$interlocutor'");
                if($interlocTable){
                    $assocIn = mysqli_fetch_assoc($interlocTable);
                    $tablenameIn = $assocIn['chats'];
                    $set_id_of_tableIn = mysqli_query($mysqli,"INSERT INTO `$tablenameIn` (chatid,interlocutor) VALUES ('$chat_id','$user_name')");
                    $createtable = mysqli_query($mysqli,"CREATE TABLE `$chat_id` ( `id` INT(11) AUTO_INCREMENT , `message` VARCHAR(100) , `sender` VARCHAR(50) , `ischecked` INT NOT NULL , PRIMARY KEY (`id`))");
                    $send = array("answer"=>"true","type"=>"newchat","chatkey"=>"$chat_id","interlocutor"=>"$interlocutor");
                    echo json_encode($send);
                }
            }
            elseif($num_rows>0){
                $isExist = mysqli_query($mysqli,"SELECT chatid FROM `$tablename` WHERE interlocutor = '$interlocutor'");
                $assoc = mysqli_fetch_assoc($isExist);
                $chat_id = $assoc['chatid'];

                $send = array("answer"=>"true", "chatkey"=>"$chat_id","interlocutor"=>"$interlocutor");
                    echo json_encode($send);
                
            }
           
        }   
    }
   
    function getlist($name){
        global $mysqli;
        $queryIsCheck = mysqli_query($mysqli,"SELECT users.id,users.username,profiles.avatar FROM users JOIN profiles ON users.username = profiles.name WHERE users.username<>'$name'");
        if($queryIsCheck){
            $numrows = mysqli_num_rows($queryIsCheck);              
                    $mass = [];
                    while($numrows>0){
                        $mass [] = mysqli_fetch_assoc($queryIsCheck); 
                        $numrows--;
                    }
                                       
                    echo json_encode($mass);
                }               
         }  
         
    function getFriedsList($name){
        global $mysqli;
        $query = mysqli_query($mysqli,"SELECT friends.id,friends.user1,friends.user2,profiles.avatar FROM friends JOIN profiles ON (friends.user1 = profiles.name OR friends.user2 = profiles.name) WHERE ((friends.user1='$name' AND profiles.name<>'$name') OR (friends.user2='$name'AND profiles.name<>'$name'))");
        if($num_rows = mysqli_num_rows($query) > 0){
            $massToSend = [];
            while ( $all = mysqli_fetch_assoc($query)) {
                    $myKey = array_search($name,$all);
                    if($myKey == "user1"){
                     $massToSend[] = array("friend"=>$all['user2'],"id"=>$all['id'],"avatar"=>$all['avatar']);
                    }else {
                     $massToSend[] = array("friend"=>$all['user1'],"id"=>$all['id'],"avatar"=>$all['avatar']);
                    }            
            }
            echo json_encode($massToSend);
        }  
    }
         
   function getmessages($chat_id){
    global $mysqli;
    $selectAll = mysqli_query($mysqli,"SELECT * FROM `$chat_id`");
    if($selectAll){
    $numrows = mysqli_num_rows($selectAll);              
                    $mass = [];
                    while($numrows>0){
                        $mass [] = mysqli_fetch_assoc($selectAll); 
                        $numrows--;
                    }
                                       
                    echo json_encode($mass);
                }else  echo json_encode($chat_id);
                
     }
     mysqli_close($mysqli);