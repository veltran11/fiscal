CREATE TABLE IF NOT EXISTS clientes (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id       INT UNSIGNED NOT NULL,
  nombre           VARCHAR(200) NOT NULL,
  tipo_documento   ENUM('CUIT','DNI','Pasaporte') NOT NULL DEFAULT 'CUIT',
  numero_documento VARCHAR(20)  DEFAULT NULL,
  condicion_iva    VARCHAR(50)  DEFAULT 'Consumidor Final',
  email            VARCHAR(150) DEFAULT NULL,
  telefono         VARCHAR(30)  DEFAULT NULL,
  direccion        VARCHAR(200) DEFAULT NULL,
  localidad        VARCHAR(100) DEFAULT NULL,
  codigo_postal    VARCHAR(10)  DEFAULT NULL,
  created_at       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_usuario (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
