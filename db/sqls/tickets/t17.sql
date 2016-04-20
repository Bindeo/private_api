ALTER TABLE OAUTH_CLIENTS ADD (STORAGE_LEFT INT, STAMPS_LEFT INT);

DROP TABLE FILES_DELETED;
DROP TABLE FILES;
DROP TABLE FILE_TYPES;

DELETE FROM TRANSLATIONS WHERE TYPE = 'FT';
ALTER TABLE TRANSLATIONS CHANGE TYPE TYPE ENUM ('AT', 'MT') NOT NULL COMMENT 'AT - ACCOUNT_TYPES, MT - MEDIA_TYPES';
UPDATE TRANSLATIONS SET ID_TRANSLATION = ID_TRANSLATION - 4 WHERE ID_TRANSLATION > 3;

source ../tables/FILES.sql;
source ../tables/FILES_DELETED.sql;
source ../triggers/TGR_BD_FILES.sql;

source ../tables/SIGNERS.sql;
source ../tables/SIGN_CODES.sql;

ALTER TABLE USERS_IDENTITIES ADD ACCOUNT VARCHAR(64) NOT NULL AFTER VALUE;
UPDATE USERS_IDENTITIES SET ACCOUNT = SHA2(CONCAT(NAME, VALUE), 256);

ALTER TABLE BULK_TYPES CHANGE CLIENT_TYPE CLIENT_TYPE ENUM('U','C', 'A') NOT NULL COMMENT 'U - Logged user, C - Client, A - All users';
INSERT INTO BULK_TYPES(CLIENT_TYPE, FK_ID_CLIENT, TYPE, ELEMENTS_TYPE, BULK_INFO, CALLBACK_TYPE, CALLBACK_VALUE)
VALUES ('A', 0, 'Sign Document', 'E', '{"title":"document","fields":["hash","size"]}', 'C', 'SignDocument');


ALTER TABLE BULK_TRANSACTIONS ADD ACCOUNT VARCHAR(64) NOT NULL AFTER CTRL_IP_DEL;