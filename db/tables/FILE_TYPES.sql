CREATE TABLE FILE_TYPES
(
  ID_TYPE TINYINT NOT NULL PRIMARY KEY,
  NAME VARCHAR(128) NOT NULL,
  FK_ID_TRANSLATION INT NOT NULL
);