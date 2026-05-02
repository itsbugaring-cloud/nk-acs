START TRANSACTION;

-- OLT Batujaya
SET @olt_id := (SELECT id FROM map_items WHERE item_type = 'olt' AND name = 'OLT Batujaya' LIMIT 1);
INSERT INTO map_items (item_type, parent_id, name, latitude, longitude, genieacs_device_id, properties, status)
SELECT 'olt', NULL, 'OLT Batujaya', -6.914744, 107.60981, NULL, '{"site":"Batujaya","model":"C-Data FD1616S-B2","protocol":"telnet","olt_link":"192.168.51.51"}', 'unknown'
WHERE @olt_id IS NULL;
SET @olt_id := COALESCE(@olt_id, LAST_INSERT_ID());
UPDATE map_items
SET latitude = -6.914744, longitude = 107.60981, properties = '{"site":"Batujaya","model":"C-Data FD1616S-B2","protocol":"telnet","olt_link":"192.168.51.51"}', status = 'unknown'
WHERE id = @olt_id;

INSERT INTO olt_config (map_item_id, output_power, pon_count, attenuation_db, olt_link)
SELECT @olt_id, 0, 16, 0, '192.168.51.51'
WHERE NOT EXISTS (SELECT 1 FROM olt_config WHERE map_item_id = @olt_id);
UPDATE olt_config SET output_power = 0, pon_count = 16, attenuation_db = 0, olt_link = '192.168.51.51' WHERE map_item_id = @olt_id;

DELETE FROM olt_pon_ports WHERE olt_item_id = @olt_id;
INSERT INTO olt_pon_ports (olt_item_id, pon_number, output_power) VALUES (@olt_id, 1, 9);
INSERT INTO olt_pon_ports (olt_item_id, pon_number, output_power) VALUES (@olt_id, 2, 9);
INSERT INTO olt_pon_ports (olt_item_id, pon_number, output_power) VALUES (@olt_id, 3, 9);
INSERT INTO olt_pon_ports (olt_item_id, pon_number, output_power) VALUES (@olt_id, 4, 9);
INSERT INTO olt_pon_ports (olt_item_id, pon_number, output_power) VALUES (@olt_id, 5, 9);
INSERT INTO olt_pon_ports (olt_item_id, pon_number, output_power) VALUES (@olt_id, 6, 9);
INSERT INTO olt_pon_ports (olt_item_id, pon_number, output_power) VALUES (@olt_id, 7, 9);
INSERT INTO olt_pon_ports (olt_item_id, pon_number, output_power) VALUES (@olt_id, 8, 9);
INSERT INTO olt_pon_ports (olt_item_id, pon_number, output_power) VALUES (@olt_id, 9, 9);
INSERT INTO olt_pon_ports (olt_item_id, pon_number, output_power) VALUES (@olt_id, 10, 9);
INSERT INTO olt_pon_ports (olt_item_id, pon_number, output_power) VALUES (@olt_id, 11, 9);
INSERT INTO olt_pon_ports (olt_item_id, pon_number, output_power) VALUES (@olt_id, 12, 9);
INSERT INTO olt_pon_ports (olt_item_id, pon_number, output_power) VALUES (@olt_id, 13, 9);
INSERT INTO olt_pon_ports (olt_item_id, pon_number, output_power) VALUES (@olt_id, 14, 9);
INSERT INTO olt_pon_ports (olt_item_id, pon_number, output_power) VALUES (@olt_id, 15, 9);
INSERT INTO olt_pon_ports (olt_item_id, pon_number, output_power) VALUES (@olt_id, 16, 9);

-- OLT Cicaheum
SET @olt_id := (SELECT id FROM map_items WHERE item_type = 'olt' AND name = 'OLT Cicaheum' LIMIT 1);
INSERT INTO map_items (item_type, parent_id, name, latitude, longitude, genieacs_device_id, properties, status)
SELECT 'olt', NULL, 'OLT Cicaheum', -6.914744, 107.65981, NULL, '{"site":"Cicaheum","model":"Tenda TES7001","protocol":"telnet","olt_link":"172.16.200.254"}', 'unknown'
WHERE @olt_id IS NULL;
SET @olt_id := COALESCE(@olt_id, LAST_INSERT_ID());
UPDATE map_items
SET latitude = -6.914744, longitude = 107.65981, properties = '{"site":"Cicaheum","model":"Tenda TES7001","protocol":"telnet","olt_link":"172.16.200.254"}', status = 'unknown'
WHERE id = @olt_id;

