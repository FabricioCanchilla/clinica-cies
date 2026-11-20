-- =====================================================================
-- CLÍNICA CIES — Ajustes de esquema v6 (cierre de los requisitos del informe)
-- Se ejecuta después de 01..04 (orden alfabético de docker-entrypoint-initdb.d).
-- En una base ya creada se puede aplicar a mano (es idempotente en lo posible):
--   docker exec -i clinica_cies_db mysql -uroot -proot123 < database/sql/05_ajustes_v6.sql
--
--   AJ01  CITA: estados «En espera» (check-in) y «En consulta» (RF021, RF037)
--   AJ04  DOSIS.COD_INS nulo cuando el medicamento no viene del familiar (RF033)
--   AJ05  Tablas nuevas: CONSENTIMIENTO (RF024), DERIVACION (RF023),
--         NOTA_ENFERMERIA y BALANCE_HIDRICO (RF035), PARAMETRO (RF044)
--   AJ09  CIE10 catálogo y CONSULTA.COD_CIE (RF022, RN43, RNF015)
--   AJ10  VENTA y DETALLE_VENTA: venta en mostrador de farmacia (RF041);
--         MEDICAMENTO.PRE_MED precio unitario de venta
--   AJ11  RESPALDO: registro de copias de seguridad y pruebas de restauración (RN48)
-- =====================================================================
USE clinica_cies;

-- ---------- AJ01 ----------
ALTER TABLE CITA DROP CHECK CK_CITA_EST;
ALTER TABLE CITA ADD CONSTRAINT CK_CITA_EST CHECK (EST_CIT IN
  ('Pendiente de pago','Confirmada','En espera','En consulta','Atendida','Cancelada','Expirada','No asistio'));

-- ---------- AJ04 ----------
ALTER TABLE DOSIS MODIFY COD_INS VARCHAR(10) NULL;
ALTER TABLE DOSIS ADD COLUMN FIR_DOS VARCHAR(100) NULL AFTER COD_EMP;   -- RNF005: firma de quien administra (opcional)

-- ---------- AJ09: CIE-10 ----------
CREATE TABLE IF NOT EXISTS CIE10 (
  id      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  COD_CIE VARCHAR(7)   NOT NULL,             -- p. ej. J06.9
  DES_CIE VARCHAR(200) NOT NULL,
  CAP_CIE VARCHAR(60)  NOT NULL,             -- capítulo
  PRIMARY KEY (id),
  UNIQUE KEY UQ_CIE10_COD (COD_CIE)
) ENGINE=InnoDB;

ALTER TABLE CONSULTA ADD COLUMN COD_CIE VARCHAR(7) NULL AFTER DIA_CNS;
ALTER TABLE CONSULTA ADD CONSTRAINT FK_CONSULTA_CIE FOREIGN KEY (COD_CIE) REFERENCES CIE10(COD_CIE);

-- ---------- AJ05: consentimiento informado ----------
CREATE TABLE IF NOT EXISTS CONSENTIMIENTO (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  COD_CNT    VARCHAR(10)  NOT NULL,
  FEC_CNT    DATETIME     NOT NULL,
  PRO_CNT    VARCHAR(150) NOT NULL,          -- procedimiento
  RIE_CNT    VARCHAR(500) NOT NULL,          -- riesgos y alternativas explicados
  FIR_CNT    VARCHAR(100) NOT NULL,          -- ruta de la firma del paciente (RN15)
  FIRNOM_CNT VARCHAR(100) NOT NULL,          -- quien firma (paciente o representante)
  IP_CNT     VARCHAR(45)  NOT NULL,
  COD_PAC    VARCHAR(10)  NOT NULL,
  COD_EMP    VARCHAR(9)   NOT NULL,
  COD_CIT    VARCHAR(10)  NULL,
  PRIMARY KEY (id),
  UNIQUE KEY UQ_CONSENT_COD (COD_CNT),
  CONSTRAINT FK_CONSENT_PAC FOREIGN KEY (COD_PAC) REFERENCES PACIENTE(COD_PAC),
  CONSTRAINT FK_CONSENT_EMP FOREIGN KEY (COD_EMP) REFERENCES EMPLEADO(COD_EMP),
  CONSTRAINT FK_CONSENT_CIT FOREIGN KEY (COD_CIT) REFERENCES CITA(COD_CIT)
) ENGINE=InnoDB;

