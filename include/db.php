<?php


    require_once '../config/coinspark_config.php'; 
    require_once '../include/log.php'; 

    define("CS_ERR_DB_NOERROR",                           "OK");
    define("CS_ERR_DB_NOT_FOUND",                         "Not Found");
    define("CS_ERR_DB_EXPIRED",                           "Expired");
    define("CS_ERR_DB_NOT_ALLOWED",                       "Not Allowed");
    define("CS_ERR_DB_CORRUPTED",                         "Corrupted");
    
    
    function open_message_db()
    {        
        @ $mysqli=new mysqli(CONST_MYSQL_MESSAGE_DB_HOST, CONST_MYSQL_MESSAGE_DB_USER, CONST_MYSQL_MESSAGE_DB_PASS, CONST_MYSQL_MESSAGE_DB_DBNAME);
        
        if(mysqli_connect_errno())
        {
            $error=sprintf("Cannot connect to %s:%s as %s, error: %s", CONST_MYSQL_MESSAGE_DB_DBNAME, CONST_MYSQL_MESSAGE_DB_HOST, CONST_MYSQL_MESSAGE_DB_USER, mysqli_connect_error());
            log_string(log_level_fatal, "", $error);
            return null;
        }
        
        return $mysqli;
    }

    function run_sql($mysqli,$query)
    {
        if(($result=$mysqli->query($query)) === false)
        {
            $error=sprintf("MySQL error %d on %s: %s",$mysqli->errno,$query,$mysqli->error);
            log_string(log_level_error, "", $error);
        }
        return $result;        
    }

    function commit_transaction($mysqli,$transaction)
    {
        if(count($transaction) == 0)
        {
            return true;
        }
//        log_debug_array($transaction);        
        $success=true;
        $mysqli->autocommit(false);
        
        foreach($transaction as $sql)
        {
            if($mysqli->query($sql) === false)
            {
                $error=sprintf("MySQL error %d on %s: %s",$mysqli->errno,$sql,$mysqli->error);
                log_string(log_level_error, "", $error);
                $success=false;
            }            
        }
        
        if($success)
        {
            if($mysqli->commit() === false)
            {
                $success=false;
                $error=sprintf("MySQL error %d on commit: %s",$mysqli->errno,$mysqli->error);
                log_string(log_level_warning, "", $error);
            }            
        }
        
        return $success;
    }    
    
    function escape_value_for_sql($value)            
    {
        if(is_null($value))
        {
            return 'NULL';
        }
            
        return "'".addslashes($value)."'";        
    }

    
    function db_insert_message(&$db,$message)
    {
        if($db == null)
        {
            $db=open_message_db();
        }
        if($db == null)
        {
            return false;
        }
        
        $transaction=array();
        
        $transaction[]="INSERT INTO transactions (TxID,MessageSalt,MessageHash,MessageSize,Sender,SenderIP,LifeTime,Created,Expired,PublicFlags,CountAddresses,CountContentParts) VALUES (".
            escape_value_for_sql($message['TxID']).",".
            escape_value_for_sql($message['MessageSalt']).",".
            escape_value_for_sql($message['MessageHash']).",".
            $message['MessageSize'].",".
            escape_value_for_sql($message['Sender']).",".
            escape_value_for_sql($message['SenderIP']).",".
            $message['LifeTime'].",".
            $message['Created'].",".
            $message['Expired'].",".
            $message['PublicFlags'].",".
            count($message['Addresses']).",".
            count($message['ContentParts']).");";
        
        $count=0;
        foreach($message['Addresses'] as $address)
        {
            $count++;
            $transaction[]="INSERT INTO addresses (TxID,Expired,AddressID,Address,RetrievalCount) VALUES (".
                escape_value_for_sql($message['TxID']).",".
                $message['Expired'].",".
                $count.",".
                escape_value_for_sql($address).",".
                "0);";            
        }
        
        $count=0;
        foreach($message['ContentParts'] as $contentpart)
        {
            $count++;
            $transaction[]="INSERT INTO details (TxID,Expired,ContentPartID,MimeType,FileName,ContentLength) VALUES (".
                escape_value_for_sql($message['TxID']).",".
                $message['Expired'].",".
                $count.",".
                escape_value_for_sql($contentpart['MimeType']).",".
                escape_value_for_sql($contentpart['FileName']).",".
                $contentpart['ContentLength'].");";                    
            
            $transaction[]="INSERT INTO contents (TxID,Expired,ContentPartID,Content) VALUES (".
                escape_value_for_sql($message['TxID']).",".
                $message['Expired'].",".
                $count.",".
                escape_value_for_sql($contentpart['Content_Base64']).");";                                
        }
        
        return commit_transaction($db, $transaction);
    }
    
    function db_get_message(&$db, $txid,$test_only=false,$address="",$sizes_only=false)
    {
        if($db == null)
        {
            $db=open_message_db();
        }
        if($db == null)
        {
            return false;
        }
        
        $result=run_sql($db,"SELECT * FROM transactions WHERE TxID='".$txid."'");
        
        if($result === false)
        {
            return false;
        }
        
        $transaction=array();
        $message=array('TxID' => $txid);
        
        $row_array=$result->fetch_assoc();
        if(is_array($row_array))
        {
            $row_array=array_change_key_case($row_array);
            $message['MessageSalt']=$row_array[strtolower('MessageSalt')];
            $message['MessageHash']=$row_array[strtolower('MessageHash')];
            $message['MessageSize']=$row_array[strtolower('MessageSize')];
            $message['Sender']=$row_array[strtolower('Sender')];
            $message['SenderIP']=$row_array[strtolower('SenderIP')];
            $message['LifeTime']=$row_array[strtolower('LifeTime')];
            $message['Created']=$row_array[strtolower('Created')];
            $message['Expired']=$row_array[strtolower('Expired')];
            $message['PublicFlags']=$row_array[strtolower('PublicFlags')];
            $message['CountAddresses']=$row_array[strtolower('CountAddresses')];
            $message['CountContentParts']=$row_array[strtolower('CountContentParts')];
        }   
        else 
        {
            $message['Error']=CS_ERR_DB_NOT_FOUND;
        }
        
        if(!isset($message['Error']))
        {
            if(!is_null($message['Expired']))
            {
                if($message['Expired']<time())
                {
                    $message['Error']=CS_ERR_DB_EXPIRED;
                }
            }
        }
        
        if(!isset($message['Error']))
        {
            if($message['PublicFlags'] == 0)
            {
                $result=run_sql($db,"SELECT * FROM addresses WHERE TxID='".$txid."' AND Address='".$address."'");
                if($result === false)
                {
                    return false;
                }
                $row_array=$result->fetch_row();
                if(!is_array($row_array))
                {
                    $message['Error']=CS_ERR_DB_NOT_ALLOWED;                    
                }
                else 
                {
                    if(!$test_only)
                    {
                        $transaction[]="UPDATE addresses SET RetrievalCount=RetrievalCount + 1 WHERE TxID='".$txid."' AND Address='".$address."'";
                    }
                }
            }
        }
        
        if(!isset($message['Error']))
        {
            $result=run_sql($db,"SELECT * FROM details WHERE TxID='".$txid."'");
            if($result === false)
            {
                return false;
            }
            $message['ContentParts']=array();
            $take_this_row=true;
            while($take_this_row)
            {
                $row_array=$result->fetch_assoc();
                if(is_array($row_array))
                {
                    $row_array=array_change_key_case($row_array);
                    if(!$test_only)
                    {
                        $message['ContentParts'][$row_array[strtolower('ContentPartID')]]=array(
                            'MimeType' => $row_array[strtolower('MimeType')],
                            'FileName' => $row_array[strtolower('FileName')],
                            'ContentLength' => $row_array[strtolower('ContentLength')],
                        );        
                    }
                    else
                    {
                        $message['ContentParts'][$row_array[strtolower('ContentPartID')]]=true;
                    }
                }            
                else 
                {
                    $take_this_row=false;
                }
            }
            
            if(count($message['ContentParts']) != $message['CountContentParts'])
            {
                $error=sprintf("Invalid number of content parts for %s, expected %d, found ",$txid,$message['CountContentParts'],count($message['ContentParts']));
                log_string(log_level_warning, "", $error);
                $message['Error']=CS_ERR_DB_CORRUPTED;                    
            }
        }   
        
        if(!$test_only && !$sizes_only)
        {
            if(!isset($message['Error']))
            {
                $result=run_sql($db,"SELECT * FROM contents WHERE TxID='".$txid."'");
                if($result === false)
                {
                    return false;
                }
                $take_this_row=true;
                while($take_this_row)
                {
                    $row_array=$result->fetch_assoc();
                    if(is_array($row_array))
                    {
                        $row_array=array_change_key_case($row_array);
                        $message['ContentParts'][$row_array[strtolower('ContentPartID')]]['Content_Base64']=$row_array[strtolower('Content')];
                    }            
                    else 
                    {
                        $take_this_row=false;
                    }
                }            
            }   
        }
        
        if(isset($message['ContentParts']))
        {
            ksort($message['ContentParts']);
        }
        
        if(!isset($message['Error']))
        {
            commit_transaction($db, $transaction);
        }
        
        return $message;
    }
    
    function db_delete_expired(&$db,$limit=0)
    {
        if($db == null)
        {
            $db=open_message_db();
        }
        if($db == null)
        {
            return false;
        }

        $transaction=array();
        
        $sql="DELETE FROM contents WHERE Expired<".time();
        if($limit > 0)
        {
            $sql.=" ORDER BY Expired LIMIT ".$limit;
        }
        
        $transaction[]=$sql;
        
        $day_ago=time()-86400;
        $sql="DELETE FROM usagerate WHERE Created<".$day_ago;
        if($limit > 0)
        {
            $sql.=" ORDER BY Expired LIMIT ".$limit;
        }
        
        $transaction[]=$sql;
        
        $hour_ago=time()-3600;
        $sql="DELETE FROM nonce WHERE Created<".$hour_ago;
        if($limit > 0)
        {
            $sql.=" ORDER BY Expired LIMIT ".$limit;
        }
        
        $transaction[]=$sql;
        
        return commit_transaction($db, $transaction);
    }
    
    function db_get_address_usage(&$db,$address,$operation)
    {
        if($db == null)
        {
            $db=open_message_db();
        }
        if($db == null)
        {
            return false;
        }
        
        $usage_type=0;
        switch($operation)
        {
            case 'create':
                $usage_type=1;
                break;
            case 'retrieve':
                $usage_type=2;
                break;
        }
        
        $info=array(
            'Address' => $address,
            'DailyCount' => 0,
            'Blocked' => false,
            );
        
        $result=run_sql($db,"SELECT * FROM usagerate WHERE Address='".$address."' AND UsageType=".$usage_type);
        
        if($result === false)
        {
            return false;
        }
        
        $row_array=$result->fetch_assoc();
        if(is_array($row_array))
        {
            $row_array=array_change_key_case($row_array);
            $info['DailyCount']=$row_array[strtolower('UsageCount')];
        }        
        
        $result=run_sql($db,"SELECT * FROM block WHERE Address='".$address."' AND UsageType=".$usage_type);
        
        if($result === false)
        {
            return false;
        }
        
        $row_array=$result->fetch_assoc();
        if(is_array($row_array))
        {
            $info['Blocked']=true;
        }        
        
        return $info;
    }
        
    function db_increment_address_usage(&$db,$addresses,$operation)
    {
        if($db == null)
        {
            $db=open_message_db();
        }
        if($db == null)
        {
            return false;
        }
        
        $usage_type=0;
        switch($operation)
        {
            case 'create':
                $usage_type=1;
                break;
            case 'retrieve':
                $usage_type=2;
                break;
        }
       
        $transaction=array();
        
        foreach ($addresses as $address)
        {
            $sql="INSERT INTO usagerate (Address,Created,UsageType,UsageCount) VALUES (".
                    escape_value_for_sql($address).",".
                    time().",".
                    $usage_type.",".
                    "1) ON DUPLICATE KEY UPDATE UsageCount=UsageCount+1;";                    
        
            $transaction[]=$sql;
        }
        
        return commit_transaction($db, $transaction);
        
    }
    
    function db_get_nonce(&$db,$nonce)
    {
        if($db == null)
        {
            $db=open_message_db();
        }
        
        if($db == null)
        {
            return false;
        }
                
        $info=array(
            'Nonce' => $nonce,
            );
        
        $result=run_sql($db,"SELECT * FROM nonce WHERE Nonce='".$nonce."'");
        
        if($result === false)
        {
            return false;
        }
        
        $row_array=$result->fetch_assoc();
        if(is_array($row_array))
        {
            $row_array=array_change_key_case($row_array);
            $info['Address']=$row_array[strtolower('Address')];
        }        
        
        return $info;
    }

    function db_insert_nonce(&$db,$nonce,$address,$ip)
    {
        if($db == null)
        {
            $db=open_message_db();
        }
        
        if($db == null)
        {
            return false;
        }
                
        $transaction=array();
        
        $sql="INSERT INTO nonce (Nonce,Created,Address,IP) VALUES (".
                escape_value_for_sql($nonce).",".
                time().",".
                escape_value_for_sql($address).",".
                escape_value_for_sql($ip).");";
        
        $transaction[]=$sql;
        
        return commit_transaction($db, $transaction);
    }
    
    function db_delete_nonce(&$db,$nonce)
    {
        if($db == null)
        {
            $db=open_message_db();
        }
        
        if($db == null)
        {
            return false;
        }
                
        $transaction=array();
        
        $sql="DELETE FROM nonce WHERE Nonce='".$nonce."'";
        
        $transaction[]=$sql;
        
        return commit_transaction($db, $transaction);
    }
    