INSERT INTO olt_config (map_item_id, output_power, pon_count, attenuation_db, olt_link)
SELECT @olt_id, 0, 1, 0, '172.16.200.254'
WHERE NOT EXISTS (SELECT 1 FROM olt_config WHERE map_item_id = @olt_id);
UPDATE olt_config SET output_power = 0, pon_count = 1, attenuation_db = 0, olt_link = '172.16.200.254' WHERE map_item_id = @olt_id;

DELETE FROM olt_pon_ports WHERE olt_item_id = @olt_id;
INSERT INTO olt_pon_ports (olt_item_id, pon_number, output_power) VALUES (@olt_id, 1, 9);

-- OLT Cikalong Wetan
SET @olt_id := (SELECT id FROM map_items WHERE item_type = 'olt' AND name = 'OLT Cikalong Wetan' LIMIT 1);
INSERT INTO map_items (item_type, parent_id, name, latitude, longitude, genieacs_device_id, properties, status)
SELECT 'olt', NULL, 'OLT Cikalong Wetan', -6.914744, 107.70981, NULL, '{"site":"Cikalong Wetan","model":"Tenda TES7001","protocol":"telnet","olt_link":"192.168.103.2"}', 'unknown'
WHERE @olt_id IS NULL;
SET @olt_id := COALESCE(@olt_id, LAST_INSERT_ID());
UPDATE map_items
SET latitude = -6.914744, longitude = 107.70981, properties = '{"site":"Cikalong Wetan","model":"Tenda TES7001","protocol":"telnet","olt_link":"192.168.103.2"}', status = 'unknown'
WHERE id = @olt_id;

INSERT INTO olt_config (map_item_id, output_power, pon_count, attenuation_db, olt_link)
SELECT @olt_id, 0, 1, 0, '192.168.103.2'
WHERE NOT EXISTS (SELECT 1 FROM olt_config WHERE map_item_id = @olt_id);
UPDATE olt_config SET output_power = 0, pon_count = 1, attenuation_db = 0, olt_link = '192.168.103.2' WHERE map_item_id = @olt_id;

DELETE FROM olt_pon_ports WHERE olt_item_id = @olt_id;
INSERT INTO olt_pon_ports (olt_item_id, pon_number, output_power) VALUES (@olt_id, 1, 9);

-- OLT Pamengpeuk
SET @olt_id := (SELECT id FROM map_items WHERE item_type = 'olt' AND name = 'OLT Pamengpeuk' LIMIT 1);
INSERT INTO map_items (item_type, parent_id, name, latitude, longitude, genieacs_device_id, properties, status)
SELECT 'olt', NULL, 'OLT Pamengpeuk', -6.914744, 107.75981, NULL, '{"site":"Pamengpeuk","model":"Tenda TES7001","protocol":"telnet","olt_link":"172.16.130.254"}', 'unknown'
WHERE @olt_id IS NULL;
SET @olt_id := COALESCE(@olt_id, LAST_INSERT_ID());
UPDATE map_items
SET latitude = -6.914744, longitude = 107.75981, properties = '{"site":"Pamengpeuk","model":"Tenda TES7001","protocol":"telnet","olt_link":"172.16.130.254"}', status = 'unknown'
WHERE id = @olt_id;

INSERT INTO olt_config (map_item_id, output_power, pon_count, attenuation_db, olt_link)
SELECT @olt_id, 0, 1, 0, '172.16.130.254'
WHERE NOT EXISTS (SELECT 1 FROM olt_config WHERE map_item_id = @olt_id);
UPDATE olt_config SET output_power = 0, pon_count = 1, attenuation_db = 0, olt_link = '172.16.130.254' WHERE map_item_id = @olt_id;

