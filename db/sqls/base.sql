# RESET

/*
DROP TABLE FILES_DELETED;
DROP TABLE FILES;
DROP TABLE BLOCKCHAIN_INFO;
DROP TABLE BLOCKCHAIN;
DROP TABLE USERS_TYPES;
DROP TABLE USERS_IDENTITIES;
DROP TABLE USERS_VALIDATIONS;
DROP TABLE USERS_LOGINS;
DROP TABLE USERS_DELETED;
DROP TABLE USERS;
DROP TABLE ACCOUNT_TYPES;
DROP TABLE MEDIA_EXTENSIONS;
DROP TABLE MEDIA_TYPES;
DROP TABLE FILE_TYPES;
DROP TABLE TRANSLATIONS;
DROP TABLE BULK_FILES;
DROP TABLE BULK_EVENTS;
DROP TABLE BULK_TYPES;
DROP TABLE BULK_TRANSACTIONS;
DROP TABLE PROCESSES_CLIENTS;
DROP TABLE PROCESSES;
DROP TABLE PROCESSES_STATUS;
DROP TABLE OAUTH_TOKENS;
DROP TABLE OAUTH_CLIENTS;

*/

# CREATION

# Tables

source ../tables/TRANSLATIONS.sql;
source ../tables/COUNTRIES.sql;
source ../tables/OAUTH_CLIENTS.sql;
source ../tables/OAUTH_TOKENS.sql;
source ../tables/MEDIA_TYPES.sql;
source ../tables/MEDIA_EXTENSIONS.sql;
source ../tables/ACCOUNT_TYPES.sql;
source ../tables/USERS.sql;
source ../tables/USERS_DELETED.sql;
source ../tables/USERS_LOGINS.sql;
source ../tables/USERS_VALIDATIONS.sql;
source ../tables/USERS_TYPES.sql;
source ../tables/USERS_IDENTITIES.sql;
source ../tables/BLOCKCHAIN.sql;
source ../tables/BLOCKCHAIN_INFO.sql;
source ../tables/FILES.sql;
source ../tables/FILES_DELETED.sql;
source ../tables/BULK_TRANSACTIONS.sql;
source ../tables/BULK_TYPES.sql;
source ../tables/BULK_FILES.sql;
source ../tables/BULK_EVENTS.sql;
source ../tables/FILES_SIGNATURE.sql;
source ../tables/SIGNERS.sql;
source ../tables/SIGN_CODES.sql;
source ../tables/PROCESSES_STATUS.sql;
source ../tables/PROCESSES.sql;
source ../tables/PROCESSES_CLIENTS.sql;

# Triggers
source ../triggers/TGR_BD_USERS.sql;
source ../triggers/TGR_BD_FILES.sql;

# Translates
source ../sqls/translations.sql;
source ../sqls/account_types.sql;
source ../sqls/media_types.sql;
source ../sqls/media_extensions.sql;

# Countries
source ../sqls/countries.sql;

# OAuth Clients
source ../sqls/oauth_clients.sql;

# Blockchain info
source ../sqls/blockchain.sql;

# Processes status
source ../sqls/processes_status.sql;

COMMIT;