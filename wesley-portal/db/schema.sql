-- Wesley Portal database schema (MySQL)
CREATE DATABASE IF NOT EXISTS `wesley_portal` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `wesley_portal`;

CREATE TABLE IF NOT EXISTS `admins` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(100) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `students` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `matric` VARCHAR(50) NOT NULL UNIQUE,
  `full_name` VARCHAR(255) NOT NULL,
  `department` VARCHAR(255) DEFAULT '',
  `programme` VARCHAR(255) DEFAULT '',
  `level` VARCHAR(50) DEFAULT '',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `semesters` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT NOT NULL,
  `session_name` VARCHAR(50) NOT NULL,
  `semester_name` VARCHAR(50) NOT NULL,
  `gpa` DECIMAL(4,2) DEFAULT 0.00,
  `cgpa` DECIMAL(4,2) DEFAULT 0.00,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS `courses` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `semester_id` INT NOT NULL,
  `code` VARCHAR(30) NOT NULL,
  `title` VARCHAR(255) DEFAULT '',
  `units` INT DEFAULT 0,
  `ca` INT DEFAULT 0,
  `exam` INT DEFAULT 0,
  `total` INT DEFAULT 0,
  `grade` CHAR(1) DEFAULT 'F',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (semester_id) REFERENCES semesters(id) ON DELETE CASCADE
);

CREATE INDEX idx_students_matric ON students(matric);
CREATE INDEX idx_semesters_student ON semesters(student_id);
CREATE INDEX idx_courses_semester ON courses(semester_id);

-- Track processed uploads to prevent duplicate processing
CREATE TABLE IF NOT EXISTS `uploads` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `file_hash` VARCHAR(80) NOT NULL UNIQUE,
  `file_name` VARCHAR(255) NOT NULL,
  `size` BIGINT NOT NULL,
  `processed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
