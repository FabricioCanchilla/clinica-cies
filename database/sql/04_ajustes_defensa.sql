-- =====================================================================
-- CLÍNICA CIES — Ajustes de esquema v5 (observaciones de la defensa)
-- Se ejecuta después de 01_estructura.sql, 02 y 03 (orden alfabético de
-- docker-entrypoint-initdb.d). En una base ya creada: docker compose down -v
-- y docker compose up --build -d.
--
--   AJ07  INTERNACION.AIS_INT: tipo de aislamiento del paciente internado
--         (p. ej. respiratorio por COVID-19). Determina la limpieza al alta.
--   AJ08  LIMPIEZA_CAMA: control de limpieza y desinfección de cada cama.
--         Una cama con ropa sucia, derrame o que alojó a un paciente con
--         aislamiento no vuelve a «Disponible» hasta completar su limpieza.
--           * Rutinaria: cambio de ropa de cama, superficies y colchón.
--           * Terminal : además ventilación del ambiente y un tiempo mínimo
--                        de acción del desinfectante (config cies.limpieza_terminal_min).
-- =====================================================================
USE clinica_cies;

ALTER TABLE INTERNACION
  ADD COLUMN AIS_INT VARCHAR(40) NULL AFTER MOT_INT;

CREATE TABLE IF NOT EXISTS LIMPIEZA_CAMA (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  COD_LIM    VARCHAR(10)  NOT NULL,
  TIP_LIM    VARCHAR(10)  NOT NULL,                 -- Rutinaria / Terminal
  MOT_LIM    VARCHAR(100) NOT NULL,                 -- alta, ropa sucia, derrame, aislamiento...
  FECSOL_LIM DATETIME     NOT NULL,                 -- cuándo se pidió
  FECINI_LIM DATETIME     NULL,                     -- cuándo empezó la limpieza
  FECFIN_LIM DATETIME     NULL,                     -- cuándo se completó
  EST_LIM    VARCHAR(12)  NOT NULL,
  SAB_LIM    TINYINT(1)   NOT NULL DEFAULT 0,       -- ropa de cama retirada y cambiada
  SUP_LIM    TINYINT(1)   NOT NULL DEFAULT 0,       -- superficies, barandas y velador desinfectados
  COL_LIM    TINYINT(1)   NOT NULL DEFAULT 0,       -- colchón y almohada limpios / funda cambiada
  VEN_LIM    TINYINT(1)   NOT NULL DEFAULT 0,       -- ambiente ventilado (obligatorio en la terminal)
  OBS_LIM    VARCHAR(255) NULL,
  COD_CAM    VARCHAR(7)   NOT NULL,
  COD_INT    VARCHAR(10)  NULL,                     -- internación que la originó (si aplica)
  COD_EMP    VARCHAR(9)   NOT NULL,                 -- quién la registró
  EJE_EMP    VARCHAR(9)   NULL,                     -- quién la realizó
  PRIMARY KEY (id),
  UNIQUE KEY UQ_LIMPIEZA_COD (COD_LIM),
  KEY IX_LIMPIEZA_CAMA_EST (COD_CAM, EST_LIM),
  CONSTRAINT CK_LIMPIEZA_TIP  CHECK (TIP_LIM IN ('Rutinaria','Terminal')),
  CONSTRAINT CK_LIMPIEZA_EST  CHECK (EST_LIM IN ('Pendiente','En proceso','Completada')),
  CONSTRAINT CK_LIMPIEZA_INI  CHECK (EST_LIM = 'Pendiente' OR FECINI_LIM IS NOT NULL),
  CONSTRAINT CK_LIMPIEZA_FIN  CHECK ((EST_LIM = 'Completada') = (FECFIN_LIM IS NOT NULL)),
  CONSTRAINT CK_LIMPIEZA_FEC  CHECK (FECFIN_LIM IS NULL OR FECFIN_LIM >= FECINI_LIM),
  CONSTRAINT CK_LIMPIEZA_TERM CHECK (EST_LIM <> 'Completada' OR TIP_LIM <> 'Terminal'
                                     OR (SAB_LIM = 1 AND SUP_LIM = 1 AND COL_LIM = 1 AND VEN_LIM = 1)),
  CONSTRAINT FK_LIMPIEZA_CAM FOREIGN KEY (COD_CAM) REFERENCES CAMA(COD_CAM),
  CONSTRAINT FK_LIMPIEZA_INT FOREIGN KEY (COD_INT) REFERENCES INTERNACION(COD_INT),
  CONSTRAINT FK_LIMPIEZA_EMP FOREIGN KEY (COD_EMP) REFERENCES EMPLEADO(COD_EMP),
  CONSTRAINT FK_LIMPIEZA_EJE FOREIGN KEY (EJE_EMP) REFERENCES EMPLEADO(COD_EMP)
) ENGINE=InnoDB;

-- La aplicación usa el mismo nivel de permisos que en el resto de tablas (02_usuario_auditoria.sql)
GRANT SELECT, INSERT, UPDATE, DELETE ON clinica_cies.LIMPIEZA_CAMA TO 'cies_app'@'%';
FLUSH PRIVILEGES;
