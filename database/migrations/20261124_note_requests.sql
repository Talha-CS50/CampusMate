CREATE TABLE note_requests (
    request_id INT AUTO_INCREMENT PRIMARY KEY,
    student_db_id INT NOT NULL,
    course_id INT NOT NULL,
    requested_title VARCHAR(200) NOT NULL,
    details TEXT,
    status ENUM('Open', 'Fulfilled') NOT NULL DEFAULT 'Open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (student_db_id)
        REFERENCES students(student_db_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    FOREIGN KEY (course_id)
        REFERENCES courses(course_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);
