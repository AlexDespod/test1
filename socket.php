<?php 
declare(encoding='UTF-8');
error_reporting(E_ALL);
ob_implicit_flush();
    define('PORT',"8000");
    define('HOST',"192.168.0.100");
    require("dataBase.php");

   

    if(!$socket = socket_create(AF_INET,SOCK_STREAM,SOL_TCP)){
        die(socket_strerror(socket_last_error($socket)) . "\n");
    }

    if(!socket_set_option($socket,SOL_SOCKET,SO_REUSEADDR,1)){
        die(socket_strerror(socket_last_error($socket)) . "\n");
    }
    
    if(!socket_bind($socket,0,PORT)){
        die(socket_strerror(socket_last_error($socket)) . "\n");
    }
     
    // if(!socket_connect($socket,HOST,8000)){
    //     die(socket_strerror(socket_last_error($socket)) . "\n");
    // }
    if(!socket_listen($socket)){
        die(socket_strerror(socket_last_error($socket)) . "\n");
    }
   
    $arr1 = [];
    
    $usersArray = array();
    echo $socket . "\r\n";
    $clientSocketArray = array($socket);

    while(true){
        $newSocketArray = $clientSocketArray;

        socket_select($newSocketArray,$arr1,$arr1,0,10);

        if(in_array($socket,$newSocketArray)){

            $newSocket = socket_accept($socket);

            $clientSocketArray[] = $newSocket;

            $header = socket_read($newSocket,10000);

            sendHeaders($header,$newSocket,HOST,PORT);

            socket_getpeername($newSocket,$client_ip_address);

            $connectionACK = newConnectionACK($client_ip_address);

            // send($connectionACK,$clientSocketArray);
            
            $newSocketArrayIndex = array_search($socket,$newSocketArray);

            unset($newSocketArray[$newSocketArrayIndex]);
        }

        foreach($newSocketArray as $newSocketArrayResource){

            while(socket_recv($newSocketArrayResource,$socketData,10000,0) >= 1){
                $socketMessage = unseal($socketData);
                $socketObj = json_decode($socketMessage);
                echo $socketMessage;
                switch($socketObj->type){
                    case 'NEWMESSAGETOUSER':
                        $interlocutor = $socketObj->interlocutor;
                        echo $interlocutor . "\r\n";
                        echo $socketObj->chat_user . "\r\n";
                        $chatMessage = createChatMessage($socketObj->chat_user,$socketObj->interlocutor,$socketObj->chat_message);
                        
                        send($chatMessage,$interlocutor,$socketObj->chat_user,$usersArray);
                        addMessageToDB($socketObj->chat_user,$socketObj->chat_id,$socketObj->chat_message);
                    break;
                    case 'GETUSER':
                        $user = $socketObj->chat_user;
                        $usersArray[] = array(
                                "socket" => $newSocketArrayResource,
                                "user" => $user
                        );
                        echo "---------------------------------------\r\n";
                        // foreach($usersArray as $user){
                        //     echo $user["socket"] . "   " . $user["user"] . "\r\n";
                        // }
                        echo  $user . "\r\n";
                        $massString = check_for_unchecked_messages($user);
                        echo  $massString  . "lolo\r\n";
                        if($massString){
                            
                            $message = createMessageOfNew($user,$massString);
                            sendUnChecked($message,$user,$usersArray);
                        }
                        
                        
                    break;
                    case 'LEAVEUSER':
                        $username = $socketObj->chat_user;
                        foreach($usersArray as $user){
                            if($user['user'] === $username){
                                $newSocketArrayIndex = array_search($user,$usersArray);
                                unset($usersArray[$newSocketArrayIndex]);
                                echo $user["user"] . "  leaved " . "\r\n";
                            }
                            
                        }
                    break;
                    case 'UPDATETOCHECK':
                        updateToCheck($socketObj->chat_user,$socketObj->chat_id);
                    break;
                    
                }
                

                

                break 2;
            }

            $socketData = @socket_read($newSocketArrayResource,10000,PHP_NORMAL_READ);
            if($socketData === false){
                socket_getpeername($newSocketArrayResource,$client_ip_address);

                $connectionACK = newDisconnectedACK($client_ip_address);

                // send($connectionACK,$clientSocketArray);

                $newSocketArrayIndex = array_search($newSocketArrayResource,$clientSocketArray);

                unset($clientSocketArray[$newSocketArrayIndex]);

                foreach($usersArray as $user){
                    if($user['socket'] == $newSocketArrayResource){
                        $newSocketArrayIndex = array_search($user,$usersArray);
                        unset($usersArray[$newSocketArrayIndex]);
                    }
                    
                }
            }

        }

        
    }

    socket_close($socket);


   

    function createChatMessage($username,$interlocutor,$messageStr){
        $messageArray['type'] = 'NEWMESSAGE';
        $messageArray['interlocutor'] = $interlocutor;
        $messageArray['sender'] = $username;
        $messageArray['message'] = $messageStr;  
        return seal(json_encode($messageArray));
    }

    function createMessageOfNew($username,$messageStr){
        $messageArray['type'] = 'UNCHECKED';
        $messageArray['user'] = $username; 
        $messageArray['message'] = $messageStr;
        
        return seal(json_encode($messageArray));
    }

    function unseal($socketData){
        $length = ord($socketData[1]) & 127;
        echo $socketData[1] . "\r\n";
        echo $length . "\r\n";
        if($length == 126){
            $mask = substr($socketData,4,4);
            $data = substr($socketData,8);
        }
        elseif($length == 127){
            $mask = substr($socketData,10,4);
            $data = substr($socketData,14);
        }
        else {
            $mask = substr($socketData,2,4);
            $data = substr($socketData,6);
        }

        $socketStr = "";

        for($i=0; $i < strlen($data); $i++){
            $socketStr .= $data[$i] ^ $mask[$i%4];
        }

        return $socketStr;
    }

    function sendHeaders($headersText,$newSocket,$host,$port){
        $headers = array();
        $tmpLine = preg_split("/\r\n/",$headersText);
        foreach($tmpLine as $line){
            $line = rtrim($line);
            if(preg_match("/\A(\S+): (.*)\z/",$line,$matches)){
                $headers[$matches[1]] = $matches[2];
            }
        }
        $key = $headers["Sec-WebSocket-Key"];
        $sKey = base64_encode(pack('H*',sha1($key.'258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
        $strHeader = "HTTP/1.1 101 Switching Protocols \r\n".
        "Upgrade: websocket\r\n".
        "Connection: Upgrade\r\n".
        "Sec-WebSocket-Accept: $sKey\r\n\r\n";
       
        socket_write($newSocket,$strHeader,strlen($strHeader));
    }


    function newConnectionACK($client_ip_address){

        $message = "new client " . $client_ip_address;
        $messageArray["message"] = $message;
        $messageArray["type" ] = "newconnection";
           
        $ask = seal(json_encode($messageArray));
        return $ask;
    }

    function newDisconnectedACK($client_ip_address){

        $message = "client " . $client_ip_address . " disconnected";
        $messageArray["message"] = $message;
        $messageArray["type"] = "newconnection";
           
        $ask = seal(json_encode($messageArray));
        return $ask;
    }

    function seal($messageArray){
        $b1 = 0x81;
        $length = strlen($messageArray);
        $header = "";
        if($length <= 125){
            $header = pack('CC',$b1,$length);
        }
        elseif($length > 125 && $length < 65536){
            $header = pack('CCn',$b1,126,$length);
        }
        elseif($length > 65536){
            $header = pack('CCNN',$b1,127,$length);
        }

        return $header.$messageArray;
    }

    function sendUnChecked($message,$myname,$array){
        $messageLength = strlen($message);
        echo "trysend to " . $myname . "\r\n";
        echo $message;
        foreach($array as $clientSocket){
            if($clientSocket["user"] == $myname){
                @socket_write($clientSocket["socket"],$message,$messageLength);
                echo "message sended to user " . $myname;
            }
        
    }
    }

    function send($message,$interlocutor,$myname,$array){
        $messageLength = strlen($message);
        echo "trysend to " . $interlocutor . "\r\n";
        echo json_encode($array);
        foreach($array as $clientSocket){
                // echo " " . $clientSocket["user"] . "\r\n";
           
            if($clientSocket["user"] == $interlocutor){
                @socket_write($clientSocket["socket"],$message,$messageLength);
                echo "message sended to user " . $interlocutor;
            }
            elseif($clientSocket["user"] == $myname){
                @socket_write($clientSocket["socket"],$message,$messageLength);
                echo "message sended to user " . $interlocutor;
            }
            
        }
    }


    function addMessageToDB($sender,$chat_id,$message){
        global $mysqli;
            $queryInsert = mysqli_query($mysqli,"INSERT INTO `$chat_id` (message,sender,ischecked) VALUES ('$message','$sender',0)");
            if($queryInsert){
                echo "good\r\n";
            }else echo "can`t make a resolve";     
    }



    function updateToCheck($user,$chat_id){
        global $mysqli;
        $query = mysqli_query($mysqli,"UPDATE `$chat_id` SET ischecked=1 WHERE sender<>'$user'");
        if($query) echo 'checked from ' . $user . "\r\n";
    }



    function check_for_unchecked_messages($user){
        global $mysqli;
        
        $mass = array();
        $queryIsCheck = mysqli_query($mysqli,"SELECT chats FROM users WHERE username = '$user'");
        if($queryIsCheck){
            $assoc = mysqli_fetch_assoc($queryIsCheck);
            $tablename = $assoc['chats']; 
            $query = mysqli_query($mysqli,"SELECT * FROM `$tablename`");
            if($query){
                $num = mysqli_num_rows($query);
            for($i = 0; $i < $num; $i++){
                $assocCheck = mysqli_fetch_assoc($query);
                // echo json_encode($assocCheck) . "loliiii\r\n";
                $chat_id = $assocCheck['chatid'];
                $queryExist = mysqli_query($mysqli,"SELECT * FROM `$chat_id` WHERE ischecked = 0 AND sender<>'$user'");
                while($assocEx = mysqli_fetch_assoc($queryExist)){
                    $mass[] = $assocEx;
                }
            }
            }
            
            
            return json_encode($mass);
        }
    }