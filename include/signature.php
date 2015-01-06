<?php

    function base58_encode($data)
    {
        $alphabet = str_split("123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz");

        if(is_array($data))
        {
            $in_size=count($data);
            $in_array=$data;            
        }
        else
        {
            $data_array=str_split($data);
            $in_size=strlen($data);
            $in_array=array();
            for($i=0;$i<$in_size;$i++)
            {
                $in_array[$i]=ord($data_array[$i]);
            }
        }
        $out_size=$in_size*2;

        $zeroes=0;
        $in_pos=0;
        while(($in_pos<$in_size) && ($in_array[$in_pos] == 0))
        {
            $zeroes++;
            $in_pos++;
        }
        
        $out_array=array();
        for($i=0;$i<$out_size;$i++)
        {
            $out_array[$i]=0;
        }
        
        $out_pos=$out_size;
        while($in_pos<$in_size)
        {
            $remainder=0;
            for($i=$in_pos;$i<$in_size;$i++)
            {
                $digit256=$in_array[$i];
                $temp=$remainder * 256 + $digit256;
                $in_array[$i]=floor($temp/58);
                $remainder=$temp%58;                
            }
            if($in_array[$in_pos]==0)
            {
                $in_pos++;
            }
            $out_pos--;
            $out_array[$out_pos]=$alphabet[$remainder];
        }
        
        while(($out_pos<$out_size) && ($out_array[$out_pos] == $alphabet[0]))
        {
            $out_pos++;
        }
        
        while($zeroes>0)
        {
            $zeroes--;
            $out_pos--;
            $out_array[$out_pos] = $alphabet[0];
        }
        
        return  implode('',array_slice($out_array, $out_pos));
    }
    
    function base58_decode($data)
    {
        $alphabet = str_split("123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz");
        $indexes=array();
        for($i=0;$i<count($alphabet);$i++)
        {
            $indexes[$alphabet[$i]]=$i;
        }
        
        $data_array=str_split($data);
        $in_size=strlen($data);
        $in_array=array();
        for($i=0;$i<$in_size;$i++)
        {
            if(!isset($indexes[$data_array[$i]]))
            {
                return false;
            }
            $in_array[$i]=$indexes[$data_array[$i]];
        }
        $zeroes=0;
        $in_pos=0;
        while(($in_pos<$in_size) && ($in_array[$in_pos] == 0))
        {
            $zeroes++;
            $in_pos++;
        }

        $out_size=$in_size;
        $out_array=array();
        for($i=0;$i<$out_size;$i++)
        {
            $out_array[$i]=0;
        }
        
        $out_pos=$out_size;
        while($in_pos<$in_size)
        {
            $remainder=0;
            for($i=$in_pos;$i<$in_size;$i++)
            {
                $digit58=$in_array[$i];
                $temp=$remainder * 58 + $digit58;
                $in_array[$i]=floor($temp/256);
                $remainder=$temp%256;                
            }
            if($in_array[$in_pos]==0)
            {
                $in_pos++;
            }
            $out_pos--;
            $out_array[$out_pos]=$remainder;
        }
        while(($out_pos<$out_size) && ($out_array[$out_pos] == 0))
        {
            $out_pos++;
        }
        
        return array_slice($out_array, $out_pos-$zeroes);        
    }
    
    function parse_script_sig($script_sig,&$signature,&$pubkey)
    {        
        if(!is_string($script_sig))
        {
            return false;
        }
        if(strlen($script_sig)<1)                                               
        {
            return false;                                                       
        }
        
        $signature_len=ord(substr($script_sig,0,1))-1;
        if($signature_len+2>strlen($script_sig))
        {
            return false;
        }
        
        $pubkey_len=ord(substr($script_sig,$signature_len+2,1));
        if($signature_len+$pubkey_len+3 != strlen($script_sig))
        {
            return false;
        }
        
        $signature=substr($script_sig,1,$signature_len);
        $pubkey=substr($script_sig,$signature_len+3,$pubkey_len);
        
        return true;
    }
    
    function validate_signature($signature)
    {
        if(!is_string($signature))
        {
            return false;
        }
        if((strlen($signature)<8) || (strlen($signature)>72))                  
        {
            return false;
        }        
        
        return true;                                                            // the rest should be validated by openssl
    }
    
    function validate_public_key($pubkey)
    {
        if(!is_string($pubkey))
        {
            return false;
        }
        if(strlen($pubkey)<33)                                              
        {
            return false;                                                       
        }
        
        $compression_type=ord(substr($pubkey,0,1));
        
        switch($compression_type)
        {
            case 0x04:
                if(strlen($pubkey) != 65)
                {
                    return false;
                }
                break;
            case 0x02:
            case 0x03:
                if(strlen($pubkey) != 33)
                {
                    return false;
                }
                break;
            default:
                return false;
        }
        
        return true;
    }    
    
    function pubkey_to_pem($pubkey)
    {        
        $der="\x30".chr(21+strlen($pubkey))."\x30\x10\x06\x07\x2a\x86\x48\xce\x3d\x02\x01\x06\x05\x2b\x81\x04\x00\x0a";
        $der.="\x03".chr(1+strlen($pubkey))."\x00".$pubkey;
        
        $der_base64=  base64_encode($der);
        
        $pem="-----BEGIN PUBLIC KEY-----\n";
        while(strlen($der_base64))
        {
            $pem.=substr($der_base64,0,64)."\n";
            $der_base64=substr($der_base64,64);
        }
        $pem.="-----END PUBLIC KEY-----\n";
        
        return $pem;
    }
    
    function verify_signature($message,$signature,$pubkey)
    {
        $prefix=CONST_TMP_DIR."/".getmypid();
        
        $key_file=$prefix.'.key';
        $sig_file=$prefix.'.sig';
        $msg_file=$prefix.'.msg';

        file_put_contents($key_file,pubkey_to_pem($pubkey));
        file_put_contents($sig_file,$signature);
        file_put_contents($msg_file,$message);

        $command="openssl dgst -sha256 -verify $key_file -signature $sig_file < $msg_file";
        $async_process=popen($command, 'r');
		
        $result="";
        if (is_resource($async_process))
        {
            $result.=fgets($async_process);
            while (!feof($async_process)) 
            {
                $this_line=fgets($async_process);
                if(strlen($this_line))
                {
                    $result.=$this_line;                        
                }
            }
            fclose($async_process);
        }        
        unlink($key_file);
        unlink($sig_file);
        unlink($msg_file);
        
        
        if(trim($result)=="Verified OK")
        {
            return true;
        }
        return false;
    }

    function validate_address($address,$network='main')
    {
        if(!is_string($address))
        {
            return false;
        }
        
        $decoded=base58_decode($address);

        if($decoded === false)
        {
            return false;
        }
        
        if(count($decoded) != 25)
        {
            return false;
        }
        $version=$decoded[0];
        switch($network)
        {
            case 'main':
                if($version != 0x00)
                {
                    return false;
                }
                break;
            case 'testnet':
                if($version != 0x6f)
                {
                    return false;
                }
                break;
            default:
                return false;
        }
        $key_hash="";
        for($i=0;$i<21;$i++)
        {
            $key_hash.=chr($decoded[$i]);
        }
        $decoded_checksum="";
        for($i=21;$i<25;$i++)
        {
            $decoded_checksum.=chr($decoded[$i]);
        }
        $checksum=substr(hash('sha256', hash('sha256', $key_hash, true), true),0,4);
        if($checksum != $decoded_checksum)
        {
            return false;
        }
        return true;
    }
    
    function verify_address($address,$pubkey,$network='main')
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
        $address_to_check=  base58_encode($key_hash.$checksum);

        return ($address_to_check == $address);
    }
