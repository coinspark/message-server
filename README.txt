# CoinSpark message delivery server v1.0 beta 1
# 
# Copyright (c) 2015 Coin Sciences Ltd - coinspark.org
# 
# Distributed under the AGPLv3 software license, see the accompanying 
# file COPYING or http://www.gnu.org/licenses/agpl-3.0.txt


ABOUT THE COINSPARK MESSAGE DELIVERY SERVER
==================================================

CoinSpark messages allow bitcoin transactions to be enriched with additional 
content, such as an explanatory note, contracts, invoices, or even multimedia 
content such as images or videos. The message is transmitted from the sender 
to recipient(s) via a message delivery server, while the message’s presence is 
denoted by some message metadata added to the bitcoin transaction in an 
OP_RETURN output. This metadata contains the address of the delivery server, 
a list of output indexes for which the message is intended, and a hash of 
the full message content.


CHOOSING A DOMAIN NAME AND/OR IP ADDRESS
==================================================

The URL of your message delivery server needs to be embedded inside CoinSpark
message metadata, and this leads to recommendations and restrictions. These are due
to the fact that, as of version 0.9.x, the bitcoin network allows only 40 bytes of
OP_RETURN metadata per bitcoin transaction, and the URL must be embedded within.

The recommended solution is as follows:

* Create a new short domain name, e.g. msg.example.com, for your delivery server
* Host it at the root of that domain's website or inside a /coinspark/ directory,
  e.g. http(s)://msg.example.com/ or http(s)://msg.example.com/coinspark/
* If you are hosting multiple sites on one server, ensure the CoinSpark directory is
  the default for requests made to its IPv4 address, so that it can also be accessed,
  for example, via http(s)://12.34.56.78/ or http(s)://12.34.56.78/coinspark/

This enables the address of your delivery server to take just a few bytes inside the
metadata. If the recommended solution is not possible, the following are permitted:

http(s)://msg.example.com/[directory]/
http(s)://msg.example.com/coinspark/[directory]/

The [directory] can contain the lowercase characters a-z, 0-9, - and . only.


CONTENTS
==================================================

1. System requirements
2. Installing and configuring message server
    2.1 Package installation
        2.1.1 Ubuntu
        2.1.2 CentOS
    2.2 Installing message-server
    2.3 Configuring message-server
3. Changelog    

1. System requirements
==================================================

Requirements for the asset server are as follows:

 - Linux operating system such as CentOS, Ubuntu or Fedora. These instructions have been tested on Ubuntu x64 10.04, 12.04, 14.04 and CentOS x64 6.4, 6.5.

 - At least 2 GB of RAM.

 - PHP 5 running under a regular web server such as Apache.
 
 - MySQL 5.


2. Installing and configuring message server
==================================================

2.1 Package installation
==================================================

2.1.1 Ubuntu
--------------------------------------------------

# Ensure you are running as the root user:

	su

# Package installation:
	
    apt-get update
    apt-get install build-essential
    apt-get install mysql-server mysql-client apache2 php5 php5-mysql php5-curl git-core
    apt-get install libssl-dev 

# Setting up Apache and MySQL:
	
    service apache2 restart
    service mysql restart
    /usr/bin/mysql_secure_installation

# Adding coinspark user:

    adduser coinspark
    usermod -a -G www-data coinspark
    usermod -a -G coinspark www-data
    chmod 775 /home/coinspark

    service apache2 restart
    

2.1.2 CentOS
--------------------------------------------------

# Ensure you are running as the root user:

    su

# Package installation:

    yum groupinstall "Development tools"
    yum install mysql-server mysql httpd php php-mysql 
    yum install wget

    cd /usr/src
    wget http://www.openssl.org/source/openssl-1.0.1k.tar.gz
    tar -zxf openssl-1.0.1k.tar.gz
    cd openssl-1.0.1k
    ./config --prefix=/usr --openssldir=/usr/local/openssl shared
    make
    make test
    make install
    cd ..
    rm -rf openssl-1.0.1k openssl-1.0.1k.tar.gz

# Setting up Apache and MySQL:
	
    service mysqld start
    /usr/bin/mysql_secure_installation
    chkconfig mysqld on

    service httpd start
    chkconfig httpd on

# Adding coinspark user:

    adduser coinspark
    passwd coinspark

    usermod -a -G apache coinspark
    usermod -a -G coinspark apache
    chmod 775 /home/coinspark

    service httpd restart

2.2 Installing the message server
==================================================

    su coinspark

    cd
    
    git clone https://github.com/coinspark/message-server message-server

    mysql -u root -p < message-server/include/message_db.sql

    mkdir .coinspark
    mkdir .coinspark/messages
    mkdir .coinspark/messages/log
    mkdir .coinspark/messages/tmp
    mkdir .coinspark/messages/test
    mkdir .coinspark/messages/test/log
    mkdir .coinspark/messages/test/key
    mkdir .coinspark/messages/test/tmp

    su

# for Ubuntu 12.04 and below:

    mv /var/www/index.html /var/www/index-original.html
    ln -s ~coinspark/message-server/public/index.php /var/www/index.php 

# for other distributions:

    mv /var/www/html/index.html /var/www/html/index-original.html
    
    	[don't worry if you get an error message here]
    
    ln -s ~coinspark/message-server/public/index.php /var/www/html/index.php 


2.3 Configuring the message server
==================================================

# Coinspark message delivery server configuration file can be found at 

    ~coinspark/message-server/config/coinspark_config.php

# To change the password used by PHP to access the database in MySQL, set the new
  password for user 'coinspark_user' in MySQL using SET PASSWORD, then modify the
  CONST_MYSQL_MESSAGE_DB_PASS constant in coinspark_config.php file accordingly.


2.4 Test the message server is responding
==================================================

# Open your chosen URL in your web browser. You should see:

    CoinSpark Message Delivery Server Status: OK


3. CHANGELOG
=====================================================================

v1.0 beta 1 - 13 January 2015
* First release