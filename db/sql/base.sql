# RESET

/*
DROP TABLE EMAILS_TO;
DROP TABLE EMAILS;
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

source /var/www/html/project/api/db/tables/TRANSLATIONS.sql;
source /var/www/html/project/api/db/tables/FILE_TYPES.sql;
source /var/www/html/project/api/db/tables/MEDIA_TYPES.sql;
source /var/www/html/project/api/db/tables/MEDIA_EXTENSIONS.sql;
source /var/www/html/project/api/db/tables/ACCOUNT_TYPES.sql;
source /var/www/html/project/api/db/tables/USERS.sql;
source /var/www/html/project/api/db/tables/USERS_DELETED.sql;
source /var/www/html/project/api/db/tables/USERS_LOGINS.sql;
source /var/www/html/project/api/db/tables/USERS_VALIDATIONS.sql;
source /var/www/html/project/api/db/tables/USERS_TYPES.sql;
source /var/www/html/project/api/db/tables/USERS_IDENTITIES.sql;
source /var/www/html/project/api/db/tables/BLOCKCHAIN.sql;
source /var/www/html/project/api/db/tables/FILES.sql;
source /var/www/html/project/api/db/tables/EMAILS.sql;
source /var/www/html/project/api/db/tables/EMAILS_TO.sql;

# Triggers
source /var/www/html/project/api/db/triggers/TGR_BD_USERS.sql;

# Translates
source /var/www/html/project/api/db/sql/translations.sql;
source /var/www/html/project/api/db/sql/account_types.sql;
source /var/www/html/project/api/db/sql/file_types.sql;
source /var/www/html/project/api/db/sql/media_types.sql;
source /var/www/html/project/api/db/sql/media_extensions.sql;

COMMIT;
