CREATE TABLE FILES_DELETED
(
  ID_FILE INT NOT NULL,
  FK_ID_USER INT NOT NULL,
  FK_ID_TYPE INT NOT NULL,
  FK_ID_MEDIA INT NOT NULL,
  NAME VARCHAR(128) NOT NULL,
  FILE_NAME VARCHAR(128) NOT NULL,
  FILE_ORIG_NAME VARCHAR(128) NOT NULL,
  HASH VARCHAR(64) NOT NULL,
  SIZE INT NOT NULL,
  CTRL_DATE DATETIME NOT NULL,
  CTRL_IP VARCHAR(128) NOT NULL,
  STATUS VARCHAR(1) NOT NULL DEFAULT 'D' COMMENT 'D - Deleted',
  CTRL_DATE_DEL DATETIME,
  CTRL_IP_DEL VARCHAR(128),
  TAG VARCHAR(256),
  DESCRIPTION VARCHAR(20000),
  TRANSACTION VARCHAR(64),
  CONFIRMED TINYINT NOT NULL COMMENT '0 - No, 1 - Yes',
  ID_GEONAMES INT,
  LATITUDE DECIMAL(8, 5),
  LONGITUDE DECIMAL(8, 5),
INDEX IX_FILES_DELETED_01 (FK_ID_USER ASC),
FULLTEXT KEY IX_FILES_DELETED_02 (NAME)
);