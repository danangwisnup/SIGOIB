-- ============================================================
-- SISTEM MONITORING IB & QUICK CHECK - Database Schema
-- MySQL 8.x / MariaDB 10.4+
-- ============================================================

CREATE DATABASE IF NOT EXISTS monitoring_ib
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE monitoring_ib;

-- ------------------------------------------------------------
-- 1. organizations
-- ------------------------------------------------------------
CREATE TABLE organizations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  parent_id INT UNSIGNED NULL,
  name VARCHAR(120) NOT NULL,
  type ENUM('BATALYON','KOMPI','PELETON') NOT NULL,
  status ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_organizations_parent (parent_id),
  CONSTRAINT fk_org_parent FOREIGN KEY (parent_id)
    REFERENCES organizations(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 2. personnel
-- ------------------------------------------------------------
CREATE TABLE personnel (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nrp VARCHAR(32) NOT NULL,
  name VARCHAR(120) NOT NULL,
  rank VARCHAR(60) NULL,
  position VARCHAR(120) NULL,
  company_id INT UNSIGNED NULL,
  platoon_id INT UNSIGNED NULL,
  photo VARCHAR(255) NULL,
  status ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_personnel_nrp (nrp),
  INDEX idx_personnel_company (company_id),
  INDEX idx_personnel_platoon (platoon_id),
  CONSTRAINT fk_personnel_company FOREIGN KEY (company_id)
    REFERENCES organizations(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_personnel_platoon FOREIGN KEY (platoon_id)
    REFERENCES organizations(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 3. devices
-- device_token: diberikan server saat APPROVE (API key perangkat)
-- ------------------------------------------------------------
CREATE TABLE devices (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  personnel_id INT UNSIGNED NOT NULL,
  device_uuid VARCHAR(64) NOT NULL,
  device_token VARCHAR(64) NULL,
  platform VARCHAR(20) NULL,
  model VARCHAR(120) NULL,
  app_version VARCHAR(20) NULL,
  status ENUM('PENDING','ACTIVE','REVOKED') NOT NULL DEFAULT 'PENDING',
  last_seen_at DATETIME NULL,
  last_battery INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  approved_at DATETIME NULL,
  revoked_at DATETIME NULL,
  UNIQUE KEY uq_devices_uuid (device_uuid),
  UNIQUE KEY uq_devices_token (device_token),
  INDEX idx_devices_personnel (personnel_id),
  INDEX idx_devices_status (status),
  CONSTRAINT fk_devices_personnel FOREIGN KEY (personnel_id)
    REFERENCES personnel(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 4. users (akun web admin)
-- ------------------------------------------------------------
CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  username VARCHAR(60) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('ADMIN','KOMANDAN','WADAN','DANKI','DANTON') NOT NULL,
  organization_id INT UNSIGNED NULL,
  status ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_users_username (username),
  CONSTRAINT fk_users_org FOREIGN KEY (organization_id)
    REFERENCES organizations(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 5. auth_tokens (token login web admin)
-- ------------------------------------------------------------
CREATE TABLE auth_tokens (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  token VARCHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_auth_tokens_token (token),
  INDEX idx_auth_tokens_user (user_id),
  CONSTRAINT fk_auth_tokens_user FOREIGN KEY (user_id)
    REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 6. monitoring_sessions
-- ------------------------------------------------------------
CREATE TABLE monitoring_sessions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  type ENUM('IB','QUICK_CHECK') NOT NULL,
  start_at DATETIME NOT NULL,
  end_at DATETIME NOT NULL,
  status ENUM('SCHEDULED','ACTIVE','COMPLETED','CANCELLED') NOT NULL DEFAULT 'SCHEDULED',
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_sessions_type (type),
  INDEX idx_sessions_status (status),
  INDEX idx_sessions_start (start_at),
  INDEX idx_sessions_end (end_at),
  CONSTRAINT fk_sessions_creator FOREIGN KEY (created_by)
    REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 7. session_personnel
-- ------------------------------------------------------------
CREATE TABLE session_personnel (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  session_id INT UNSIGNED NOT NULL,
  personnel_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ended_at DATETIME NULL,
  UNIQUE KEY uq_session_personnel (session_id, personnel_id),
  INDEX idx_session_personnel_personnel (personnel_id),
  CONSTRAINT fk_sp_session FOREIGN KEY (session_id)
    REFERENCES monitoring_sessions(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_sp_personnel FOREIGN KEY (personnel_id)
    REFERENCES personnel(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 8. locations
-- client_point_id: idempotency key dari mobile (offline queue)
-- ------------------------------------------------------------
CREATE TABLE locations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  device_id INT UNSIGNED NOT NULL,
  client_point_id VARCHAR(64) NULL,
  latitude DECIMAL(10,7) NOT NULL,
  longitude DECIMAL(10,7) NOT NULL,
  accuracy FLOAT NULL,
  altitude FLOAT NULL,
  speed FLOAT NULL,
  battery INT NULL,
  recorded_at DATETIME(3) NOT NULL,
  received_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  UNIQUE KEY uq_locations_client_point (device_id, client_point_id),
  INDEX idx_locations_device_time (device_id, recorded_at),
  INDEX idx_locations_recorded (recorded_at),
  CONSTRAINT fk_locations_device FOREIGN KEY (device_id)
    REFERENCES devices(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 9. location_sessions (1 GPS point -> N session aktif)
-- ------------------------------------------------------------
CREATE TABLE location_sessions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  location_id BIGINT UNSIGNED NOT NULL,
  session_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_location_session (location_id, session_id),
  INDEX idx_ls_session (session_id),
  CONSTRAINT fk_ls_location FOREIGN KEY (location_id)
    REFERENCES locations(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_ls_session FOREIGN KEY (session_id)
    REFERENCES monitoring_sessions(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 10. geofences (circle only untuk MVP)
-- ------------------------------------------------------------
CREATE TABLE geofences (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  category VARCHAR(80) NULL,
  latitude DECIMAL(10,7) NOT NULL,
  longitude DECIMAL(10,7) NOT NULL,
  radius INT UNSIGNED NOT NULL COMMENT 'meter',
  status ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_geofences_creator FOREIGN KEY (created_by)
    REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 11. alerts
-- ------------------------------------------------------------
CREATE TABLE alerts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  personnel_id INT UNSIGNED NOT NULL,
  device_id INT UNSIGNED NOT NULL,
  geofence_id INT UNSIGNED NULL,
  type ENUM('ENTER','INSIDE','EXIT') NOT NULL,
  latitude DECIMAL(10,7) NULL,
  longitude DECIMAL(10,7) NULL,
  occurred_at DATETIME NOT NULL,
  status ENUM('OPEN','ACKNOWLEDGED','RESOLVED') NOT NULL DEFAULT 'OPEN',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_alerts_status (status),
  INDEX idx_alerts_personnel (personnel_id),
  CONSTRAINT fk_alerts_personnel FOREIGN KEY (personnel_id)
    REFERENCES personnel(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_alerts_device FOREIGN KEY (device_id)
    REFERENCES devices(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_alerts_geofence FOREIGN KEY (geofence_id)
    REFERENCES geofences(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 12. device_events
-- ------------------------------------------------------------
CREATE TABLE device_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  device_id INT UNSIGNED NOT NULL,
  event_type VARCHAR(40) NOT NULL,
  battery INT NULL,
  latitude DECIMAL(10,7) NULL,
  longitude DECIMAL(10,7) NULL,
  metadata TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_device_events_device (device_id, created_at),
  CONSTRAINT fk_device_events_device FOREIGN KEY (device_id)
    REFERENCES devices(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 13. audit_logs
-- ------------------------------------------------------------
CREATE TABLE audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  action VARCHAR(60) NOT NULL,
  target_type VARCHAR(40) NULL,
  target_id VARCHAR(40) NULL,
  description TEXT NULL,
  ip_address VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_audit_user (user_id),
  INDEX idx_audit_created (created_at),
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id)
    REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Retention GPS detail ~90 hari (jalankan berkala via cron,
-- location_sessions ikut terhapus via ON DELETE CASCADE):
--   DELETE FROM locations WHERE received_at < NOW() - INTERVAL 90 DAY;
-- Historical device & audit TIDAK ikut terhapus.
-- ------------------------------------------------------------