DELETE FROM olt_pon_ports WHERE olt_item_id = @olt_id;
INSERT INTO olt_pon_ports (olt_item_id, pon_number, output_power) VALUES (@olt_id, 1, 9);

-- OLT Jamblang
SET @olt_id := (SELECT id FROM map_items WHERE item_type = 'olt' AND name = 'OLT Jamblang' LIMIT 1);
INSERT INTO map_items (item_type, parent_id, name, latitude, longitude, genieacs_device_id, properties, status)
SELECT 'olt', NULL, 'OLT Jamblang', -6.949744, 107.60981, NULL, '{"site":"Jamblang","model":"Tenda TES7001","protocol":"telnet","olt_link":"172.16.95.254"}', 'unknown'
WHERE @olt_id IS NULL;
SET @olt_id := COALESCE(@olt_id, LAST_INSERT_ID());
UPDATE map_items
SET latitude = -6.949744, longitude = 107.60981, properties = '{"site":"Jamblang","model":"Tenda TES7001","protocol":"telnet","olt_link":"172.16.95.254"}', status = 'unknown'
WHERE id = @olt_id;

INSERT INTO olt_config (map_item_id, output_power, pon_count, attenuation_db, olt_link)
SELECT @olt_id, 0, 1, 0, '172.16.95.254'
WHERE NOT EXISTS (SELECT 1 FROM olt_config WHERE map_item_id = @olt_id);
UPDATE olt_config SET output_power = 0, pon_count = 1, attenuation_db = 0, olt_link = '172.16.95.254' WHERE map_item_id = @olt_id;

DELETE FROM olt_pon_ports WHERE olt_item_id = @olt_id;
INSERT INTO olt_pon_ports (olt_item_id, pon_number, output_power) VALUES (@olt_id, 1, 9);

-- OLT Bojong Asih
SET @olt_id := (SELECT id FROM map_items WHERE item_type = 'olt' AND name = 'OLT Bojong Asih' LIMIT 1);
INSERT INTO map_items (item_type, parent_id, name, latitude, longitude, genieacs_device_id, properties, status)
SELECT 'olt', NULL, 'OLT Bojong Asih', -6.949744, 107.65981, NULL, '{"site":"Bojong Asih","model":"Tenda TES7001","protocol":"telnet","olt_link":"172.40.140.254"}', 'unknown'
WHERE @olt_id IS NULL;
SET @olt_id := COALESCE(@olt_id, LAST_INSERT_ID());
UPDATE map_items
SET latitude = -6.949744, longitude = 107.65981, properties = '{"site":"Bojong Asih","model":"Tenda TES7001","protocol":"telnet","olt_link":"172.40.140.254"}', status = 'unknown'
WHERE id = @olt_id;

INSERT INTO olt_config (map_item_id, output_power, pon_count, attenuation_db, olt_link)
SELECT @olt_id, 0, 1, 0, '172.40.140.254'
WHERE NOT EXISTS (SELECT 1 FROM olt_config WHERE map_item_id = @olt_id);
UPDATE olt_config SET output_power = 0, pon_count = 1, attenuation_db = 0, olt_link = '172.40.140.254' WHERE map_item_id = @olt_id;

DELETE FROM olt_pon_ports WHERE olt_item_id = @olt_id;
INSERT INTO olt_pon_ports (olt_item_id, pon_number, output_power) VALUES (@olt_id, 1, 9);

-- OLT Rusun
SET @olt_id := (SELECT id FROM map_items WHERE item_type = 'olt' AND name = 'OLT Rusun' LIMIT 1);
INSERT INTO map_items (item_type, parent_id, name, latitude, longitude, genieacs_device_id, properties, status)
SELECT 'olt', NULL, 'OLT Rusun', -6.949744, 107.70981, NULL, '{"site":"Rusun","model":"Tenda TES7001","protocol":"telnet","olt_link":"172.16.100.254"}', 'unknown'
WHERE @olt_id IS NULL;
SET @olt_id := COALESCE(@olt_id, LAST_INSERT_ID());
UPDATE map_items
SET latitude = -6.949744, longitude = 107.70981, properties = '{"site":"Rusun","model":"Tenda TES7001","protocol":"telnet","olt_link":"172.16.100.254"}', status = 'unknown'
WHERE id = @olt_id;

