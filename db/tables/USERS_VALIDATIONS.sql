CREATE TABLE USERS_VALIDATIONS
(
  TOKEN VARCHAR(128) NOT NULL PRIMARY KEY,
  TYPE ENUM('E', 'V', 'P') NOT NULL COMMENT 'E: Change email, V: Validate email, P: Recover password',
  FK_ID_USER INT NOT NULL,
  EMAIL VARCHAR(128) NOT NULL,
  CTRL_DATE DATETIME NOT NULL,
  CTRL_IP VARCHAR(128) NOT NULL,
  CONFIRMED TINYINT(1) NOT NULL DEFAULT 0,
  NEW_IDENTITY INT,
  OLD_IDENTITY INT,
  INDEX IX_USERS_VALIDATIONS_01 (FK_ID_USER ASC)
);