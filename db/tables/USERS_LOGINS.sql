CREATE TABLE USERS_LOGINS
(
  FK_ID_USER INT NOT NULL,
  EMAIL VARCHAR(128) NOT NULL,
  CTRL_DATE DATETIME NOT NULL,
  SOURCE ENUM('FRONT', 'API') NOT NULL,
  CTRL_IP VARCHAR(128) NOT NULL,
  ID_GEONAMES INT,
  LATITUDE DECIMAL(8, 5),
  LONGITUDE DECIMAL(8, 5)
);