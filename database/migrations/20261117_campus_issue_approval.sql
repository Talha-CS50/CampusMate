ALTER TABLE campus_issues
    ADD COLUMN approval_status ENUM('Pending', 'Approved', 'Rejected')
        NOT NULL DEFAULT 'Pending' AFTER status,
    ADD COLUMN admin_feedback TEXT NULL AFTER approval_status;
