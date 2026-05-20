# ProctorX

## Distributed Online Exam Proctoring System

ProctorX is a full-stack web-based online examination and proctoring system built with PHP, HTML, CSS, JavaScript, MySQL, and XAMPP. It allows teachers to create and manage online exams, students to take exams through a guided and monitored interface, and proctors to monitor exam activity through assigned exam dashboards.

The system supports automatic checking for objective questions, manual checking for essay questions, student-centered result review, basic proctoring event logging, violation tracking, and role-based access control.

---

## Table of Contents

1. [Project Overview](#project-overview)
2. [Main Objective](#main-objective)
3. [System Users](#system-users)
4. [Core Features](#core-features)
5. [Technology Stack](#technology-stack)
6. [How Distributed Computing Applies](#how-distributed-computing-applies)
7. [System Architecture](#system-architecture)
8. [System Flow](#system-flow)
9. [Main Modules](#main-modules)
10. [Question Types and Checking Logic](#question-types-and-checking-logic)
11. [Proctoring Logic](#proctoring-logic)
12. [Suggested Project Structure](#suggested-project-structure)
13. [Database Tables](#database-tables)
14. [Required Database Updates](#required-database-updates)
15. [Installation Guide](#installation-guide)
16. [Testing Checklist](#testing-checklist)
17. [Known Limitations](#known-limitations)
18. [Future Improvements](#future-improvements)
19. [Security Notes](#security-notes)
20. [Repository Information](#repository-information)

---

## Project Overview

ProctorX is a Distributed Online Exam Proctoring System designed for academic online examinations. It provides a structured platform where teachers can prepare exams, students can take exams remotely, and proctors can monitor student behavior during exam sessions.

The system is intended as an academic full-stack web application project that demonstrates exam management, role-based access, answer processing, result review, and basic distributed proctoring behavior using browser-side event detection and server-side logging.

---

## Main Objective

The main objective of ProctorX is to provide a simple, functional, and organized online exam system where:

- Teachers can create and manage exams.
- Students can take exams online.
- Proctors can monitor assigned exam sessions.
- Suspicious activity can be logged during exam-taking.
- Results can be reviewed and finalized by teachers.
- Essay answers can be manually checked.
- Exam activity can be monitored from different user devices.

---

## System Users

ProctorX has three main user roles.

### 1. Teacher

The teacher is responsible for academic and administrative exam management. The teacher can create students, create proctors, create classes, create exams, add questions, assign students, assign proctors, view results, and manually review answers.

### 2. Student

The student is the exam taker. The student can view assigned exams, read instructions, take exams one question at a time, auto-save answers, submit exams, and view results.

### 3. Proctor

The proctor is responsible for monitoring assigned exam sessions. The proctor can view assigned exams, monitor active attempts, view proctoring event logs, flag attempts, mark attempts under review, and clear attempts.

---

## Core Features

### Teacher Features

- Teacher dashboard
- Student management
- Proctor management
- Class management
- Exam management
- Question management
- Student assignment to exams
- Proctor assignment to exams
- Question types:
  - Multiple Choice
  - True or False
  - Identification
  - Essay
- Result viewing per student
- Answer review
- Correct and wrong answer indicators
- Essay manual scoring
- Teacher feedback for essay answers
- Review status update:
  - Normal
  - Under Review
  - Flagged
  - Cleared
  - Invalidated

### Student Features

- Student dashboard
- Assigned exams page
- Exam status tracking:
  - Available
  - Upcoming
  - Completed
  - Closed
- Exam instruction page
- One-question-at-a-time exam-taking interface
- Back and Next navigation
- Auto-save answers
- Essay word count
- Exam timer
- Exam submission
- Student result page
- Answer review when allowed

### Proctor Features

- Proctor dashboard
- Assigned exams page
- Exam monitoring page
- Attempt activity view
- Active attempt tracking
- Submitted attempt tracking
- Violation count display
- Proctoring event timeline
- Flag attempt
- Mark under review
- Clear attempt

### System Features

- Role-based authentication
- Role-based page protection
- CSRF protection
- Password hashing
- Auto-checking for objective questions
- Manual checking for essays
- Proctoring event logging
- Violation count tracking
- Last activity tracking
- Centralized result review

---

## Technology Stack

### Frontend

- HTML
- CSS
- JavaScript

### Backend

- PHP

### Database

- MySQL / MariaDB

### Local Development Server

- XAMPP

### Recommended Environment

- PHP 7.x or higher
- MySQL or MariaDB
- Apache through XAMPP
- Google Chrome, Microsoft Edge, or Mozilla Firefox

---

## How Distributed Computing Applies

ProctorX applies distributed computing concepts at the application level.

This system is not a high-performance distributed cluster, cloud microservice platform, or multi-server architecture. Instead, it demonstrates distributed computing through the way multiple independent client devices participate in exam-taking, monitoring, event detection, and centralized coordination at the same time.

In ProctorX, the students, teachers, and proctors use different devices and browsers. Each device performs part of the overall system work while the PHP server and MySQL database coordinate and store the shared state.

### 1. Distributed Student Devices

Each student takes the exam using their own device and browser. These devices act as distributed exam-taking nodes.

Each student device performs local tasks such as:

- Displaying the exam interface
- Showing one question at a time
- Handling Back and Next navigation
- Capturing selected answers
- Counting essay words
- Running the exam timer
- Detecting tab switching
- Detecting copy and paste attempts
- Detecting right-click attempts
- Detecting fullscreen exit
- Detecting inactivity

This means not all work happens on the server. Some work is distributed to the student browsers.

### 2. Parallel Exam Sessions

Multiple students can take the same exam at the same time.

Each student has a separate exam attempt record. While one student is answering question 1, another student may be answering question 5, and another may already be submitting.

The system handles parallel activity such as:

- Multiple students saving answers
- Multiple students submitting exams
- Multiple browsers logging proctoring events
- Multiple attempts updating violation counts
- Proctors monitoring different students at the same time
- Teachers reviewing submitted attempts

This demonstrates parallel behavior in a web-based distributed system.

### 3. Browser as a Distributed Monitoring Node

The student browser acts as a monitoring node during exam-taking.

For example, when a student switches tabs, the browser detects the event using JavaScript and sends it to the server.

Basic event flow:

```text
Student Browser
↓
Detects suspicious behavior
↓
Sends event to PHP server
↓
Server validates the request
↓
Server stores event in proctor_logs
↓
Server updates exam_attempts.violation_count
↓
Proctor sees updated activity
```

The detection happens on the student device, while validation, logging, storage, and review happen on the server side.

### 4. Central Server Coordination

The PHP server acts as the central coordinator of the distributed system.

It receives requests from:

- Teacher browsers
- Student browsers
- Proctor browsers

The server coordinates:

- Authentication
- Role access control
- Exam availability
- Answer saving
- Exam submission
- Score computation
- Proctoring event logging
- Violation updates
- Result review
- Manual essay checking

Even though the users are distributed across different devices, the PHP server controls the business logic and validates all important actions.

### 5. Shared Database as Central State

The MySQL database stores the shared state of the system.

It contains:

- User accounts
- Classes
- Exams
- Questions
- Choices
- Exam assignments
- Proctor assignments
- Student attempts
- Student answers
- Proctoring logs
- Review statuses
- Scores

All users interact with the same database indirectly through the PHP server. This allows teachers, students, and proctors to work with consistent shared data.

### 6. Distributed Supervision

The proctor can monitor students who are taking exams from different devices and locations.

Students do not need to be physically beside the proctor. The system gathers activity from distributed student devices and presents it in one proctor monitoring dashboard.

Example:

```text
Student Device 1 → Sends answer saves and proctoring logs
Student Device 2 → Sends answer saves and proctoring logs
Student Device 3 → Sends answer saves and proctoring logs
                              ↓
                         PHP Server
                              ↓
                         MySQL Database
                              ↓
                      Proctor Monitoring Page
```

This is distributed supervision because the student activity is separated across multiple devices but monitored through a centralized system.

### 7. Concurrent Data Processing

ProctorX supports concurrent actions such as:

- Many students taking an exam at the same time
- Many auto-save requests happening during an exam
- Many proctoring logs being recorded from different browsers
- Proctors viewing active attempts while students are still answering
- Teachers reviewing submitted results while proctors monitor live attempts

The server processes these independent requests and stores the results in the database.

### 8. Distributed Computing Summary

Distributed computing applies to ProctorX because:

- Users access the system from different devices.
- Student browsers perform local exam and proctoring tasks.
- Multiple students can take exams in parallel.
- The server receives and processes distributed requests.
- The database stores the central state.
- Proctors monitor remote student activity.
- Teachers review results generated from distributed exam sessions.

In simple terms, ProctorX distributes exam-taking and proctoring activity across many student devices while using a central PHP server and MySQL database to coordinate, store, and review all activity.

---

## System Architecture

```text
Teacher Browser
      ↓
      ↓
Student Browser  →  Apache/PHP Server  →  MySQL Database
      ↑                    ↓
      ↑                    ↓
Proctor Browser  ←  Monitoring Data and Logs
```

### Architecture Explanation

- Teacher Browser: Used for exam creation, management, and review.
- Student Browser: Used for taking exams and sending answer/proctoring data.
- Proctor Browser: Used for monitoring live attempts and reviewing suspicious events.
- PHP Server: Handles business logic, validation, authentication, and request processing.
- MySQL Database: Stores all shared system data.

---

## System Flow

### Teacher Flow

```text
Teacher Login
↓
Teacher Dashboard
↓
Manage Students
↓
Manage Proctors
↓
Manage Classes
↓
Create Exam
↓
Assign Students
↓
Assign Proctors
↓
Add Questions
↓
Wait for Student Submission
↓
Open Results
↓
View Results Per Student
↓
View Answers
↓
Check Correct/Wrong Answers
↓
Manually Score Essays
↓
Finalize Review Status
```

### Student Flow

```text
Student Login
↓
Student Dashboard
↓
Assigned Exams
↓
Open Exam Instructions
↓
Start Exam
↓
Take Exam One Question at a Time
↓
Auto-save Answers
↓
Submit Exam
↓
View Result
```

### Proctor Flow

```text
Proctor Login
↓
Proctor Dashboard
↓
Assigned Exams
↓
Monitor Exam
↓
View Active Student Attempts
↓
View Attempt Activity
↓
Check Proctoring Timeline
↓
Flag / Under Review / Clear Attempt
```

---

## Main Modules

### Authentication Module

Handles login, logout, role-based redirection, and protected access.

Important files:

```text
login.php
logout.php
includes/auth.php
includes/functions.php
```

### Teacher Module

Handles teacher dashboard, students, proctors, classes, exams, questions, results, and manual review.

Important files:

```text
teacher/dashboard.php
teacher/students.php
teacher/proctors.php
teacher/classes.php
teacher/exams.php
teacher/questions.php
teacher/results.php
teacher/review_attempt.php
```

### Student Module

Handles student dashboard, assigned exams, instructions, exam-taking, submission, and result viewing.

Important files:

```text
student/dashboard.php
student/exams.php
student/exam_instructions.php
student/take_exam.php
student/result.php
```

### Proctor Module

Handles proctor dashboard, assigned exams, exam monitoring, and attempt activity view.

Important files:

```text
proctor/dashboard.php
proctor/assigned_exams.php
proctor/monitor_exam.php
proctor/view_attempt.php
```

### Action Handlers

Handles backend processing for AJAX and form actions.

Important files:

```text
actions/save_answer.php
actions/submit_exam.php
actions/log_event.php
```

---

## Question Types and Checking Logic

### Multiple Choice

- Stored in the `questions` table.
- Choices A, B, C, and D are stored in the `choices` table.
- One choice is marked as correct.
- Automatically checked by the system.

### True or False

- Stored in the `questions` table.
- The choices `True` and `False` are stored in the `choices` table.
- One choice is marked as correct.
- Automatically checked by the system.

### Identification

- Stored in the `questions` table.
- One correct answer is stored in the `choices` table.
- Automatically checked by comparing the student answer with the stored correct answer.
- Matching is normalized by trimming spaces and ignoring letter case.

### Essay

- Stored only in the `questions` table.
- No correct answer is stored.
- The student writes a free response.
- The teacher manually reads and scores the answer.
- Essay questions place the attempt under review until manually checked.

---

## Proctoring Logic

During the exam, JavaScript runs in the student browser and detects suspicious activities.

Supported proctoring events:

```text
tab_switch
window_blur
fullscreen_exit
copy_attempt
paste_attempt
right_click
inactivity
```

When an event happens:

```text
Browser detects event
↓
JavaScript sends event to actions/log_event.php
↓
PHP validates the student and attempt
↓
Event is inserted into proctor_logs
↓
exam_attempts.violation_count increases
↓
exam_attempts.review_status may become under_review
↓
Proctor can view the event
```

---

## Suggested Project Structure

```text
proctorx/
│
├── actions/
│   ├── log_event.php
│   ├── save_answer.php
│   └── submit_exam.php
│
├── assets/
│   ├── css/
│   │   ├── auth.css
│   │   ├── dashboard.css
│   │   ├── students.css
│   │   ├── proctors.css
│   │   ├── classes.css
│   │   ├── exams.css
│   │   ├── questions.css
│   │   ├── student-dashboard.css
│   │   ├── student-exams.css
│   │   ├── exam-instructions.css
│   │   ├── take-exam.css
│   │   ├── student-result.css
│   │   ├── teacher-results.css
│   │   ├── proctor-dashboard.css
│   │   ├── proctor-assigned-exams.css
│   │   ├── proctor-monitor.css
│   │   └── proctor-view-attempt.css
│   │
│   └── js/
│       ├── questions.js
│       ├── student-exams.js
│       ├── take-exam.js
│       ├── proctoring.js
│       ├── proctor-assigned-exams.js
│       ├── proctor-monitor.js
│       └── proctor-view-attempt.js
│
├── config/
│   └── database.php
│
├── includes/
│   ├── auth.php
│   ├── dashboard_footer.php
│   ├── dashboard_header.php
│   ├── functions.php
│   └── sidebar.php
│
├── proctor/
│   ├── dashboard.php
│   ├── assigned_exams.php
│   ├── monitor_exam.php
│   └── view_attempt.php
│
├── student/
│   ├── dashboard.php
│   ├── exams.php
│   ├── exam_instructions.php
│   ├── take_exam.php
│   └── result.php
│
├── teacher/
│   ├── dashboard.php
│   ├── students.php
│   ├── proctors.php
│   ├── classes.php
│   ├── exams.php
│   ├── questions.php
│   ├── results.php
│   └── review_attempt.php
│
├── index.php
├── login.php
├── logout.php
└── README.md
```

---

## Database Tables

Main database tables:

```text
users
classes
class_students
exams
exam_students
exam_proctors
questions
choices
exam_attempts
student_answers
proctor_logs
```

### users

Stores teacher, student, and proctor accounts.

Common fields:

```text
id
full_name
username
email
password_hash
role
status
created_by
created_at
updated_at
```

### classes

Stores teacher-created class records.

Common fields:

```text
id
teacher_id
class_name
section
school_year
status
created_at
updated_at
```

### class_students

Stores students assigned to classes.

Common fields:

```text
id
class_id
student_id
created_at
```

### exams

Stores exam information.

Common fields:

```text
id
teacher_id
title
subject
description
duration_minutes
start_datetime
end_datetime
total_points
randomize_questions
show_result
webcam_required
fullscreen_required
max_attempts
status
created_at
updated_at
```

### exam_students

Stores student assignments to exams.

Common fields:

```text
id
exam_id
student_id
created_at
```

### exam_proctors

Stores proctor assignments to exams.

Common fields:

```text
id
exam_id
proctor_id
created_at
```

### questions

Stores exam questions.

Common fields:

```text
id
exam_id
question_text
question_type
points
position
created_at
updated_at
```

### choices

Stores choices and correct answers for multiple choice, true or false, and identification questions.

Common fields:

```text
id
question_id
choice_text
is_correct
position
created_at
updated_at
```

### exam_attempts

Stores each student exam attempt.

Common fields:

```text
id
exam_id
student_id
attempt_status
review_status
score
total_points_at_time
violation_count
started_at
submitted_at
last_activity_at
created_at
updated_at
```

### student_answers

Stores submitted answers.

Common fields:

```text
id
attempt_id
question_id
choice_id
answer_text
points_awarded
teacher_feedback
checked_at
created_at
updated_at
```

### proctor_logs

Stores proctoring event logs.

Common fields:

```text
id
attempt_id
event_type
severity
event_description
metadata_json
created_at
```

---

## Required Database Updates

### Question Type Update

The `questions.question_type` field should support:

```text
multiple_choice
true_false
identification
essay
```

SQL:

```sql
ALTER TABLE questions 
MODIFY question_type ENUM('multiple_choice', 'true_false', 'identification', 'essay') 
NOT NULL DEFAULT 'multiple_choice';
```

### Student Answer Review Fields

The `student_answers` table should include teacher review fields.

SQL:

```sql
ALTER TABLE student_answers
ADD COLUMN points_awarded DECIMAL(10,2) NULL AFTER answer_text,
ADD COLUMN teacher_feedback TEXT NULL AFTER points_awarded,
ADD COLUMN checked_at DATETIME NULL AFTER teacher_feedback;
```

### Proctor Logs Table

If the `proctor_logs` table does not exist, create it.

SQL:

```sql
CREATE TABLE proctor_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    attempt_id INT NOT NULL,
    event_type VARCHAR(80) NOT NULL,
    severity ENUM('low', 'medium', 'high') NOT NULL DEFAULT 'low',
    event_description TEXT NOT NULL,
    metadata_json TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_proctor_logs_attempt_id (attempt_id),
    INDEX idx_proctor_logs_event_type (event_type),
    INDEX idx_proctor_logs_severity (severity),
    INDEX idx_proctor_logs_created_at (created_at),

    CONSTRAINT fk_proctor_logs_attempt
        FOREIGN KEY (attempt_id)
        REFERENCES exam_attempts(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Last Activity Field

The `exam_attempts` table should include `last_activity_at`.

SQL:

```sql
ALTER TABLE exam_attempts
ADD COLUMN last_activity_at DATETIME NULL AFTER submitted_at;
```

---

## Installation Guide

### 1. Install XAMPP

Install XAMPP and start:

```text
Apache
MySQL
```

### 2. Place the Project Folder

Move the project folder to:

```text
C:\xampp\htdocs\
```

Example:

```text
C:\xampp\htdocs\proctorx
```

### 3. Create the Database

Open phpMyAdmin:

```text
http://localhost/phpmyadmin
```

Create a database named:

```text
proctorx_db
```

Import your SQL schema.

### 4. Configure the Database

Open:

```text
config/database.php
```

Recommended local configuration:

```php
<?php

date_default_timezone_set('Asia/Manila');

define('APP_URL', '/proctorx');

$host = "localhost";
$dbname = "proctorx_db";
$db_username = "root";
$db_password = "";

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $db_username,
        $db_password
    );

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $pdo->exec("SET time_zone = '+08:00'");
} catch (PDOException $e) {
    die("Database connection failed.");
}
```

### 5. Run the System

Open:

```text
http://localhost/proctorx
```

or:

```text
http://localhost/proctorx/login.php
```

---

## Testing Checklist

### Teacher Testing

```text
Login as teacher
Create student
Create proctor
Create class
Create exam
Assign student to exam
Assign proctor to exam
Add multiple choice question
Add true or false question
Add identification question
Add essay question
Wait for student submission
Open results
View results per student
View answers
Score essay
Finalize review status
```

### Student Testing

```text
Login as student
Open dashboard
Open assigned exams
Open exam instructions
Start exam
Answer multiple choice
Answer true or false
Answer identification
Answer essay
Use Back and Next buttons
Submit exam
View result
```

### Proctor Testing

```text
Login as proctor
Open dashboard
Open assigned exams
Monitor exam
View active attempts
View attempt activity
Check proctoring logs
Flag attempt
Mark under review
Clear attempt
```

### Proctoring Event Testing

While taking an exam as a student, test:

```text
Switch tab
Click outside the browser
Right click
Copy text
Paste text
Exit fullscreen
Stay inactive for 60 seconds
```

Then verify:

```text
proctor_logs table has new records
exam_attempts.violation_count increases
exam_attempts.review_status becomes under_review when needed
```

---

## Known Limitations

This is an MVP and uses basic browser-based proctoring only.

The system does not yet include:

- Real webcam recording
- AI face detection
- Audio monitoring
- Screen recording
- Advanced plagiarism detection
- WebSocket real-time monitoring
- Multi-server deployment
- Email notifications
- PDF report export
- Excel export
- Admin role
- Password reset

---

## Future Improvements

Possible future upgrades:

- Webcam snapshot capture
- Real-time monitoring using WebSockets
- Student results list page
- Printable result summary
- Export results to PDF
- Export results to Excel
- Proctor remarks
- Teacher remarks
- Stronger analytics dashboard
- Admin role
- Password reset
- Email notifications
- Multi-school support
- Deployment to live hosting
- Better mobile optimization
- Audit trail for all role actions

---

## Security Notes

Current security features:

- Role-based access control
- Session-based authentication
- Password hashing
- CSRF token validation
- Protected teacher, student, and proctor pages
- Server-side validation for important actions

Recommended security improvements:

- Use HTTPS in production
- Add password reset
- Add stronger password policy
- Add login attempt limit
- Add account lockout after repeated failed login attempts
- Add audit logs
- Validate file permissions before deployment
- Hide detailed PHP errors in production

---

## Repository Information

### Suggested Repository Name

```text
proctorx-online-exam-proctoring-system
```

### Suggested Repository Description

```text
A PHP and MySQL-based distributed online exam proctoring system with teacher, student, and proctor roles, exam management, auto-checking, essay review, and basic proctoring logs.
```

### Suggested Final Commit Message

```bash
git add .
git commit -m "Complete ProctorX MVP distributed online exam proctoring system"
```

---

## Author

Developed as a full-stack academic web application project using PHP, MySQL, HTML, CSS, JavaScript, and XAMPP.

---

## License

This project is intended for academic and educational purposes.
