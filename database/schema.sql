-- =============================================================
--  Harvy Mance Films — Full Database Schema
--  Database: harvy_mance_films
-- =============================================================

CREATE DATABASE IF NOT EXISTS harvy_mance_films
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE harvy_mance_films;

-- -------------------------------------------------------------
-- 1. USERS  (admins + clients)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100)  NOT NULL,
    email      VARCHAR(100)  NOT NULL UNIQUE,
    password   VARCHAR(255)  NOT NULL,
    role       ENUM('admin','client')            DEFAULT 'client',
    status     ENUM('active','inactive')         DEFAULT 'active',
    created_at TIMESTAMP                         DEFAULT CURRENT_TIMESTAMP
);

-- -------------------------------------------------------------
-- 2. PACKAGES  (photography / videography packages)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS packages (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100)                     NOT NULL,
    description TEXT,
    price       DECIMAL(10,2)                    NOT NULL,
    inclusions  TEXT,
    status      ENUM('active','archived')        DEFAULT 'active',
    created_at  TIMESTAMP                        DEFAULT CURRENT_TIMESTAMP
);

-- -------------------------------------------------------------
-- 3. BOOKINGS
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS bookings (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    client_id    INT           NOT NULL,
    package_id   INT           NOT NULL,
    booking_date DATE          NOT NULL,
    event_type   VARCHAR(100),
    venue        VARCHAR(255),
    notes        TEXT,
    status       ENUM('pending','approved','rescheduled','cancelled') DEFAULT 'pending',
    created_at   TIMESTAMP                        DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id)  REFERENCES users(id)    ON DELETE CASCADE,
    FOREIGN KEY (package_id) REFERENCES packages(id) ON DELETE RESTRICT
);

-- -------------------------------------------------------------
-- 4. STAFF
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS staff (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100)  NOT NULL,
    role       VARCHAR(100),
    email      VARCHAR(100)  UNIQUE,
    phone      VARCHAR(20),
    status     ENUM('active','inactive')         DEFAULT 'active',
    created_at TIMESTAMP                         DEFAULT CURRENT_TIMESTAMP
);

-- -------------------------------------------------------------
-- 5. STAFF SCHEDULES  (links staff to a booking date)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS staff_schedules (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    staff_id     INT NOT NULL,
    booking_id   INT NOT NULL,
    booking_date DATE NOT NULL,
    FOREIGN KEY (staff_id)   REFERENCES staff(id)    ON DELETE CASCADE,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
);

-- -------------------------------------------------------------
-- 6. POST-PRODUCTION
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS post_production (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    booking_id       INT,
    photo_status     ENUM('not_started','in_progress','completed') DEFAULT 'not_started',
    video_status     ENUM('not_started','in_progress','completed') DEFAULT 'not_started',
    other_status     ENUM('not_started','in_progress','completed') DEFAULT 'not_started',
    progress_percent INT                             DEFAULT 0,
    deadline         DATE,
    deadline_status  ENUM('early','near','late')     DEFAULT 'early',
    notes            TEXT,
    drive_link       VARCHAR(500),
    updated_at       TIMESTAMP                       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL
);

-- -------------------------------------------------------------
-- 7. CANCELLATIONS  (log of cancelled bookings with deposit tracking)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS cancellations (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    booking_id       INT           NOT NULL,
    client_id        INT           NOT NULL,
    reason           TEXT,
    deposit_amount   DECIMAL(10,2) DEFAULT 0,
    deposit_retained DECIMAL(10,2) DEFAULT 0,
    cancellation_status ENUM('pending_approval','approved','rejected') DEFAULT 'pending_approval',
    initiated_by     ENUM('client','admin') DEFAULT 'client',
    reject_reason    TEXT,
    cancelled_at     TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (client_id)  REFERENCES users(id)    ON DELETE CASCADE
);

-- -------------------------------------------------------------
-- 8. REPORTS  (saved/generated report metadata — optional)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS reports (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    generated_by INT          NOT NULL,
    report_type  VARCHAR(100) NOT NULL,      -- e.g. 'monthly_bookings', 'revenue'
    date_from    DATE,
    date_to      DATE,
    file_path    VARCHAR(255),               -- if exported to file
    created_at   TIMESTAMP                   DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE CASCADE
);

-- -------------------------------------------------------------
-- 9. INVOICES
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS invoices (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    booking_id   INT           NOT NULL,
    client_id    INT           NOT NULL,
    amount       DECIMAL(10,2) NOT NULL,
    deposit_paid DECIMAL(10,2) DEFAULT 0,
    balance      DECIMAL(10,2) DEFAULT 0,
    issued_date  DATE          NOT NULL,
    status       ENUM('unpaid','partial','paid') DEFAULT 'unpaid',
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (client_id)  REFERENCES users(id)    ON DELETE CASCADE
);

-- =============================================================
--  SEED: default admin account
--  password: Admin@1234  (bcrypt hash)
-- =============================================================
INSERT INTO users (name, email, password, role, status) VALUES (
    'Admin',
    'admin@harvymancefilms.com',
    '$2y$12$Y5KQXZ1nW8vL3mP9dR7uOuEoHsJtGfAbCdEfGhIjKlMnOpQrStUv',  -- replace with: password_hash('Admin@1234', PASSWORD_BCRYPT)
    'admin',
    'active'
);
