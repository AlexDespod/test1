<?php 
error_reporting(E_ALL);
ob_implicit_flush();
    define('PORT',"8000");
    define('HOST',"192.168.0.101");
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
    

    $clientSocketArray = array($socket);

    while(true){
        $newSocketArray = $clientSocketArray;

        socket_select($newSocketArray,$arr1,$arr1,0,10);

        if(in_array($socket,$newSocketArray)){

            $newSocket = socket_accept($socket);

            $clientSocketArray[] = $newSocket;

            $header = socket_read($newSocket,1024);

            sendHeaders($header,$newSocket,HOST,PORT);

            socket_getpeername($newSocket,$client_ip_address);

            $connectionACK = newConnectionACK($client_ip_address);

            send($connectionACK,$clientSocketArray);
            
            $newSocketArrayIndex = array_search($socket,$newSocketArray);

            unset($newSocketArray[$newSocketArrayIndex]);
        }

        foreach($newSocketArray as $newSocketArrayResource){

            while(socket_recv($newSocketArrayResource,$socketData,1024,0) >= 1){
                $socketMessage = unseal($socketData);
                $socketObj = json_decode($socketMessage);

                $chatMessage = createChatMessage($socketObj->chat_user,$socketObj->chat_message);

                send($chatMessage,$clientSocketArray);

                break 2;
            }

            $socketData = @socket_read($newSocketArrayResource,1024,PHP_NORMAL_READ);
            if($socketData === false){
                socket_getpeername($newSocketArrayResource,$client_ip_address);

                $connectionACK = newDisconnectedACK($client_ip_address);

                send($connectionACK,$clientSocketArray);

                $newSocketArrayIndex = array_search($newSocketArrayResource,$clientSocketArray);

                unset($clientSocketArray[$newSocketArrayIndex]);
            }

        }

        
    }

    socket_close($socket);


    function createChatMessage($username,$messageStr){
        $messageArray['user'] = $username;
        $messageArray['type'] = 'chat-box';
        $messageArray['message'] = $messageStr;


        return seal(json_encode($messageArray));
    }



    function unseal($socketData){
        $length = ord($socketData[1]);

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

    function send($message,$array){
        $messageLength = strlen($message);
        foreach($array as $clientSocket){
            @socket_write($clientSocket,$message,$messageLength);
        }
    }