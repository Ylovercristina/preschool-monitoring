-- Preschool Monitoring System Schema
-- Group Members: Rhysa A. Caruz, Cristine Joy B. Jaojao, Xyrha Viel Sacal
-- Compatible with MySQL 5.7+ / MariaDB 10.3+ / SQLite 3

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(191) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'teacher', 'parent') NOT NULL,
    phone VARCHAR(50),
    status ENUM('active', 'pending_approval', 'archived', 'rejected') NOT NULL DEFAULT 'active',
    avatar VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS classrooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    room_number VARCHAR(50),
    capacity INT DEFAULT 20,
    teacher_id INT,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    lrn VARCHAR(50) UNIQUE,
    dob DATE NOT NULL,
    gender ENUM('Male', 'Female'),
    blood_type VARCHAR(10),
    classroom_id INT,
    parent_id INT,
    address TEXT,
    emergency_contact_name VARCHAR(150),
    emergency_contact_phone VARCHAR(50),
    allergies TEXT,
    medical_notes TEXT,
    enrollment_status ENUM('enrolled', 'archived', 'withdrawn') DEFAULT 'enrolled',
    admission_date DATE,
    avatar VARCHAR(255),
    FOREIGN KEY (classroom_id) REFERENCES classrooms(id) ON DELETE SET NULL,
    FOREIGN KEY (parent_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    classroom_id INT,
    date DATE NOT NULL,
    status ENUM('present', 'absent', 'late', 'excused') NOT NULL,
    time_in TIME,
    time_out TIME,
    remarks TEXT,
    recorded_by INT,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (classroom_id) REFERENCES classrooms(id) ON DELETE SET NULL,
    FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS academic_milestones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    category VARCHAR(100) NOT NULL,
    description TEXT
);

CREATE TABLE IF NOT EXISTS student_milestones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    milestone_id INT NOT NULL,
    rating ENUM('mastered', 'progressing', 'needs_support') NOT NULL DEFAULT 'progressing',
    remarks TEXT,
    evaluated_by INT,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (milestone_id) REFERENCES academic_milestones(id) ON DELETE CASCADE,
    FOREIGN KEY (evaluated_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS academic_assessments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    term VARCHAR(50) NOT NULL,
    overall_remarks TEXT,
    needs_intervention TINYINT(1) DEFAULT 0,
    teacher_id INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS authorized_pickups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    parent_id INT NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    relationship VARCHAR(80) NOT NULL,
    phone VARCHAR(50) NOT NULL,
    pin_code VARCHAR(10) NOT NULL,
    id_photo VARCHAR(255),
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS pickup_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    pickup_person_id INT,
    verified_by_teacher_id INT,
    pickup_time DATETIME DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (pickup_person_id) REFERENCES authorized_pickups(id) ON DELETE SET NULL,
    FOREIGN KEY (verified_by_teacher_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS fees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    description TEXT,
    amount DECIMAL(10,2) NOT NULL,
    school_year VARCHAR(20) NOT NULL,
    due_date DATE
);

CREATE TABLE IF NOT EXISTS student_fees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    fee_id INT NOT NULL,
    amount_due DECIMAL(10,2) NOT NULL,
    amount_paid DECIMAL(10,2) DEFAULT 0.00,
    status ENUM('unpaid', 'partially_paid', 'paid') DEFAULT 'unpaid',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (fee_id) REFERENCES fees(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS payment_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_fee_id INT NOT NULL,
    student_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    reference_no VARCHAR(100),
    receipt_no VARCHAR(100) NOT NULL,
    logged_by INT,
    notes TEXT,
    payment_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_fee_id) REFERENCES student_fees(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (logged_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    description TEXT,
    event_date DATE NOT NULL,
    start_time TIME,
    end_time TIME,
    location VARCHAR(150),
    event_type VARCHAR(50) DEFAULT 'School Activity',
    created_by INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS emergency_alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    severity ENUM('urgent', 'critical', 'warning') DEFAULT 'urgent',
    posted_by INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    is_active TINYINT(1) DEFAULT 1,
    FOREIGN KEY (posted_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    student_id INT,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    type VARCHAR(50) DEFAULT 'info',
    link VARCHAR(255),
    is_read TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);