-- ---------- AJ05: derivaciones e interconsultas ----------
CREATE TABLE IF NOT EXISTS DERIVACION (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  COD_DER    VARCHAR(10)  NOT NULL,
  FEC_DER    DATETIME     NOT NULL,
  TIP_DER    VARCHAR(14)  NOT NULL,          -- Derivación / Interconsulta
  PRI_DER    VARCHAR(8)   NOT NULL,          -- Normal / Urgente
  MOT_DER    VARCHAR(300) NOT NULL,
  EST_DER    VARCHAR(10)  NOT NULL,          -- Pendiente / Respondida / Rechazada
  RES_DER    VARCHAR(500) NULL,              -- respuesta del especialista
  FECRES_DER DATETIME     NULL,
  COD_PAC    VARCHAR(10)  NOT NULL,
  COD_CNS    VARCHAR(10)  NULL,
  ORI_EMP    VARCHAR(9)   NOT NULL,          -- médico que deriva
  COD_ESP    VARCHAR(7)   NOT NULL,          -- especialidad destino
  DES_EMP    VARCHAR(9)   NULL,              -- especialista que responde
  PRIMARY KEY (id),
  UNIQUE KEY UQ_DERIV_COD (COD_DER),
  KEY IX_DERIV_ESP_EST (COD_ESP, EST_DER),
  CONSTRAINT CK_DERIV_TIP CHECK (TIP_DER IN ('Derivación','Interconsulta')),
  CONSTRAINT CK_DERIV_PRI CHECK (PRI_DER IN ('Normal','Urgente')),
  CONSTRAINT CK_DERIV_EST CHECK (EST_DER IN ('Pendiente','Respondida','Rechazada')),
  CONSTRAINT CK_DERIV_RES CHECK (EST_DER = 'Pendiente' OR (RES_DER IS NOT NULL AND FECRES_DER IS NOT NULL)),
  CONSTRAINT FK_DERIV_PAC FOREIGN KEY (COD_PAC) REFERENCES PACIENTE(COD_PAC),
  CONSTRAINT FK_DERIV_CNS FOREIGN KEY (COD_CNS) REFERENCES CONSULTA(COD_CNS),
  CONSTRAINT FK_DERIV_ORI FOREIGN KEY (ORI_EMP) REFERENCES EMPLEADO(COD_EMP),
  CONSTRAINT FK_DERIV_DES FOREIGN KEY (DES_EMP) REFERENCES EMPLEADO(COD_EMP),
  CONSTRAINT FK_DERIV_ESP FOREIGN KEY (COD_ESP) REFERENCES ESPECIALIDAD(COD_ESP)
) ENGINE=InnoDB;

