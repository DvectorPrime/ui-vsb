-- =====================================================================
-- ui-vsb : Vehicle Service Booking & Payment Platform
-- database/ui_vsb.sql  (import this file in phpMyAdmin or via mysql CLI)
-- Creates the database, 6 tables, and comprehensive realistic demo dataset.
-- Safe to re-import: tables are dropped first in reverse dependency order.
--
-- Seed accounts:
--   Admin:    admin@ui.edu.ng          / Admin@123
--   Customer: customer@ui.edu.ng       / Customer@123
--   Customer: kemi.adebayo@ui.edu.ng   / Customer@123
--   Customer: emeka.okafor@ui.edu.ng   / Customer@123
-- =====================================================================
CREATE DATABASE IF NOT EXISTS ui_vsb_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ui_vsb_db;

DROP TABLE IF EXISTS status_history;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS bookings;
DROP TABLE IF EXISTS vehicles;
DROP TABLE IF EXISTS services;
DROP TABLE IF EXISTS users;

-- users: customers and admins. Passwords stored strictly as bcrypt hashes.
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

-- services: workshop catalogue. is_active=0 hides a service from customer bookings.
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
-- idx_slot index optimizes slot capacity checks.
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

-- payments: records every payment attempt (successful, failed, and refunded).
-- Only card_last4 is stored; full card numbers and CVVs are NEVER persisted.
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

-- status_history: audit trail shown on customer tracking and admin detail pages.
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

-- ---------------------------------------------------------------------
-- SEED SERVICES (Catalogue)
-- ---------------------------------------------------------------------
INSERT INTO services (service_id, name, description, price, duration_mins, is_active) VALUES
(1, 'Engine Servicing',    'Full engine check, filter and fluid service', 45000.00, 120, 1),
(2, 'Oil Change',          'Engine oil and oil filter replacement',       15000.00,  45, 1),
(3, 'Brake Inspection',    'Pads, discs, fluid and line inspection',      10000.00,  60, 1),
(4, 'Tyre Replacement',    'Remove, fit and balance new tyres',           30000.00,  90, 1),
(5, 'Vehicle Diagnostics', 'Computer diagnostics and fault report',       12000.00,  60, 1);

-- ---------------------------------------------------------------------
-- SEED USERS (Bcrypt password hashes)
-- ---------------------------------------------------------------------
INSERT INTO users (user_id, full_name, email, phone, password_hash, role) VALUES
(1, 'Workshop Admin',  'admin@ui.edu.ng',          '08000000000', '$2y$10$gssYSGeuYDGTRhZpRNE4Cuffn9cuWzH4W8ZBSMgGZQoTYd6lIw7sO', 'admin'),
(2, 'Adeyemi Johnson', 'customer@ui.edu.ng',       '08031234567', '$2y$10$y2uXDoXqJ5eQQ7xGxYlbzO/1R1Msd60OQI14Px88oKFSr.FGkC34W', 'customer'),
(3, 'Kemi Adebayo',    'kemi.adebayo@ui.edu.ng',   '08029876543', '$2y$10$y2uXDoXqJ5eQQ7xGxYlbzO/1R1Msd60OQI14Px88oKFSr.FGkC34W', 'customer'),
(4, 'Emeka Okafor',    'emeka.okafor@ui.edu.ng',   '08055443322', '$2y$10$y2uXDoXqJ5eQQ7xGxYlbzO/1R1Msd60OQI14Px88oKFSr.FGkC34W', 'customer');

-- ---------------------------------------------------------------------
-- SEED VEHICLES
-- ---------------------------------------------------------------------
INSERT INTO vehicles (vehicle_id, user_id, reg_number, make, model, year) VALUES
(1, 2, 'LAG-458-XY', 'Toyota',        'Corolla', 2018),
(2, 2, 'IBD-122-AA', 'Honda',         'Civic',   2020),
(3, 3, 'ABJ-901-CD', 'Ford',          'Focus',   2019),
(4, 3, 'OYO-334-EE', 'Toyota',        'RAV4',    2022),
(5, 4, 'KDL-772-BC', 'Mercedes-Benz', 'C200',    2017);

