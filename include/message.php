<?php

    function generate_nonce_code($prefix)
    {
        $code=$prefix.' ';
        $time=time()%1000000000;
        $pid=(getmypid()*rand(0,65535))%1000000000;
        
        $code.=sprintf("%18d",$time*$pid);
        return $code;
    }

    function message_pre_create($db,$params,&$error)
    {
        $nonce=generate_nonce_code('create');
                
        if(!db_insert_nonce($db, $nonce, $params['sender'], $params['sender_ip']))
        {
            $error=array_to_object(array('code'=>COINSPARK_ERR_INTERNAL_ERROR,'message'=>"Something went wrong internally"));
            return null;
        }
        
        return array(
            'sender' =>  $params['sender'],
            'nonce'  =>  $nonce
            );
    }

    function message_create($db,$params,&$error)
    {
        $time_now=time();
        $message=array(
            'TxID'          => $params['txid'],
            'MessageSalt'   => $params['salt_encoded'],
            'MessageSize'   => $params['total_size'],
            'MessageHash'   => "",
            'Sender'        => $params['sender'],
            'SenderIP'      => $params['sender_ip'],
            'LifeTime'      => $params['keepseconds'],
            'Created'       => $time_now,
            'Expired'       => $time_now+$params['keepseconds'],
            'PublicFlags'   => $params['public'] ? 1 : 0,
            'Addresses'     => $params['public'] ? array() : $params['recipients'],
            'ContentParts'  => array(),            
        );
        foreach($params['message'] as $message_part)
        {
            $message['ContentParts'][]=array(
                'MimeType'            => $message_part['mimetype'],
                'FileName'            => $message_part['filename'],
                'ContentLength'       => $message_part['size'],
                'Content_Base64'      => $message_part['encoded'],
            );
        }
        
        if(!db_insert_message($db, $message))
        {
            $error=array_to_object(array('code'=>COINSPARK_ERR_INTERNAL_ERROR,'message'=>"Something went wrong internally"));
            return null;            
        }
        
        if(!$params['skip_usage_rate_check'])
        {
            db_increment_address_usage($db, array($params['sender'],$params['sender_ip']), 'create');
        }
        db_delete_nonce($db, $params['nonce']);
        
        log_string(log_level_message, "", "INSERT message for tx ".$params['txid']);
        
        return array(
            'txid' =>  $params['txid'],
            'keepseconds'  => $params['keepseconds']
            );
    }

    function message_pre_retrieve($db,$params,&$error)
    {
        $message = db_get_message($db, $params['txid'], true, $params['recipient']);
        
        if(isset($message['Error']))
        {
            switch($message['Error'])
            {
                case CS_ERR_DB_NOT_FOUND:
                    log_string(log_level_reject,"","REJECT: txid not found: ".$params['txid']);
                    $error=array_to_object(array('code'=>COINSPARK_ERR_TX_MESSAGE_UNKNOWN,'message'=>"This server does not have any message for transaction txid."));
                    break;
                case CS_ERR_DB_EXPIRED:
                    log_string(log_level_reject,"","REJECT: txid expired: ".$params['txid']);
                    $error=array_to_object(array('code'=>COINSPARK_ERR_TX_MESSAGE_EXPIRED,'message'=>"This server no longer has the message for txid, since it expired based on the storage time that was requested by the sender."));
                    break;
                case CS_ERR_DB_NOT_ALLOWED:
                    log_string(log_level_reject,"","REJECT: txid recipient not allowed: ".$params['txid'].", address: ".$params['recipient']);
                    $error=array_to_object(array('code'=>COINSPARK_ERR_RECIPIENT_NOT_ACCEPTED,'message'=>"The provided recipient is not one of the permitted addresses for retrieving this message."));
                    break;
                case CS_ERR_DB_CORRUPTED:
                    $error=array_to_object(array('code'=>COINSPARK_ERR_INTERNAL_ERROR,'message'=>"Something went wrong internally"));
                    break;
            }
            return null;
        }
        
        $nonce=generate_nonce_code('retrieve');
        
        if(!db_insert_nonce($db, $nonce, $params['recipient'], $params['recipient_ip']))
        {
            $error=array_to_object(array('code'=>COINSPARK_ERR_INTERNAL_ERROR,'message'=>"Something went wrong internally"));
            return null;
        }
        
        return array(
            'recipient' =>  $params['recipient'],
            'nonce'  =>  $nonce
            );
    }

    function message_retrieve($db,$params,&$error)
    {
        $message = db_get_message($db, $params['txid'], false, $params['recipient'],$params['sizesonly']);
        
        if(isset($message['Error']))
        {
            switch($message['Error'])
            {
                case CS_ERR_DB_NOT_FOUND:
                    log_string(log_level_reject,"","REJECT: txid not found: ".$params['txid']);
                    $error=array_to_object(array('code'=>COINSPARK_ERR_TX_MESSAGE_UNKNOWN,'message'=>"This server does not have any message for transaction txid."));
                    break;
                case CS_ERR_DB_EXPIRED:
                    log_string(log_level_reject,"","REJECT: txid expired: ".$params['txid']);
                    $error=array_to_object(array('code'=>COINSPARK_ERR_TX_MESSAGE_EXPIRED,'message'=>"This server no longer has the message for txid, since it expired based on the storage time that was requested by the sender."));
                    break;
                case CS_ERR_DB_NOT_ALLOWED:
                    log_string(log_level_reject,"","REJECT: txid recipient not allowed: ".$params['txid'].", address: ".$params['recipient']);
                    $error=array_to_object(array('code'=>COINSPARK_ERR_RECIPIENT_NOT_ACCEPTED,'message'=>"The provided recipient is not one of the permitted addresses for retrieving this message."));
                    break;
                case CS_ERR_DB_CORRUPTED:
                    $error=array_to_object(array('code'=>COINSPARK_ERR_INTERNAL_ERROR,'message'=>"Something went wrong internally"));
                    break;
                default:
                    $error=array_to_object(array('code'=>COINSPARK_ERR_INTERNAL_ERROR,'message'=>"Something went wrong internally"));
                    break;
            }
            return null;
        }
        
        
        $result=array(
            'salt'      => $message['MessageSalt'],
            'message'   => array(),
        );
        
        foreach($message['ContentParts'] as $content_part)
        {
            $message_part=new stdClass();
            $message_part->mimetype=$content_part['MimeType'];
            $message_part->filename=$content_part['FileName'];
            if($params['sizesonly'])
            {
                $message_part->bytes=$content_part['ContentLength'];                
            }
            else
            {
                $message_part->content=$content_part['Content_Base64'];
            }            
            $result['message'][]=$message_part;
        }
        
        if(!$params['sizesonly'])
        {
            if(!$params['skip_usage_rate_check'])
            {
                db_increment_address_usage($db, array($params['recipient'],$params['recipient_ip']), 'retrieve');
            }
        }
        db_delete_nonce($db, $params['nonce']);
        
        log_string(log_level_message, "", "GET    message for tx ".$params['txid'].', recipient: '.$params['recipient']);
        return $result;        
    }
    