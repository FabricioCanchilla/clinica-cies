-- Usuario propio del microservicio Quarkus (permisos minimos)
CREATE USER IF NOT EXISTS 'cies_quarkus'@'%' IDENTIFIED BY 'cies_quarkus123';

-- OTP: crea codigos y actualiza su estado/intentos (no se borra nada)
GRANT SELECT, INSERT, UPDATE ON clinica_cies.OTP TO 'cies_quarkus'@'%';

-- Comprobantes de pago: numera e inserta (no modifica ni borra)
GRANT SELECT, INSERT ON clinica_cies.COMPROBANTE TO 'cies_quarkus'@'%';
GRANT SELECT, INSERT ON clinica_cies.DETALLE_COMPROBANTE TO 'cies_quarkus'@'%';
GRANT SELECT, INSERT ON clinica_cies.COMPROBANTE_ENTREGA TO 'cies_quarkus'@'%';

-- Solo lectura de las tablas que consulta para armar el PDF
GRANT SELECT ON clinica_cies.PACIENTE TO 'cies_quarkus'@'%';
GRANT SELECT ON clinica_cies.CITA TO 'cies_quarkus'@'%';
GRANT SELECT ON clinica_cies.SERVICIO TO 'cies_quarkus'@'%';
GRANT SELECT ON clinica_cies.EMPLEADO TO 'cies_quarkus'@'%';
GRANT SELECT ON clinica_cies.INSUMO_TERCERO TO 'cies_quarkus'@'%';
GRANT SELECT ON clinica_cies.ENTREGANTE TO 'cies_quarkus'@'%';
GRANT SELECT ON clinica_cies.MEDICAMENTO TO 'cies_quarkus'@'%';
GRANT SELECT ON clinica_cies.CAJA TO 'cies_quarkus'@'%';

FLUSH PRIVILEGES;