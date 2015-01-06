<?php

    define('CONST_SERVER_NAME', 'CSPK');                                        
    define('CONST_LOG_DIR', '/home/coinspark/.coinspark/messages/log');              // log directory
    define('CONST_TMP_DIR', '/home/coinspark/.coinspark/messages/tmp');              // temporary file directory
    
    define('CONST_MYSQL_MESSAGE_DB_HOST', '127.0.0.1');                         // database host
    define('CONST_MYSQL_MESSAGE_DB_DBNAME', 'coinspark_messages');              // database name
    define('CONST_MYSQL_MESSAGE_DB_USER', 'coinspark_user');                    // user name
    define('CONST_MYSQL_MESSAGE_DB_PASS', 'abcdefgh');                          // user password
    
    
    define('COINSPARK_CREATE_DEFAULT_NETWORK', 'main');                         // default network if 'network' parameter is not specified
    define('COINSPARK_CREATE_NETWORK_ALLOW', 'main,testnet');                   // comma-delimited list of allowed networks
    
    define('COINSPARK_CREATE_SENDER_UNLIMITED', "");                            // comma-delimited list of sender addresses allowed without any limitations (if not in _BLOCKED). 
    define('COINSPARK_CREATE_SENDER_OTHER_ALLOWED', true);                      // if true - addresses not listed in _UNLIMITED are allowed
    define('COINSPARK_CREATE_SENDER_OTHER_DAILY_MAX', 12000);                     // for other senders, maximum messages allowed per day
    define('COINSPARK_CREATE_SENDER_BLOCKED', "");                              // comma-delimited list of sender addresses that are never accepted
    
    define('COINSPARK_CREATE_IP_UNLIMITED', "");                                // comma-delimited list of sender ips allowed without any limitations (if not in _BLOCKED). 
    define('COINSPARK_CREATE_IP_OTHER_ALLOWED', true);                          // if true - ips not listed in _UNLIMITED are allowed
    define('COINSPARK_CREATE_IP_OTHER_DAILY_MAX', 60000);                        // for other ips, maximum messages allowed per day
    define('COINSPARK_CREATE_IP_BLOCKED', "");                                  // comma-delimited list of sender ips that are never accepted
    
    define('COINSPARK_CREATE_ALLOW_PUBLIC', true);                              // allow public messages
    define('COINSPARK_CREATE_ALLOW_PRIVATE', true);                             // allow non-public messages

    define('COINSPARK_CREATE_RECIPIENTS_MAX', 16);                              // maximum recipients per message

    define('COINSPARK_CREATE_KEEPSECONDS_MAX', 86400);                          // maximum number of seconds message can be stored

    define('COINSPARK_CREATE_SEED_MIN_BYTES', 8);                               // minimal size of the seed string (binary)
    define('COINSPARK_CREATE_SEED_MAX_BYTES',48);                               // maximal size of the seed string (binary)

    define('COINSPARK_CREATE_MAX_PARTS', 16);                                   // maximum number of content parts allowed
    define('COINSPARK_CREATE_MIMETYPE_MAX_BYTES',64);                           // maximal size of the mimetype
    define('COINSPARK_CREATE_MIMETYPE_BLOCKED', '');                            // comma-delimited list of disallowed MIME types
    define('COINSPARK_CREATE_MIMETYPE_ALLOWED', '');                            // if not empty, comma-delimited while list of mime types, all other mime types will be blocked
    define('COINSPARK_CREATE_FILENAME_MAX_BYTES',128);                          // maximal size of the file name
    define('COINSPARK_CREATE_FILENAME_EXTENSION_BLOCK', '.exe,.com');           // comma-delimited list of disallowed filename extensions (not case sensitive)
    define('COINSPARK_CREATE_MAX_PART_BYTES', 4096);                            // maximum number of bytes in one part,16777216
    define('COINSPARK_CREATE_MAX_TOTAL_BYTES', 65535);                          // maximum total number of bytes allowed    ,16777216
    
    define('COINSPARK_RETRIEVE_RECIPIENT_UNLIMITED', "");                       // comma-delimited list of recipient addresses allowed without any limitations (if not in _BLOCKED). 
    define('COINSPARK_RETRIEVE_RECIPIENT_OTHER_ALLOWED', true);                 // if true - addresses not listed in _UNLIMITED are allowed
    define('COINSPARK_RETRIEVE_RECIPIENT_OTHER_DAILY_MAX', 12000);                // for other recipients, maximum messages allowed per day
    define('COINSPARK_RETRIEVE_RECIPIENT_BLOCKED', "");                         // comma-delimited list of recipient addresses that are never accepted
    
    define('COINSPARK_RETRIEVE_IP_UNLIMITED', "");                              // comma-delimited list of recipient ips allowed without any limitations (if not in _BLOCKED). 
    define('COINSPARK_RETRIEVE_IP_OTHER_ALLOWED', true);                        // if true - ips not listed in _UNLIMITED are allowed
    define('COINSPARK_RETRIEVE_IP_OTHER_DAILY_MAX', 60000);                       // for other ips, maximum messages allowed per day
    define('COINSPARK_RETRIEVE_IP_BLOCKED', "");                                // comma-delimited list of recipient ips that are never accepted
    

    define("COINSPARK_ERR_NOERROR",                                         0);
    
    define("COINSPARK_ERR_PARSE_ERROR",                                -32700);
    define("COINSPARK_ERR_INVALID_REQUEST",                            -32600);
    define("COINSPARK_ERR_METHOD_NOT_FOUND",                           -32601);
    define("COINSPARK_ERR_INVALID_PARAMS",                             -32602);
    define("COINSPARK_ERR_INTERNAL_ERROR",                             -32603);
    
    define("COINSPARK_ERR_ASSET_NOT_FOUND",                            -10000);
    define("COINSPARK_ERR_TXOUT_NOT_FOUND",                            -10001);
    
    define("COINSPARK_ERR_SENDER_NOT_ACCEPTED",                        -11000);
    define("COINSPARK_ERR_SENDER_IS_SUSPENDED",                        -11001);
    define("COINSPARK_ERR_SENDER_NETWORK_NOT_ACCEPTABLE",              -11002);
    define("COINSPARK_ERR_SENDER_IP_NOT_ACCEPTED",                     -11003);
    define("COINSPARK_ERR_SENDER_IP_IS_SUSPENDED",                     -11004);
    
    define("COINSPARK_ERR_NO_PUBLIC_MESSAGES",                         -11010);
    define("COINSPARK_ERR_ONLY_PUBLIC_MESSAGES",                       -11011);
    
    define("COINSPARK_ERR_TOO_MANY_RECIPIENTS",                        -11020);
    define("COINSPARK_ERR_RECIPIENT_NOT_ACCEPTED_ON_CREATE",           -11021);
    define("COINSPARK_ERR_RECIPIENT_IS_SUSPENDED_ON_CREATE",           -11022);
    define("COINSPARK_ERR_RECIPIENT_IP_NOT_ACCEPTED_ON_CREATE",        -11023);
    define("COINSPARK_ERR_RECIPIENT_IP_IS_SUSPENDED_ON_CREATE",        -11024);
    
    define("COINSPARK_ERR_DURATION_NOT_ACCEPTABLE",                    -11030);
    
    define("COINSPARK_ERR_SEED_NOT_ACCEPTABLE",                        -11040);
    
    define("COINSPARK_ERR_TOO_MANY_MESSAGE_PARTS",                     -11050);
    define("COINSPARK_ERR_TOTAL_MESSAGE_TOO_LARGE",                    -11051);
    define("COINSPARK_ERR_MIME_TYPE_NOT_ACCEPTABLE",                   -11052);
    define("COINSPARK_ERR_FILE_NAME_NOT_ACCEPTABLE",                   -11053);
    define("COINSPARK_ERR_CONTENT_TOO_LARGE",                          -11054);
    
    define("COINSPARK_ERR_TXID_INVALID",                               -11080);
    
    define("COINSPARK_ERR_TX_MESSAGE_UNKNOWN",                         -12000);
    define("COINSPARK_ERR_TX_MESSAGE_PENDING",                         -12001);
    define("COINSPARK_ERR_TX_MESSAGE_EXPIRED",                         -12002);
    
    define("COINSPARK_ERR_RECIPIENT_NOT_ACCEPTED",                     -12010);
    define("COINSPARK_ERR_RECIPIENT_IS_SUSPENDED",                     -12011);
    define("COINSPARK_ERR_RECIPIENT_NETWORK_NOT_ACCEPTABLE",           -12012);
    define("COINSPARK_ERR_RECIPIENT_IP_NOT_ACCEPTED",                  -12013);
    define("COINSPARK_ERR_RECIPIENT_IP_IS_SUSPENDED",                  -12014);
    
    define("COINSPARK_ERR_NONCE_NOT_FOUND",                            -13000);

    define("COINSPARK_ERR_SIGNATURE_INCORRECT",                        -13010);
    
    require_once "../include/filter.php";
    
    class CoinSparkMessageFilter extends CoinSparkMessageFilterDefault
    {
    }