CREATE TABLE USERS_TYPES
(
  FK_ID_USER INT NOT NULL,
  FK_ID_TYPE TINYINT NOT NULL,
  DATE_START DATETIME NOT NULL,
  NEXT_PAYMENT DATE,
  LAST_RESET DATE,
  DATE_END DATETIME,
  INDEX IX_USERS_TYPES_01 (FK_ID_USER),
  INDEX IX_USERS_TYPES_02(FK_ID_TYPE, NEXT_PAYMENT),
  CONSTRAINT FK_USERS_TYPES_01 FOREIGN KEY FK_USERS_TYPES_01(FK_ID_TYPE) REFERENCES ACCOUNT_TYPES(ID_TYPE) ON DELETE RESTRICT
);