INSERT INTO olt_config (map_item_id, output_power, pon_count, attenuation_db, olt_link)
SELECT @olt_id, 0, 1, 0, '172.16.100.254'
WHERE NOT EXISTS (SELECT 1 FROM olt_config WHERE map_item_id = @olt_id);
UPDATE olt_config SET output_power = 0, pon_count = 1, attenuation_db = 0, olt_link = '172.16.100.254' WHERE map_item_id = @olt_id;

DELETE FROM olt_pon_ports WHERE olt_item_id = @olt_id;
INSERT INTO olt_pon_ports (olt_item_id, pon_number, output_power) VALUES (@olt_id, 1, 9);

-- OLT Ibun
SET @olt_id := (SELECT id FROM map_items WHERE item_type = 'olt' AND name = 'OLT Ibun' LIMIT 1);
INSERT INTO map_items (item_type, parent_id, name, latitude, longitude, genieacs_device_id, properties, status)
SELECT 'olt', NULL, 'OLT Ibun', -6.949744, 107.75981, NULL, '{"site":"Ibun","model":"C-Data FD1608S","protocol":"telnet","olt_link":"172.16.15.15"}', 'unknown'
WHERE @olt_id IS NULL;
SET @olt_id := COALESCE(@olt_id, LAST_INSERT_ID());
UPDATE map_items
SET latitude = -6.949744, longitude = 107.75981, properties = '{"site":"Ibun","model":"C-Data FD1608S","protocol":"telnet","olt_link":"172.16.15.15"}', status = 'unknown'
WHERE id = @olt_id;

INSERT INTO olt_config (map_item_id, output_power, pon_count, attenuation_db, olt_link)
SELECT @olt_id, 0, 8, 0, '172.16.15.15'
WHERE NOT EXISTS (SELECT 1 FROM olt_config WHERE map_item_id = @olt_id);
UPDATE olt_config SET output_power = 0, pon_count = 8, attenuation_db = 0, olt_link = '172.16.15.15' WHERE map_item_id = @olt_id;

DELETE FROM olt_pon_ports WHERE olt_item_id = @olt_id;
INSERT INTO olt_pon_ports (olt_item_id, pon_number, output_power) VALUES (@olt_id, 1, 9);
INSERT INTO olt_pon_ports (olt_item_id, pon_number, output_power) VALUES (@olt_id, 2, 9);
INSERT INTO olt_pon_ports (olt_item_id, pon_number, output_power) VALUES (@olt_id, 3, 9);
INSERT INTO olt_pon_ports (olt_item_id, pon_number, output_power) VALUES (@olt_id, 4, 9);
INSERT INTO olt_pon_ports (olt_item_id, pon_number, output_power) VALUES (@olt_id, 5, 9);
INSERT INTO olt_pon_ports (olt_item_id, pon_number, output_power) VALUES (@olt_id, 6, 9);
INSERT INTO olt_pon_ports (olt_item_id, pon_number, output_power) VALUES (@olt_id, 7, 9);
INSERT INTO olt_pon_ports (olt_item_id, pon_number, output_power) VALUES (@olt_id, 8, 9);

-- OLT Pangalengan
SET @olt_id := (SELECT id FROM map_items WHERE item_type = 'olt' AND name = 'OLT Pangalengan' LIMIT 1);
INSERT INTO map_items (item_type, parent_id, name, latitude, longitude, genieacs_device_id, properties, status)
SELECT 'olt', NULL, 'OLT Pangalengan', -6.984744, 107.60981, NULL, '{"site":"Pangalengan","model":"Tenda TES7001","protocol":"telnet","olt_link":"172.10.100.254"}', 'unknown'
WHERE @olt_id IS NULL;
SET @olt_id := COALESCE(@olt_id, LAST_INSERT_ID());
UPDATE map_items
SET latitude = -6.984744, longitude = 107.60981, properties = '{"site":"Pangalengan","model":"Tenda TES7001","protocol":"telnet","olt_link":"172.10.100.254"}', status = 'unknown'
WHERE id = @olt_id;

