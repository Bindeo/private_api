# RESET

/*
DROP TABLE EMAILS_TO;
DROP TABLE EMAILS_DELETED;
DROP TABLE EMAILS;
DROP TABLE FILES_DELETED;
DROP TABLE FILES;
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
*/

# CREATION

# Tables

source ../tables/TRANSLATIONS.sql;
source ../tables/FILE_TYPES.sql;
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
source ../tables/FILES.sql;
source ../tables/FILES_DELETED.sql;
source ../tables/EMAILS.sql;
source ../tables/EMAILS_DELETED.sql;
source ../tables/EMAILS_TO.sql;

# Triggers
source ../triggers/TGR_BD_USERS.sql;
source ../triggers/TGR_BD_FILES.sql;
source ../triggers/TGR_BD_EMAILS.sql;

# Translates
source ../sqls/translations.sql;
source ../sqls/account_types.sql;
source ../sqls/file_types.sql;
source ../sqls/media_types.sql;
source ../sqls/media_extensions.sql;

COMMIT;
