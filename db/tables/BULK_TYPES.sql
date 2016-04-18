CREATE TABLE BULK_TYPES
(
  CLIENT_TYPE ENUM('U','C', 'A') NOT NULL COMMENT 'U - Logged user, C - Client, A - All users',
  FK_ID_CLIENT INT NOT NULL,
  TYPE VARCHAR(32) NOT NULL,
  ELEMENTS_TYPE ENUM ('F','E') NOT NULL COMMENT 'F - File, E - Event',
  BULK_INFO VARCHAR(1000) NOT NULL,
  DEFAULT_INFO VARCHAR(4000),
  CALLBACK_TYPE ENUM ('E', 'C') NOT NULL COMMENT 'E - Email, C - Class',
  CALLBACK_VALUE VARCHAR(128),
  PRIMARY KEY (CLIENT_TYPE ASC, FK_ID_CLIENT ASC, TYPE ASC)
);