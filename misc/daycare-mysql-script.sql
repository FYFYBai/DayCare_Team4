-- 1. Users Table 

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(320) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('parent', 'educator', 'manager') NOT NULL,
    activation_status TINYINT(1) NOT NULL DEFAULT 0,
    isDeleted TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. Children Table

CREATE TABLE children (
    id INT AUTO_INCREMENT PRIMARY KEY,
    parent_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    date_of_birth DATE NOT NULL,
    profile_photo_path VARCHAR(200) NOT NULL,
    isDeleted TINYINT(1) NOT NULL DEFAULT 0,
    FOREIGN KEY (parent_id) REFERENCES users(id)
) ENGINE=InnoDB;
 
-- 3. Registrations Table

CREATE TABLE registrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    child_id INT NOT NULL,
    registration_date DATE NOT NULL,
    status ENUM('present', 'absent') NOT NULL DEFAULT 'absent',
    isDeleted TINYINT(1) NOT NULL DEFAULT 0,
    FOREIGN KEY (child_id) REFERENCES children(id)
) ENGINE=InnoDB;

-- 4. Events Table

CREATE TABLE events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    start_date DATETIME NOT NULL,
    end_date DATETIME NOT NULL,
    description TEXT,
    isDeleted TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB;

-- 5. Payments Table

CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_date DATETIME NOT NULL,
    payment_status ENUM('pending', 'completed', 'failed') NOT NULL DEFAULT 'pending',
    isDeleted TINYINT(1) NOT NULL DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

 -- 6. Event_Children Table
CREATE TABLE event_children (
    event_id INT NOT NULL,
    child_id INT NOT NULL,
    registration_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    isDeleted TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (event_id, child_id),
    FOREIGN KEY (event_id) REFERENCES events(id),
    FOREIGN KEY (child_id) REFERENCES children(id)
) ENGINE=InnoDB;

-- alter table events to add a new column
ALTER TABLE events
    ADD COLUMN created_by INT NOT NULL,
    ADD FOREIGN KEY (created_by) REFERENCES users(id);

-- alter table user to add some new columns
ALTER TABLE users ADD COLUMN isAdmin TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE users ADD COLUMN profile_photo_path VARCHAR(255);
ALTER TABLE users ADD COLUMN reset_token VARCHAR(64);
ALTER TABLE users ADD COLUMN activation_token VARCHAR(64);

ALTER TABLE children ADD COLUMN educator_id INT NOT NULL;
ALTER TABLE users ADD COLUMN reset_expires DATETIME DEFAULT NULL;

Soft Delete Column:
The isDeleted column (with a default value of 0) is used to mark a record as "deleted" without physically removing it from the database. Your application queries should filter by isDeleted = 0 to exclude soft-deleted records.

ALTER TABLE users MODIFY COLUMN role ENUM('parent', 'educator', 'manager', 'admin') NOT NULL;


-- Insert two educators
INSERT INTO users (name, email, password, role, isAdmin, activation_status, isDeleted, profile_photo_path, created_at)
VALUES 
('Educator One', 'educator1@example.com', '$2y$10$ICYFkD05U/KU4uufSA9GwOVe/IiasQVJZkfRIQqu7p07ZDkPJzhcW', 'educator', 0, 1, 0, 'default.png', NOW()), -- Educator123!
('Educator Two', 'educator2@example.com', '$2y$10$Rsx27IlJjS68roR6gFa3du6o3B2vnWAyxEEg6RrooyiTXxVMXSLkS', 'educator', 0, 1, 0, 'default.png', NOW()); -- Educator456!

-- Insert one manager
INSERT INTO users (name, email, password, role, isAdmin, activation_status, isDeleted, profile_photo_path, created_at)
VALUES 
('Manager One', 'manager1@example.com', '$2y$10$o0SxebghsaJLeVXhyXV5MeESye5znKgNrQHK1545fI4Gv6beAHAY2', 'manager', 0, 1, 0, 'default.png', NOW()); -- Manager123!

-- Insert one admin
INSERT INTO users (name, email, password, role, isAdmin, activation_status, isDeleted, profile_photo_path, created_at)
VALUES 
('Admin One', 'admin1@example.com', '$2y$10$cYeeUbbRURq466EksgnUYOc32ppIpWyVaKx/X8FUFxTgTBmSP317C', 'admin', 1, 1, 0, 'default.png', NOW()); -- Admin123!
