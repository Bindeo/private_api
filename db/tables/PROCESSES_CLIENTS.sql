CREATE TABLE PROCESSES_CLIENTS
(
  TYPE ENUM('N', 'S') NOT NULL COMMENT 'N - Notarization, S - Signature',
  ID_ELEMENT INT NOT NULL,
  CLIENT_TYPE ENUM('U','C') NOT NULL COMMENT 'U - Logged user, C - Client',
  FK_ID_CLIENT INT NOT NULL,
PRIMARY KEY (TYPE, ID_ELEMENT, CLIENT_TYPE, FK_ID_CLIENT),
INDEX IX_PROCESSES_CLIENTS_01(CLIENT_TYPE, FK_ID_CLIENT),
CONSTRAINT FK_PROCESSES_CLIENTS_01 FOREIGN KEY FK_PROCESSES_CLIENTS_01(TYPE, ID_ELEMENT) REFERENCES PROCESSES(TYPE, ID_ELEMENT) ON DELETE CASCADE
);