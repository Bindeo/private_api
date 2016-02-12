# RESET

/*
DROP TABLE FILES;
DROP TABLE BLOCKCHAIN;
DROP TABLE CLIENTS_TYPES;
DROP TABLE CLIENTS_VALIDATIONS;
DROP TABLE CLIENTS_LOGINS;
DROP TABLE CLIENTS_DELETED;
DROP TABLE CLIENTS;
DROP TABLE ACCOUNT_TYPES;
*/

# CREATION

# Tables
source /var/www/html/project/db/tables/ACCOUNT_TYPES.sql;
source /var/www/html/project/db/tables/CLIENTS.sql;
source /var/www/html/project/db/tables/CLIENTS_DELETED.sql;
source /var/www/html/project/db/tables/CLIENTS_LOGINS.sql;
source /var/www/html/project/db/tables/CLIENTS_VALIDATIONS.sql;
source /var/www/html/project/db/tables/CLIENTS_TYPES.sql;
source /var/www/html/project/db/tables/BLOCKCHAIN.sql;
source /var/www/html/project/db/tables/FILES.sql;

# Triggers
source /var/www/html/project/db/triggers/TGR_BD_CLIENTS.sql;


INSERT INTO ACCOUNT_TYPES(ID_TYPE, TYPE, COST, MAX_STORAGE, MAX_STAMPS_MONTH)
VALUES (1, 'Admin', 0, 0, 0);
INSERT INTO ACCOUNT_TYPES(ID_TYPE, TYPE, COST, MAX_STORAGE, MAX_STAMPS_MONTH)
VALUES (2, 'Free user', 0, 524288, 10);
INSERT INTO ACCOUNT_TYPES(ID_TYPE, TYPE, COST, MAX_STORAGE, MAX_STAMPS_MONTH)
VALUES (3, 'Premium user', 10, 52428800, 1000);
COMMIT;
