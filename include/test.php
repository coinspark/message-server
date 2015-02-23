<?php

    chdir(dirname(__FILE__));
    
    require_once '../config/coinspark_config.php'; 
    require_once '../include/log.php'; 
    require_once '../include/signature.php'; 
    
    
//    define('CONST_TEST_SERVER_URL', '127.0.0.1');                       
    define('CONST_TEST_SERVER_URL', 'msg3.coinspark.org');                       
    define('CONST_TEST_KEY_DIR', '/home/coinspark/.coinspark/messages/test/key');                          
    define('CONST_TEST_LOG_DIR', '/home/coinspark/.coinspark/messages/test/log');                          
    define('CONST_TEST_TMP_DIR', '/home/coinspark/.coinspark/messages/test/tmp');                          
    define('CONST_TEST_MAX_KEYS',16);                          
    define('CONST_TEST_START_SEED',1000000);                          
    define('CONST_TEST_SEED_COUNT',100);                          
    
    define("CONST_TEST_ERROR_ONE_OF",10);
    define("CONST_DIE_ON_ERROR",true);
    
    define("CONST_TEST_NOT_USED_PUBKEY","02c7cace7cbc7c62a0514572a0c2322eab863e5653f99f432f52cffe5fb2707f60");
    define("CONST_TEST_NOT_USED_ADDRESS","1Gf1UDw39Bk3jtLVkTH6FcHenP42b2iyba");
    
    define("CONST_BITCOIN_CLI_ACCOUNT","<bitcoin-account>");
    define("CONST_BITCOIN_CLI_PATH","/path/to/bitcoin/cli");
    
    function output_string($message,$level=log_level_message)
    {
        if(isset($_SERVER['SERVER_NAME']))
        {
            $message=htmlspecialchars($message);
            echo '<font face="courier new">'.$message."</font><br/>";
        }
        else
        {
            echo $message."\n";
        }
        log_string($level, "", $message,CONST_TEST_LOG_DIR);
    }
    
    function query($url,$request)
    {
        $curl=curl_init();
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);        
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($request));    
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));    
        $response=curl_exec($curl);
        curl_close($curl);
        return json_decode($response);
        
    }

    function run_command($command,$input=null)
    {
        $result="";
        $async_process=popen($command, 'r');
		                
        if (is_resource($async_process))
        {
            while (!feof($async_process)) 
            {
                $result.=fread($async_process, 4096);
            }               
            fclose($async_process);
        }        
        return $result;
    }
    
    function create_privatekey($id)
    {
        $prefix=CONST_TEST_KEY_DIR."/private".sprintf("%02d",$id);
        
        $key_file=$prefix.'.pem';
        
        if(!file_exists($key_file))
        {
            run_command("openssl ecparam -genkey -name secp256k1 -noout -out ".$key_file);
            output_string("Created private key: ".$key_file);
        }
    }
    
    function get_pubkey($id,$compressed=true)
    {
        $prefix=CONST_TEST_KEY_DIR."/private".sprintf("%02d",$id);
        
        $key_file=$prefix.'.pem';
        
        if(!file_exists($key_file))
        {
            return false;
        }
        
        $command="openssl ec -in ".$key_file." -pubout -outform DER  ";
        if($compressed)
        {
            $command.=" -conv_form compressed";
        }
        $command.=" 2> /dev/null";
        $full_key=run_command($command);
        
        return substr($full_key,23);
    }
    
    function get_address($pubkey,$network='main')
    {
        $version="\x00";
        switch($network)
        {
            case 'main':
                break;
            case 'testnet':
                $version="\x6f";    
                break;
            default:
                return false;
        }
        $key_hash= $version.hash('ripemd160',hash('sha256', $pubkey, true),true);
        $checksum=substr(hash('sha256', hash('sha256', $key_hash, true), true),0,4);
        return base58_encode($key_hash.$checksum);        
    }
    
    function get_sigscript($message,$id,$pubkey)
    {
        $prefix=CONST_TEST_KEY_DIR."/private".sprintf("%02d",$id);
        
        $key_file=$prefix.'.pem';
        $msg_file=CONST_TEST_TMP_DIR."/msg".rand(1,999999).".msg";
        file_put_contents($msg_file,$message);
        
        if(!file_exists($key_file))
        {
            return false;
        }
        
        $command="openssl dgst -sha256 -sign ".$key_file." < ".$msg_file;
        $command.=" 2> /dev/null";
        
        $signature=run_command($command);
        
        unlink($msg_file);

        return $signature;
//        return chr(strlen($signature)+1).$signature."\x00".chr(strlen($pubkey)).$pubkey;
    }

    function get_bitcoin_cli_signature($message,$address)
    {
        return trim(run_command(CONST_BITCOIN_CLI_PATH." signmessage ".$address." ".  escapeshellarg($message)));         
    }
    
    function cleanup()
    {
        run_command("rm -rf * from ".CONST_TEST_TMP_DIR);
    }
    
    function generate_random_message($seed=null)
    {
        if(!is_null($seed))
        {
            srand($seed);
        }
        
        $message=array();
        $message['error']=0;
        
        $str="";        
        for($i=0;$i<24;$i++)
        {
            $str.=chr(rand(0,255));
        }
        
        $message['txid']=  bin_to_hex($str).sprintf("%08x",time()).sprintf("%04x",getmypid()).sprintf("%04x",rand(0,65536));
                
        $message['network']='main';
        if(rand(0,9)==0)
        {
            $message['network']='testnet';            
        }
        
        $message['sender_key_id']=rand(0, CONST_TEST_MAX_KEYS-1);

        $message['sender_bitcoin_cli']=false;
        $message['sender_pubkey']=get_pubkey($message['sender_key_id']);
        $message['sender_address']=get_address($message['sender_pubkey'],$message['network']);        

        if(rand(0,1)==1)
        {
            $addresses=json_decode(run_command(CONST_BITCOIN_CLI_PATH." getaddressesbyaccount ".CONST_BITCOIN_CLI_ACCOUNT));
            $addr=  $addresses[array_rand($addresses)];
            $addr_info=json_decode(run_command(CONST_BITCOIN_CLI_PATH." validateaddress ".$addr));
            $pubkey=$addr_info->pubkey;
            
            $message['network']='main';
            $message['sender_bitcoin_cli']=true;
            $message['sender_pubkey']=  hex_to_bin($pubkey);
            $message['sender_address']=$addr;                    
        }
        
        $message['ispublic']=false;
        if(rand(0,9)==0)
        {
            $message['ispublic']=true;
        }        
        
        if($message['ispublic'])
        {
            $message['num_recipients']=0;
        }
        else
        {
            $message['num_recipients']=rand(1,min(CONST_TEST_MAX_KEYS,COINSPARK_CREATE_RECIPIENTS_MAX));            
        }
        
        $message['recipients']=array();
        
        $available_recipients=array();
        for($i=0;$i<CONST_TEST_MAX_KEYS;$i++)
        {
            $available_recipients[$i]=$i;
        }
        shuffle($available_recipients);
        for($i=0;$i<$message['num_recipients'];$i++)
        {
            $recipient_id=$available_recipients[$i];
            $compressed=true;
            if(rand(0,9)==0)
            {
                $compressed=false;
            }
            $pubkey=get_pubkey($recipient_id,$compressed);
            $message['recipients'][$i]=array(
                'id' => $recipient_id,
                'compressed' => $compressed,
                'pubkey' => $pubkey,
                'address' => get_address($pubkey, $message['network'])
            );
        }
        $message['keepseconds']=rand(1,COINSPARK_CREATE_KEEPSECONDS_MAX);
        $message['salt_size']=rand(COINSPARK_CREATE_SALT_MIN_BYTES,COINSPARK_CREATE_SALT_MAX_BYTES);
        
        $str="";        
        for($i=0;$i<$message['salt_size'];$i++)
        {
            $str.=chr(rand(0,255));
        }
        $message['salt']=  base64_encode($str);
        
        $message['num_content_parts']=rand(1,COINSPARK_CREATE_MAX_PARTS);
        
        $available_mimetypes=array("application/pdf","application/zip","image/gif","text/html","image/jpeg","image/png","text/csv","text/javascript","text/plain");
        $available_extensions=array(".pdf",".zip",".gif",".html",".jpg",".png",".csv",".js",".txt");
        
        $message['content_parts']=array();
        for($part=0;$part<$message['num_content_parts'];$part++)
        {
            $mimetype=$available_mimetypes[array_rand($available_mimetypes)];
            $filename=null;
            if(rand(0,1)==0)
            {
                $filename_size=rand(8,COINSPARK_CREATE_FILENAME_MAX_BYTES-5);
                $filename="";
                for($i=0;$i<$filename_size;$i++)
                {
                    $filename.=chr(rand(32,120));
                }
                $filename.=$available_extensions[array_rand($available_extensions)];                
            }
            $content_size=rand(1,min(COINSPARK_CREATE_MAX_PART_BYTES/4096,COINSPARK_CREATE_MAX_TOTAL_BYTES/4096-$message['num_content_parts']));
            $str="";        
            for($i=0;$i<$content_size;$i++)
            {
                $str.=chr(rand(0,255));
            }            
            $message['content_parts'][$part]=array(
                'mimetype'   => $mimetype,
                'filename'   => $filename,
                'bytes'      => $content_size,
                'content'    => base64_encode($str),
            );
        }    
    
        
        return $message;
    }
    
    function request_pre_create($message,$error=null)
    {
        $request=new stdClass();
        $request->id=rand(1,1000000);
        $request->method='coinspark_message_pre_create';
        $request->params=new stdClass();
        if($message['network']=='testnet')
        {
            $request->params->testnet=true;
        }
        $request->params->sender=$message['sender_address'];
        $request->params->ispublic=$message['ispublic'];
        if(!$message['ispublic'])
        {
            $request->params->recipients=array();
            foreach($message['recipients'] as $recipient_info)
            {
                $request->params->recipients[]=$recipient_info['address'];
            }
        }
        $request->params->keepseconds=$message['keepseconds'];
        $request->params->salt=$message['salt'];
        $request->params->message=array();
        
        foreach($message['content_parts'] as $content_part)
        {
            $message_part=new stdClass();
            $message_part->mimetype=$content_part['mimetype'];
            $message_part->filename=$content_part['filename'];
            $message_part->bytes=$content_part['bytes'];
            $request->params->message[]=$message_part;
        }

        if(!is_null($error) && ($error['r'] == 'pre_create'))
        {
            switch($error['n'])
            {
                case "testnet not supported":
                    $request->params->testnet=true;
                    break;
                case "sender blocked permanently":
                case "sender not allowed":
                case "sender blocked temporarily":
                    $request->params->testnet=false;
                    $request->params->sender=CONST_TEST_NOT_USED_ADDRESS;
                    break;
                case "sender white list":
                    $request->params->testnet=false;
                    $request->params->ispublic=true;
                    $request->params->sender=  get_address(get_pubkey(0));
                    break;
                case "public not allowed":
                    $request->params->ispublic=true;
                    break;
                case "private not allowed":
                    $request->params->ispublic=false;
                    break;
                case "mime blocked":
                case "mime not allowed":
                    $request->params->message[rand(0,count($request->params->message)-1)]->mimetype="not_allowed";
                    break;
                case "filename blocked":
                    $request->params->message[rand(0,count($request->params->message)-1)]->filename=
                        substr($request->params->message[rand(0,count($request->params->message)-1)]->filename,0,-4).".bad";
                    break;
                case "sender not set":
                    unset($request->params->sender);
                    break;
                case "sender address invalid":
                    $request->params->sender=substr($request->params->sender,0,-5);
                    break;
                case "public not set":
                    unset($request->params->ispublic);
                    break;
                case "recipients not set":
                    $request->params->ispublic=false;
                    unset($request->params->recipients);
                    break;
                case "too many recipients":
                    $request->params->ispublic=false;
                    $request->params->recipients=array();
                    for($i=0;$i<COINSPARK_CREATE_RECIPIENTS_MAX+1;$i++)
                    {
                        $request->params->recipients[]="A";
                    }
                    break;
                case "one of recipients invalid":
                    $request->params->ispublic=false;
                    $request->params->recipients=array("ABC");
                    break;
                case "one recipient blocked permanently":
                case "one recipient not allowed":
                case "one recipient blocked temporarily":
                    $request->params->testnet=false;
                    $request->params->ispublic=false;
                    $request->params->sender=get_address(get_pubkey(0));
                    $request->params->recipients=array(CONST_TEST_NOT_USED_ADDRESS);
                    break;
                case "one recipient white list":
                case "one recipient max usage":
                    $request->params->testnet=false;
                    $request->params->ispublic=false;
                    $request->params->sender=get_address(get_pubkey(0));;
                    $request->params->recipients=array(get_address(get_pubkey(0)));
                    break;
                case "keepseconds not set":
                    unset($request->params->keepseconds);
                    break;
                case "keepseconds not numeric":
                    $request->params->keepseconds="A";
                    break;
                case "keepseconds negative":
                    $request->params->keepseconds=-5;
                    break;
                case "keepseconds too large":
                    $request->params->keepseconds=COINSPARK_CREATE_KEEPSECONDS_MAX+1;
                    break;
                case "salt not set":
                    unset($request->params->salt);
                    break;
                case "salt not base64":
                    $request->params->salt.="$";
                    break;
                case "salt too short":
                    $request->params->salt=base64_encode(substr(base64_decode($request->params->salt),0,COINSPARK_CREATE_SALT_MIN_BYTES-1));
                    break;
                case "salt too long":
                    $request->params->salt=base64_encode(base64_decode($request->params->salt).str_repeat("A", COINSPARK_CREATE_SALT_MAX_BYTES));
                    break;
                case "message not set":
                    unset($request->params->message);
                    break;
                case "too many message parts":
                    $request->params->message=array();
                    for($i=0;$i<COINSPARK_CREATE_MAX_PARTS+1;$i++)
                    {
                        $request->params->message[]="A";
                    }
                    break;
                case "mime not set":
                    unset($request->params->message[rand(0,count($request->params->message)-1)]->mimetype);
                    break;
                case "mime too long":
                    $request->params->message[rand(0,count($request->params->message)-1)]->mimetype=  str_repeat("A", COINSPARK_CREATE_MIMETYPE_MAX_BYTES+1);
                    break;
                case "filename too long":
                    $request->params->message[rand(0,count($request->params->message)-1)]->filename=  str_repeat("A", COINSPARK_CREATE_FILENAME_MAX_BYTES+1);
                    break;
                case "bytes not set":
                    unset($request->params->message[rand(0,count($request->params->message)-1)]->bytes);
                    break;
                case "bytes not numeric":
                    $request->params->message[rand(0,count($request->params->message)-1)]->bytes="A";
                    break;
                case "bytes negative":
                    $request->params->message[rand(0,count($request->params->message)-1)]->bytes=-5;
                    break;
                case "bytes too long":
                    $request->params->message[rand(0,count($request->params->message)-1)]->bytes=COINSPARK_CREATE_MAX_PART_BYTES+1;
                    break;
                case "bytes total too long":
                    $request->params->message=array();
                    $part=new stdClass();
                    $part->mimetype="Allowed";
                    $part->filename="Allowed";
                    $part->bytes=COINSPARK_CREATE_MAX_PART_BYTES;
                    for($i=0;$i<COINSPARK_CREATE_MAX_PARTS;$i++)
                    {
                        $request->params->message[]=$part;
                    }
                    break;
            }
        }
        return $request;
    }
    
    function request_create($message,$nonce,$error=null)
    {
        $request=new stdClass();
        $request->id=rand(1,1000000);
        $request->method='coinspark_message_create';
        $request->params=new stdClass();
        if($message['network']=='testnet')
        {
            $request->params->testnet=true;
        }
        $request->params->sender=$message['sender_address'];
        $request->params->nonce=$nonce;
        if($message['sender_bitcoin_cli'])
        {
            $request->params->signature=  get_bitcoin_cli_signature($nonce, $message['sender_address']);
        }
        else
        {
            $request->params->signature=  base64_encode(get_sigscript($nonce, $message['sender_key_id'], $message['sender_pubkey']));
        }
        $request->params->pubkey= bin_to_hex($message['sender_pubkey']);
        $request->params->txid=$message['txid'];
        $request->params->ispublic=$message['ispublic'];
        if(!$message['ispublic'])
        {
            $request->params->recipients=array();
            foreach($message['recipients'] as $recipient_info)
            {
                $request->params->recipients[]=$recipient_info['address'];
            }
        }
        $request->params->keepseconds=$message['keepseconds'];
        $request->params->salt=$message['salt'];
        $request->params->message=array();
        
        foreach($message['content_parts'] as $content_part)
        {
            $message_part=new stdClass();
            $message_part->mimetype=$content_part['mimetype'];
            $message_part->filename=$content_part['filename'];
            $message_part->content=$content_part['content'];
            $request->params->message[]=$message_part;
        }
        
        if(!is_null($error) && ($error['r'] == 'create'))
        {
            switch($error['n'])
            {
                case "content not set":
                    unset($request->params->message[rand(0,count($request->params->message)-1)]->content);
                    break;
                case "content not base64":
                    $request->params->message[rand(0,count($request->params->message)-1)]->content=
                            $request->params->message[rand(0,count($request->params->message)-1)]->content."$";
                    break;
                case "content too long":
                    $request->params->message[rand(0,count($request->params->message)-1)]->content= base64_encode(str_repeat("A", COINSPARK_CREATE_MAX_PART_BYTES+1));
                    break;
                case "content total too long":
                    $request->params->message=array();
                    $part=new stdClass();
                    $part->mimetype="Allowed";
                    $part->filename="Allowed";
                    $part->content=base64_encode(str_repeat("A", COINSPARK_CREATE_MAX_PART_BYTES));
                    for($i=0;$i<COINSPARK_CREATE_MAX_PARTS;$i++)
                    {
                        $request->params->message[]=$part;
                    }
                    break;
                case "nonce not set on create":
                    unset($request->params->nonce);
                    break;
                case "nonce invalid on create":
                    $request->params->nonce="A";
                    break;
                case "nonce for other op on create":
                    $request->params->nonce="retrieve 1";
                    break;
                case "nonce not found on create":
                    $request->params->nonce="create 1";
                    break;
                case "nonce for other address on create":
                    $request->params->testnet=false;
                    $request->params->ispublic=true;
                    $request->params->sender=CONST_TEST_NOT_USED_ADDRESS;
                    break;
                case "txid not set":
                    unset($request->params->txid);
                    break;
                case "txid not string":
                    $request->params->txid=array();
                    break;
                case "txid wrong length":
                    $request->params->txid.="A";
                    break;
                case "txid not hexadecimal":
                    $request->params->txid=substr($request->params->txid,0,-1)."$";
                    break;
                case "signature not set":
                    unset($request->params->signature);
                    break;
                case "signature not base64":
                    $request->params->signature.="$";
                    break;
                case "signature not parseable":
                    $request->params->signature=  base64_encode("A".base64_decode($request->params->signature));
                    break;
                case "signature invalid pubkey":
                    $request->params->pubkey.="A";
/*                    
                    $decoded=base64_decode($request->params->signature);
                    $signature_len=ord(substr($decoded,0,1))-1;
                    $pubkey_len=ord(substr($decoded,$signature_len+2,1));
                    $signature=substr($decoded,1,$signature_len);
                    $pubkey=substr($decoded,$signature_len+3,$pubkey_len);
                    $pubkey.="A";
                    $pubkey_len++;
                    $decoded=substr($decoded,0,$signature_len+2).chr($pubkey_len).$pubkey;
                    $request->params->signature=  base64_encode($decoded);
 * 
 */
                    break;
                case "signature wrong address":
                    $request->params->pubkey=CONST_TEST_NOT_USED_PUBKEY;
/*                    
                    $decoded=base64_decode($request->params->signature);
                    $signature_len=ord(substr($decoded,0,1))-1;
                    $signature=substr($decoded,1,$signature_len);
                    $pubkey=  hex_to_bin(CONST_TEST_NOT_USED_PUBKEY);
                    $pubkey_len=strlen($pubkey);
                    $decoded=substr($decoded,0,$signature_len+2).chr($pubkey_len).$pubkey;
                    $request->params->signature=  base64_encode($decoded);
 * 
 */
                    break;
                case "signature wrong signature":
                    $decoded=base64_decode($request->params->signature);
                    $signature_len=ord(substr($decoded,0,1))-1;
                    $pubkey_len=ord(substr($decoded,$signature_len+2,1));
                    $signature=substr($decoded,1,$signature_len);
                    $pubkey=substr($decoded,$signature_len+3,$pubkey_len);
                    $last_byte=(ord(substr($signature,-1))+1)%256;
                    $signature=substr($signature,0,-1).chr($last_byte);
                    $decoded=chr(strlen($signature)+1).$signature."\x00".chr(strlen($pubkey)).$pubkey;
                    $request->params->signature=  base64_encode($decoded);
                    break;
            }
        }
        return $request;
    }
    
    function request_pre_retrieve($message,$recipient,$error=null)
    {
        $request=new stdClass();
        $request->id=rand(1,1000000);
        $request->method='coinspark_message_pre_retrieve';
        $request->params=new stdClass();
        if($message['network']=='testnet')
        {
            $request->params->testnet=true;
        }
        $request->params->txid=$message['txid'];
        $request->params->recipient=$recipient['address'];
        
        if(!is_null($error) && ($error['r'] == 'pre_retrieve'))
        {
            switch($error['n'])
            {
                case "recipient blocked permanently":
                case "recipient not allowed":
                case "recipient blocked temporarily":
                    $request->params->testnet=false;
                    $request->params->recipient=CONST_TEST_NOT_USED_ADDRESS;
                    break;
                case "recipient white list":
                    $request->params->testnet=false;
                    $request->params->recipient=  get_address(get_pubkey(0));
                    break;
                case "txid expired":
                    sleep(COINSPARK_CREATE_KEEPSECONDS_MAX+1);
                    break;
                case "recipient not set":
                    unset($request->params->recipient);
                    break;
                case "recipient address invalid":
                    $request->params->recipient=substr($request->params->recipient,0,-5);
                    break;
                case "txid not found":
                    $request->params->txid=substr($request->params->txid,0,-16)."aaaaaaaaaaaaaaaa";
                    break;
                case "recipient not accepted for txid":
                    $request->params->testnet=false;
                    $request->params->recipient=CONST_TEST_NOT_USED_ADDRESS;
                    break;
            }
        }
        return $request;
    }
    
    function request_retrieve_sizesonly($message,$recipient,$nonce,$error=null)
    {
        $request=new stdClass();
        $request->id=rand(1,1000000);
        $request->method='coinspark_message_retrieve';
        $request->params=new stdClass();
        if($message['network']=='testnet')
        {
            $request->params->testnet=true;
        }
        $request->params->txid=$message['txid'];
        $request->params->recipient=$recipient['address'];
        $request->params->sizesonly=true;
        if(!$message['ispublic'])        
        {
            $request->params->nonce=$nonce;
            $request->params->signature=  base64_encode(get_sigscript($nonce, $recipient['id'], $recipient['pubkey']));
            $request->params->pubkey= bin_to_hex($recipient['pubkey']);
        }
        if(!is_null($error) && ($error['r'] == 'retrieve'))
        {
            switch($error['n'])
            {
                case "nonce not set on retrieve":
                    unset($request->params->nonce);
                    break;
                case "nonce invalid on retrieve":
                    $request->params->nonce="A";
                    break;
                case "nonce for other op on retrieve":
                    $request->params->nonce="create 1";
                    break;
                case "nonce not found on retrieve":
                    $request->params->nonce="retrieve 1";
                    break;
                case "nonce for other address on retrieve":
                    $request->params->testnet=false;
                    $request->params->recipient=CONST_TEST_NOT_USED_ADDRESS;
                    break;                
            }
        }
        
        return $request;
    }
    
    function request_retrieve($message,$recipient,$nonce,$error=null)
    {
        $request=new stdClass();
        $request->id=rand(1,1000000);
        $request->method='coinspark_message_retrieve';
        $request->params=new stdClass();
        if($message['network']=='testnet')
        {
            $request->params->testnet=true;
        }
        $request->params->txid=$message['txid'];
        $request->params->recipient=$recipient['address'];
        if(!$message['ispublic'])
        {
            $request->params->nonce=$nonce;
            $request->params->signature=  base64_encode(get_sigscript($nonce, $recipient['id'], $recipient['pubkey']));
            $request->params->pubkey= bin_to_hex($recipient['pubkey']);
        }
        if(!is_null($error) && ($error['r'] == 'retrieve'))
        {
            switch($error['n'])
            {
                case "nonce not set on retrieve":
                    unset($request->params->nonce);
                    break;
                case "nonce invalid on retrieve":
                    $request->params->nonce="A";
                    break;
                case "nonce for other op on retrieve":
                    $request->params->nonce="create 1";
                    break;
                case "nonce not found on retrieve":
                    $request->params->nonce="retrieve 1";
                    break;
                case "nonce for other address on retrieve":
                    $request->params->testnet=false;
                    $request->params->recipient=CONST_TEST_NOT_USED_ADDRESS;
                    break;                
            }
        }
        
        return $request;
    }
    
    function ignore_mismatch($message,$error)
    {
        if($message['ispublic'])
        {
            if($error['n']=='recipient not accepted for txid')
            {
                return true;
            }
        }
        return false;
    }
    
    function check_message($message,$result,$sizes_only=false)
    {
        if($message['salt'] != $result->salt)
        {
            output_string("Salt mismatch");
            return false;
        }
        if(count($message['content_parts']) != count($result->message))
        {
            output_string("Count mismatch");
            return false;
        }        
        foreach(array_keys($message['content_parts']) as $key)
        {
            $in=$message['content_parts'][$key];
            $out=$result->message[$key];
            if($in['mimetype'] != $out->mimetype)
            {
                output_string("mimetype mismatch: ".$key);
                return false;
            }
            if(is_null($in['filename']) != is_null($out->filename))
            {
                output_string("filename mismatch: ".$key);
                return false;
            }
            if(!is_null($in['filename']))
            {
                if($in['filename'] != $out->filename)
                {
                    output_string("filename mismatch: ".$key);
                    return false;
                }
            }
            if($sizes_only)
            {
                if($in['bytes'] != $out->bytes)
                {
                    output_string("content mismatch: ".$key);
                    return false;
                }            
            }
            else
            {
                if($in['content'] != $out->content)
                {
                    output_string("content mismatch: ".$key);
                    return false;
                }                            
            }
        }
        
        return  true;
    }
    
    function init_errors()
    {
        return array(
            
            array("t"=>"a","n"=>"no errors"                       ,"r"=>"none"        ,"c"=>COINSPARK_ERR_NOERROR),
            
            array("t"=>"-","n"=>"testnet not supported"           ,"r"=>"pre_create"  ,"c"=>COINSPARK_ERR_SENDER_NETWORK_NOT_ACCEPTABLE,"v"=>COINSPARK_CREATE_NETWORK_ALLOW),
            array("t"=>"-","n"=>"sender ip blocked permanently"   ,"r"=>"pre_create"  ,"c"=>COINSPARK_ERR_SENDER_NOT_ACCEPTED       ,"v"=>COINSPARK_CREATE_IP_BLOCKED),
            array("t"=>"-","n"=>"sender ip not allowed"           ,"r"=>"pre_create"  ,"c"=>COINSPARK_ERR_SENDER_NOT_ACCEPTED       ,"v"=>COINSPARK_CREATE_IP_OTHER_ALLOWED),
            array("t"=>"-","n"=>"sender ip max usage"             ,"r"=>"pre_create"  ,"c"=>COINSPARK_ERR_SENDER_IS_SUSPENDED       ,"v"=>COINSPARK_CREATE_IP_OTHER_DAILY_MAX),
            array("t"=>"-","n"=>"sender ip blocked temporarily"   ,"r"=>"pre_create"  ,"c"=>COINSPARK_ERR_SENDER_IS_SUSPENDED       ,"v"=>"DB"),
            array("t"=>"-","n"=>"sender ip white list"            ,"r"=>"pre_create"  ,"c"=>COINSPARK_ERR_NOERROR                      ,"v"=>COINSPARK_CREATE_IP_OTHER_ALLOWED|COINSPARK_CREATE_IP_UNLIMITED),
            array("t"=>"-","n"=>"sender blocked permanently"      ,"r"=>"pre_create"  ,"c"=>COINSPARK_ERR_SENDER_NOT_ACCEPTED          ,"v"=>COINSPARK_CREATE_SENDER_BLOCKED),
            array("t"=>"-","n"=>"sender not allowed"              ,"r"=>"pre_create"  ,"c"=>COINSPARK_ERR_SENDER_NOT_ACCEPTED          ,"v"=>COINSPARK_CREATE_SENDER_OTHER_ALLOWED),
            array("t"=>"-","n"=>"sender max usage"                ,"r"=>"pre_create"  ,"c"=>COINSPARK_ERR_SENDER_IS_SUSPENDED          ,"v"=>COINSPARK_CREATE_SENDER_OTHER_DAILY_MAX),
            array("t"=>"-","n"=>"sender blocked temporarily"      ,"r"=>"pre_create"  ,"c"=>COINSPARK_ERR_SENDER_IS_SUSPENDED          ,"v"=>"DB"),
            array("t"=>"-","n"=>"sender white list"               ,"r"=>"pre_create"  ,"c"=>COINSPARK_ERR_NOERROR                      ,"v"=>COINSPARK_CREATE_SENDER_OTHER_ALLOWED|COINSPARK_CREATE_SENDER_UNLIMITED),
            array("t"=>"-","n"=>"public not allowed"              ,"r"=>"pre_create"  ,"c"=>COINSPARK_ERR_NO_PUBLIC_MESSAGES           ,"v"=>COINSPARK_CREATE_ALLOW_PUBLIC),
            array("t"=>"-","n"=>"private not allowed"             ,"r"=>"pre_create"  ,"c"=>COINSPARK_ERR_ONLY_PUBLIC_MESSAGES         ,"v"=>COINSPARK_CREATE_ALLOW_PRIVATE),
            array("t"=>"-","n"=>"mime blocked"                    ,"r"=>"pre_create"  ,"c"=>COINSPARK_ERR_MIME_TYPE_NOT_ACCEPTABLE     ,"v"=>COINSPARK_CREATE_MIMETYPE_BLOCKED),
            array("t"=>"-","n"=>"mime not allowed"                ,"r"=>"pre_create"  ,"c"=>COINSPARK_ERR_MIME_TYPE_NOT_ACCEPTABLE     ,"v"=>COINSPARK_CREATE_MIMETYPE_ALLOWED),
            array("t"=>"-","n"=>"filename blocked"                ,"r"=>"pre_create"  ,"c"=>COINSPARK_ERR_FILE_NAME_NOT_ACCEPTABLE     ,"v"=>COINSPARK_CREATE_MIMETYPE_BLOCKED),
            array("t"=>"-","n"=>"one recipient blocked permanently","r"=>"pre_create" ,"c"=>COINSPARK_ERR_RECIPIENT_NOT_ACCEPTED_ON_CREATE,"v"=>COINSPARK_RETRIEVE_RECIPIENT_BLOCKED),
            array("t"=>"-","n"=>"one recipient not allowed"       ,"r"=>"pre_create"  ,"c"=>COINSPARK_ERR_RECIPIENT_NOT_ACCEPTED_ON_CREATE,"v"=>COINSPARK_RETRIEVE_RECIPIENT_OTHER_ALLOWED),
            array("t"=>"-","n"=>"one recipient max usage"         ,"r"=>"pre_create"  ,"c"=>COINSPARK_ERR_RECIPIENT_IS_SUSPENDED_ON_CREATE,"v"=>COINSPARK_RETRIEVE_RECIPIENT_OTHER_DAILY_MAX),
            array("t"=>"-","n"=>"one recipient blocked temporarily","r"=>"pre_create" ,"c"=>COINSPARK_ERR_RECIPIENT_IS_SUSPENDED_ON_CREATE,"v"=>"DB"),
            array("t"=>"-","n"=>"one recipient white list"        ,"r"=>"pre_create"  ,"c"=>COINSPARK_ERR_NOERROR                      ,"v"=>COINSPARK_RETRIEVE_RECIPIENT_OTHER_ALLOWED|COINSPARK_RETRIEVE_RECIPIENT_UNLIMITED),

            array("t"=>"-","n"=>"recipient ip blocked permanently","r"=>"pre_retrieve","c"=>COINSPARK_ERR_RECIPIENT_NOT_ACCEPTED    ,"v"=>COINSPARK_RETRIEVE_IP_BLOCKED),
            array("t"=>"-","n"=>"recipient ip not allowed"        ,"r"=>"pre_retrieve","c"=>COINSPARK_ERR_RECIPIENT_NOT_ACCEPTED    ,"v"=>COINSPARK_RETRIEVE_IP_OTHER_ALLOWED),
            array("t"=>"-","n"=>"recipient ip max usage"          ,"r"=>"pre_retrieve","c"=>COINSPARK_ERR_RECIPIENT_IS_SUSPENDED    ,"v"=>COINSPARK_RETRIEVE_IP_OTHER_DAILY_MAX),
            array("t"=>"-","n"=>"recipient ip blocked temporarily","r"=>"pre_retrieve","c"=>COINSPARK_ERR_RECIPIENT_IS_SUSPENDED    ,"v"=>"DB"),
            array("t"=>"-","n"=>"recipient ip white list"         ,"r"=>"pre_retrieve","c"=>COINSPARK_ERR_NOERROR                    ,"v"=>COINSPARK_RETRIEVE_IP_OTHER_ALLOWED|COINSPARK_RETRIEVE_IP_UNLIMITED),
/* Running these tests requres commenting out relevant checks on in acceptAllowedRecipients() and message_pre_retrieve()             
            array("t"=>"-","n"=>"recipient blocked permanently"   ,"r"=>"pre_retrieve","c"=>COINSPARK_ERR_RECIPIENT_NOT_ACCEPTED       ,"v"=>COINSPARK_RETRIEVE_RECIPIENT_BLOCKED),
            array("t"=>"-","n"=>"recipient not allowed"           ,"r"=>"pre_retrieve","c"=>COINSPARK_ERR_RECIPIENT_NOT_ACCEPTED       ,"v"=>COINSPARK_RETRIEVE_RECIPIENT_OTHER_ALLOWED),
            array("t"=>"-","n"=>"recipient max usage"             ,"r"=>"pre_retrieve","c"=>COINSPARK_ERR_RECIPIENT_IS_SUSPENDED       ,"v"=>COINSPARK_RETRIEVE_RECIPIENT_OTHER_DAILY_MAX),
            array("t"=>"-","n"=>"recipient blocked temporarily"   ,"r"=>"pre_retrieve","c"=>COINSPARK_ERR_RECIPIENT_IS_SUSPENDED       ,"v"=>"DB"),
            array("t"=>"-","n"=>"recipient white list"            ,"r"=>"pre_retrieve","c"=>COINSPARK_ERR_NOERROR                      ,"v"=>COINSPARK_RETRIEVE_RECIPIENT_OTHER_ALLOWED|COINSPARK_RETRIEVE_RECIPIENT_UNLIMITED), 
 */
            array("t"=>"-","n"=>"txid expired"                    ,"r"=>"pre_retrieve","c"=>COINSPARK_ERR_TX_MESSAGE_EXPIRED           ,"v"=>COINSPARK_CREATE_KEEPSECONDS_MAX),
            
            array("t"=>"b","n"=>"sender not set"                  ,"r"=>"pre_create"  ,"c"=>COINSPARK_ERR_INVALID_PARAMS),
            array("t"=>"b","n"=>"sender address invalid"          ,"r"=>"pre_create"  ,"c"=>COINSPARK_ERR_INVALID_PARAMS),
            array("t"=>"b","n"=>"public not set"                  ,"r"=>"pre_create"  ,"c"=>COINSPARK_ERR_INVALID_PARAMS),
            array("t"=>"b","n"=>"recipients not set"              ,"r"=>"pre_create"  ,"c"=>COINSPARK_ERR_INVALID_PARAMS),
            array("t"=>"b","n"=>"too many recipients"             ,"r"=>"pre_create"  ,"c"=>COINSPARK_ERR_TOO_MANY_RECIPIENTS),
            array("t"=>"b","n"=>"one of recipients invalid"       ,"r"=>"pre_create"  ,"c"=>COINSPARK_ERR_INVALID_PARAMS),
            array("t"=>"b","n"=>"keepseconds not set"             ,"r"=>"pre_create"  ,"c"=>COINSPARK_ERR_INVALID_PARAMS),
            array("t"=>"b","n"=>"keepseconds not numeric"         ,"r"=>"pre_create"  ,"c"=>COINSPARK_ERR_INVALID_PARAMS),
            array("t"=>"b","n"=>"keepseconds negative"            ,"r"=>"pre_create"  ,"c"=>COINSPARK_ERR_DURATION_NOT_ACCEPTABLE),
            array("t"=>"b","n"=>"keepseconds too large"           ,"r"=>"pre_create"  ,"c"=>COINSPARK_ERR_DURATION_NOT_ACCEPTABLE),
            array("t"=>"b","n"=>"salt not set"                    ,"r"=>"pre_create"  ,"c"=>COINSPARK_ERR_INVALID_PARAMS),
            array("t"=>"b","n"=>"salt not base64"                 ,"r"=>"pre_create"  ,"c"=>COINSPARK_ERR_INVALID_PARAMS),
            array("t"=>"b","n"=>"salt too short"                  ,"r"=>"pre_create"  ,"c"=>COINSPARK_ERR_SALT_NOT_ACCEPTABLE),
            array("t"=>"b","n"=>"salt too long"                   ,"r"=>"pre_create"  ,"c"=>COINSPARK_ERR_SALT_NOT_ACCEPTABLE),
            array("t"=>"b","n"=>"message not set"                 ,"r"=>"pre_create"  ,"c"=>COINSPARK_ERR_INVALID_PARAMS),
            array("t"=>"b","n"=>"too many message parts"          ,"r"=>"pre_create"  ,"c"=>COINSPARK_ERR_TOO_MANY_MESSAGE_PARTS),
            array("t"=>"b","n"=>"mime not set"                    ,"r"=>"pre_create"  ,"c"=>COINSPARK_ERR_INVALID_PARAMS),
            array("t"=>"b","n"=>"mime too long"                   ,"r"=>"pre_create"  ,"c"=>COINSPARK_ERR_MIME_TYPE_NOT_ACCEPTABLE),
            array("t"=>"b","n"=>"filename too long"               ,"r"=>"pre_create"  ,"c"=>COINSPARK_ERR_FILE_NAME_NOT_ACCEPTABLE),
            array("t"=>"b","n"=>"bytes not set"                   ,"r"=>"pre_create"  ,"c"=>COINSPARK_ERR_INVALID_PARAMS),
            array("t"=>"b","n"=>"bytes not numeric"               ,"r"=>"pre_create"  ,"c"=>COINSPARK_ERR_INVALID_PARAMS),
            array("t"=>"b","n"=>"bytes negative"                  ,"r"=>"pre_create"  ,"c"=>COINSPARK_ERR_INVALID_PARAMS),
            array("t"=>"b","n"=>"bytes too long"                  ,"r"=>"pre_create"  ,"c"=>COINSPARK_ERR_CONTENT_TOO_LARGE),
            array("t"=>"b","n"=>"bytes total too long"            ,"r"=>"pre_create"  ,"c"=>COINSPARK_ERR_TOTAL_MESSAGE_TOO_LARGE),

            array("t"=>"b","n"=>"content not set"                 ,"r"=>"create"      ,"c"=>COINSPARK_ERR_INVALID_PARAMS),
            array("t"=>"b","n"=>"content not base64"              ,"r"=>"create"      ,"c"=>COINSPARK_ERR_INVALID_PARAMS),
            array("t"=>"b","n"=>"content too long"                ,"r"=>"create"      ,"c"=>COINSPARK_ERR_CONTENT_TOO_LARGE),
            array("t"=>"b","n"=>"content total too long"          ,"r"=>"create"      ,"c"=>COINSPARK_ERR_TOTAL_MESSAGE_TOO_LARGE),
            array("t"=>"b","n"=>"nonce not set on create"         ,"r"=>"create"      ,"c"=>COINSPARK_ERR_INVALID_PARAMS),
            array("t"=>"b","n"=>"nonce invalid on create"         ,"r"=>"create"      ,"c"=>COINSPARK_ERR_NONCE_NOT_FOUND),
            array("t"=>"b","n"=>"nonce for other op on create"    ,"r"=>"create"      ,"c"=>COINSPARK_ERR_NONCE_NOT_FOUND),
            array("t"=>"b","n"=>"nonce not found on create"       ,"r"=>"create"      ,"c"=>COINSPARK_ERR_NONCE_NOT_FOUND),
            array("t"=>"b","n"=>"nonce for other address on create","r"=>"create"     ,"c"=>COINSPARK_ERR_NONCE_NOT_FOUND),
            array("t"=>"b","n"=>"txid not set"                    ,"r"=>"create"      ,"c"=>COINSPARK_ERR_INVALID_PARAMS),
            array("t"=>"b","n"=>"txid not string"                 ,"r"=>"create"      ,"c"=>COINSPARK_ERR_INVALID_PARAMS),
            array("t"=>"b","n"=>"txid wrong length"               ,"r"=>"create"      ,"c"=>COINSPARK_ERR_TXID_INVALID),
            array("t"=>"b","n"=>"txid not hexadecimal"            ,"r"=>"create"      ,"c"=>COINSPARK_ERR_TXID_INVALID),
            array("t"=>"b","n"=>"signature not set"               ,"r"=>"create"      ,"c"=>COINSPARK_ERR_INVALID_PARAMS),
            array("t"=>"b","n"=>"signature not base64"            ,"r"=>"create"      ,"c"=>COINSPARK_ERR_INVALID_PARAMS),
            array("t"=>"b","n"=>"signature not parseable"         ,"r"=>"create"      ,"c"=>COINSPARK_ERR_SIGNATURE_INCORRECT),
            array("t"=>"b","n"=>"signature invalid pubkey"        ,"r"=>"create"      ,"c"=>COINSPARK_ERR_PUBKEY_INCORRECT),
            array("t"=>"b","n"=>"signature wrong address"         ,"r"=>"create"      ,"c"=>COINSPARK_ERR_PUBKEY_ADDRESS_MISMATCH),
            array("t"=>"b","n"=>"signature wrong signature"       ,"r"=>"create"      ,"c"=>COINSPARK_ERR_SIGNATURE_INCORRECT),

            array("t"=>"b","n"=>"recipient not set"               ,"r"=>"pre_retrieve","c"=>COINSPARK_ERR_INVALID_PARAMS),
            array("t"=>"b","n"=>"recipient address invalid"       ,"r"=>"pre_retrieve","c"=>COINSPARK_ERR_INVALID_PARAMS),
            array("t"=>"b","n"=>"txid not found"                  ,"r"=>"pre_retrieve","c"=>COINSPARK_ERR_TX_MESSAGE_UNKNOWN),
            array("t"=>"b","n"=>"recipient not accepted for txid" ,"r"=>"pre_retrieve","c"=>COINSPARK_ERR_RECIPIENT_NOT_ACCEPTED),

            array("t"=>"b","n"=>"nonce not set on retrieve"       ,"r"=>"retrieve"    ,"c"=>COINSPARK_ERR_INVALID_PARAMS),
            array("t"=>"b","n"=>"nonce invalid on retrieve"       ,"r"=>"retrieve"    ,"c"=>COINSPARK_ERR_NONCE_NOT_FOUND),
            array("t"=>"b","n"=>"nonce for other op on retrieve"  ,"r"=>"retrieve"    ,"c"=>COINSPARK_ERR_NONCE_NOT_FOUND),
            array("t"=>"b","n"=>"nonce not found on retrieve"     ,"r"=>"retrieve"    ,"c"=>COINSPARK_ERR_NONCE_NOT_FOUND),
            array("t"=>"b","n"=>"nonce for other address on retrieve","r"=>"retrieve" ,"c"=>COINSPARK_ERR_NONCE_NOT_FOUND),
            
        );
    }
    
    function make_test($error_mode='none')
    {
        set_time_limit(0);    

        output_string("---- Test started ----");

        $all_errors=  init_errors();
        $none_errors=array();
        $batch_errors=array();
        $manual_errors=array();
        
        foreach($all_errors as $error_def)
        {
            if($error_def['t']=="a")
            {
                $none_errors[]=$error_def;
                $batch_errors[]=$error_def;
            }
            if($error_def['t']=="b")
            {
                $batch_errors[]=$error_def;
            }
            if($error_def['t']=="m")
            {
                $manual_errors[]=$error_def;
            }
        }
        
        for($id=0;$id<CONST_TEST_MAX_KEYS;$id++)
        {
            create_privatekey($id);
        }

        for($seed=CONST_TEST_START_SEED+0;$seed<CONST_TEST_START_SEED+CONST_TEST_SEED_COUNT;$seed++)
        {
            
            
            $shift=0;
            if($error_mode=='random_pid')
            {
                $shift=getmypid()*1000;
            }
            output_string(sprintf("Starting test %6d",$seed+$shift));
            
            $message=generate_random_message($seed+$shift);
            
            $errors=$none_errors;
            switch($error_mode)
            {
                case 'batch':
                    $errors=$batch_errors;
                    break;
                case 'manual':
                    $errors=$manual_errors;
                    break;
                case 'random':
                case 'random_pid':
                    if(rand(0,CONST_TEST_ERROR_ONE_OF-1)==0)
                    {
                        $errors=array($batch_errors[rand(0,count($batch_errors)-1)]);
                    }
                    break;
            }

            $created=false;
            foreach($errors as $error_def)
            {
                output_string(sprintf("Error: %s",$error_def["n"]));
                $error=false;
                $request=  request_pre_create($message,$error_def);

                $start_time=  microtime(true);
                $response=  query(CONST_TEST_SERVER_URL, $request);
                output_string(sprintf("PC: %7.4f",  microtime(true)-$start_time));
        
                if(property_exists($response, "error"))
                {
                    if(($error_def['r'] !='pre_create') || ($error_def['c'] != $response->error->code))
                    {
                        output_string("pre_create: ".$response->error->code.': '.$response->error->message,log_level_reject);                   
                        if(CONST_DIE_ON_ERROR){die();}
                    }
                    $error=true;
//                    echo nl2br(htmlspecialchars(print_r($request,true)));            
                }
                else
                {
                    if(($error_def['r'] =='pre_create') && ($error_def['c'] != COINSPARK_ERR_NOERROR))
                    {
                        output_string("pre_create: expected: ".$error_def['c'],log_level_reject);                                            
                        if(CONST_DIE_ON_ERROR){die();}
                    }
                    if($error_def['r'] =='pre_create')
                    {
                        $error=true;
                    }
                }
                    
                if(!$error)
                {
                    if(!$created || (($error_def['c'] != COINSPARK_ERR_NOERROR) && ($error_def['r'] =='create')))
                    {
                        $request=request_create($message,$response->result->nonce,$error_def);
                        $start_time=microtime(true);
                        $response=  query(CONST_TEST_SERVER_URL, $request);
                        output_string(sprintf("CR: %7.4f",  microtime(true)-$start_time));
    //                  echo nl2br(htmlspecialchars(print_r($response,true)));
                        if(property_exists($response, "error"))
                        {
                            if(($error_def['r'] !='create') || ($error_def['c'] != $response->error->code))
                            {
                                output_string("create: ".$response->error->code.': '.$response->error->message,log_level_reject);
                                if(CONST_DIE_ON_ERROR){die();}
                            }
                            $error=true;
    //                      echo nl2br(htmlspecialchars(print_r($request,true)));            
                        }
                        else
                        {
                            if(($error_def['r'] =='create') && ($error_def['c'] != COINSPARK_ERR_NOERROR))
                            {
                                output_string("create: expected: ".$error_def['c'],log_level_reject);                                            
                                if(CONST_DIE_ON_ERROR){die();}
                            }                        
                            if($error_def['r'] =='create')
                            {
                                $error=true;
                            }
                            $created=true;
                        }
                    }
                }
                
                if(!$error)
                {
                    if($message['ispublic'])
                    {
                        $recipient_id=rand(0,CONST_TEST_MAX_KEYS-1);
                        $compressed=true;
                        if(rand(0,9)==0)
                        {
                            $compressed=false;
                        }
                        $pubkey=get_pubkey($recipient_id,$compressed);
                        $recipient=array(
                            'id' => $recipient_id,
                            'compressed' => $compressed,
                            'pubkey' => $pubkey,
                            'address' => get_address($pubkey, $message['network'])
                        );
                    }
                    else
                    {
                        $recipient=$message['recipients'][rand(0,count($message['recipients'])-1)];
                    }

                    $request=  request_pre_retrieve($message,$recipient,null);

                    $start_time=microtime(true);
                    $response=  query(CONST_TEST_SERVER_URL, $request);
                    output_string(sprintf("PR: %7.4f",  microtime(true)-$start_time));
    //                echo nl2br(htmlspecialchars(print_r($response,true)));

                    if(property_exists($response, "error"))
                    {
                        if(($error_def['r'] !='pre_retrieve') || ($error_def['c'] != $response->error->code))
                        {
                            output_string("pre_retrieve: ".$response->error->code.': '.$response->error->message,log_level_reject);
                            if(CONST_DIE_ON_ERROR){die();}
                        }
                        $error=true;
    //                  echo nl2br(htmlspecialchars(print_r($request,true)));            
                    }
                    else
                    {
                        if(($error_def['r'] =='pre_retrieve_sizesonly') && ($error_def['c'] != COINSPARK_ERR_NOERROR))
                        {
                            if(!ignore_mismatch($message,$error_def))
                            {
                                output_string("pre_retrieve: expected: ".$error_def['c'],log_level_reject);                                            
                                if(CONST_DIE_ON_ERROR){die();}
                            }
                        }                        
                        if($error_def['r'] =='pre_retrieve')
                        {
                            $error=true;
                        }
                    }
                }
                
                if(!$error)
                {
                    $request= request_retrieve_sizesonly($message,$recipient,$response->result->nonce,null);
                    $start_time=microtime(true);
                    $response=  query(CONST_TEST_SERVER_URL, $request);
                    output_string(sprintf("RS: %7.4f",  microtime(true)-$start_time));
//                    echo nl2br(htmlspecialchars(print_r($response,true)));
//                    print_r($response);
                    
                    if(property_exists($response, "error"))
                    {
                        if(($error_def['r'] !='retrieve') || ($error_def['c'] != $response->error->code))
                        {
                            output_string("retrieve: ".$response->error->code.': '.$response->error->message,log_level_reject);
                            if(CONST_DIE_ON_ERROR){die();}
                        }
                        $error=true;
//                      echo nl2br(htmlspecialchars(print_r($request,true)));            
                    }                    
                    else
                    {
                        if(($error_def['r'] =='retrieve_sizesonly') && ($error_def['c'] != COINSPARK_ERR_NOERROR))
                        {
                            output_string("retrieve: expected: ".$error_def['c'],log_level_reject);                                            
                            if(CONST_DIE_ON_ERROR){die();}
                        }                        
                        if($error_def['r'] =='retrieve')
                        {
                            $error=true;
                        }
                    }
                }                
                
                if(!$error)
                {
                    if(!check_message($message, $response->result,true))
                    {
                        $error=true;
                        output_string("mismatch",log_level_reject);
                        if(CONST_DIE_ON_ERROR){die();}
//                          echo nl2br(htmlspecialchars(print_r($message,true)));
//                          echo nl2br(htmlspecialchars(print_r($response,true)));
                    }
                    else
                    {
//                          echo "MATCH!!!<BR/>";                    
                    }                    
                }
                
                if(!$error)
                {
                    $request=  request_pre_retrieve($message,$recipient,$error_def);

                    $start_time=microtime(true);
                    $response=  query(CONST_TEST_SERVER_URL, $request);
                    output_string(sprintf("PR: %7.4f",  microtime(true)-$start_time));
    //                echo nl2br(htmlspecialchars(print_r($response,true)));

                    if(property_exists($response, "error"))
                    {
                        if(($error_def['r'] !='pre_retrieve') || ($error_def['c'] != $response->error->code))
                        {
                            output_string("pre_retrieve: ".$response->error->code.': '.$response->error->message,log_level_reject);
                            if(CONST_DIE_ON_ERROR){die();}
                        }
                        $error=true;
    //                  echo nl2br(htmlspecialchars(print_r($request,true)));            
                    }
                    else
                    {
                        if(($error_def['r'] =='pre_retrieve') && ($error_def['c'] != COINSPARK_ERR_NOERROR))
                        {
                            if(!ignore_mismatch($message,$error_def))
                            {
                                output_string("pre_retrieve: expected: ".$error_def['c'],log_level_reject);                                            
                                if(CONST_DIE_ON_ERROR){die();}
                            }
                        }                        
                        if($error_def['r'] =='pre_retrieve')
                        {
                            $error=true;
                        }
                    }
                }
                
                if(!$error)
                {
                    $request=  request_retrieve($message,$recipient,$response->result->nonce,$error_def);
                    $start_time=microtime(true);
                    $response=  query(CONST_TEST_SERVER_URL, $request);
                    output_string(sprintf("RT: %7.4f",  microtime(true)-$start_time));
    //                echo nl2br(htmlspecialchars(print_r($response,true)));
                
                    if(property_exists($response, "error"))
                    {
                        if(($error_def['r'] !='retrieve') || ($error_def['c'] != $response->error->code))
                        {
                            output_string("retrieve: ".$response->error->code.': '.$response->error->message,log_level_reject);
                            if(CONST_DIE_ON_ERROR){die();}
                        }
                        $error=true;
//                      echo nl2br(htmlspecialchars(print_r($request,true)));            
                    }                    
                    else
                    {
                        if(($error_def['r'] =='retrieve') && ($error_def['c'] != COINSPARK_ERR_NOERROR))
                        {
                            output_string("retrieve: expected: ".$error_def['c'],log_level_reject);                                            
                            if(CONST_DIE_ON_ERROR){die();}
                        }                        
                        if($error_def['r'] =='retrieve')
                        {
                            $error=true;
                        }
                    }
                }                
                
                if(!$error)
                {
                    if(!check_message($message, $response->result))
                    {
                        $error=true;
                        output_string("mismatch",log_level_reject);
                        if(CONST_DIE_ON_ERROR){die();}
//                          echo nl2br(htmlspecialchars(print_r($message,true)));
//                          echo nl2br(htmlspecialchars(print_r($response,true)));
                    }
                    else
                    {
//                          echo "MATCH!!!<BR/>";                    
                    }                    
                }
            }
        }        
        output_string("---- Test completed ----");
        
        return array();
    }
    
    