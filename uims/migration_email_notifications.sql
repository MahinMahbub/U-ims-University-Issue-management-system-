-- Run once on an existing U-IMS database (phpMyAdmin > uims > SQL).
-- Optional: the pages add the column and the table by themselves when they are first needed.
USE uims;

-- Staff need an email address so they can be told about their jobs.
-- (Run this line only if the column does not exist yet; it fails harmlessly if it does.)
ALTER TABLE staff ADD COLUMN email VARCHAR(150) NULL AFTER phone;

CREATE TABLE IF NOT EXISTS email_log (
    email_id INT AUTO_INCREMENT PRIMARY KEY,
    issue_id INT NULL,
    staff_id INT NULL,
    sent_by INT NULL,
    to_email VARCHAR(150) NOT NULL,
    to_name VARCHAR(120) NULL,
    subject VARCHAR(200) NOT NULL,
    body TEXT NOT NULL,
    status ENUM('sent','failed','not_configured') NOT NULL,
    error VARCHAR(500) NULL,
    admin_seen TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_email_seen (admin_seen, created_at),
    FOREIGN KEY (issue_id) REFERENCES issues(issue_id) ON DELETE SET NULL,
    FOREIGN KEY (staff_id) REFERENCES staff(staff_id) ON DELETE SET NULL,
    FOREIGN KEY (sent_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;
