CREATE USER coinspark_user@localhost IDENTIFIED BY 'abcdefgh';
GRANT SELECT,INSERT,UPDATE,DELETE,CREATE,DROP,ALTER,LOCK TABLES, CREATE TEMPORARY TABLES, INDEX on *.* to coinspark_user@localhost;

CREATE DATABASE IF NOT EXISTS coinspark_messages;

USE coinspark_messages;

CREATE TABLE IF NOT EXISTS transactions(
    TxID                    VARCHAR(64) NOT NULL,
    MessageSalt             VARCHAR(64) NOT NULL,
    MessageHash             VARCHAR(64) NOT NULL,
    MessageSize             INT(11) NOT NULL,
    Sender                  VARCHAR(64) NOT NULL,
    SenderIP                VARCHAR(64) NOT NULL,
    LifeTime                INT(11) NULL,
    Created                 INT(11) NULL,
    Expired                 INT(11) NULL,
    PublicFlags             INT(11) NULL,
    CountAddresses          INT(11) NOT NULL,
    CountContentParts       INT(11) NOT NULL
) engine =  InnoDB DEFAULT CHARSET=utf8;

CREATE UNIQUE INDEX idx_txs_txid      ON transactions (TxID) USING HASH;
CREATE INDEX idx_txs_expired          ON transactions (Expired);

CREATE TABLE IF NOT EXISTS addresses(
    TxID                    VARCHAR(64) NOT NULL,
    Expired                 INT(11) NULL,
    AddressID               INT(11) NOT NULL,
    Address                 VARCHAR(64) NOT NULL,
    RetrievalCount          INT(11) NOT NULL
) engine =  InnoDB DEFAULT CHARSET=utf8;

CREATE UNIQUE INDEX idx_ads_address      ON addresses (TxID,Address) USING HASH;
CREATE INDEX idx_ads_txid      ON addresses (TxID) USING HASH;
CREATE INDEX idx_ads_expired   ON addresses (Expired);

CREATE TABLE IF NOT EXISTS details(
    TxID                    VARCHAR(64) NOT NULL,
    Expired                 INT(11) NULL,
    ContentPartID           INT(11) NOT NULL,
    MimeType                VARCHAR(64) NULL,
    FileName                VARCHAR(256) NULL,
    ContentLength           INT(11) NOT NULL
) engine =  InnoDB DEFAULT CHARSET=utf8;

CREATE UNIQUE INDEX idx_dts_contentpartid      ON details (TxID,ContentPartID) USING HASH;
CREATE INDEX idx_dts_txid      ON details (TxID) USING HASH;
CREATE INDEX idx_dts_expired   ON details (Expired);

CREATE TABLE IF NOT EXISTS contents(
    TxID                    VARCHAR(64) NOT NULL,
    Expired                 INT(11) NULL,
    ContentPartID           INT(11) NOT NULL,
    Content                 BLOB NULL
) engine =  InnoDB DEFAULT CHARSET=utf8;

CREATE UNIQUE INDEX idx_cns_contentpartid      ON contents (TxID,ContentPartID) USING HASH;
CREATE INDEX idx_cns_expired   ON contents (Expired);

CREATE TABLE IF NOT EXISTS usagerate(
    Address                 VARCHAR(64) NOT NULL,
    Created                 INT(11) NULL,
    UsageType               INT(11) NOT NULL,
    UsageCount              INT(11) NULL
) engine =  MEMORY DEFAULT CHARSET=utf8;

CREATE UNIQUE INDEX idx_usr_address      ON usagerate (Address,UsageType) USING HASH;
CREATE INDEX idx_usr_created   ON usagerate (Created) USING BTREE;

CREATE TABLE IF NOT EXISTS block(
    Address                 VARCHAR(64) NOT NULL,
    Created                 INT(11) NULL,
    UsageType               INT(11) NOT NULL,
    Description             VARCHAR(256) NOT NULL
) engine =  InnoDB DEFAULT CHARSET=utf8;

CREATE UNIQUE INDEX idx_blk_address      ON block (Address,UsageType) USING HASH;
CREATE INDEX idx_blk_created   ON block (Created);


CREATE TABLE IF NOT EXISTS nonce(
    Nonce                   VARCHAR(64) NOT NULL,
    Created                 INT(11) NULL,
    Address                 VARCHAR(64) NOT NULL,
    IP                      VARCHAR(64) NOT NULL
) engine =  MEMORY DEFAULT CHARSET=utf8;

CREATE UNIQUE INDEX idx_non_nonce      ON nonce (Nonce) USING HASH;
CREATE INDEX idx_non_created   ON nonce (Created) USING BTREE;