INSERT INTO olt_config (map_item_id, output_power, pon_count, attenuation_db, olt_link)
SELECT @olt_id, 0, 1, 0, '172.10.100.254'
WHERE NOT EXISTS (SELECT 1 FROM olt_config WHERE map_item_id = @olt_id);
UPDATE olt_config SET output_power = 0, pon_count = 1, attenuation_db = 0, olt_link = '172.10.100.254' WHERE map_item_id = @olt_id;

DELETE FROM olt_pon_ports WHERE olt_item_id = @olt_id;
INSERT INTO olt_pon_ports (olt_item_id, pon_number, output_power) VALUES (@olt_id, 1, 9);

-- OLT Pangalengan 2
SET @olt_id := (SELECT id FROM map_items WHERE item_type = 'olt' AND name = 'OLT Pangalengan 2' LIMIT 1);
INSERT INTO map_items (item_type, parent_id, name, latitude, longitude, genieacs_device_id, properties, status)
SELECT 'olt', NULL, 'OLT Pangalengan 2', -6.984744, 107.65981, NULL, '{"site":"Pangalengan 2","model":"C-Data FD1604E-C1","protocol":"telnet","olt_link":"172.210.19.19"}', 'unknown'
WHERE @olt_id IS NULL;
SET @olt_id := COALESCE(@olt_id, LAST_INSERT_ID());
UPDATE map_items
SET latitude = -6.984744, longitude = 107.65981, properties = '{"site":"Pangalengan 2","model":"C-Data FD1604E-C1","protocol":"telnet","olt_link":"172.210.19.19"}', status = 'unknown'
WHERE id = @olt_id;

INSERT INTO olt_config (map_item_id, output_power, pon_count, attenuation_db, olt_link)
SELECT @olt_id, 0, 4, 0, '172.210.19.19'
WHERE NOT EXISTS (SELECT 1 FROM olt_config WHERE map_item_id = @olt_id);
UPDATE olt_config SET output_power = 0, pon_count = 4, attenuation_db = 0, olt_link = '172.210.19.19' WHERE map_item_id = @olt_id;

DELETE FROM olt_pon_ports WHERE olt_item_id = @olt_id;
INSERT INTO olt_pon_ports (olt_item_id, pon_number, output_power) VALUES (@olt_id, 1, 9);
INSERT INTO olt_pon_ports (olt_item_id, pon_number, output_power) VALUES (@olt_id, 2, 9);
INSERT INTO olt_pon_ports (olt_item_id, pon_number, output_power) VALUES (@olt_id, 3, 9);
INSERT INTO olt_pon_ports (olt_item_id, pon_number, output_power) VALUES (@olt_id, 4, 9);

-- OLT Sumedang
SET @olt_id := (SELECT id FROM map_items WHERE item_type = 'olt' AND name = 'OLT Sumedang' LIMIT 1);
INSERT INTO map_items (item_type, parent_id, name, latitude, longitude, genieacs_device_id, properties, status)
SELECT 'olt', NULL, 'OLT Sumedang', -6.984744, 107.70981, NULL, '{"site":"Sumedang","model":"Tenda TES7001","protocol":"telnet","olt_link":"192.168.5.254"}', 'unknown'
WHERE @olt_id IS NULL;
SET @olt_id := COALESCE(@olt_id, LAST_INSERT_ID());
UPDATE map_items
SET latitude = -6.984744, longitude = 107.70981, properties = '{"site":"Sumedang","model":"Tenda TES7001","protocol":"telnet","olt_link":"192.168.5.254"}', status = 'unknown'
WHERE id = @olt_id;

