# Blockchain info per client
INSERT INTO BLOCKCHAIN_INFO(FK_ID_CLIENT, ACCOUNT, NUMBER_ADDRESSES, TOTAL_UNSPENTS) VALUES(4, 'FACTUM_IT', 3, 30);
INSERT INTO BLOCKCHAIN_INFO(FK_ID_CLIENT, ACCOUNT, NUMBER_ADDRESSES, TOTAL_UNSPENTS) VALUES(5, 'ISDI', 3, 30);

# Bulk Types
INSERT INTO BULK_TYPES(CLIENT_TYPE, FK_ID_CLIENT, TYPE, ELEMENTS_TYPE, BULK_INFO, CALLBACK_TYPE, CALLBACK_VALUE)
VALUES  ('C', 4, 'Audit Book', 'E', '{"title":"document","fields":["hash","size"]}', 'E', 'mestrada@bindeo.com');
INSERT INTO BULK_TYPES(CLIENT_TYPE, FK_ID_CLIENT, TYPE, ELEMENTS_TYPE, BULK_INFO, DEFAULT_INFO, CALLBACK_TYPE, CALLBACK_VALUE)
VALUES  ('C', 5, 'Smart Certificates', 'F', '{"title":"owner","fields":["name","full_name"]}', '{"name":"ISDI","full_name":"Instituto Superior para el Desarrollo de Internet"}', 'E', 'mestrada@bindeo.com');