-- ---------------------------------------------------------------------
-- SEED BOOKINGS (Demonstrating all lifecycle & workshop stepper states)
-- ---------------------------------------------------------------------
INSERT INTO bookings (booking_id, booking_ref, user_id, vehicle_id, service_id, booking_date, slot_time, amount, booking_status, service_status, notes, created_at) VALUES
(1,  'VSB-20261010-A101', 2, 1, 1, '2026-10-10', '09:00:00', 45000.00, 'approved',        'ready_for_pickup', 'Routine annual servicing',          '2026-10-09 08:30:00'),
(2,  'VSB-20261012-B202', 3, 3, 2, '2026-10-12', '10:00:00', 15000.00, 'approved',        'completed',        'Synthetic 5W-30 oil requested',     '2026-10-11 09:15:00'),
(3,  'VSB-20261014-C303', 4, 5, 3, '2026-10-14', '11:00:00', 10000.00, 'approved',        'in_progress',      'Squeaking noise in front brakes',   '2026-10-13 14:00:00'),
(4,  'VSB-20261016-D404', 2, 2, 4, '2026-10-16', '08:00:00', 30000.00, 'approved',        'received',         'Replace two rear tyres',            '2026-10-15 11:20:00'),
(5,  'VSB-20261018-E505', 3, 4, 5, '2026-10-18', '13:00:00', 12000.00, 'approved',        'not_started',      'Check engine light illuminated',    '2026-10-17 16:45:00'),
(6,  'VSB-20261020-F606', 4, 5, 1, '2026-10-28', '09:00:00', 45000.00, 'pending_approval', 'not_started',      'Full diagnostic and service check', '2026-10-20 10:10:00'),
(7,  'VSB-20261021-G707', 2, 1, 2, '2026-10-29', '14:00:00', 15000.00, 'pending_approval', 'not_started',      'Quick oil service before trip',     '2026-10-21 12:00:00'),
(8,  'VSB-20261022-H808', 3, 3, 3, '2026-10-30', '10:00:00', 10000.00, 'pending_payment',  'not_started',      'Pre-travel safety inspection',      '2026-10-26 02:00:00'),
(9,  'VSB-20261023-I909', 4, 5, 4, '2026-10-25', '12:00:00', 30000.00, 'rejected',         'not_started',      'Bay unavailable due to power work', '2026-10-23 09:30:00'),
(10, 'VSB-20261024-J010', 2, 2, 5, '2026-10-24', '15:00:00', 12000.00, 'cancelled',        'not_started',      'Unpaid hold expired',               '2026-10-24 07:00:00');

-- ---------------------------------------------------------------------
-- SEED PAYMENTS (Including successful, failed attempts, and refunded)
-- ---------------------------------------------------------------------
INSERT INTO payments (payment_id, booking_id, transaction_ref, amount, method, card_last4, status, failure_reason, paid_at) VALUES
(1, 1, 'TXN-20261009-918231', 45000.00, 'test_card', '4242', 'success',  NULL,                 '2026-10-09 08:35:12'),
(2, 2, 'TXN-20261011-781920', 15000.00, 'test_card', '4242', 'success',  NULL,                 '2026-10-11 09:18:44'),
(3, 3, 'TXN-20261013-672910', 10000.00, 'test_card', '4242', 'success',  NULL,                 '2026-10-13 14:04:19'),
(4, 4, 'TXN-20261015-561920', 30000.00, 'test_card', '4242', 'success',  NULL,                 '2026-10-15 11:24:50'),
(5, 5, 'TXN-20261017-451920', 12000.00, 'test_card', '4242', 'success',  NULL,                 '2026-10-17 16:50:02'),
(6, 6, 'TXN-20261020-341920', 45000.00, 'test_card', '4242', 'success',  NULL,                 '2026-10-20 10:14:31'),
(7, 7, 'TXN-20261021-231920', 15000.00, 'test_card', '4242', 'success',  NULL,                 '2026-10-21 12:05:15'),
(8, 8, 'TXN-20261026-121921', 10000.00, 'test_card', '0002', 'failed',   'card_declined',      '2026-10-26 02:02:10'),
(9, 8, 'TXN-20261026-121922', 10000.00, 'test_card', '9995', 'failed',   'insufficient_funds', '2026-10-26 02:04:30'),
(10,9, 'TXN-20261023-011920', 30000.00, 'test_card', '4242', 'refunded', NULL,                 '2026-10-23 09:35:00');

