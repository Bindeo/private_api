CREATE TABLE ACCOUNT_TYPES
(
  ID_TYPE TINYINT NOT NULL PRIMARY KEY,
  TYPE VARCHAR(32) NOT NULL,
  COST INT NOT NULL,
  MAX_STORAGE INT NOT NULL,
  MAX_STAMPS_MONTH INT NOT NULL
);