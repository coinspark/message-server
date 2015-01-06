<?php

    require_once '../config/coinspark_config.php'; 
    
    function check_file($file_name)
    {
        if(!is_file($file_name))
        {
            touch($file_name);
            chmod($file_name, 0664);
            if(!is_file($file_name))
            {
                return false;
            }
        }
        return true;
    }
    
    define("log_items", 3);                                                     // Number of different objects wich should be logged and transported - logs, articles, results, operations, billing rows, etc.
    define("log_item_log",       0);                                            // Log records
    define("log_item_error",     1);                                            // Errors (escalated log records)
    define("log_item_sql",       2);                                            // Database transactions which should be processed asynchronously
    define("log_item_detail",    3);                                            // Detailed log records
    
/*
 * --- Log levels and layers
 */
    
    define("log_level_undefined",  0x00000000);
    
    define("log_level_fatal",      0x00000001);                                 // Fatal error, cannot continue, usually preceded by error or warning
    define("log_level_error",      0x00000002);                                 // Error, bug or corrupted data
    define("log_level_warning",    0x00000004);                                 // Warning, abnormal behaviour, can continue
    define("log_level_escape",     0x00000008);                                 // Warning, not optimal performance, can continue
    
    define("log_level_reject",     0x00000010);                                 // Service was rejected
    define("log_level_filter",     0x00000020);                                 // Errors in input data
    
    define("log_level_message",    0x00000100);                                 // Major event
    define("log_level_trace",      0x00000200);                                 // Minor event
    
    define("log_level_debug",      0x00008000);                                 // Debug - temporary record

    define("log_level_escalate",   0x00000007);                                 // If level ANDs with this number error record is generated and log buffer is flushed immediately
    define("log_level_mask",       0x0000FFFF);               
    
    function log_string($level,$code,$message,$dir=CONST_LOG_DIR)
    {
        global $env;
        
        switch($level)
        {
            case log_level_fatal:              $output_level='FTL!';break;
            case log_level_error:              $output_level='ERR!';break;
            case log_level_warning:            $output_level='WRN!';break;
            case log_level_escape:             $output_level='ESC ';break;
            case log_level_reject:             $output_level='RJC ';break;
            case log_level_filter:             $output_level='FLT ';break;
            case log_level_message:            $output_level='M   ';break;
            case log_level_trace:              $output_level='T   ';break;
            case log_level_debug:              $output_level='DBG!';break;
            default:                           $output_level='    ';break; 
        }
        switch($level)
        {
            case log_level_trace:              return;
        }        
        
        $time=microtime(true);
        $output_row=sprintf("%s-%04X",CONST_SERVER_NAME,  getmypid())."\t";
        $output_row.=gmdate('Y-m-d H:i:s',$time).'.'.sprintf("%03d",floor(($time*1000)%1000))."\t".$output_level."\t".$code."\t".$message;
        $file_name=$dir.'/'.gmdate('Ymd',$time).'.log';
        check_file($file_name);
        $fhan=fopen($file_name,'a');
        flock($fhan,LOCK_EX);
        fwrite($fhan,$output_row."\n");
        flock($fhan,LOCK_UN);
        fclose($fhan);
    }
    
    function log_debug($message,$code='')
    {
        log_string(log_level_debug, $code, $message);
    }
    
    function log_debug_array($arr,$code='')
    {
        log_debug('C: '.count($arr),$code);
        foreach($arr as $k=>$v)
        {
            log_debug('V: '.$k.'=>'.$v,$code);
        }
    }
    
