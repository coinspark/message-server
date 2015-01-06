#
# CoinSpark message delivery server
# 
# Copyright (c) 2015 Coin Sciences Ltd - coinspark.org
# 
# Distributed under the AGPLv3 software license, see the accompanying 
# file COPYING or http://www.gnu.org/licenses/agpl-3.0.txt
#


GENERAL INFORMATION
==================================================

CoinSpark messages allow bitcoin transactions to be enriched with additional 
content, such as an explanatory note, contracts, invoices, or even multimedia 
content such as images or videos. The message is transmitted from the sender 
to recipient(s) via a message delivery server, while the message’s presence is 
denoted by some message metadata added to the bitcoin transaction in an 
OP_RETURN output. This metadata contains the address of the delivery server, 
a list of output indexes for which the message is intended, and a hash of 
the full message content.

CONTENTS
==================================================

1. System requirements
2. Installing and configuring message server
    2.1 Package installation
        2.1.1 Ubuntu
        2.1.2 CentOS
    2.2 Installing message-server
    2.3 Configuring message-server
3. Testing installation
4. Changelog    

1. System requirements
==================================================

Requirements for the asset server are as follows:

 - Linux operating system such as CentOS, Ubuntu or Fedora. These instructions have been tested on Ubuntu x64 10.04, 12.04, 14.04 and CentOS x64 6.4, 6.5.

 - At least 2 GB of RAM.

 - PHP 5 running under a regular web server such as Apache.


2. Installing and configuring message server
==================================================

2.1 Package installation
==================================================

2.1.1 Ubuntu
--------------------------------------------------

# Package installation:

    sudo apt-get update
    sudo apt-get install build-essential
    sudo apt-get install mysql-server mysql apache2 php5
    sudo apt-get install libssl-dev 

    /etc/init.d/apache2 restart

# Adding coinspark user:

    sudo adduser coinspark
    sudo usermod -a -G apache coinspark
    sudo usermod -a -G coinspark apache
    sudo chmod 775 /home/coinspark
    

2.1.2 CentOS
--------------------------------------------------

    su

# Package installation:

    yum groupinstall "Development tools"
    yum install mysql-server mysql httpd php
    yum install wget

    cd /usr/src
    wget http://www.openssl.org/source/openssl-1.0.1g.tar.gz
    tar -zxf openssl-1.0.1g.tar.gz
    cd openssl-1.0.1g
    ./config --prefix=/usr --openssldir=/usr/local/openssl shared
    make
    make test
    make install
    cd /usr/src
    rm -rf openssl-1.0.1g.tar.gz
    rm -rf openssl-1.0.1g

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


2.2 Installing message-server
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



    su root

# for Ubuntu 12.04 and below:

    mv /var/www/index.html /var/www/index-original.html
    ln -s ~coinspark/message-server/public/index.php /var/www/index.php 

# for other distributions:

    mv /var/www/html/index.html /var/www/html/index-original.html
    
    	[don't worry if you get an error message here]
    
    ln -s ~coinspark/message-server/public/index.php /var/www/html/index.php 

    service httpd restart

