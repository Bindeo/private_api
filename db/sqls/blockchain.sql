# Blockchain info per client
INSERT INTO BLOCKCHAIN_INFO(FK_ID_CLIENT, ACCOUNT, NUMBER_ADDRESSES, TOTAL_UNSPENTS) VALUES(4, 'FACTUM_IT', 3, 30);
INSERT INTO BLOCKCHAIN_INFO(FK_ID_CLIENT, ACCOUNT, NUMBER_ADDRESSES, TOTAL_UNSPENTS) VALUES(5, 'ISDI', 3, 30);

# Bulk Types
INSERT INTO BULK_TYPES(CLIENT_TYPE, FK_ID_CLIENT, TYPE, ASSET, ELEMENTS_TYPE, BULK_INFO, CALLBACK_TYPE, CALLBACK_VALUE)
VALUES ('C', 4, 'Audit Book', 'N', 'E', '{"title":"document","fields":["hash","size"]}', 'E', 'mestrada@bindeo.com');
INSERT INTO BULK_TYPES(CLIENT_TYPE, FK_ID_CLIENT, TYPE, ASSET, ELEMENTS_TYPE, BULK_INFO, DEFAULT_INFO, CALLBACK_TYPE, CALLBACK_VALUE)
VALUES ('C', 5, 'Smart Certificates', 'N', 'F', '{"title":"owner","fields":["name","full_name"]}', '{"name":"ISDI","full_name":"Instituto Superior para el Desarrollo de Internet"}', 'E', 'mestrada@bindeo.com');
INSERT INTO BULK_TYPES(CLIENT_TYPE, FK_ID_CLIENT, TYPE, ASSET, ELEMENTS_TYPE, ALLOW_ANONYMOUS, BULK_INFO, DEFAULT_INFO, CALLBACK_TYPE, CALLBACK_VALUE)
VALUES ('A', 0, 'Sign Document', 'S', 'E', 1, '{"title":"document","fields":["hash","size","transaction"]}', '{"transaction":"PENDING"}', 'C', 'SignDocument');