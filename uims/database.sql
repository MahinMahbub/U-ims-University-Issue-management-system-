CREATE DATABASE IF NOT EXISTS uims CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE uims;

CREATE TABLE roles (
    role_id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(30) NOT NULL UNIQUE
);

CREATE TABLE departments (
    department_id INT AUTO_INCREMENT PRIMARY KEY,
    department_name VARCHAR(100) NOT NULL UNIQUE
);

CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    role_id INT NOT NULL,
    department_id INT NULL,
    student_id VARCHAR(50) NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    batch VARCHAR(30) NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(role_id),
    FOREIGN KEY (department_id) REFERENCES departments(department_id) ON DELETE SET NULL
);

CREATE TABLE categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(80) NOT NULL UNIQUE
);

CREATE TABLE locations (
    location_id INT AUTO_INCREMENT PRIMARY KEY,
    location_name VARCHAR(150) NOT NULL UNIQUE
);

CREATE TABLE issues (
    issue_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    category_id INT NOT NULL,
    location_id INT NOT NULL,
    department_id INT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    observed_at DATETIME NULL,
    priority ENUM('Low','Medium','High','Critical') DEFAULT 'Medium',
    status ENUM('Pending Verification','More Information','Under Review','Verified','Rejected','In Progress','Resolved','Closed') DEFAULT 'Pending Verification',
    rejection_reason TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(category_id),
    FOREIGN KEY (location_id) REFERENCES locations(location_id),
    FOREIGN KEY (department_id) REFERENCES departments(department_id) ON DELETE SET NULL
);

CREATE TABLE evidence (
    evidence_id INT AUTO_INCREMENT PRIMARY KEY,
    issue_id INT NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_type VARCHAR(100) NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (issue_id) REFERENCES issues(issue_id) ON DELETE CASCADE
);

CREATE TABLE issue_updates (
    update_id INT AUTO_INCREMENT PRIMARY KEY,
    issue_id INT NOT NULL,
    authority_id INT NOT NULL,
    status VARCHAR(50) NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (issue_id) REFERENCES issues(issue_id) ON DELETE CASCADE,
    FOREIGN KEY (authority_id) REFERENCES users(user_id) ON DELETE CASCADE
);

CREATE TABLE notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    issue_id INT NULL,
    message VARCHAR(255) NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (issue_id) REFERENCES issues(issue_id) ON DELETE CASCADE
);


INSERT INTO roles (role_name) VALUES ('Student'), ('Authority'), ('Administrator');

INSERT INTO departments (department_name) VALUES
('Administration'), ('Electrical'), ('IT Support'), ('Academic'), ('Facilities'), ('Security');

INSERT INTO categories (category_name) VALUES
('Infrastructure'), ('Education / Academic'), ('IT / Technology'),
('Sanitation'), ('Security'), ('Other');

INSERT INTO locations (location_name) VALUES
('Main Academic Building'), ('Academic Building - Room 305'),
('Computer Lab'), ('Library'), ('Cafeteria'), ('Main Gate');


INSERT INTO users
(role_id, department_id, student_id, name, email, batch, password_hash)
VALUES
(1, NULL, 'STU001', 'Demo Student', 'student@uims.local', '2025', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC2xYfK4a7y1X7X2uW5K'),
(2, 3, NULL, 'Demo Authority', 'authority@uims.local', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC2xYfK4a7y1X7X2uW5K'),
(3, 1, NULL, 'Demo Administrator', 'admin@uims.local', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC2xYfK4a7y1X7X2uW5K');

