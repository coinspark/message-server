<?php

    chdir(dirname(__FILE__));
        
    require_once "../include/constants.php";
    require_once "../config/coinspark_config.php";
    require_once "../include/signature.php";
    require_once "../include/log.php";
    require_once "../include/db.php";
    require_once "../include/filter.php";
    require_once "../include/message.php";
    
    function bin_to_hex($binary)
    {
        $result="";
        for($i=0;$i<strlen($binary);$i++)
        {
            $result.=sprintf("%02x",ord(substr($binary,$i,1)));
        }
        return $result;
    }
    
    function hex_to_bin($str)
    {
        $map=array(
            '0'=>0,
            '1'=>1,
            '2'=>2,
            '3'=>3,
            '4'=>4,
            '5'=>5,
            '6'=>6,
            '7'=>7,
            '8'=>8,
            '9'=>9,
            'a'=>10,
            'b'=>11,
            'c'=>12,
            'd'=>13,
            'e'=>14,
            'f'=>15,
        );
        $str=  strtolower($str);
        $len=strlen($str)/2;
        $res='';
        for($i=0;$i<$len;$i++)
        {
            $res.=chr($map[substr($str,2*$i+0,1)]*16+$map[substr($str,2*$i+1,1)]);
        }                
        return $res;
    }

    function array_to_object($arr)
    {
        if(is_array($arr))
        {
            $obj=new stdClass();
            foreach($arr as $key => $value)
            {
                if(strlen($key))
                {
                    $obj->$key=$value;
                }
            }

            return $obj;
        }
        
        return $arr;
    }
    
    $decoded=null;
    
    if(isset($_SERVER['SERVER_NAME']))
    {
        switch($_SERVER['REQUEST_METHOD'])
        {
            case 'POST':
                $payload=file_get_contents('php://input');
                $decoded=json_decode($payload,true);
                break;
            case 'GET':
                $decoded=array();
                $decoded['method']='info';
                $decoded['error_mode']='none';
                $decoded['id']=0;
                $decoded['params']=array();
                foreach($_GET as $key=>$value)
                {
                    if($key=='m')
                    {
                        $decoded['method']=$value;    
                    }
                    else 
                    {
                        if($key=='id')
                        {
                            $decoded['id']=$value;    
                        }
                        else 
                        {
                            if($key=='e')
                            {
                                $decoded['error_mode']=$value;    
                            }
                            else
                            {
                                $decoded['params'][$key]=$value;                                                        
                            }
                        }
                    }
                }
                break;
        }
    }
    else
    {
        $decoded=array();
        $decoded['method']='test';
        $decoded['error_mode']='none';
        $decoded['id']=0;
        $decoded['params']=array();       
        if($argc>1)
        {
            $decoded['method']=$argv[1];
        }
        if($argc>2)
        {
            $decoded['error_mode']=$argv[2];
        }
        $_SERVER['REMOTE_ADDR']="cli";
    }
    
    $response=array();
    
    if(is_null($decoded))
    {
        $response['error']=new stdClass();
        $response['error']->code=COINSPARK_ERR_PARSE_ERROR;
        $response['error']->message="Could not parse the JSON input received";
    }
    else
    {
        if(is_array($decoded))
        {
            if(isset($decoded['method']))
            {
                $response['method']=$decoded['method'];
            }
            else
            {
                $response['error']=new stdClass();
                $response['error']->code=COINSPARK_ERR_INVALID_REQUEST;
                $response['error']->message="The JSON received doesn't have method field";                          
            }            
            if(isset($decoded['id']))
            {
                $response['id']=$decoded['id'];
            }
            else
            {
                $response['error']=new stdClass();
                $response['error']->code=COINSPARK_ERR_INVALID_REQUEST;
                $response['error']->message="The JSON received doesn't have id field";                          
            }
            if(!isset($decoded['params']) || !is_array($decoded['params']))
            {
                $response['error']=new stdClass();
                $response['error']->code=COINSPARK_ERR_INVALID_REQUEST;
                $response['error']->message="The JSON received doesn't have id field";                          
            }
        }
        else 
        {
            $response['error']=new stdClass();
            $response['error']->code=COINSPARK_ERR_INVALID_REQUEST;
            $response['error']->message="The JSON received is not JSON object/array";               
        }
    }

    $log_level=log_level_filter;
    
    $json_output=true;
    
    
    if(!isset($response['error']))
    {
        log_string(log_level_message, "", "IP: ".$_SERVER['REMOTE_ADDR'].", METHOD: ".$decoded['method']);        
        
        $db=null;
        db_delete_expired($db);
        $filter=new CoinSparkMessageFilter($decoded['params'],$db);
        switch($decoded['method'])
        {
            case 'coinspark_message_pre_create':
                $response['error']=$filter->acceptPreCreation();
                if($response['error'] === true)
                {                 
                    $response['error']=null;
                    $response['result']= array_to_object(message_pre_create($db,$filter->getFilteredParams(), $response['error']));
                }
                break;
            case 'coinspark_message_create':
                $response['error']=$filter->acceptCreation();
                if($response['error'] ===true)
                {                 
                    $response['error']=null;
                    require_once "../include/message.php";
                    $response['result']=  array_to_object(message_create($db,$filter->getFilteredParams(), $response['error']));
                }
                break;
            case 'coinspark_message_pre_retrieve':
                $response['error']=$filter->acceptPreRetrieval();
                if($response['error'] === true)
                {                 
                    $response['error']=null;
                    require_once "../include/message.php";
                    $response['result']= array_to_object(message_pre_retrieve($db,$filter->getFilteredParams(), $response['error']));
                }
                break;
            case 'coinspark_message_retrieve':
                $response['error']=$filter->acceptRetrieval();
                if($response['error'] === true)
                {                 
                    $response['error']=null;
                    require_once "../include/message.php";
                    $response['result']= array_to_object(message_retrieve($db,$filter->getFilteredParams(), $response['error']));
                }
                break;
            case 'test':                
                require_once "../include/test.php";
                $response['result']=  make_test($decoded['error_mode']);
                $json_output=false;
                break;
            case 'info':                          
                $response['result']=array('Status' => ($db == null) ? "Cannot connect to MySQL database" : "OK");
                $json_output=false;
                break;
            default:
                $response['error']=new stdClass();
                $response['error']->code=COINSPARK_ERR_METHOD_NOT_FOUND;
                $response['error']->message="The method in the request is not available";               
                break;
        }
    }
    

    if(isset($response['error']) && !is_null($response['error']))
    {
        log_string(log_level_filter, "", "IP: ".$_SERVER['REMOTE_ADDR'].", ERROR: ".$response['error']->code.' - '.$response['error']->message);        
        if(isset($response['result']))
        {
            unset($response['result']);
        }
    }
    else
    {
        unset($response['error']);
    }
    
    if($json_output)
    {
        echo json_encode(array_to_object($response));
    }
    else
    {
        if($_SERVER['REMOTE_ADDR']!="cli")
        {
?>    
<HTML>
    <HEAD>
        <META HTTP-EQUIV="Content-Type" CONTENT="text/html; charset=UTF-8" />
        <TITLE> CoinSpark Message Delivery Server</TITLE>
    </HEAD>
    <BODY>
        <?php
            if(isset($response['error']))
            {
        ?>
            Error: <?php echo($response['error']->message); ?>
        <?php                
            }
            else 
            {
                foreach($response['result'] as $key=>$value)
                {       
                    echo $key.": ".$value."<BR/>";
                }
            }
        ?>
    </BODY>
</HTML>


<?php
        }
    }
    

