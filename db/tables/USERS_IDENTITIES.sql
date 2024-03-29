CREATE TABLE USERS_IDENTITIES
(
  ID_IDENTITY INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
  FK_ID_USER INT NOT NULL,
  MAIN TINYINT(1) NOT NULL DEFAULT 0,
  TYPE ENUM('E') NOT NULL COMMENT 'E - Email',
  NAME VARCHAR(128) NOT NULL,
  VALUE VARCHAR(128) NOT NULL,
  DOCUMENT VARCHAR(12),
  ACCOUNT VARCHAR(64) NOT NULL,
  CONFIRMED TINYINT(1) NOT NULL DEFAULT 0,
  STATUS ENUM('A', 'D') NOT NULL DEFAULT 'A' COMMENT 'A - Active, D - Disabled',
  CTRL_IP VARCHAR(128) NOT NULL,
  CTRL_DATE DATE NOT NULL,
  CTRL_IP_MOD VARCHAR(128),
  CTRL_DATE_MOD DATETIME,
  INDEX IX_USERS_IDENTITIES_01(FK_ID_USER)
);