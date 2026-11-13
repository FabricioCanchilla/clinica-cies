USE clinica_cies;

CREATE USER IF NOT EXISTS 'cies_app'@'%' IDENTIFIED BY 'cies_app123';

DROP PROCEDURE IF EXISTS sp_permisos_app;
DELIMITER $$
CREATE PROCEDURE sp_permisos_app()
BEGIN
  DECLARE fin INT DEFAULT 0;
  DECLARE t VARCHAR(64);
  DECLARE cur CURSOR FOR
    SELECT TABLE_NAME FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = 'clinica_cies' AND TABLE_TYPE = 'BASE TABLE';
  DECLARE CONTINUE HANDLER FOR NOT FOUND SET fin = 1;

  OPEN cur;
  bucle: LOOP
    FETCH cur INTO t;
    IF fin = 1 THEN LEAVE bucle; END IF;
    IF LOWER(t) = 'auditoria' THEN
      SET @sql = CONCAT('GRANT SELECT, INSERT ON clinica_cies.`', t, '` TO ''cies_app''@''%''');
    ELSE
      SET @sql = CONCAT('GRANT SELECT, INSERT, UPDATE, DELETE ON clinica_cies.`', t, '` TO ''cies_app''@''%''');
    END IF;
    PREPARE s FROM @sql;
    EXECUTE s;
    DEALLOCATE PREPARE s;
  END LOOP;
  CLOSE cur;
END$$
DELIMITER ;

CALL sp_permisos_app();
DROP PROCEDURE sp_permisos_app;