-- ---------------------------------------------------------------------
-- SEED STATUS HISTORY (Audit Trail)
-- ---------------------------------------------------------------------
INSERT INTO status_history (booking_id, status, note, changed_by, changed_at) VALUES
-- Booking 1 (completed and ready for pickup)
(1, 'pending_payment',  'Booking created',                     2, '2026-10-09 08:30:00'),
(1, 'pending_approval', 'Payment received',                    2, '2026-10-09 08:35:12'),
(1, 'approved',         'Booking approved by workshop admin',  1, '2026-10-09 10:00:00'),
(1, 'received',         'Vehicle received at Workshop Bay 1',  1, '2026-10-10 09:05:00'),
(1, 'in_progress',      'Engine oil drained, filters removed', 1, '2026-10-10 09:30:00'),
(1, 'completed',        'Engine servicing finished and tested',1, '2026-10-10 11:20:00'),
(1, 'ready_for_pickup', 'Vehicle washed and parked at Bay A',  1, '2026-10-10 11:45:00'),

-- Booking 2
(2, 'pending_payment',  'Booking created',                     3, '2026-10-11 09:15:00'),
(2, 'pending_approval', 'Payment received',                    3, '2026-10-11 09:18:44'),
(2, 'approved',         'Booking approved',                    1, '2026-10-11 11:00:00'),
(2, 'received',         'Vehicle received at Workshop Bay 2',  1, '2026-10-12 10:00:00'),
(2, 'in_progress',      'Oil filter changed, new oil added',   1, '2026-10-12 10:15:00'),
(2, 'completed',        'Oil change completed',                1, '2026-10-12 10:45:00'),

-- Booking 3
(3, 'pending_payment',  'Booking created',                     4, '2026-10-13 14:00:00'),
(3, 'pending_approval', 'Payment received',                    4, '2026-10-13 14:04:19'),
(3, 'approved',         'Booking approved',                    1, '2026-10-13 15:30:00'),
(3, 'received',         'Vehicle received at Bay 1',           1, '2026-10-14 11:00:00'),
(3, 'in_progress',      'Front calipers and pads disassembled',1, '2026-10-14 11:25:00'),

-- Booking 4
(4, 'pending_payment',  'Booking created',                     2, '2026-10-15 11:20:00'),
(4, 'pending_approval', 'Payment received',                    2, '2026-10-15 11:24:50'),
(4, 'approved',         'Booking approved',                    1, '2026-10-15 13:00:00'),
(4, 'received',         'Vehicle parked in tyre bay',          1, '2026-10-16 08:05:00'),

-- Booking 5
(5, 'pending_payment',  'Booking created',                     3, '2026-10-17 16:45:00'),
(5, 'pending_approval', 'Payment received',                    3, '2026-10-17 16:50:02'),
(5, 'approved',         'Booking approved for workshop queue', 1, '2026-10-18 08:30:00'),

-- Booking 6 & 7 (Pending admin approval)
(6, 'pending_payment',  'Booking created',                     4, '2026-10-20 10:10:00'),
(6, 'pending_approval', 'Payment received',                    4, '2026-10-20 10:14:31'),
(7, 'pending_payment',  'Booking created',                     2, '2026-10-21 12:00:00'),
(7, 'pending_approval', 'Payment received',                    2, '2026-10-21 12:05:15'),

-- Booking 8 (Pending payment)
(8, 'pending_payment',  'Booking created',                     3, '2026-10-26 02:00:00'),

-- Booking 9 (Rejected with refund)
(9, 'pending_payment',  'Booking created',                     4, '2026-10-23 09:30:00'),
(9, 'pending_approval', 'Payment received',                    4, '2026-10-23 09:35:00'),
(9, 'rejected',         'Rejected: Bay unavailable. Payment refunded.', 1, '2026-10-23 10:00:00'),

-- Booking 10 (Expired hold)
(10, 'pending_payment', 'Booking created',                     2, '2026-10-24 07:00:00'),
(10, 'cancelled',       'Unpaid booking hold expired',         NULL, '2026-10-24 07:30:00');
