CREATE DATABASE lms_db CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE lms_db;


-- users table
CREATE TABLE users (
id INT AUTO_INCREMENT PRIMARY KEY,
username VARCHAR(100) UNIQUE NOT NULL,
password VARCHAR(255) NOT NULL,
fname VARCHAR(100),
lname VARCHAR(100),
email VARCHAR(150) UNIQUE,
role ENUM('admin','proponent','user') DEFAULT 'user',
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
updated_at TIMESTAMP NULL
);

-- proponent could be same users with role='proponent'


-- courses table
CREATE TABLE courses (
id INT AUTO_INCREMENT PRIMARY KEY,
title VARCHAR(255) NOT NULL,
description TEXT,
proponent_id INT NOT NULL, -- fk to users
file_pdf VARCHAR(255) NULL,
file_video VARCHAR(255) NULL,
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
expires_at DATE NULL, -- optional course-level expiration
is_active TINYINT(1) DEFAULT 1,
FOREIGN KEY (proponent_id) REFERENCES users(id) ON DELETE CASCADE
);


-- lessons table (optional: break course into lessons)
CREATE TABLE lessons (
id INT AUTO_INCREMENT PRIMARY KEY,
course_id INT NOT NULL,
title VARCHAR(255),
content TEXT, -- optional text
file_pdf VARCHAR(255) NULL,
file_video VARCHAR(255) NULL,
ord INT DEFAULT 0,
FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
);


-- enrollments table
CREATE TABLE enrollments (
id INT AUTO_INCREMENT PRIMARY KEY,
user_id INT NOT NULL,
course_id INT NOT NULL,
enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
completed_at TIMESTAMP NULL,
expired_at DATE NULL,
progress DECIMAL(5,2) DEFAULT 0, -- percent 0.00 - 100.00
status ENUM('ongoing','completed','expired') DEFAULT 'ongoing',
total_time_seconds INT DEFAULT 0, -- cumulative time spent in seconds
FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
UNIQUE KEY ux_user_course (user_id, course_id)
);


-- lesson completion (if using lessons)
CREATE TABLE lesson_progress (
id INT AUTO_INCREMENT PRIMARY KEY,
enrollment_id INT NOT NULL,
lesson_id INT NOT NULL,
completed_at TIMESTAMP NULL,
FOREIGN KEY (enrollment_id) REFERENCES enrollments(id) ON DELETE CASCADE,
FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE,
UNIQUE KEY ux_enroll_lesson (enrollment_id, lesson_id)
);


-- time tracking log (session-level)
CREATE TABLE time_logs (
id INT AUTO_INCREMENT PRIMARY KEY,
enrollment_id INT NOT NULL,
start_ts TIMESTAMP NOT NULL,
end_ts TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
seconds INT NOT NULL,
user_agent VARCHAR(255) NULL,
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
FOREIGN KEY (enrollment_id) REFERENCES enrollments(id) ON DELETE CASCADE
);


-- news/updates table
CREATE TABLE news (
id INT AUTO_INCREMENT PRIMARY KEY,
title VARCHAR(255),
body TEXT,
created_by INT,
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
is_published TINYINT(1) DEFAULT 1
);


CREATE TABLE IF NOT EXISTS departments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- user_departments junction table for many-to-many relationship
CREATE TABLE IF NOT EXISTS user_departments (
    user_id INT NOT NULL,
    department_id INT NOT NULL,
    PRIMARY KEY (user_id, department_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
);


INSERT INTO departments (name) VALUES
('Anesthetics'),
('Breast Screening'),
('Cardiology'),
('Ear, Nose and Throat (ENT)'),
('Elderly Services Department'),
('Gastroenterology'),
('General Surgery'),
('Gynecology');