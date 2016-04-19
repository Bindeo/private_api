CREATE TABLE BULK_TRANSACTIONS
(
  ID_BULK_TRANSACTION INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  EXTERNAL_ID VARCHAR(64) NOT NULL,
  TYPE VARCHAR(32) NOT NULL,
  ELEMENTS_TYPE ENUM ('F','E') NOT NULL COMMENT 'F - File, E - Event',
  CLIENT_TYPE ENUM('U','C') NOT NULL COMMENT 'U - Logged user, C - Client',
  FK_ID_CLIENT INT NOT NULL,
  CLOSED TINYINT NOT NULL DEFAULT 0 COMMENT '0 - No, 1 - Yes',
  NUM_ITEMS INT NOT NULL,
  STRUCTURE TEXT NOT NULL,
  HASH VARCHAR(64) NOT NULL,
  CTRL_DATE DATETIME NOT NULL,
  CTRL_IP VARCHAR(128) NOT NULL,
  STATUS VARCHAR(1) NOT NULL DEFAULT 'A' COMMENT 'A - Active | T - Trash',
  CTRL_DATE_CLOSED DATETIME,
  CTRL_IP_CLOSED VARCHAR(128),
  CTRL_DATE_DEL DATETIME,
  CTRL_IP_DEL VARCHAR(128),
  ACCOUNT VARCHAR(64) NOT NULL,
  TRANSACTION VARCHAR(64),
  CONFIRMED TINYINT NOT NULL DEFAULT 0 COMMENT '0 - No, 1 - Yes',
  ID_GEONAMES INT,
  LATITUDE DECIMAL(8, 5),
  LONGITUDE DECIMAL(8, 5),
UNIQUE INDEX IX_BULK_TRANSACTIONS_01 (CLIENT_TYPE ASC, FK_ID_CLIENT ASC, EXTERNAL_ID ASC),
INDEX IX_BULK_TRANSACTIONS_02 (HASH ASC)
);