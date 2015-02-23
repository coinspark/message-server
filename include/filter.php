<?php


    class CoinSparkMessageFilterDefault
    {
        private $params;
        private $filtered;
        private $db;
        
        function __construct($request_params,$db=null)
        {
            $this->params=$request_params;
            $this->filtered=array();
            $this->db=$db;
        }
               
        public function getFilteredParams()
        {
            return $this->filtered;
        }
        
        public function getDB()
        {
            return $this->db;
        }
        
        public function acceptPreCreation()
        {
            if (($error = $this->acceptNetwork()) !== true)
            {
                return $error;
            }
            if (($error = $this->acceptSender()) !== true)
            {
                return $error;
            }
            if (($error = $this->acceptCreationIP()) !== true)
            {
                return $error;
            }
            if (($error = $this->acceptPublic()) !== true)
            {
                return $error;
            }
            if (($error = $this->acceptAllowedRecipients()) !== true)
            {
                return $error;
            }
            if (($error = $this->acceptKeepSeconds()) !== true)
            {
                return $error;
            }
            if (($error = $this->acceptSalt()) !== true)
            {
                return $error;
            }
            if (($error = $this->acceptMessage(false)) !== true)
            {
                return $error;
            }

            return true;
        }
        
        public function acceptCreation()
        {
            if (($error = $this->acceptNetwork()) !== true)
            {
                return $error;
            }
            if (($error = $this->acceptSender()) !== true)
            {
                return $error;
            }
            if (($error = $this->acceptCreationIP()) !== true)
            {
                return $error;
            }
            if (($error = $this->acceptPublic()) !== true)
            {
                return $error;
            }
            if (($error = $this->acceptAllowedRecipients()) !== true)
            {
                return $error;
            }
            if (($error = $this->acceptKeepSeconds()) !== true)
            {
                return $error;
            }
            if (($error = $this->acceptSalt()) !== true)
            {
                return $error;
            }
            if (($error = $this->acceptMessage(true)) !== true)
            {
                return $error;
            }
            if (($error = $this->acceptTxID()) !== true)
            {
                return $error;
            }            
            if (($error = $this->acceptPubKey()) !== true)
            {
                return $error;
            }
            if (($error = $this->acceptNonce('create')) !== true)
            {
                return $error;
            }
            if (($error = $this->acceptSignature('create')) !== true)
            {
                return $error;
            }
            
            return true;
        }
        
        public function acceptPreRetrieval()
        {
            if (($error = $this->acceptNetwork()) !== true)
            {
                return $error;
            }
            if (($error = $this->acceptRecipient()) !== true)
            {
                return $error;
            }
            if (($error = $this->acceptRetrievalIP()) !== true)
            {
                return $error;
            }
            if (($error = $this->acceptTxID()) !== true)
            {
                return $error;
            }            
            return true;
        }
        
        public function acceptRetrieval()
        {
            if (($error = $this->acceptNetwork()) !== true)
            {
                return $error;
            }
            if (($error = $this->acceptSizesOnly()) !== true)
            {
                return $error;
            }
            if (($error = $this->acceptRecipient()) !== true)
            {
                return $error;
            }
            if (($error = $this->acceptRetrievalIP()) !== true)
            {
                return $error;
            }
            if (($error = $this->acceptTxID()) !== true)
            {
                return $error;
            }            
            if (($error = $this->acceptPubKey()) !== true)
            {
                return $error;
            }
            if (($error = $this->acceptNonce('retrieve')) !== true)
            {
                return $error;
            }
            if (($error = $this->acceptSignature('retrieve')) !== true)
            {
                return $error;
            }
            return true;
        }
        
        
        private function acceptNetwork($operation='create')
        {
            $this->filtered['network']=COINSPARK_CREATE_DEFAULT_NETWORK;
            
            $not_supported_error_code=COINSPARK_ERR_SENDER_NETWORK_NOT_ACCEPTABLE;
            
            if($operation=='retrieve')
            {
                $not_supported_error_code=COINSPARK_ERR_RECIPIENT_NETWORK_NOT_ACCEPTABLE;
            }
            
            if(isset($this->params['testnet']) && $this->params['testnet'])
            {
                if(($operation=='create') || in_array("testnet", explode(',',COINSPARK_CREATE_NETWORK_ALLOW)))
                {
                    $this->filtered['network']="testnet";
                }
                else
                {
                    log_string(log_level_reject,"","FILTER: network - not supported: testnet");
                    return array_to_object(array('code'=>$not_supported_error_code,'message'=>"The testnet is not supported"));
                }
            }
            else
            {
                $this->filtered['network']=COINSPARK_CREATE_DEFAULT_NETWORK;
            }
            return true;
        }
        
        private function acceptCreationIP()
        {
            $ip=$_SERVER['REMOTE_ADDR'];
            $this->filtered['sender_ip']=$ip;
            
            if(isset($this->filtered['skip_ip_check']) && $this->filtered['skip_ip_check'])
            {
                return true;
            }
            
            
            if(in_array($ip, explode(',',COINSPARK_CREATE_IP_BLOCKED)))
            {
                log_string(log_level_reject,"","FILTER: sender ip - blocked: ".$ip);
                return array_to_object(array('code'=>COINSPARK_ERR_SENDER_NOT_ACCEPTED,'message'=>"The sender ip will never be accepted, e.g. if this server only allows messages from certain senders."));                
            }

            $ip_info=db_get_address_usage($this->db, $ip,'create');
            
            if(!is_array($ip_info))
            {
                return array_to_object(array('code'=>COINSPARK_ERR_INTERNAL_ERROR,'message'=>"Something went wrong internally"));
            }
            
            if(isset($ip_info['Blocked']) && $ip_info['Blocked'])                    
            {
                log_string(log_level_reject,"","FILTER: sender ip - suspended: ".$ip);
                return array_to_object(array('code'=>COINSPARK_ERR_SENDER_IS_SUSPENDED,'message'=>"The sender IP is suspended permanently."));                                    
            }
            if(!in_array($ip, explode(',',COINSPARK_CREATE_IP_UNLIMITED)))
            {
                if(COINSPARK_CREATE_IP_OTHER_ALLOWED)
                {
                    if(isset($ip_info['DailyCount']))
                    {
                        if($ip_info['DailyCount']>=COINSPARK_CREATE_IP_OTHER_DAILY_MAX)
                        {
                            log_string(log_level_reject,"","FILTER: sender ip - usage rate: ".$ip);
                            return array_to_object(array('code'=>COINSPARK_ERR_SENDER_IS_SUSPENDED,'message'=>"The sender IP has been temporarily suspended, e.g. if they have already sent too many messages via this server."));                        
                        }
                    }                    
                }
                else
                {
                    log_string(log_level_reject,"","FILTER: sender ip - not allowed: ".$ip);
                    return array_to_object(array('code'=>COINSPARK_ERR_SENDER_NOT_ACCEPTED,'message'=>"The sender ip will never be accepted, e.g. if this server only allows messages from certain senders."));                                                    
                }
            }            
            
            return true;
            
        }

        private function acceptRetrievalIP()
        {
            $ip=$_SERVER['REMOTE_ADDR'];
            $this->filtered['recipient_ip']=$ip;
            
            if(isset($this->filtered['skip_ip_check']) && $this->filtered['skip_ip_check'])
            {
                return true;
            }
            
/*            
            if(in_array($ip, explode(',',COINSPARK_RETRIEVE_IP_BLOCKED)))
            {
                log_string(log_level_reject,"","FILTER: recipient ip - blocked: ".$ip);
                return array_to_object(array('code'=>COINSPARK_ERR_RECIPIENT_NOT_ACCEPTED,'message'=>"The recipient ip will never be accepted, e.g. if this server only allows messages from certain recipients."));                
            }
*/
            $ip_info=db_get_address_usage($this->db, $ip,'retrieve');
            
            if(!is_array($ip_info))
            {
                return array_to_object(array('code'=>COINSPARK_ERR_INTERNAL_ERROR,'message'=>"Something went wrong internally"));
            }
            
            if(isset($ip_info['Blocked']) && $ip_info['Blocked'])                    
            {
                log_string(log_level_reject,"","FILTER: recipient ip - suspended: ".$ip);
                return array_to_object(array('code'=>COINSPARK_ERR_RECIPIENT_IS_SUSPENDED,'message'=>"The recipient IP is suspended permanently."));                                    
            }
            
            if(!in_array($ip, explode(',',COINSPARK_RETRIEVE_IP_UNLIMITED)))
            {
                if(isset($ip_info['DailyCount']))
                {
                    if($ip_info['DailyCount']>=COINSPARK_RETRIEVE_IP_OTHER_DAILY_MAX)
                    {
                        log_string(log_level_reject,"","FILTER: recipient ip - usage rate: ".$ip);
                        return array_to_object(array('code'=>COINSPARK_ERR_RECIPIENT_IS_SUSPENDED,'message'=>"The recipient IP has been temporarily suspended, e.g. if they have already retrieved too many messages via this server."));                        
                    }
                }                    
/*                
                if(COINSPARK_RETRIEVE_IP_OTHER_ALLOWED)
                {
                    if(isset($ip_info['DailyCount']))
                    {
                        if($ip_info['DailyCount']>=COINSPARK_RETRIEVE_IP_OTHER_DAILY_MAX)
                        {
                            log_string(log_level_reject,"","FILTER: recipient ip - usage rate: ".$ip);
                            return array_to_object(array('code'=>COINSPARK_ERR_RECIPIENT_IS_SUSPENDED,'message'=>"The recipient IP has been temporarily suspended, e.g. if they have already retrieved too many messages via this server."));                        
                        }
                    }                    
                }
                else
                {
                    log_string(log_level_reject,"","FILTER: recipient ip - not allowed: ".$ip);
                    return array_to_object(array('code'=>COINSPARK_ERR_RECIPIENT_NOT_ACCEPTED,'message'=>"The recipient ip will never be accepted, e.g. if this server only allows messages from certain recipients."));                                    
                }
 * 
 */
            }            
            
            
            return true;
            
        }
        
        private function acceptSender()
        {
            if(!isset($this->params['sender']))
            {
                log_string(log_level_filter,"","FILTER: sender - not set");
                return array_to_object(array('code'=>COINSPARK_ERR_INVALID_PARAMS,'message'=>"The sender parameter is required"));
            }
            
            if(!validate_address(trim($this->params['sender']),$this->filtered['network']))
            {
                log_string(log_level_filter,"","FILTER: sender - invalid: ".$this->params['sender'].", network: ".$this->filtered['network']);
                return array_to_object(array('code'=>COINSPARK_ERR_INVALID_PARAMS,'message'=>"The sender is not a valid address for given network"));
            }
            
            $address=trim($this->params['sender']);
            
            if(in_array($address, explode(',',COINSPARK_CREATE_SENDER_BLOCKED)))
            {
                log_string(log_level_reject,"","FILTER: sender - blocked: ".$address);
                return array_to_object(array('code'=>COINSPARK_ERR_SENDER_NOT_ACCEPTED,'message'=>"The sender bitcoin address will never be accepted, e.g. if this server only allows messages from certain senders."));                
            }

            $sender_info=db_get_address_usage($this->db, $address,'create');
            
            if(!is_array($sender_info))
            {
                return array_to_object(array('code'=>COINSPARK_ERR_INTERNAL_ERROR,'message'=>"Something went wrong internally"));
            }
            
            if(isset($sender_info['Blocked']) && $sender_info['Blocked'])                    
            {
                log_string(log_level_reject,"","FILTER: sender - suspended: ".$address);
                return array_to_object(array('code'=>COINSPARK_ERR_SENDER_IS_SUSPENDED,'message'=>"The sender bitcoin address is suspended permanently."));                                    
            }
            
            $this->filtered['skip_ip_check']=false;
            $this->filtered['skip_usage_rate_check']=false;
            if(!in_array($address, explode(',',COINSPARK_CREATE_SENDER_UNLIMITED)))
            {
                if(COINSPARK_CREATE_SENDER_OTHER_ALLOWED)
                {
                    if(isset($sender_info['DailyCount']))
                    {
                        if($sender_info['DailyCount']>=COINSPARK_CREATE_SENDER_OTHER_DAILY_MAX)
                        {
                            log_string(log_level_reject,"","FILTER: sender - usage rate: ".$address);
                            return array_to_object(array('code'=>COINSPARK_ERR_SENDER_IS_SUSPENDED,'message'=>"The sender bitcoin address has been temporarily suspended, e.g. if they have already sent too many messages via this server."));                        
                        }
                    }                    
                }
                else
                {
                    log_string(log_level_reject,"","FILTER: sender - not allowed: ".$address);
                    return array_to_object(array('code'=>COINSPARK_ERR_SENDER_NOT_ACCEPTED,'message'=>"The sender bitcoin address will never be accepted, e.g. if this server only allows messages from certain senders."));                                    
                }
            }
            else
            {
                $this->filtered['skip_ip_check']=true;
                $this->filtered['skip_usage_rate_check']=true;
            }
            
            $this->filtered['sender']=$address;
            
            return true;
        }
        
        private function acceptRecipient()
        {
            if(!isset($this->params['recipient']))
            {
                log_string(log_level_filter,"","FILTER: recipient - not set");
                return array_to_object(array('code'=>COINSPARK_ERR_INVALID_PARAMS,'message'=>"The recipient parameter is required"));
            }
            
            if(!validate_address(trim($this->params['recipient']),$this->filtered['network']))
            {
                log_string(log_level_filter,"","FILTER: recipient - invalid: ".$this->params['recipient'].", network: ".$this->filtered['network']);
                return array_to_object(array('code'=>COINSPARK_ERR_INVALID_PARAMS,'message'=>"The recipient is not a valid address for given network"));
            }
            
            $address=trim($this->params['recipient']);
            
            if(in_array($address, explode(',',COINSPARK_RETRIEVE_RECIPIENT_BLOCKED)))
            {
                log_string(log_level_reject,"","FILTER: recipient - blocked: ".$address);
                return array_to_object(array('code'=>COINSPARK_ERR_RECIPIENT_NOT_ACCEPTED,'message'=>"The recipient bitcoin address will never be accepted, e.g. if this server only allows messages from certain recipients."));                
            }

            $recipient_info=db_get_address_usage($this->db, $address,'retrieve');
            
            if(!is_array($recipient_info))
            {
                return array_to_object(array('code'=>COINSPARK_ERR_INTERNAL_ERROR,'message'=>"Something went wrong internally"));
            }
            
            if(isset($recipient_info['Blocked']) && $recipient_info['Blocked'])                    
            {
                log_string(log_level_reject,"","FILTER: recipient - suspended: ".$address);
                return array_to_object(array('code'=>COINSPARK_ERR_RECIPIENT_IS_SUSPENDED,'message'=>"The recipient bitcoin address is suspended permanently."));                                    
            }
            $this->filtered['skip_ip_check']=false;
            $this->filtered['skip_usage_rate_check']=false;
            if(!in_array($address, explode(',',COINSPARK_RETRIEVE_RECIPIENT_UNLIMITED)))
            {
                if(COINSPARK_RETRIEVE_RECIPIENT_OTHER_ALLOWED)
                {
                    if(isset($recipient_info['DailyCount']))
                    {
                        if($recipient_info['DailyCount']>=COINSPARK_RETRIEVE_RECIPIENT_OTHER_DAILY_MAX)
                        {
                            log_debug($address);
                            log_string(log_level_reject,"","FILTER: recipient - usage rate: ".$address);
                            return array_to_object(array('code'=>COINSPARK_ERR_RECIPIENT_IS_SUSPENDED,'message'=>"The recipient bitcoin address has been temporarily suspended, e.g. if they have already retrieved too many messages via this server."));                        
                        }
                    }                    
                }
                else
                {
                    log_string(log_level_reject,"","FILTER: recipient - not allowed: ".$address);
                    return array_to_object(array('code'=>COINSPARK_ERR_RECIPIENT_NOT_ACCEPTED,'message'=>"The recipient bitcoin address will never be accepted, e.g. if this server only allows messages from certain recipients."));                                    
                }
            }            
            else
            {
                $this->filtered['skip_ip_check']=true;
                $this->filtered['skip_usage_rate_check']=false;
            }
            
            $this->filtered['recipient']=$address;
            
            return true;
        }
        
        
        private function acceptPublic()
        {
            if(!isset($this->params['ispublic']))
            {
                log_string(log_level_filter,"","FILTER: public - not set");
                return array_to_object(array('code'=>COINSPARK_ERR_INVALID_PARAMS,'message'=>"The public parameter is required"));
            }
            
            $public=false;
            if($this->params['ispublic'])
            {
                $public=true;
            }
            
            if($public)
            {
                if(!COINSPARK_CREATE_ALLOW_PUBLIC)
                {
                    log_string(log_level_reject,"","FILTER: public - not allowed");
                    return array_to_object(array('code'=>COINSPARK_ERR_NO_PUBLIC_MESSAGES,'message'=>"The public parameter is not allowed to be true, because this server doesn’t allow public messages."));
                }
            }
            else
            {
                if(!COINSPARK_CREATE_ALLOW_PRIVATE)
                {
                    log_string(log_level_reject,"","FILTER: public - not allowed");
                    return array_to_object(array('code'=>COINSPARK_ERR_ONLY_PUBLIC_MESSAGES,'message'=>"The public parameter is not allowed to be false, because this server only allows public messages."));                    
                }
            }
            
            $this->filtered['public']=$public;
            return true;
        }

        private function acceptAllowedRecipients()
        {
            if($this->filtered['public'])
            {
                return true;
            }
            
            if(!isset($this->params['recipients']) || !is_array($this->params['recipients']))
            {
                log_string(log_level_filter,"","FILTER: recipients - not set");
                return array_to_object(array('code'=>COINSPARK_ERR_INVALID_PARAMS,'message'=>"The recipients parameter is required"));
            }
            
            if(count($this->params['recipients'])>COINSPARK_CREATE_RECIPIENTS_MAX)
            {
                log_string(log_level_reject,"","FILTER: recipients - too many: ".count($this->params['recipients']));
                return array_to_object(array('code'=>COINSPARK_ERR_TOO_MANY_RECIPIENTS,'message'=>"The recipients parameter contains too many elements."));
            }
            
            $this->filtered['recipients']=array();

            foreach($this->params['recipients'] as $recipient)
            {
                if(!validate_address($recipient,$this->filtered['network']))
                {
                    log_string(log_level_filter,"","FILTER: recipients - invalid: ".$recipient.", network: ".$this->filtered['network']);
                    return array_to_object(array('code'=>COINSPARK_ERR_INVALID_PARAMS,'message'=>"The recipient is not a valid address for given network",'data'=>$recipient));
                }
                
                if(in_array($recipient, explode(',',COINSPARK_RETRIEVE_RECIPIENT_BLOCKED)))
                {
                    log_string(log_level_reject,"","FILTER: recipients - blocked: ".$recipient);
                    return array_to_object(array('code'=>COINSPARK_ERR_RECIPIENT_NOT_ACCEPTED_ON_CREATE,'message'=>"The recipient bitcoin address will never be accepted, e.g. if this server only allows messages to certain recipients.",'data'=>$recipient));                
                }

                $recipient_info=  db_get_address_usage($this->db, $recipient,'retrieve');

                if(!is_array($recipient_info))
                {
                    return array_to_object(array('code'=>COINSPARK_ERR_INTERNAL_ERROR,'message'=>"Something went wrong internally"));
                }
                
                if(isset($recipient_info['Blocked']) && $recipient_info['Blocked'])                    
                {
                    log_string(log_level_reject,"","FILTER: recipients - suspended: ".$recipient);
                    return array_to_object(array('code'=>COINSPARK_ERR_RECIPIENT_IS_SUSPENDED_ON_CREATE,'message'=>"The recipient bitcoin address is suspended permanently.",'data'=>$recipient));                                    
                }

                if(!in_array($recipient, explode(',',COINSPARK_RETRIEVE_RECIPIENT_UNLIMITED)))
                {
                    if(COINSPARK_RETRIEVE_RECIPIENT_OTHER_ALLOWED)
                    {
                        if($recipient_info['DailyCount']>=COINSPARK_RETRIEVE_RECIPIENT_OTHER_DAILY_MAX)
                        {
                            log_string(log_level_reject,"","FILTER: recipients - usage rate: ".$recipient);
                            return array_to_object(array('code'=>COINSPARK_ERR_RECIPIENT_IS_SUSPENDED_ON_CREATE,'message'=>"The recipients parameter contains an address which has been temporarily suspended, e.g. because too many messages have been delivered to this recipient.",'data'=>$recipient));                        
                        }
                    }
                    else
                    {
                        log_string(log_level_reject,"","FILTER: recipients - not allowed: ".$recipient);
                        return array_to_object(array('code'=>COINSPARK_ERR_RECIPIENT_NOT_ACCEPTED_ON_CREATE,'message'=>"The recipient bitcoin address will never be accepted, e.g. if this server only allows messages to certain recipients.",'data'=>$recipient));                                        
                    }
                }            
                $this->filtered['recipients'][]=$recipient;
            }            
            return true;
        }
        
        private function acceptKeepSeconds()
        {
            if(!isset($this->params['keepseconds']))
            {
                log_string(log_level_filter,"","FILTER: keepseconds - not set");
                return array_to_object(array('code'=>COINSPARK_ERR_INVALID_PARAMS,'message'=>"The keepseconds parameter is required"));
            }
            
            if(!is_numeric($this->params['keepseconds']))
            {
                log_string(log_level_filter,"","FILTER: keepseconds - not numeric");
                return array_to_object(array('code'=>COINSPARK_ERR_INVALID_PARAMS,'message'=>"The keepseconds parameter should be numeric"));
            }
            
            if($this->params['keepseconds']<=0)
            {
                log_string(log_level_filter,"","FILTER: keepseconds - negative: ".$this->params['keepseconds']);
                return array_to_object(array('code'=>COINSPARK_ERR_DURATION_NOT_ACCEPTABLE,'message'=>"The keepseconds parameter should be positive"));                
            }
            if($this->params['keepseconds']>COINSPARK_CREATE_KEEPSECONDS_MAX)
            {
                log_string(log_level_reject,"","FILTER: keepseconds - too large: ".$this->params['keepseconds']);
                return array_to_object(array('code'=>COINSPARK_ERR_DURATION_NOT_ACCEPTABLE,'message'=>"The keepseconds parameter is not acceptable, because this server is only willing to store messages for a shorter period of time."));                
            }
            
            $this->filtered['keepseconds']=round($this->params['keepseconds']);
            return true;
        }        
        
        private function acceptSalt()
        {
            if(!isset($this->params['salt']))
            {
                log_string(log_level_filter,"","FILTER: salt - not set");
                return array_to_object(array('code'=>COINSPARK_ERR_INVALID_PARAMS,'message'=>"The salt parameter is required"));                
            }
            
            $salt=base64_decode(trim($this->params['salt']), true);
            if($salt === false)
            {
                log_string(log_level_filter,"","FILTER: salt - not base64: ".$this->params['salt']);
                return array_to_object(array('code'=>COINSPARK_ERR_INVALID_PARAMS,'message'=>"The salt parameter is not base64 encoded string"));                
            }
            
            if(strlen($salt)<COINSPARK_CREATE_SALT_MIN_BYTES)
            {
                log_string(log_level_reject,"","FILTER: salt - too short: ".strlen($salt));
                return array_to_object(array('code'=>COINSPARK_ERR_SALT_NOT_ACCEPTABLE,'message'=>"The salt parameter is not acceptable, e.g. because it is the empty string or is too short to provide sufficient security."));                                
            }
            
            if(strlen($salt)>COINSPARK_CREATE_SALT_MAX_BYTES)
            {
                log_string(log_level_reject,"","FILTER: salt - too long: ".strlen($salt));
                return array_to_object(array('code'=>COINSPARK_ERR_SALT_NOT_ACCEPTABLE,'message'=>"The salt parameter is too long."));                                
            }
            
            $this->filtered['salt']=$salt;
            $this->filtered['salt_encoded']=trim($this->params['salt']);
            return true;
        }
        
        private function acceptMessage($validate_content)
        {
            if(!isset($this->params['message']) || !is_array($this->params['message']))
            {
                log_string(log_level_filter,"","FILTER: message - not set");
                return array_to_object(array('code'=>COINSPARK_ERR_INVALID_PARAMS,'message'=>"The message parameter is required"));
            }
            
            if(count($this->params['message'])>COINSPARK_CREATE_MAX_PARTS)
            {
                log_string(log_level_reject,"","FILTER: message - too many parts: ".count($this->params['message']));
                return array_to_object(array('code'=>COINSPARK_ERR_TOO_MANY_MESSAGE_PARTS,'message'=>"The message contains too many individual message parts."));
            }
    
            $this->filtered['message']=array();
            $count=0;
            $total=0;
            foreach($this->params['message'] as $message_part)
            {
                $count++;
                if(!isset($message_part['mimetype']))
                {
                    log_string(log_level_filter,"","FILTER: mimetype - not set");
                    return array_to_object(array('code'=>COINSPARK_ERR_INVALID_PARAMS,'message'=>"The mime type parameter is required",'data'=>$message_part));
                }
                
                $mimetype=trim($message_part['mimetype']);
                if(in_array($mimetype, explode(',',COINSPARK_CREATE_MIMETYPE_BLOCKED)))
                {
                    log_string(log_level_reject,"","FILTER: mimetype - blocked: ".$mimetype);
                    return array_to_object(array('code'=>COINSPARK_ERR_MIME_TYPE_NOT_ACCEPTABLE,'message'=>"One of the message elements contains a mimetype which this server is not willing to deliver, e.g. because it is for text messages only.",'data'=>$message_part));                
                }

                if((strlen(COINSPARK_CREATE_MIMETYPE_ALLOWED)>0) && !in_array($mimetype, explode(',',COINSPARK_CREATE_MIMETYPE_ALLOWED)))
                {
                    log_string(log_level_reject,"","FILTER: mimetype - not allowed: ".$mimetype);
                    return array_to_object(array('code'=>COINSPARK_ERR_MIME_TYPE_NOT_ACCEPTABLE,'message'=>"One of the message elements contains a mimetype which this server is not willing to deliver, e.g. because it is for text messages only.",'data'=>$message_part));                
                }
                
                if(strlen($mimetype)>COINSPARK_CREATE_MIMETYPE_MAX_BYTES)
                {
                    log_string(log_level_reject,"","FILTER: mimetype - too long: ".strlen($mimetype));
                    return array_to_object(array('code'=>COINSPARK_ERR_MIME_TYPE_NOT_ACCEPTABLE,'message'=>"One of the message elements contains a mimetype which this server is not willing to deliver, because it is too long.",'data'=>$message_part));                                    
                }
                
                $filename=null;
                if(isset($message_part['filename']))
                {
                    if(!is_null($message_part['filename']))
                    {
                        $filename=$message_part['filename'];
                        foreach(explode(",",COINSPARK_CREATE_FILENAME_EXTENSION_BLOCK) as $extension)
                        {
                            if(strtolower(substr($filename,-strlen($extension))) == strtolower($extension))
                            {
                                log_string(log_level_reject,"","FILTER: filename - blocked: ".$filename);
                                return array_to_object(array('code'=>COINSPARK_ERR_FILE_NAME_NOT_ACCEPTABLE,'message'=>"One of the message elements contains a file name which this server is not willing to deliver, because it is too long.",'data'=>$message_part));                                                                                                
                            }
                        }
                        if(strlen($filename)>COINSPARK_CREATE_FILENAME_MAX_BYTES)
                        {
                            log_string(log_level_reject,"","FILTER: filename - too long: ".strlen($filename));
                            return array_to_object(array('code'=>COINSPARK_ERR_FILE_NAME_NOT_ACCEPTABLE,'message'=>"One of the message elements contains a file name which this server is not willing to deliver, e.g. because it is an .exe file.",'data'=>$message_part));                                                                
                        }
                    }                    
                }
                
                
                $size=0;
                $content=null;
                $encoded=null;
                if($validate_content)
                {
                    if(!isset($message_part['content']))
                    {
                        log_string(log_level_filter,"","FILTER: content - not set");
                        return array_to_object(array('code'=>COINSPARK_ERR_INVALID_PARAMS,'message'=>"The bytes parameter is required",'data'=>$message_part));
                    }                    
                    
                    $encoded=trim($message_part['content']);
                    $content=base64_decode($encoded, true);
                    if($content === false)
                    {
                        log_string(log_level_filter,"","FILTER: content - not base64");
                        return array_to_object(array('code'=>COINSPARK_ERR_INVALID_PARAMS,'message'=>"The content parameter is not base64 encoded string"));                
                    }
                    
                    $size=strlen($content);
                }
                else
                {
                    if(!isset($message_part['bytes']))
                    {
                        log_string(log_level_filter,"","FILTER: bytes - not set");
                        return array_to_object(array('code'=>COINSPARK_ERR_INVALID_PARAMS,'message'=>"The bytes parameter is required",'data'=>$message_part));
                    }
                    
                    if(!is_numeric($message_part['bytes']))
                    {
                        log_string(log_level_filter,"","FILTER: bytes - not numeric");
                        return array_to_object(array('code'=>COINSPARK_ERR_INVALID_PARAMS,'message'=>"The bytes parameter should be numeric",'data'=>$message_part));                        
                    }

                    $size=round($message_part['bytes']);
                }
                
                if($size<0)
                {
                    log_string(log_level_filter,"","FILTER: bytes - negative");
                    return array_to_object(array('code'=>COINSPARK_ERR_INVALID_PARAMS,'message'=>"The bytes parameter should be positive",'data'=>$message_part));                                            
                }
                
                if($size>COINSPARK_CREATE_MAX_PART_BYTES)
                {
                    log_string(log_level_reject,"","FILTER: content - too large: ".$size);
                    return array_to_object(array('code'=>COINSPARK_ERR_CONTENT_TOO_LARGE,'message'=>"One of the message elements is larger than this server is willing to deliver.",'data'=>$message_part));                                                                
                }
                $total+=$size;
                
                $this->filtered['message'][]=array(
                    'mimetype' => $mimetype,
                    'filename' => $filename,
                    'size'     => $size,
                    'content'  => $content,
                    'encoded'  => $encoded,
                );
            }
            
            if($total>COINSPARK_CREATE_MAX_TOTAL_BYTES)
            {
                log_string(log_level_reject,"","FILTER: message - too large: ".$total);
                return array_to_object(array('code'=>COINSPARK_ERR_TOTAL_MESSAGE_TOO_LARGE,'message'=>"The total number of bytes represented by the message elements is too large for this server to deliver."));                
            }
            
            $this->filtered['total_size']=$total;
            return true;
        }
        
        private function acceptNonce($operation)        
        {
            if(!isset($this->params['nonce']))
            {
                log_string(log_level_filter,"","FILTER: nonce - not set");
                return array_to_object(array('code'=>COINSPARK_ERR_INVALID_PARAMS,'message'=>"The nonce parameter is required"));
            }
                        
            
            $nonce=trim($this->params['nonce']);
            $arr=explode(" ",$nonce);
            if((count($arr)<2) || $arr[0] != $operation)
            {
                log_string(log_level_filter,"","FILTER: nonce - bad nonce: ".$nonce.", operation: ".$operation);
                return array_to_object(array('code'=>COINSPARK_ERR_NONCE_NOT_FOUND,'message'=>"The nonce provided does not match a nonce that was previously supplied by the message delivery server in the response to a coinspark_message_pre_$operation request."));                                
            }
            
            
            $address="";
            switch($operation)
            {
                case 'create':
                    $address=$this->filtered['sender'];                    
                    break;
                case 'retrieve':
                    $address=$this->filtered['recipient'];                    
                    break;
                default:
                    log_string(log_level_filter,"","FILTER: nonce - bad operation: ".$operation);
                    return array_to_object(array('code'=>COINSPARK_ERR_INTERNAL_ERROR,'message'=>"Something went wrong internally"));
            }
            
            $nonce_info=  db_get_nonce($this->db, $nonce);
            
            if(!is_array($nonce_info))
            {
                return array_to_object(array('code'=>COINSPARK_ERR_INTERNAL_ERROR,'message'=>"Something went wrong internally"));
            }
            
            if(!isset($nonce_info['Address']) || ($nonce_info['Address'] != $address))
            {
                log_string(log_level_filter,"","FILTER: nonce - address: ".$nonce.", address: ".$address.", expected: ".$nonce_info['Address']);
                return array_to_object(array('code'=>COINSPARK_ERR_NONCE_NOT_FOUND,'message'=>"The nonce provided does not match a nonce that was previously supplied by the message delivery server in the response to a coinspark_message_pre_$operation request."));                                
            }

            $this->filtered['nonce']=$nonce;            
            return true;
        }
        
        private function acceptTxID()                        
        {
            if(!isset($this->params['txid']))
            {
                log_string(log_level_filter,"","FILTER: txid - not set");
                return array_to_object(array('code'=>COINSPARK_ERR_INVALID_PARAMS,'message'=>"The txid parameter is required"));
            }
                        
            if(!is_string(trim($this->params['txid'])))
            {
                log_string(log_level_filter,"","FILTER: txid - not string");
                return array_to_object(array('code'=>COINSPARK_ERR_INVALID_PARAMS,'message'=>"The txid field is not a valid bitcoin transaction ID."));                
            }
            
            $txid=strtolower(trim($this->params['txid']));
            if(strlen($txid) != 64)
            {
                log_string(log_level_filter,"","FILTER: txid - wrong size: ".strlen($txid));
                return array_to_object(array('code'=>COINSPARK_ERR_TXID_INVALID,'message'=>"The txid field is not a valid bitcoin transaction ID."));                                
            }
            
            if(preg_match("/[^a-f0-9]/", $txid))
            {
                log_string(log_level_filter,"","FILTER: txid - not hex: ".$txid);
                return array_to_object(array('code'=>COINSPARK_ERR_TXID_INVALID,'message'=>"The txid field is not a valid bitcoin transaction ID."));                                                
            }
            
            $this->filtered['txid']=$txid;            
            return true;
        }

        private function acceptSizesOnly()
        {
            $this->filtered['sizesonly']=false;
            
            if(isset($this->params['sizesonly']) && $this->params['sizesonly'])
            {
                $this->filtered['sizesonly']=true;
            }
            
            return true;            
        }
        
        private function acceptPubKey()
        {
            if(!isset($this->params['pubkey']))
            {
                return true;
            }
            if(!is_string(trim($this->params['pubkey'])))
            {
                log_string(log_level_filter,"","FILTER: pubkey - not string");
                return array_to_object(array('code'=>COINSPARK_ERR_INVALID_PARAMS,'message'=>"The pubkey field is not a valid public key."));                
            }
                        
            $pubkey=strtolower(trim($this->params['pubkey']));
            if(preg_match("/[^a-f0-9]/", $pubkey))
            {
                log_string(log_level_filter,"","FILTER: txid - not hex: ".$pubkey);
                return array_to_object(array('code'=>COINSPARK_ERR_PUBKEY_INCORRECT,'message'=>"The pubkey field is not a valid public key."));                                                
            }
            
            $this->filtered['pubkey']=  hex_to_bin($pubkey);            
            
            return true;
        }
        
        private function acceptSignature($operation)        
        {
            if(!isset($this->params['signature']))
            {
                log_string(log_level_filter,"","FILTER: sigscript - not set");
                return array_to_object(array('code'=>COINSPARK_ERR_INVALID_PARAMS,'message'=>"The signature parameter is required"));
            }
                                    
            $address="";
            $owner="";
            switch($operation)
            {
                case 'create':
                    $address=$this->filtered['sender'];                    
                    $owner='sender';
                    break;
                case 'retrieve':
                    $address=$this->filtered['recipient'];                    
                    $owner='recipient';
                    break;
                default:
                    log_string(log_level_filter,"","FILTER: sigscript - wrong operation: ".$operation);
                    return array_to_object(array('code'=>COINSPARK_ERR_INTERNAL_ERROR,'message'=>"Something went wrong internally"));
            }
            
            $sigscript=base64_decode(trim($this->params['signature']), true);
            if($sigscript === false)
            {
                log_string(log_level_filter,"","FILTER: sigscript - not base64: ".bin_to_hex($sigscript));
                return array_to_object(array('code'=>COINSPARK_ERR_INVALID_PARAMS,'message'=>"The signature field does not contain a base64-encoded bitcoin signature of the nonce by the owner of the $owner address."));                                
            }
            
            $pubkey="";
            if(isset($this->filtered['pubkey']))
            {
                $pubkey=$this->filtered['pubkey'];
            }
            
            if(!parse_script_sig($sigscript, $signature, $pubkey))
            {
                log_string(log_level_filter,"","FILTER: sigscript - cannot parse: ".bin_to_hex($sigscript));
                return array_to_object(array('code'=>COINSPARK_ERR_SIGNATURE_INCORRECT,'message'=>"The signature field does not contain a base64-encoded bitcoin signature of the nonce by the owner of the $owner address."));                                
            }
            
            if(!validate_public_key($pubkey))
            {
                log_string(log_level_filter,"","FILTER: sigscript - invalid pubkey: ".bin_to_hex($sigscript));
                return array_to_object(array('code'=>COINSPARK_ERR_PUBKEY_INCORRECT,'message'=>"The pubkey field is not a valid public key."));                                
            }
            
            if(!validate_signature($signature))
            {
                log_string(log_level_filter,"","FILTER: sigscript - invalid signature: ".bin_to_hex($sigscript));
                return array_to_object(array('code'=>COINSPARK_ERR_SIGNATURE_INCORRECT,'message'=>"The signature field does not contain a base64-encoded bitcoin signature of the nonce by the owner of the $owner address."));                                
            }

            if(!verify_address($address, $pubkey,$this->filtered['network']))
            {
                log_string(log_level_reject,"","FILTER: sigscript - wrong address: ".bin_to_hex($sigscript).", address: ".$address);
                return array_to_object(array('code'=>COINSPARK_ERR_PUBKEY_ADDRESS_MISMATCH,'message'=>"Public key doen't match the $owner address."));                                
            }
            
            if(!verify_signature($this->filtered['nonce'], $signature, $pubkey))
            {
                log_string(log_level_reject,"","FILTER: sigscript - wrong signature: ".bin_to_hex($sigscript).", nonce: ".$this->filtered['nonce']);
                return array_to_object(array('code'=>COINSPARK_ERR_SIGNATURE_INCORRECT,'message'=>"The signature field does not contain a base64-encoded bitcoin signature of the nonce by the owner of the $owner address."));                                
            }            
            
            return true;
        }
    }

