# RESET

/*
DROP TABLE BULK_FILES;
DROP TABLE BULK_TRANSACTION;
*/

# CREATION

# Tables

source ../tables/BULK_TRANSACTION.sql;
source ../tables/BULK_FILES.sql;

ALTER TABLE BLOCKCHAIN MODIFY COLUMN TYPE ENUM('F', 'E', 'B') NOT NULL COMMENT 'F - File, E - Email, B - Bulk transaction';