CREATE TABLE PROCESSES
(
  TYPE ENUM('N', 'S') NOT NULL COMMENT 'N - Notarization, S - Signature',
  ID_ELEMENT INT NOT NULL,
  CLIENT_TYPE ENUM('U','C') NOT NULL COMMENT 'U - Logged user, C - Client',
  FK_ID_CLIENT INT NOT NULL,
  FK_ID_STATUS TINYINT NOT NULL,
  NAME VARCHAR(128) NOT NULL,
  CTRL_DATE DATETIME NOT NULL,
  ADDITIONAL_DATA VARCHAR(4000),
PRIMARY KEY (TYPE, ID_ELEMENT),
INDEX IX_PROCESSES_01 (FK_ID_CLIENT ASC, FK_ID_STATUS ASC),
FULLTEXT KEY IX_PROCESSES_02 (NAME)
);