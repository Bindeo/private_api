CREATE TABLE BULK_TRANSACTION
(
  ID_BULK_TRANSACTION INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  FK_ID_USER INT NOT NULL,
  NUM_FILES INT NOT NULL,
  STRUCTURE TEXT NOT NULL,
  HASH VARCHAR(64) NOT NULL,
  CTRL_DATE DATETIME NOT NULL,
  CTRL_IP VARCHAR(128) NOT NULL,
  STATUS VARCHAR(1) NOT NULL DEFAULT 'A' COMMENT 'A - Active | T - Trash',
  CTRL_DATE_DEL DATETIME,
  CTRL_IP_DEL VARCHAR(128),
  TRANSACTION VARCHAR(64),
  CONFIRMED TINYINT NOT NULL DEFAULT 0 COMMENT '0 - No, 1 - Yes',
  ID_GEONAMES INT,
  LATITUDE DECIMAL(8, 5),
  LONGITUDE DECIMAL(8, 5),
INDEX IX_BULK_TRANSACTION_01 (FK_ID_USER ASC, HASH ASC)
);