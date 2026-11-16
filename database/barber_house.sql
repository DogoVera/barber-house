-- ================================================================
-- BARBER HOUSE - INSTALACIÓN NUEVA COMPLETA (MySQL/MariaDB de XAMPP)
-- ================================================================
-- Importar este único archivo en una sola operación, en una instalación nueva.
-- CREATE DATABASE falla si el nombre ya existe: no reutilizar sobre datos reales.
-- No contiene borrado/recreación de bases ni actualizaciones de instalaciones.
-- Para pruebas, el runner sustituye SOLO el nombre de destino por una base
-- temporal de nombre único; nunca importa este archivo contra barber_house.
--
-- Incluye las bajas lógicas y anulación de cobros del antiguo upgrade_crud.sql.
-- Ese script ya no es necesario: todas sus columnas/FK están en estas tablas.
-- Se conservan nombres, tipos, relaciones y restricciones del esquema vigente.
-- InnoDB permite transacciones y bloqueos FOR UPDATE en los modelos.
-- Las FK permanecen activadas durante toda la importación.
-- ================================================================

-- Codificación para nombres y observaciones en español.
SET NAMES utf8mb4;

-- Crear y seleccionar la base nueva. No usar IF NOT EXISTS para mezclar datos.
CREATE DATABASE barber_house CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE barber_house;

-- ================================================================
-- ROLES: perfiles de acceso comprobados en backend.
-- PK: id. code es único y se utiliza en las verificaciones de permisos.
-- Relación: un rol tiene muchos usuarios (users.role_id).
-- ================================================================
CREATE TABLE roles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(30) NOT NULL UNIQUE,
  name VARCHAR(60) NOT NULL
) ENGINE=InnoDB;

-- ================================================================
-- USERS: cuentas del personal, hash de contraseña y último acceso.
-- PK: id. FK: role_id -> roles.id. username no puede repetirse.
-- active bloquea el acceso; deleted_at conserva una baja lógica.
-- Los movimientos referencian al usuario para mantener su autoría histórica.
-- ================================================================
CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  role_id INT UNSIGNED NOT NULL,
  username VARCHAR(60) NOT NULL UNIQUE,
  full_name VARCHAR(120) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  deleted_at DATETIME NULL,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_users_role FOREIGN KEY(role_id) REFERENCES roles(id)
) ENGINE=InnoDB;

-- ================================================================
-- CLIENTS: nombre, apellido, teléfono y observación del cliente.
-- PK: id. Relación: un cliente puede tener muchos turnos.
-- deleted_at evita borrar el historial; los índices aceleran las búsquedas.
-- ================================================================
CREATE TABLE clients (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  first_name VARCHAR(80) NOT NULL,
  last_name VARCHAR(80) NOT NULL,
  phone VARCHAR(40) NOT NULL,
  tipo VARCHAR(40) NOT NULL,
  notes VARCHAR(500) NULL,
  deleted_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX idx_clients_name(last_name,first_name),
  INDEX idx_clients_phone(phone)
) ENGINE=InnoDB;

-- ================================================================
-- BARBERS: datos del peluquero y porcentaje actual de comisión.
-- PK: id. CHECK limita el porcentaje a 0..100. active/deleted_at controlan
-- su disponibilidad para nuevos turnos sin borrar las atenciones históricas.
-- Relacionado con jornadas, servicios y turnos.
-- ================================================================
CREATE TABLE barbers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(120) NOT NULL,
  phone VARCHAR(40) NULL,
  commission_percent DECIMAL(5,2) NOT NULL DEFAULT 0 CHECK (commission_percent BETWEEN 0 AND 100),
  active TINYINT(1) NOT NULL DEFAULT 1,
  deleted_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL
) ENGINE=InnoDB;

