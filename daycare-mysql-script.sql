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

/* -- 6. Event_Attendees Table

CREATE TABLE event_attendees (
    event_id INT NOT NULL,
    user_id INT NOT NULL,
    isDeleted TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (event_id, user_id),
    FOREIGN KEY (event_id) REFERENCES events(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS event_attendees;

 */

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


Soft Delete Column:
The isDeleted column (with a default value of 0) is used to mark a record as "deleted" without physically removing it from the database. Your application queries should filter by isDeleted = 0 to exclude soft-deleted records.