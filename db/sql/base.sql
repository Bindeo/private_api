# RESET

/*
DROP TABLE FILES;
DROP TABLE BLOCKCHAIN;
DROP TABLE USERS_TYPES;
DROP TABLE USERS_VALIDATIONS;
DROP TABLE USERS_LOGINS;
DROP TABLE USERS_DELETED;
DROP TABLE USERS;
DROP TABLE ACCOUNT_TYPES;
*/

# CREATION

# Tables

source /var/www/html/project/api/db/tables/ACCOUNT_TYPES.sql;
source /var/www/html/project/api/db/tables/USERS.sql;
source /var/www/html/project/api/db/tables/USERS_DELETED.sql;
source /var/www/html/project/api/db/tables/USERS_LOGINS.sql;
source /var/www/html/project/api/db/tables/USERS_VALIDATIONS.sql;
source /var/www/html/project/api/db/tables/USERS_TYPES.sql;
source /var/www/html/project/api/db/tables/BLOCKCHAIN.sql;
source /var/www/html/project/api/db/tables/FILES.sql;

# Triggers
source /var/www/html/project/api/db/triggers/TGR_BD_USERS.sql;


INSERT INTO ACCOUNT_TYPES(ID_TYPE, TYPE, COST, MAX_STORAGE, MAX_STAMPS_MONTH)
VALUES (1, 'Admin', 0, 0, 0);
INSERT INTO ACCOUNT_TYPES(ID_TYPE, TYPE, COST, MAX_STORAGE, MAX_STAMPS_MONTH)
VALUES (2, 'Free user', 0, 524288, 10);
INSERT INTO ACCOUNT_TYPES(ID_TYPE, TYPE, COST, MAX_STORAGE, MAX_STAMPS_MONTH)
VALUES (3, 'Premium user', 10, 52428800, 1000);
COMMIT;
