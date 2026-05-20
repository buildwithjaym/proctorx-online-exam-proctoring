CREATE DATABASE IF NOT EXISTS proctorx_db
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE proctorx_db;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS proctor_reports;
DROP TABLE IF EXISTS webcam_snapshots;
DROP TABLE IF EXISTS proctor_logs;
DROP TABLE IF EXISTS student_answers;
DROP TABLE IF EXISTS exam_attempts;
DROP TABLE IF EXISTS choices;
DROP TABLE IF EXISTS questions;
DROP TABLE IF EXISTS exam_proctors;
DROP TABLE IF EXISTS exam_students;
DROP TABLE IF EXISTS exams;
DROP TABLE IF EXISTS class_students;
DROP TABLE IF EXISTS classes;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

-- =========================================================
-- USERS TABLE
-- Stores teachers, students, and proctors
-- =========================================================

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,

    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NULL,
    username VARCHAR(80) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,

    role ENUM('teacher', 'student', 'proctor') NOT NULL,
    status ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active',

    created_by INT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_users_username (username),
    UNIQUE KEY uq_users_email (email),

    INDEX idx_users_role (role),
    INDEX idx_users_status (status),
    INDEX idx_users_role_status (role, status),
    INDEX idx_users_created_by (created_by),

    CONSTRAINT fk_users_created_by
        FOREIGN KEY (created_by)
        REFERENCES users(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================================================
-- CLASSES TABLE
-- Teacher-created class/section grouping
-- Example: BSIT 3A, Grade 10 - Einstein
-- =========================================================

CREATE TABLE classes (
    id INT AUTO_INCREMENT PRIMARY KEY,

    teacher_id INT NOT NULL,

    class_name VARCHAR(120) NOT NULL,
    section VARCHAR(120) NULL,
    school_year VARCHAR(30) NULL,

    status ENUM('active', 'inactive', 'archived') NOT NULL DEFAULT 'active',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_classes_teacher_class_section_sy 
        (teacher_id, class_name, section, school_year),

    INDEX idx_classes_teacher_id (teacher_id),
    INDEX idx_classes_status (status),

    CONSTRAINT fk_classes_teacher
        FOREIGN KEY (teacher_id)
        REFERENCES users(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================================================
-- CLASS STUDENTS TABLE
-- Assigns students to teacher-created classes
-- =========================================================

CREATE TABLE class_students (
    id INT AUTO_INCREMENT PRIMARY KEY,

    class_id INT NOT NULL,
    student_id INT NOT NULL,

    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_class_students (class_id, student_id),

    INDEX idx_class_students_class_id (class_id),
    INDEX idx_class_students_student_id (student_id),

    CONSTRAINT fk_class_students_class
        FOREIGN KEY (class_id)
        REFERENCES classes(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_class_students_student
        FOREIGN KEY (student_id)
        REFERENCES users(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================================================
-- EXAMS TABLE
-- Teacher-created exams
-- =========================================================

CREATE TABLE exams (
    id INT AUTO_INCREMENT PRIMARY KEY,

    teacher_id INT NOT NULL,

    title VARCHAR(180) NOT NULL,
    subject VARCHAR(120) NULL,
    description TEXT NULL,

    duration_minutes INT NOT NULL DEFAULT 60,

    start_datetime DATETIME NOT NULL,
    end_datetime DATETIME NOT NULL,

    total_points DECIMAL(8,2) NOT NULL DEFAULT 0.00,

    randomize_questions TINYINT(1) NOT NULL DEFAULT 0,
    show_result TINYINT(1) NOT NULL DEFAULT 0,
    webcam_required TINYINT(1) NOT NULL DEFAULT 1,
    fullscreen_required TINYINT(1) NOT NULL DEFAULT 1,

    max_attempts INT NOT NULL DEFAULT 1,

    status ENUM('draft', 'published', 'closed', 'archived') NOT NULL DEFAULT 'draft',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_exams_teacher_id (teacher_id),
    INDEX idx_exams_status (status),
    INDEX idx_exams_schedule (start_datetime, end_datetime),
    INDEX idx_exams_teacher_status (teacher_id, status),

    CONSTRAINT fk_exams_teacher
        FOREIGN KEY (teacher_id)
        REFERENCES users(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================================================
-- EXAM STUDENTS TABLE
-- Students assigned to take an exam
-- =========================================================

CREATE TABLE exam_students (
    id INT AUTO_INCREMENT PRIMARY KEY,

    exam_id INT NOT NULL,
    student_id INT NOT NULL,

    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_exam_students (exam_id, student_id),

    INDEX idx_exam_students_exam_id (exam_id),
    INDEX idx_exam_students_student_id (student_id),

    CONSTRAINT fk_exam_students_exam
        FOREIGN KEY (exam_id)
        REFERENCES exams(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_exam_students_student
        FOREIGN KEY (student_id)
        REFERENCES users(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================================================
-- EXAM PROCTORS TABLE
-- Proctors assigned to monitor an exam
-- =========================================================

CREATE TABLE exam_proctors (
    id INT AUTO_INCREMENT PRIMARY KEY,

    exam_id INT NOT NULL,
    proctor_id INT NOT NULL,

    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_exam_proctors (exam_id, proctor_id),

    INDEX idx_exam_proctors_exam_id (exam_id),
    INDEX idx_exam_proctors_proctor_id (proctor_id),

    CONSTRAINT fk_exam_proctors_exam
        FOREIGN KEY (exam_id)
        REFERENCES exams(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_exam_proctors_proctor
        FOREIGN KEY (proctor_id)
        REFERENCES users(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================================================
-- QUESTIONS TABLE
-- Exam questions
-- MVP supports multiple choice and true/false
-- =========================================================

CREATE TABLE questions (
    id INT AUTO_INCREMENT PRIMARY KEY,

    exam_id INT NOT NULL,

    question_text TEXT NOT NULL,

    question_type ENUM('multiple_choice', 'true_false') NOT NULL DEFAULT 'multiple_choice',

    points DECIMAL(6,2) NOT NULL DEFAULT 1.00,

    position INT NOT NULL DEFAULT 1,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_questions_exam_id (exam_id),
    INDEX idx_questions_exam_position (exam_id, position),

    CONSTRAINT fk_questions_exam
        FOREIGN KEY (exam_id)
        REFERENCES exams(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================================================
-- CHOICES TABLE
-- Choices/options for questions
-- Also used for true/false
-- =========================================================

CREATE TABLE choices (
    id INT AUTO_INCREMENT PRIMARY KEY,

    question_id INT NOT NULL,

    choice_text TEXT NOT NULL,
    is_correct TINYINT(1) NOT NULL DEFAULT 0,

    position INT NOT NULL DEFAULT 1,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_choices_question_id (question_id),
    INDEX idx_choices_question_position (question_id, position),
    INDEX idx_choices_is_correct (is_correct),

    CONSTRAINT fk_choices_question
        FOREIGN KEY (question_id)
        REFERENCES questions(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================================================
-- EXAM ATTEMPTS TABLE
-- One row per student exam session
-- =========================================================

CREATE TABLE exam_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,

    exam_id INT NOT NULL,
    student_id INT NOT NULL,

    attempt_no INT NOT NULL DEFAULT 1,

    started_at DATETIME NOT NULL,
    submitted_at DATETIME NULL,

    attempt_status ENUM(
        'in_progress',
        'submitted',
        'auto_submitted',
        'expired'
    ) NOT NULL DEFAULT 'in_progress',

    review_status ENUM(
        'normal',
        'flagged',
        'under_review',
        'cleared',
        'invalidated'
    ) NOT NULL DEFAULT 'normal',

    score DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    total_points_at_time DECIMAL(8,2) NOT NULL DEFAULT 0.00,

    violation_count INT NOT NULL DEFAULT 0,

    flagged_by INT NULL,
    proctor_remarks TEXT NULL,

    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,

    last_activity_at DATETIME NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_exam_attempt_student_attempt 
        (exam_id, student_id, attempt_no),

    INDEX idx_exam_attempts_exam_id (exam_id),
    INDEX idx_exam_attempts_student_id (student_id),
    INDEX idx_exam_attempts_status (attempt_status),
    INDEX idx_exam_attempts_review_status (review_status),
    INDEX idx_exam_attempts_exam_status (exam_id, attempt_status),
    INDEX idx_exam_attempts_flagged_by (flagged_by),

    CONSTRAINT fk_exam_attempts_exam
        FOREIGN KEY (exam_id)
        REFERENCES exams(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_exam_attempts_student
        FOREIGN KEY (student_id)
        REFERENCES users(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT fk_exam_attempts_flagged_by
        FOREIGN KEY (flagged_by)
        REFERENCES users(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================================================
-- STUDENT ANSWERS TABLE
-- Stores auto-saved and final submitted answers
-- =========================================================

CREATE TABLE student_answers (
    id INT AUTO_INCREMENT PRIMARY KEY,

    attempt_id INT NOT NULL,
    question_id INT NOT NULL,
    choice_id INT NULL,

    answer_text TEXT NULL,

    is_correct TINYINT(1) NOT NULL DEFAULT 0,
    points_awarded DECIMAL(6,2) NOT NULL DEFAULT 0.00,

    answered_at DATETIME NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_student_answer_question 
        (attempt_id, question_id),

    INDEX idx_student_answers_attempt_id (attempt_id),
    INDEX idx_student_answers_question_id (question_id),
    INDEX idx_student_answers_choice_id (choice_id),

    CONSTRAINT fk_student_answers_attempt
        FOREIGN KEY (attempt_id)
        REFERENCES exam_attempts(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_student_answers_question
        FOREIGN KEY (question_id)
        REFERENCES questions(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_student_answers_choice
        FOREIGN KEY (choice_id)
        REFERENCES choices(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================================================
-- PROCTOR LOGS TABLE
-- System-generated suspicious activity logs
-- =========================================================

CREATE TABLE proctor_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,

    attempt_id INT NOT NULL,

    event_type ENUM(
        'tab_switch',
        'window_blur',
        'fullscreen_exit',
        'copy_attempt',
        'paste_attempt',
        'right_click',
        'camera_disabled',
        'camera_restored',
        'inactivity',
        'snapshot_failed',
        'network_issue',
        'manual_flag'
    ) NOT NULL,

    severity ENUM('low', 'medium', 'high') NOT NULL DEFAULT 'low',

    event_description VARCHAR(255) NOT NULL,

    metadata_json JSON NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_proctor_logs_attempt_id (attempt_id),
    INDEX idx_proctor_logs_event_type (event_type),
    INDEX idx_proctor_logs_severity (severity),
    INDEX idx_proctor_logs_attempt_time (attempt_id, created_at),

    CONSTRAINT fk_proctor_logs_attempt
        FOREIGN KEY (attempt_id)
        REFERENCES exam_attempts(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================================================
-- WEBCAM SNAPSHOTS TABLE
-- Stores webcam snapshot file paths
-- =========================================================

CREATE TABLE webcam_snapshots (
    id INT AUTO_INCREMENT PRIMARY KEY,

    attempt_id INT NOT NULL,

    image_path VARCHAR(255) NOT NULL,

    status ENUM('captured', 'failed') NOT NULL DEFAULT 'captured',

    captured_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_webcam_snapshots_attempt_id (attempt_id),
    INDEX idx_webcam_snapshots_attempt_time (attempt_id, captured_at),

    CONSTRAINT fk_webcam_snapshots_attempt
        FOREIGN KEY (attempt_id)
        REFERENCES exam_attempts(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================================================
-- PROCTOR REPORTS TABLE
-- Proctor's human review/remarks per student attempt
-- =========================================================

CREATE TABLE proctor_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,

    attempt_id INT NOT NULL,
    proctor_id INT NOT NULL,

    remarks TEXT NULL,

    recommendation ENUM(
        'normal',
        'needs_review',
        'suspicious'
    ) NOT NULL DEFAULT 'normal',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_proctor_report_attempt_proctor 
        (attempt_id, proctor_id),

    INDEX idx_proctor_reports_attempt_id (attempt_id),
    INDEX idx_proctor_reports_proctor_id (proctor_id),
    INDEX idx_proctor_reports_recommendation (recommendation),

    CONSTRAINT fk_proctor_reports_attempt
        FOREIGN KEY (attempt_id)
        REFERENCES exam_attempts(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_proctor_reports_proctor
        FOREIGN KEY (proctor_id)
        REFERENCES users(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;