-- ================================================================
-- BARBER_SCHEDULES: una jornada por día de semana y peluquero.
-- PK: id. FK: barber_id -> barbers.id (borrado físico en cascada).
-- UNIQUE(barber_id,day_of_week) evita duplicar la jornada del mismo día.
-- Los modelos comprueban que cada turno entre completo dentro del horario.
-- ================================================================
CREATE TABLE barber_schedules (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  barber_id INT UNSIGNED NOT NULL,
  day_of_week TINYINT UNSIGNED NOT NULL COMMENT '1=Lunes ... 7=Domingo',
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_barber_day(barber_id,day_of_week),
  CONSTRAINT fk_schedule_barber FOREIGN KEY(barber_id) REFERENCES barbers(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ================================================================
-- SERVICES: catálogo actual de nombre, precio y duración en minutos.
-- PK: id. CHECK impide precio negativo y duración cero.
-- Las bajas son lógicas; cambiar estos valores no cambia los snapshots.
-- ================================================================
CREATE TABLE services (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  price DECIMAL(12,2) NOT NULL CHECK (price >= 0),
  duration_minutes SMALLINT UNSIGNED NOT NULL CHECK (duration_minutes > 0),
  active TINYINT(1) NOT NULL DEFAULT 1,
  deleted_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL
) ENGINE=InnoDB;

-- ================================================================
-- BARBER_SERVICES: relación muchos-a-muchos peluquero/servicio permitido.
-- PK compuesta: (barber_id,service_id), evita asignaciones duplicadas.
-- FK a barbers y services; cascada solo en borrado físico de los padres.
-- ================================================================
CREATE TABLE barber_services (
  barber_id INT UNSIGNED NOT NULL,
  service_id INT UNSIGNED NOT NULL,
  PRIMARY KEY(barber_id,service_id),
  CONSTRAINT fk_bs_barber FOREIGN KEY(barber_id) REFERENCES barbers(id) ON DELETE CASCADE,
  CONSTRAINT fk_bs_service FOREIGN KEY(service_id) REFERENCES services(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ================================================================
-- APPOINTMENTS: agenda única de reservas y atenciones espontáneas.
-- PK: id. FK: client_id -> clients, barber_id -> barbers,
-- created_by -> users. Las FK sin cascada protegen referencias históricas.
-- status admite reservado, atendido, cancelado y ausente (códigos en inglés).
-- Los modelos validan jornada/cruces por intervalo y bloquean filas al guardar;
-- no existe una restricción SQL que por sí sola resuelva superposiciones.
-- ================================================================
CREATE TABLE appointments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  client_id INT UNSIGNED NOT NULL,
  barber_id INT UNSIGNED NOT NULL,
  start_at DATETIME NOT NULL,
  end_at DATETIME NOT NULL,
  status ENUM('reserved','attended','cancelled','no_show') NOT NULL DEFAULT 'reserved',
  notes VARCHAR(500) NULL,
  walk_in TINYINT(1) NOT NULL DEFAULT 0,
  created_by INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_appt_client FOREIGN KEY(client_id) REFERENCES clients(id),
  CONSTRAINT fk_appt_barber FOREIGN KEY(barber_id) REFERENCES barbers(id),
  CONSTRAINT fk_appt_user FOREIGN KEY(created_by) REFERENCES users(id),
  INDEX idx_appt_date(start_at),
  INDEX idx_appt_barber_time(barber_id,start_at,end_at)
) ENGINE=InnoDB;

-- ================================================================
-- APPOINTMENT_SERVICES: varios servicios por turno y sus valores históricos.
-- PK: id. UNIQUE(appointment_id,service_id) impide repetir un servicio.
-- FK: appointment_id -> appointments (cascada física), service_id -> services.
-- price_snapshot/duration_snapshot congelan precio/duración aplicados al turno.
-- ================================================================
CREATE TABLE appointment_services (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  appointment_id INT UNSIGNED NOT NULL,
  service_id INT UNSIGNED NOT NULL,
  price_snapshot DECIMAL(12,2) NOT NULL,
  duration_snapshot SMALLINT UNSIGNED NOT NULL,
  UNIQUE KEY uq_appt_service(appointment_id,service_id),
  CONSTRAINT fk_as_appt FOREIGN KEY(appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
  CONSTRAINT fk_as_service FOREIGN KEY(service_id) REFERENCES services(id)
) ENGINE=InnoDB;

-- ================================================================
-- PAYMENT_METHODS: formas de pago ampliables por el Encargado.
-- PK: id. name único. Solo las activas se ofrecen para registrar cobros.
-- Relación: una forma de pago puede utilizarse en muchos cobros.
-- ================================================================
CREATE TABLE payment_methods (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL UNIQUE,
  active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

-- ================================================================
-- PAYMENTS: cobro e ingreso económico de una atención; no se duplica ingreso.
-- PK: id. appointment_id único: un cobro por turno, incluso si está anulado.
-- FK: turno, forma de pago, usuario autor y usuario que anula (opcional).
-- amount guarda el importe REAL positivo, aunque difiera del total de lista.
-- commission_percent_snapshot/commission_amount preservan comisión histórica.
-- voided_at/voided_by permiten anular sin borrar; los anulados no suman.
-- ================================================================
CREATE TABLE payments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  appointment_id INT UNSIGNED NOT NULL UNIQUE,
  payment_method_id INT UNSIGNED NOT NULL,
  amount DECIMAL(12,2) NOT NULL CHECK (amount > 0),
  commission_percent_snapshot DECIMAL(5,2) NOT NULL,
  commission_amount DECIMAL(12,2) NOT NULL,
  notes VARCHAR(500) NULL,
  paid_at DATETIME NOT NULL,
  created_by INT UNSIGNED NOT NULL,
  updated_at DATETIME NULL,
  voided_at DATETIME NULL,
  voided_by INT UNSIGNED NULL,
  CONSTRAINT fk_payment_appt FOREIGN KEY(appointment_id) REFERENCES appointments(id),
  CONSTRAINT fk_payment_method FOREIGN KEY(payment_method_id) REFERENCES payment_methods(id),
  CONSTRAINT fk_payment_user FOREIGN KEY(created_by) REFERENCES users(id),
  CONSTRAINT fk_payment_void_user FOREIGN KEY(voided_by) REFERENCES users(id),
  INDEX idx_payment_date(paid_at)
) ENGINE=InnoDB;

-- ================================================================
-- EXPENSE_CATEGORIES: categorías simples de gastos del local.
-- PK: id. name único. Relación: una categoría agrupa muchos egresos.
-- ================================================================
CREATE TABLE expense_categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL UNIQUE,
  active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

-- ================================================================
-- EXPENSES: fecha, categoría, concepto y monto de cada gasto.
-- PK: id. FK: categoría, usuario autor y usuario que anula (opcional).
-- CHECK exige monto positivo. La anulación conserva trazabilidad y excluye
-- el gasto de los totales. El índice acelera filtros por fecha.
-- ================================================================
CREATE TABLE expenses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  expense_date DATE NOT NULL,
  category_id INT UNSIGNED NOT NULL,
  concept VARCHAR(180) NOT NULL,
  amount DECIMAL(12,2) NOT NULL CHECK (amount > 0),
  created_by INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  voided_at DATETIME NULL,
  voided_by INT UNSIGNED NULL,
  CONSTRAINT fk_exp_cat FOREIGN KEY(category_id) REFERENCES expense_categories(id),
  CONSTRAINT fk_exp_user FOREIGN KEY(created_by) REFERENCES users(id),
  CONSTRAINT fk_exp_void_user FOREIGN KEY(voided_by) REFERENCES users(id),
  INDEX idx_expense_date(expense_date)
) ENGINE=InnoDB;

-- ================================================================
-- DATOS INICIALES Y DE DEMOSTRACIÓN
-- Se insertan primero los padres y después sus relaciones; no desactivar FK.
-- Fechas NOW()/CURDATE() corresponden al reloj local de MySQL/MariaDB.
-- ================================================================
INSERT INTO roles(code,name) VALUES ('recepcionista','Recepcionista'),('encargado','Encargado');

-- Acceso demo: recepcion / 123456 (Recepcionista) y encargado / 123456.
-- Se almacena un hash bcrypt, nunca la contraseña en texto plano.
INSERT INTO users(role_id,username,full_name,password_hash,active,created_at,updated_at) VALUES
(1,'recepcion','Recepción Barber House','$2y$10$4Am17vkMZox/2A72Ui9iA.VcQ1nhx761DAEthGhJok.4tQCyRf9Mm',1,NOW(),NOW()),
(2,'encargado','Encargado Barber House','$2y$10$4Am17vkMZox/2A72Ui9iA.VcQ1nhx761DAEthGhJok.4tQCyRf9Mm',1,NOW(),NOW());

-- Clientes ficticios reutilizables en la agenda de demostración.
INSERT INTO clients(first_name,last_name,phone,notes,created_at,updated_at) VALUES
('Juan','Pérez','264 555 1234','Prefiere turnos por la tarde.',NOW(),NOW()),
('Diego','Gómez','264 555 7281','',NOW(),NOW()),
('Lucas','Díaz','264 555 3345','Corte corto.',NOW(),NOW()),
('Martín','Sosa','264 555 9190','',NOW(),NOW());

-- Peluqueros y porcentajes actuales; al cobrar se copiará el porcentaje aplicado.
INSERT INTO barbers(full_name,phone,commission_percent,active,created_at,updated_at) VALUES
('Carlos Balmaceda','264 555 1122',45,1,NOW(),NOW()),
('Jesús Funes','264 555 2233',50,1,NOW(),NOW()),
('Nahuel Vera','264 555 3344',40,1,NOW(),NOW());

-- Catálogo inicial con precio y tiempo aproximado de cada servicio.
INSERT INTO services(name,price,duration_minutes,active,created_at,updated_at) VALUES
('Corte',12000,45,1,NOW(),NOW()),
('Barba',8000,30,1,NOW(),NOW()),
('Corte + brushing',18000,60,1,NOW(),NOW()),
('Color',15000,60,1,NOW(),NOW());

-- En la demo todos realizan todos los servicios; la aplicación permite cambiarlos.
INSERT INTO barber_services(barber_id,service_id) SELECT b.id,s.id FROM barbers b CROSS JOIN services s;

-- Jornadas de lunes a sábado; al no haber fila de domingo no se ofrecen turnos.
INSERT INTO barber_schedules(barber_id,day_of_week,start_time,end_time,active) VALUES
(1,1,'09:00','18:00',1),(1,2,'09:00','18:00',1),(1,3,'09:00','18:00',1),(1,4,'09:00','18:00',1),(1,5,'09:00','18:00',1),(1,6,'09:00','14:00',1),
(2,1,'10:00','19:00',1),(2,2,'10:00','19:00',1),(2,3,'10:00','19:00',1),(2,4,'10:00','19:00',1),(2,5,'10:00','19:00',1),(2,6,'10:00','16:00',1),
(3,1,'14:00','21:00',1),(3,2,'14:00','21:00',1),(3,3,'14:00','21:00',1),(3,4,'14:00','21:00',1),(3,5,'14:00','21:00',1),(3,6,'10:00','16:00',1);

-- Catálogos mínimos esperados en el relevamiento.
INSERT INTO payment_methods(name,active) VALUES ('Efectivo',1),('Transferencia',1),('Tarjeta',1);
INSERT INTO expense_categories(name,active) VALUES ('Servicios',1),('Limpieza',1),('Mantenimiento',1),('Otros',1);

-- La agenda de prueba tiene reservas el próximo lunes y una atención cobrada ayer.
INSERT INTO appointments(client_id,barber_id,start_at,end_at,status,notes,walk_in,created_by,created_at,updated_at) VALUES
(1,2,CONCAT(DATE_ADD(CURDATE(),INTERVAL MOD(7-WEEKDAY(CURDATE()),7) DAY),' 15:00:00'),CONCAT(DATE_ADD(CURDATE(),INTERVAL MOD(7-WEEKDAY(CURDATE()),7) DAY),' 15:45:00'),'reserved','',0,1,NOW(),NOW()),
(2,1,CONCAT(DATE_SUB(CURDATE(),INTERVAL 1 DAY),' 16:00:00'),CONCAT(DATE_SUB(CURDATE(),INTERVAL 1 DAY),' 17:15:00'),'attended','',0,1,NOW(),NOW()),
(3,3,CONCAT(DATE_ADD(CURDATE(),INTERVAL MOD(7-WEEKDAY(CURDATE()),7) DAY),' 17:00:00'),CONCAT(DATE_ADD(CURDATE(),INTERVAL MOD(7-WEEKDAY(CURDATE()),7) DAY),' 17:45:00'),'reserved','',0,1,NOW(),NOW());
-- Snapshots correspondientes a los servicios de esas tres atenciones.
INSERT INTO appointment_services(appointment_id,service_id,price_snapshot,duration_snapshot) VALUES
(1,1,12000,45),(2,1,12000,45),(2,2,8000,30),(3,1,12000,45);
-- Cobro demo: $18.000 reales, 45% de comisión histórica = $8.100.
INSERT INTO payments(appointment_id,payment_method_id,amount,commission_percent_snapshot,commission_amount,notes,paid_at,created_by) VALUES
(2,1,18000,45,8100,'Descuento de cortesía',CONCAT(DATE_SUB(CURDATE(),INTERVAL 1 DAY),' 17:20:00'),1);
-- Gasto ficticio para comprobar el resumen contable y las categorías.
INSERT INTO expenses(expense_date,category_id,concept,amount,created_by,created_at,updated_at) VALUES
(DATE_SUB(CURDATE(),INTERVAL 1 DAY),2,'Insumos de limpieza',25000,2,NOW(),NOW());

-- Fin de la instalación: 13 tablas, relaciones válidas y acceso de ambos roles.