-- ---------- AJ05: notas de enfermería y cambio de turno ----------
CREATE TABLE IF NOT EXISTS NOTA_ENFERMERIA (
  id      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  COD_NEN VARCHAR(10)  NOT NULL,
  FEC_NEN DATETIME     NOT NULL,
  TIP_NEN VARCHAR(16)  NOT NULL,             -- Nota / Cambio de turno
  TUR_NEN VARCHAR(8)   NOT NULL,             -- Mañana / Tarde / Noche
  TXT_NEN VARCHAR(1000) NOT NULL,
  COD_INT VARCHAR(10)  NOT NULL,
  COD_EMP VARCHAR(9)   NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY UQ_NOTAENF_COD (COD_NEN),
  CONSTRAINT CK_NOTAENF_TIP CHECK (TIP_NEN IN ('Nota','Cambio de turno')),
  CONSTRAINT CK_NOTAENF_TUR CHECK (TUR_NEN IN ('Mañana','Tarde','Noche')),
  CONSTRAINT FK_NOTAENF_INT FOREIGN KEY (COD_INT) REFERENCES INTERNACION(COD_INT),
  CONSTRAINT FK_NOTAENF_EMP FOREIGN KEY (COD_EMP) REFERENCES EMPLEADO(COD_EMP)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS BALANCE_HIDRICO (
  id      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  COD_BAL VARCHAR(10)  NOT NULL,
  FEC_BAL DATETIME     NOT NULL,
  TIP_BAL VARCHAR(8)   NOT NULL,             -- Ingreso / Egreso
  CON_BAL VARCHAR(20)  NOT NULL,             -- Oral, Intravenoso, Sonda / Orina, Drenaje, Vómito, Deposición
  VOL_BAL SMALLINT     NOT NULL,             -- mL
  COD_INT VARCHAR(10)  NOT NULL,
  COD_EMP VARCHAR(9)   NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY UQ_BALANCE_COD (COD_BAL),
  KEY IX_BALANCE_INT (COD_INT, FEC_BAL),
  CONSTRAINT CK_BALANCE_TIP CHECK (TIP_BAL IN ('Ingreso','Egreso')),
  CONSTRAINT CK_BALANCE_VOL CHECK (VOL_BAL > 0 AND VOL_BAL <= 5000),
  CONSTRAINT FK_BALANCE_INT FOREIGN KEY (COD_INT) REFERENCES INTERNACION(COD_INT),
  CONSTRAINT FK_BALANCE_EMP FOREIGN KEY (COD_EMP) REFERENCES EMPLEADO(COD_EMP)
) ENGINE=InnoDB;

-- ---------- AJ05: parámetros de configuración (RF044) ----------
CREATE TABLE IF NOT EXISTS PARAMETRO (
  id      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  COD_PAR VARCHAR(40)  NOT NULL,
  VAL_PAR VARCHAR(100) NOT NULL,
  DES_PAR VARCHAR(200) NOT NULL,
  MIN_PAR INT          NULL,
  MAX_PAR INT          NULL,
  FEC_PAR DATETIME     NOT NULL,
  COD_USR VARCHAR(8)   NULL,
  PRIMARY KEY (id),
  UNIQUE KEY UQ_PARAMETRO_COD (COD_PAR),
  CONSTRAINT FK_PARAMETRO_USR FOREIGN KEY (COD_USR) REFERENCES USUARIO(COD_USR)
) ENGINE=InnoDB;

INSERT IGNORE INTO PARAMETRO (COD_PAR, VAL_PAR, DES_PAR, MIN_PAR, MAX_PAR, FEC_PAR) VALUES
 ('anticipo_porcentaje',        '30', 'Porcentaje del precio de la consulta que se paga como anticipo (RN19)', 1, 100, NOW()),
 ('minutos_inactividad',        '15', 'Minutos de inactividad antes de cerrar la sesión (RN06)', 5, 60, NOW()),
 ('dias_max_anticipacion',      '30', 'Días hacia adelante en que se puede agendar una cita (RN17)', 1, 90, NOW()),
 ('descuento_sin_autorizacion', '20', 'Descuento máximo (%) sin autorización de un administrador (RN29)', 0, 100, NOW()),
 ('limpieza_terminal_min',      '45', 'Minutos mínimos de la desinfección terminal de una cama (AJ08)', 10, 240, NOW()),
 ('respaldo_retencion_dias',    '30', 'Días que se conservan las copias de seguridad (RN48)', 7, 365, NOW());

-- ---------- AJ10: venta en mostrador (RF041) ----------
ALTER TABLE MEDICAMENTO ADD COLUMN PRE_MED DECIMAL(8,2) NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS VENTA (
  id        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  NRO_VEN   VARCHAR(11)   NOT NULL,          -- V-00000001 correlativo
  FEC_VEN   DATETIME      NOT NULL,
  CLI_VEN   VARCHAR(100)  NOT NULL,          -- nombre del cliente ambulante o del paciente
  TOT_VEN   DECIMAL(10,2) NOT NULL,
  EST_VEN   VARCHAR(8)    NOT NULL,          -- Emitida / Anulada
  TIP_VEN   VARCHAR(14)   NOT NULL,          -- Ambulante / Hospitalizado
  MOTANU_VEN VARCHAR(200) NULL,
  COD_PAC   VARCHAR(10)   NULL,
  COD_INT   VARCHAR(10)   NULL,              -- consumo cargado a la cuenta del hospitalizado
  COD_MET   VARCHAR(7)    NULL,
  COD_EMP   VARCHAR(9)    NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY UQ_VENTA_NRO (NRO_VEN),
  CONSTRAINT CK_VENTA_TOT CHECK (TOT_VEN >= 0),
  CONSTRAINT CK_VENTA_EST CHECK (EST_VEN IN ('Emitida','Anulada')),
  CONSTRAINT CK_VENTA_TIP CHECK (TIP_VEN IN ('Ambulante','Hospitalizado')),
  CONSTRAINT CK_VENTA_HOSP CHECK (TIP_VEN = 'Ambulante' OR COD_INT IS NOT NULL),
  CONSTRAINT CK_VENTA_ANU CHECK (EST_VEN = 'Emitida' OR MOTANU_VEN IS NOT NULL),
  CONSTRAINT FK_VENTA_PAC FOREIGN KEY (COD_PAC) REFERENCES PACIENTE(COD_PAC),
  CONSTRAINT FK_VENTA_INT FOREIGN KEY (COD_INT) REFERENCES INTERNACION(COD_INT),
  CONSTRAINT FK_VENTA_MET FOREIGN KEY (COD_MET) REFERENCES METODO_PAGO(COD_MET),
  CONSTRAINT FK_VENTA_EMP FOREIGN KEY (COD_EMP) REFERENCES EMPLEADO(COD_EMP)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS DETALLE_VENTA (
  id      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  COD_DVE VARCHAR(10)  NOT NULL,
  NRO_VEN VARCHAR(11)  NOT NULL,
  COD_MED VARCHAR(8)   NOT NULL,
  COD_LOT VARCHAR(10)  NOT NULL,             -- lote del que salió (PEPS)
  CAN_DVE SMALLINT     NOT NULL,
  PRE_DVE DECIMAL(8,2) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY UQ_DETVEN_COD (COD_DVE),
  CONSTRAINT CK_DETVEN_CAN CHECK (CAN_DVE > 0),
  CONSTRAINT CK_DETVEN_PRE CHECK (PRE_DVE >= 0),
  CONSTRAINT FK_DETVEN_VEN FOREIGN KEY (NRO_VEN) REFERENCES VENTA(NRO_VEN),
  CONSTRAINT FK_DETVEN_MED FOREIGN KEY (COD_MED) REFERENCES MEDICAMENTO(COD_MED),
  CONSTRAINT FK_DETVEN_LOT FOREIGN KEY (COD_LOT) REFERENCES LOTE_MEDICAMENTO(COD_LOT)
) ENGINE=InnoDB;

-- ---------- AJ11: respaldos (RN48) ----------
CREATE TABLE IF NOT EXISTS RESPALDO (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  COD_RES    VARCHAR(10)  NOT NULL,
  FEC_RES    DATETIME     NOT NULL,
  TIP_RES    VARCHAR(10)  NOT NULL,          -- Automático / Manual
  ARC_RES    VARCHAR(150) NOT NULL,          -- archivo en storage/app/private/respaldos
  TAM_RES    BIGINT       NOT NULL,          -- bytes
  HAS_RES    CHAR(64)     NOT NULL,          -- SHA-256 del archivo
  TAB_RES    SMALLINT     NOT NULL,          -- tablas respaldadas
  FIL_RES    INT          NOT NULL,          -- filas respaldadas
  EST_RES    VARCHAR(10)  NOT NULL,          -- Vigente / Eliminado
  FECPRU_RES DATETIME     NULL,              -- última prueba de restauración
  RESPRU_RES VARCHAR(255) NULL,              -- resultado de la prueba
  COD_USR    VARCHAR(8)   NULL,
  PRIMARY KEY (id),
  UNIQUE KEY UQ_RESPALDO_COD (COD_RES),
  CONSTRAINT CK_RESPALDO_TIP CHECK (TIP_RES IN ('Automático','Manual')),
  CONSTRAINT CK_RESPALDO_EST CHECK (EST_RES IN ('Vigente','Eliminado')),
  CONSTRAINT FK_RESPALDO_USR FOREIGN KEY (COD_USR) REFERENCES USUARIO(COD_USR)
) ENGINE=InnoDB;

-- Permisos de la aplicación: mismos que el resto de tablas (02_usuario_auditoria.sql)
GRANT SELECT, INSERT, UPDATE, DELETE ON clinica_cies.CIE10           TO 'cies_app'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE ON clinica_cies.CONSENTIMIENTO  TO 'cies_app'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE ON clinica_cies.DERIVACION      TO 'cies_app'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE ON clinica_cies.NOTA_ENFERMERIA TO 'cies_app'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE ON clinica_cies.BALANCE_HIDRICO TO 'cies_app'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE ON clinica_cies.PARAMETRO       TO 'cies_app'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE ON clinica_cies.VENTA           TO 'cies_app'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE ON clinica_cies.DETALLE_VENTA   TO 'cies_app'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE ON clinica_cies.RESPALDO        TO 'cies_app'@'%';
FLUSH PRIVILEGES;

-- RN48: base de pruebas de restauración. La aplicación restaura ahí la última copia y compara
-- tabla por tabla las filas con las del respaldo (prueba verificable, sin tocar la base real).
CREATE DATABASE IF NOT EXISTS clinica_cies_restauracion CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON clinica_cies_restauracion.* TO 'cies_app'@'%';
FLUSH PRIVILEGES;
