-- =====================================================================
-- ui-vsb : Vehicle Service Booking & Payment Platform
-- database/ui_vsb.sql  (import this file in phpMyAdmin)
-- Creates the database, 6 tables, and seed data. Safe to re-import:
-- tables are dropped first (children before parents, because of foreign keys).
-- Seed logins: admin@ui.edu.ng / Admin@123 , customer@ui.edu.ng / Customer@123
-- =====================================================================
CREATE DATABASE IF NOT EXISTS ui_vsb_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ui_vsb_db;

DROP TABLE IF EXISTS status_history;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS bookings;
DROP TABLE IF EXISTS vehicles;
DROP TABLE IF EXISTS services;
DROP TABLE IF EXISTS users;

-- users: customers and admins. Passwords are stored only as bcrypt hashes.
CREATE TABLE users (
  user_id       INT AUTO_INCREMENT PRIMARY KEY,
  full_name     VARCHAR(100) NOT NULL,
  email         VARCHAR(120) NOT NULL UNIQUE,
  phone         VARCHAR(20)  NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('customer','admin') NOT NULL DEFAULT 'customer',
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- vehicles: cars registered by customers (reg_number is unique across the system).
CREATE TABLE vehicles (
  vehicle_id   INT AUTO_INCREMENT PRIMARY KEY,
  user_id      INT NOT NULL,
  reg_number   VARCHAR(20) NOT NULL UNIQUE,
  make         VARCHAR(50) NOT NULL,
  model        VARCHAR(50) NOT NULL,
  year         YEAR NOT NULL,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- services: the workshop catalogue. is_active=0 hides a service from customers.
CREATE TABLE services (
  service_id    INT AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(100) NOT NULL,
  description   TEXT,
  price         DECIMAL(10,2) NOT NULL,
  duration_mins INT NOT NULL DEFAULT 60,
  is_active     TINYINT(1) NOT NULL DEFAULT 1,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- bookings: one row per appointment. amount is a price snapshot at booking time.
-- idx_slot makes the "how many bookings in this slot?" query fast.
CREATE TABLE bookings (
  booking_id      INT AUTO_INCREMENT PRIMARY KEY,
  booking_ref     VARCHAR(20) NOT NULL UNIQUE,
  user_id         INT NOT NULL,
  vehicle_id      INT NOT NULL,
  service_id      INT NOT NULL,
  booking_date    DATE NOT NULL,
  slot_time       TIME NOT NULL,
  amount          DECIMAL(10,2) NOT NULL,
  booking_status  ENUM('pending_payment','pending_approval','approved','rejected','cancelled')
                  NOT NULL DEFAULT 'pending_payment',
  service_status  ENUM('not_started','received','in_progress','completed','ready_for_pickup')
                  NOT NULL DEFAULT 'not_started',
  notes           VARCHAR(255) NULL,
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id)    REFERENCES users(user_id),
  FOREIGN KEY (vehicle_id) REFERENCES vehicles(vehicle_id),
  FOREIGN KEY (service_id) REFERENCES services(service_id),
  INDEX idx_slot (booking_date, slot_time)
) ENGINE=InnoDB;

-- payments: every payment attempt (successful AND failed). Only the last 4 card digits are kept.
CREATE TABLE payments (
  payment_id      INT AUTO_INCREMENT PRIMARY KEY,
  booking_id      INT NOT NULL,
  transaction_ref VARCHAR(30) NOT NULL UNIQUE,
  amount          DECIMAL(10,2) NOT NULL,
  method          VARCHAR(20) NOT NULL DEFAULT 'test_card',
  card_last4      CHAR(4) NULL,
  status          ENUM('success','failed','refunded') NOT NULL,
  failure_reason  VARCHAR(100) NULL,
  paid_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (booking_id) REFERENCES bookings(booking_id)
) ENGINE=InnoDB;

-- status_history: audit trail shown on the customer's tracking page.
CREATE TABLE status_history (
  history_id  INT AUTO_INCREMENT PRIMARY KEY,
  booking_id  INT NOT NULL,
  status      VARCHAR(30) NOT NULL,
  note        VARCHAR(255) NULL,
  changed_by  INT NULL,
  changed_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (booking_id) REFERENCES bookings(booking_id) ON DELETE CASCADE,
  FOREIGN KEY (changed_by) REFERENCES users(user_id)
) ENGINE=InnoDB;

-- ---------------------------- SEED DATA ------------------------------
INSERT INTO services (name, description, price, duration_mins) VALUES
('Engine Servicing',    'Full engine check, filter and fluid service', 45000.00, 120),
('Oil Change',          'Engine oil and oil filter replacement',       15000.00,  45),
('Brake Inspection',    'Pads, discs, fluid and line inspection',      10000.00,  60),
('Tyre Replacement',    'Remove, fit and balance new tyres',           30000.00,  90),
('Vehicle Diagnostics', 'Computer diagnostics and fault report',       12000.00,  60);

-- Real bcrypt hashes (password_hash), so the import works with no extra step.
INSERT INTO users (full_name, email, phone, password_hash, role) VALUES
('Workshop Admin',  'admin@ui.edu.ng',    '08000000000', '$2y$10$gssYSGeuYDGTRhZpRNE4Cuffn9cuWzH4W8ZBSMgGZQoTYd6lIw7sO', 'admin'),
('Demo Customer',   'customer@ui.edu.ng', '08012345678', '$2y$10$y2uXDoXqJ5eQQ7xGxYlbzO/1R1Msd60OQI14Px88oKFSr.FGkC34W', 'customer');

-- Demo vehicles for the demo customer.
INSERT INTO vehicles (user_id, reg_number, make, model, year)
SELECT user_id, 'ABC-123-XY', 'Toyota', 'Corolla', 2018 FROM users WHERE email = 'customer@ui.edu.ng';
INSERT INTO vehicles (user_id, reg_number, make, model, year)
SELECT user_id, 'IBD-456-AA', 'Honda', 'Accord', 2015 FROM users WHERE email = 'customer@ui.edu.ng';
INSERT INTO vehicles (user_id, reg_number, make, model, year)
SELECT user_id, 'LAG-789-KJ', 'Hyundai', 'Elantra', 2020 FROM users WHERE email = 'customer@ui.edu.ng';
