CREATE TABLE BULK_EVENTS
(
  ID_BULK_EVENT INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  FK_ID_BULK INT NOT NULL,
  CLIENT_TYPE ENUM('U','C'),
  FK_ID_CLIENT INT,
  NAME VARCHAR(128) NOT NULL,
  TIMESTAMP DATETIME NOT NULL,
  DATA VARCHAR(4000) NOT NULL,
  CTRL_DATE DATETIME NOT NULL,
  CTRL_IP VARCHAR(128) NOT NULL,
INDEX IX_BULK_EVENTS_01 (FK_ID_BULK ASC),
INDEX IX_BULK_EVENTS_02 (FK_ID_CLIENT ASC),
CONSTRAINT FK_BULK_EVENTS_01 FOREIGN KEY FK_BULK_EVENTS_01(FK_ID_BULK) REFERENCES BULK_TRANSACTIONS(ID_BULK_TRANSACTION) ON DELETE CASCADE
);