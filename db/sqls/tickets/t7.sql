DROP TABLE BULK_FILES;
DROP TABLE BULK_TRANSACTION;
DROP TABLE BLOCKCHAIN;

source ../tables/BLOCKCHAIN.sql;
source ../tables/BULK_TRANSACTIONS.sql;
source ../tables/BULK_FILES.sql;
source ../tables/BULK_EVENTS.sql;
source ../tables/BULK_TYPES.sql;

INSERT INTO BULK_TYPES(CLIENT_TYPE, FK_ID_CLIENT, TYPE, ELEMENTS_TYPE, BULK_INFO)
VALUES  ('C', 3, 'Audit Book', 'E', '{"title":"document","fields":["hash","size"]}');

INSERT INTO BULK_TYPES(CLIENT_TYPE, FK_ID_CLIENT, TYPE, ELEMENTS_TYPE, BULK_INFO, DEFAULT_INFO)
VALUES  ('C', 4, 'Smart Certificates', 'F', '{"title":"owner","fields":["name","full_name"]}', '{"name":"ISDI","full_name":"Instituto Superior para el Desarrollo de Internet"}');