INSERT INTO olt_config (map_item_id, output_power, pon_count, attenuation_db, olt_link)
SELECT @olt_id, 0, 1, 0, '192.168.5.254'
WHERE NOT EXISTS (SELECT 1 FROM olt_config WHERE map_item_id = @olt_id);
UPDATE olt_config SET output_power = 0, pon_count = 1, attenuation_db = 0, olt_link = '192.168.5.254' WHERE map_item_id = @olt_id;

DELETE FROM olt_pon_ports WHERE olt_item_id = @olt_id;
INSERT INTO olt_pon_ports (olt_item_id, pon_number, output_power) VALUES (@olt_id, 1, 9);

-- OLT Tasikmalaya
SET @olt_id := (SELECT id FROM map_items WHERE item_type = 'olt' AND name = 'OLT Tasikmalaya' LIMIT 1);
INSERT INTO map_items (item_type, parent_id, name, latitude, longitude, genieacs_device_id, properties, status)
SELECT 'olt', NULL, 'OLT Tasikmalaya', -6.984744, 107.75981, NULL, '{"site":"Tasikmalaya","model":"Tenda TES7001","protocol":"telnet","olt_link":"172.16.98.254"}', 'unknown'
WHERE @olt_id IS NULL;
SET @olt_id := COALESCE(@olt_id, LAST_INSERT_ID());
UPDATE map_items
SET latitude = -6.984744, longitude = 107.75981, properties = '{"site":"Tasikmalaya","model":"Tenda TES7001","protocol":"telnet","olt_link":"172.16.98.254"}', status = 'unknown'
WHERE id = @olt_id;

INSERT INTO olt_config (map_item_id, output_power, pon_count, attenuation_db, olt_link)
SELECT @olt_id, 0, 1, 0, '172.16.98.254'
WHERE NOT EXISTS (SELECT 1 FROM olt_config WHERE map_item_id = @olt_id);
UPDATE olt_config SET output_power = 0, pon_count = 1, attenuation_db = 0, olt_link = '172.16.98.254' WHERE map_item_id = @olt_id;

DELETE FROM olt_pon_ports WHERE olt_item_id = @olt_id;
INSERT INTO olt_pon_ports (olt_item_id, pon_number, output_power) VALUES (@olt_id, 1, 9);

-- OLT Singaparna
SET @olt_id := (SELECT id FROM map_items WHERE item_type = 'olt' AND name = 'OLT Singaparna' LIMIT 1);
INSERT INTO map_items (item_type, parent_id, name, latitude, longitude, genieacs_device_id, properties, status)
SELECT 'olt', NULL, 'OLT Singaparna', -7.019744, 107.60981, NULL, '{"site":"Singaparna","model":"HSGQ G02ID","protocol":"telnet","olt_link":"172.16.20.254"}', 'unknown'
WHERE @olt_id IS NULL;
SET @olt_id := COALESCE(@olt_id, LAST_INSERT_ID());
UPDATE map_items
SET latitude = -7.019744, longitude = 107.60981, properties = '{"site":"Singaparna","model":"HSGQ G02ID","protocol":"telnet","olt_link":"172.16.20.254"}', status = 'unknown'
WHERE id = @olt_id;

INSERT INTO olt_config (map_item_id, output_power, pon_count, attenuation_db, olt_link)
SELECT @olt_id, 0, 4, 0, '172.16.20.254'
WHERE NOT EXISTS (SELECT 1 FROM olt_config WHERE map_item_id = @olt_id);
UPDATE olt_config SET output_power = 0, pon_count = 4, attenuation_db = 0, olt_link = '172.16.20.254' WHERE map_item_id = @olt_id;

DELETE FROM olt_pon_ports WHERE olt_item_id = @olt_id;
INSERT INTO olt_pon_ports (olt_item_id, pon_number, output_power) VALUES (@olt_id, 1, 9);
INSERT INTO olt_pon_ports (olt_item_id, pon_number, output_power) VALUES (@olt_id, 2, 9);
INSERT INTO olt_pon_ports (olt_item_id, pon_number, output_power) VALUES (@olt_id, 3, 9);
INSERT INTO olt_pon_ports (olt_item_id, pon_number, output_power) VALUES (@olt_id, 4, 9);

COMMIT;