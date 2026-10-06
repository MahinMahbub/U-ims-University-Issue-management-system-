-- Run once on an existing U-IMS database (phpMyAdmin > uims > SQL).
-- The pages also create the two tables automatically if they are missing,
-- so this file is optional, but running it keeps everything in one place.
USE uims;

CREATE TABLE IF NOT EXISTS staff (
    staff_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    staff_role ENUM('Supervisor','Attendant') NOT NULL,
    phone VARCHAR(30) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_staff_role (staff_role, is_active)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS issue_assignments (
    assignment_id INT AUTO_INCREMENT PRIMARY KEY,
    issue_id INT NOT NULL,
    staff_id INT NOT NULL,
    assigned_by INT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_issue_staff (issue_id, staff_id),
    FOREIGN KEY (issue_id) REFERENCES issues(issue_id) ON DELETE CASCADE,
    FOREIGN KEY (staff_id) REFERENCES staff(staff_id) ON DELETE RESTRICT,
    FOREIGN KEY (assigned_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- The demo accounts shipped with the old database.sql had a password hash that
-- did not match the word "password", so the demo logins never worked.
-- This only touches rows that still carry that broken hash.
UPDATE users
SET password_hash = '$2y$10$vSs6Z2JyzLK.BLOl/xWwlOR16vYyZQovUm/5X2ncabLPcTZ3.FSrS'
WHERE password_hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC2xYfK4a7y1X7X2uW5K'
  AND email IN ('student@uims.local', 'authority@uims.local', 'admin@uims.local');
