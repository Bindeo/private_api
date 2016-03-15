CREATE TABLE BLOCKCHAIN
(
  TRANSACTION VARCHAR(64) NOT NULL,
  NET ENUM('bitcoin') NOT NULL,
  CONFIRMED TINYINT NOT NULL DEFAULT 0 COMMENT '0 - No, 1 - Yes',
  FK_ID_USER INT NOT NULL,
  FK_ID_IDENTITY INT NOT NULL,
  HASH VARCHAR(64) NOT NULL,
  JSON_DATA VARCHAR(1024) NOT NULL,
  CTRL_DATE DATETIME NOT NULL,
  CTRL_IP VARCHAR(128) NOT NULL,
  TYPE ENUM('F', 'E', 'B') NOT NULL COMMENT 'F - File, E - Email, B - Bulk transaction',
  FK_ID_ELEMENT INT NOT NULL,
  STATUS_ELEMENT VARCHAR(1) NOT NULL DEFAULT 'A' COMMENT 'A - Active | D - Deleted',
  ID_GEONAMES INT,
  LATITUDE DECIMAL(8, 5),
  LONGITUDE DECIMAL(8, 5),
  PRIMARY KEY (TRANSACTION, STATUS_ELEMENT),
  INDEX IX_BLOCKCHAIN_01 (FK_ID_USER ASC, FK_ID_IDENTITY ASC)
)
PARTITION BY LIST COLUMNS(STATUS_ELEMENT)
(
  PARTITION ACTIVE_ELEMENTS VALUES IN('A'),
  PARTITION DELETED_ELEMENTS VALUES IN('D')
);