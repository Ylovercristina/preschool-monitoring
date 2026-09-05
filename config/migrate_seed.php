<?php
/**
 * Database Migration & Seeder Script
 * Preschool Monitoring System
 */

function runMigrationAndSeed($pdo) {
    // 1. Create Tables
    $queries = [
        "CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT UNIQUE NOT NULL,
            password TEXT NOT NULL,
            role TEXT NOT NULL CHECK(role IN ('admin', 'teacher', 'parent')),
            phone TEXT,
            status TEXT NOT NULL DEFAULT 'active' CHECK(status IN ('active', 'pending_approval', 'archived', 'rejected')),
            avatar TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )",

        "CREATE TABLE IF NOT EXISTS classrooms (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            room_number TEXT,
            capacity INTEGER DEFAULT 20,
            teacher_id INTEGER,
            FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE SET NULL
        )",

        "CREATE TABLE IF NOT EXISTS students (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            first_name TEXT NOT NULL,
            last_name TEXT NOT NULL,
            lrn TEXT UNIQUE,
            dob DATE NOT NULL,
            gender TEXT CHECK(gender IN ('Male', 'Female')),
            blood_type TEXT,
            classroom_id INTEGER,
            parent_id INTEGER,
            address TEXT,
            emergency_contact_name TEXT,
            emergency_contact_phone TEXT,
            allergies TEXT,
            medical_notes TEXT,
            enrollment_status TEXT DEFAULT 'enrolled' CHECK(enrollment_status IN ('enrolled', 'archived', 'withdrawn')),
            admission_date DATE,
            avatar TEXT,
            FOREIGN KEY (classroom_id) REFERENCES classrooms(id) ON DELETE SET NULL,
            FOREIGN KEY (parent_id) REFERENCES users(id) ON DELETE SET NULL
        )",

        "CREATE TABLE IF NOT EXISTS attendance (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            student_id INTEGER NOT NULL,
            classroom_id INTEGER,
            date DATE NOT NULL,
            status TEXT NOT NULL CHECK(status IN ('present', 'absent', 'late', 'excused')),
            time_in TIME,
            time_out TIME,
            remarks TEXT,
            recorded_by INTEGER,
            FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
            FOREIGN KEY (classroom_id) REFERENCES classrooms(id) ON DELETE SET NULL,
            FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
        )",

        "CREATE TABLE IF NOT EXISTS academic_milestones (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            category TEXT NOT NULL,
            description TEXT
        )",

        "CREATE TABLE IF NOT EXISTS student_milestones (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            student_id INTEGER NOT NULL,
            milestone_id INTEGER NOT NULL,
            rating TEXT NOT NULL DEFAULT 'progressing' CHECK(rating IN ('mastered', 'progressing', 'needs_support')),
            remarks TEXT,
            evaluated_by INTEGER,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
            FOREIGN KEY (milestone_id) REFERENCES academic_milestones(id) ON DELETE CASCADE,
            FOREIGN KEY (evaluated_by) REFERENCES users(id) ON DELETE SET NULL
        )",

        "CREATE TABLE IF NOT EXISTS academic_assessments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            student_id INTEGER NOT NULL,
            term TEXT NOT NULL,
            overall_remarks TEXT,
            needs_intervention INTEGER DEFAULT 0,
            teacher_id INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
            FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE SET NULL
        )",

        "CREATE TABLE IF NOT EXISTS authorized_pickups (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            student_id INTEGER NOT NULL,
            parent_id INTEGER NOT NULL,
            full_name TEXT NOT NULL,
            relationship TEXT NOT NULL,
            phone TEXT NOT NULL,
            pin_code TEXT NOT NULL,
            id_photo TEXT,
            is_active INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
            FOREIGN KEY (parent_id) REFERENCES users(id) ON DELETE CASCADE
        )",

        "CREATE TABLE IF NOT EXISTS pickup_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            student_id INTEGER NOT NULL,
            pickup_person_id INTEGER,
            verified_by_teacher_id INTEGER,
            pickup_time DATETIME DEFAULT CURRENT_TIMESTAMP,
            notes TEXT,
            FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
            FOREIGN KEY (pickup_person_id) REFERENCES authorized_pickups(id) ON DELETE SET NULL,
            FOREIGN KEY (verified_by_teacher_id) REFERENCES users(id) ON DELETE SET NULL
        )",

        "CREATE TABLE IF NOT EXISTS fees (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            description TEXT,
            amount REAL NOT NULL,
            school_year TEXT NOT NULL,
            due_date DATE
        )",

        "CREATE TABLE IF NOT EXISTS student_fees (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            student_id INTEGER NOT NULL,
            fee_id INTEGER NOT NULL,
            amount_due REAL NOT NULL,
            amount_paid REAL DEFAULT 0,
            status TEXT DEFAULT 'unpaid' CHECK(status IN ('unpaid', 'partially_paid', 'paid')),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
            FOREIGN KEY (fee_id) REFERENCES fees(id) ON DELETE CASCADE
        )",

        "CREATE TABLE IF NOT EXISTS payment_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            student_fee_id INTEGER NOT NULL,
            student_id INTEGER NOT NULL,
            amount REAL NOT NULL,
            payment_method TEXT NOT NULL,
            reference_no TEXT,
            receipt_no TEXT NOT NULL,
            logged_by INTEGER,
            notes TEXT,
            payment_date DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (student_fee_id) REFERENCES student_fees(id) ON DELETE CASCADE,
            FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
            FOREIGN KEY (logged_by) REFERENCES users(id) ON DELETE SET NULL
        )",

        "CREATE TABLE IF NOT EXISTS events (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            description TEXT,
            event_date DATE NOT NULL,
            start_time TIME,
            end_time TIME,
            location TEXT,
            event_type TEXT DEFAULT 'School Activity',
            created_by INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        )",

        "CREATE TABLE IF NOT EXISTS emergency_alerts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            message TEXT NOT NULL,
            severity TEXT DEFAULT 'urgent' CHECK(severity IN ('urgent', 'critical', 'warning')),
            posted_by INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            is_active INTEGER DEFAULT 1,
            FOREIGN KEY (posted_by) REFERENCES users(id) ON DELETE SET NULL
        )",

        "CREATE TABLE IF NOT EXISTS messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sender_id INTEGER NOT NULL,
            receiver_id INTEGER NOT NULL,
            student_id INTEGER,
            message TEXT NOT NULL,
            is_read INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE SET NULL
        )",

        "CREATE TABLE IF NOT EXISTS notifications (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            title TEXT NOT NULL,
            message TEXT NOT NULL,
            type TEXT DEFAULT 'info',
            link TEXT,
            is_read INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )",

        "CREATE TABLE IF NOT EXISTS activity_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            action TEXT NOT NULL,
            details TEXT,
            ip_address TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        )"
    ];

    foreach ($queries as $query) {
        $pdo->exec($query);
    }

    // 2. Check if seed data exists
    $userCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    if ($userCount > 0) {
        return; // already seeded
    }

    // 3. Seed Users
    // Default demo passwords are role123 (hashed with BCRYPT)
    $passwords = [
        'admin' => password_hash('admin123', PASSWORD_DEFAULT),
        'teacher' => password_hash('teacher123', PASSWORD_DEFAULT),
        'parent' => password_hash('parent123', PASSWORD_DEFAULT)
    ];

    $users = [
        ['Admin User', 'admin@preschool.com', $passwords['admin'], 'admin', '+63 917 111 2233', 'active'],
        ['Sarah Jenkins', 'teacher@preschool.com', $passwords['teacher'], 'teacher', '+63 918 222 3344', 'active'],
        ['Maria Santos', 'maria@preschool.com', $passwords['teacher'], 'teacher', '+63 919 333 4455', 'active'],
        ['Emily Watson', 'parent@preschool.com', $passwords['parent'], 'parent', '+63 920 444 5566', 'active'],
        ['John Davis', 'john@preschool.com', $passwords['parent'], 'parent', '+63 921 555 6677', 'active'],
        ['Clara Reyes', 'clara@preschool.com', $passwords['parent'], 'parent', '+63 922 666 7788', 'pending_approval'], // Pending approval requirement
        ['Grace Tan', 'grace@preschool.com', $passwords['parent'], 'parent', '+63 923 777 8899', 'active']
    ];

    $uStmt = $pdo->prepare("INSERT INTO users (name, email, password, role, phone, status, created_at) VALUES (?, ?, ?, ?, ?, ?, datetime('now'))");
    foreach ($users as $u) {
        $uStmt->execute($u);
    }

    // 4. Seed Classrooms
    $classrooms = [
        ['Nursery - Tiny Tots', 'Room 101', 15, 2], // Sarah
        ['Pre-K - Little Explorers', 'Room 102', 18, 2], // Sarah
        ['Kindergarten - Bright Stars', 'Room 103', 20, 3] // Maria
    ];
    $cStmt = $pdo->prepare("INSERT INTO classrooms (name, room_number, capacity, teacher_id) VALUES (?, ?, ?, ?)");
    foreach ($classrooms as $c) {
        $cStmt->execute($c);
    }

    // 5. Seed Students
    $students = [
        // id=1, Leo Watson (Parent=Emily Watson id=4, Class=Pre-K id=2)
        ['Leo', 'Watson', 'LRN-2026-001', '2022-04-12', 'Male', 'O+', 2, 4, '124 Sunflower St, Green Valley', 'Robert Watson (Uncle)', '+63 917 888 1111', 'Peanuts, Shellfish', 'Asthma inhaler kept in nurse cabinet', 'enrolled', '2026-06-01', null],
        // id=2, Mia Davis (Parent=John Davis id=5, Class=Pre-K id=2)
        ['Mia', 'Davis', 'LRN-2026-002', '2022-07-25', 'Female', 'A+', 2, 5, '45 Maple Ave, Oakwood Park', 'Helen Davis (Grandmother)', '+63 917 888 2222', 'None known', 'Mild eczema on elbows', 'enrolled', '2026-06-01', null],
        // id=3, Ethan Tan (Parent=Grace Tan id=7, Class=Nursery id=1)
        ['Ethan', 'Tan', 'LRN-2026-003', '2023-01-15', 'Male', 'B+', 1, 7, '78 Blossom Lane, Sunnydale', 'David Tan (Father)', '+63 917 888 3333', 'Dairy (mild lactose intolerance)', 'None', 'enrolled', '2026-06-05', null],
        // id=4, Chloe Garcia (Class=Kindergarten id=3)
        ['Chloe', 'Garcia', 'LRN-2026-004', '2021-09-10', 'Female', 'AB+', 3, 4, '89 Rainbow Crest, Riverdale', 'Elena Garcia (Mother)', '+63 917 888 4444', 'None', 'Wears reading glasses', 'enrolled', '2026-06-01', null],
        // id=5, Noah Ramos (Class=Nursery id=1)
        ['Noah', 'Ramos', 'LRN-2026-005', '2023-03-20', 'Male', 'O+', 1, 5, '12 Cedar Way, Pinecrest', 'Carla Ramos (Aunt)', '+63 917 888 5555', 'Dust mites', 'Needs afternoon nap schedule', 'enrolled', '2026-06-10', null],
        // id=6, Sophia Chen (Class=Pre-K id=2)
        ['Sophia', 'Chen', 'LRN-2026-006', '2022-11-04', 'Female', 'A+', 2, 7, '30 Orchid St, Parkview', 'Michael Chen (Father)', '+63 917 888 6666', 'Eggs', 'Epipen available with school nurse', 'enrolled', '2026-06-01', null]
    ];
    $sStmt = $pdo->prepare("INSERT INTO students (first_name, last_name, lrn, dob, gender, blood_type, classroom_id, parent_id, address, emergency_contact_name, emergency_contact_phone, allergies, medical_notes, enrollment_status, admission_date, avatar) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($students as $s) {
        $sStmt->execute($s);
    }

    // 6. Seed Academic Milestones
    $milestones = [
        ['Letter Recognition (A-M)', 'Language & Literacy', 'Identifies uppercase and lowercase letters from A to M with confidence.'],
        ['Phonics & Sound Matching', 'Language & Literacy', 'Produces corresponding letter sounds for common consonants and short vowels.'],
        ['Counting 1 to 20', 'Cognitive & Logic', 'Accurately counts objects up to 20 using one-to-one correspondence.'],
        ['Shape & Color Categorization', 'Cognitive & Logic', 'Sorts objects by multiple attributes such as size, basic geometric shape, and color.'],
        ['Pencil Grip & Scissor Control', 'Motor Skills', 'Uses three-finger tripod grasp to hold crayons and cuts along straight lines.'],
        ['Balance & Hop on One Foot', 'Motor Skills', 'Maintains gross motor balance and hops consecutively on dominant foot.'],
        ['Sharing & Turn-Taking', 'Social & Emotional', 'Plays cooperatively with classmates, takes turns with learning materials.'],
        ['Expressing Emotions Respectfully', 'Social & Emotional', 'Uses words rather than physical frustration to express feelings and needs.'],
        ['Color Mixing & Painting', 'Creative Arts', 'Explores blending primary colors to create secondary tones during art time.'],
        ['Music Rhythm & Movement', 'Creative Arts', 'Claps in sync with rhythmic nursery rhymes and participates in song dances.']
    ];
    $mStmt = $pdo->prepare("INSERT INTO academic_milestones (title, category, description) VALUES (?, ?, ?)");
    foreach ($milestones as $m) {
        $mStmt->execute($m);
    }

    // 7. Seed Student Milestones Evaluation
    $studentMilestones = [
        // Leo Watson
        [1, 1, 'mastered', 'Recognizes all letters A-M smoothly! Excellent enthusiasm.', 2],
        [1, 2, 'progressing', 'Making great progress with vowel sounds.', 2],
        [1, 3, 'mastered', 'Counted 20 counters without skipping numbers.', 2],
        [1, 5, 'needs_support', 'Struggles with scissors; requires gentle hand-over-hand practice.', 2],
        [1, 7, 'mastered', 'Very kind friend; readily shares building blocks.', 2],
        // Mia Davis
        [2, 1, 'mastered', 'Outstanding alphabet recognition.', 2],
        [2, 3, 'progressing', 'Can count up to 15 independently.', 2],
        [2, 5, 'mastered', 'Remarkable fine motor skills and scissor control.', 2],
        [2, 7, 'progressing', 'Adjusting well to sharing during center play.', 2],
        [2, 8, 'needs_support', 'Can become shy when overwhelmed; encouragement helps.', 2],
        // Ethan Tan
        [3, 3, 'progressing', 'Counts 1 to 5 with finger cues.', 2],
        [3, 7, 'needs_support', 'Still adapting to group circle time; working on sitting quietly.', 2],
        // Noah Ramos
        [5, 4, 'mastered', 'Identifies circles, triangles, squares accurately.', 2],
        [5, 8, 'mastered', 'Very calm and expressive verbal communicator.', 2]
    ];
    $smStmt = $pdo->prepare("INSERT INTO student_milestones (student_id, milestone_id, rating, remarks, evaluated_by, updated_at) VALUES (?, ?, ?, ?, ?, datetime('now', '-2 days'))");
    foreach ($studentMilestones as $sm) {
        $smStmt->execute($sm);
    }

    // 8. Seed Assessments (identifying students needing support)
    $assessments = [
        [1, 'Term 1 - Midterm', 'Leo is lively, bright, and social. Needs fine motor intervention for cutting and pencil grip.', 1, 2],
        [2, 'Term 1 - Midterm', 'Mia exhibits strong academic skills. Needs emotional support during transitions.', 0, 2],
        [3, 'Term 1 - Midterm', 'Ethan is adjusting nicely to preschool routines. Speech and socialization developing well.', 0, 2]
    ];
    $aStmt = $pdo->prepare("INSERT INTO academic_assessments (student_id, term, overall_remarks, needs_intervention, teacher_id) VALUES (?, ?, ?, ?, ?)");
    foreach ($assessments as $a) {
        $aStmt->execute($a);
    }

    // 9. Seed Attendance (past 5 days + today)
    $dates = [
        date('Y-m-d', strtotime('-4 days')),
        date('Y-m-d', strtotime('-3 days')),
        date('Y-m-d', strtotime('-2 days')),
        date('Y-m-d', strtotime('-1 days')),
        date('Y-m-d')
    ];

    $attStmt = $pdo->prepare("INSERT INTO attendance (student_id, classroom_id, date, status, time_in, time_out, remarks, recorded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($dates as $idx => $d) {
        // Leo Watson
        $attStmt->execute([1, 2, $d, 'present', '08:05:00', '11:30:00', 'On time and cheerful', 2]);
        // Mia Davis
        $attStmt->execute([2, 2, $d, ($idx === 1 ? 'late' : 'present'), ($idx === 1 ? '08:35:00' : '08:10:00'), '11:30:00', ($idx === 1 ? 'Doctor checkup' : 'Present'), 2]);
        // Ethan Tan
        $attStmt->execute([3, 1, $d, ($idx === 3 ? 'absent' : 'present'), ($idx === 3 ? null : '08:15:00'), ($idx === 3 ? null : '11:30:00'), ($idx === 3 ? 'Mild fever, resting at home' : 'Active participation'), 2]);
        // Chloe Garcia
        $attStmt->execute([4, 3, $d, 'present', '08:00:00', '11:30:00', 'Great helper today', 3]);
        // Noah Ramos
        $attStmt->execute([5, 1, $d, 'present', '08:12:00', '11:30:00', 'Participated well in rhythm time', 2]);
        // Sophia Chen
        $attStmt->execute([6, 2, $d, ($idx === 2 ? 'excused' : 'present'), ($idx === 2 ? null : '08:08:00'), ($idx === 2 ? null : '11:30:00'), ($idx === 2 ? 'Family emergency' : 'Attentive'), 2]);
    }

    // 10. Seed Authorized Pickups (Critical safety requirement)
    $pickups = [
        [1, 4, 'Robert Watson', 'Uncle', '+63 917 888 1111', '8921', null, 1],
        [1, 4, 'Margaret Watson', 'Grandmother', '+63 917 888 1122', '3412', null, 1],
        [2, 5, 'Helen Davis', 'Grandmother', '+63 917 888 2222', '7709', null, 1],
        [3, 7, 'David Tan', 'Father', '+63 917 888 3333', '4580', null, 1],
        [6, 7, 'Michael Chen', 'Father', '+63 917 888 6666', '1294', null, 1]
    ];
    $pStmt = $pdo->prepare("INSERT INTO authorized_pickups (student_id, parent_id, full_name, relationship, phone, pin_code, id_photo, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($pickups as $p) {
        $pStmt->execute($p);
    }

    // 11. Seed Pickup Logs
    $pickupLogs = [
        [1, 1, 2, date('Y-m-d 11:35:00', strtotime('-1 days')), 'Verified ID & PIN 8921 with Teacher Sarah.'],
        [2, 3, 2, date('Y-m-d 11:32:00', strtotime('-1 days')), 'Grandmother Helen picked up student safely. Verified.']
    ];
    $plStmt = $pdo->prepare("INSERT INTO pickup_logs (student_id, pickup_person_id, verified_by_teacher_id, pickup_time, notes) VALUES (?, ?, ?, ?, ?)");
    foreach ($pickupLogs as $pl) {
        $plStmt->execute($pl);
    }

    // 12. Seed Fees & Student Fees
    $fees = [
        ['1st Term Tuition Fee', 'Standard tuition covering classroom instructions, guided learning, and supervision.', 8500.00, '2026-2027', '2026-09-30'],
        ['Learning Materials & Craft Kit', 'Montessori sensory materials, art supplies, workbooks, and handwriting pads.', 2500.00, '2026-2027', '2026-09-15'],
        ['Healthy Snack & Nutrition Program', 'Daily organic fruits, dairy snacks, and clean water program.', 1800.00, '2026-2027', '2026-10-15'],
        ['Field Trip & Educational Activity Fee', 'Transportation, admission to petting zoo, and safety wristbands.', 1200.00, '2026-2027', '2026-10-30']
    ];
    $fStmt = $pdo->prepare("INSERT INTO fees (title, description, amount, school_year, due_date) VALUES (?, ?, ?, ?, ?)");
    foreach ($fees as $f) {
        $fStmt->execute($f);
    }

    // Assign fees to students
    $studentFees = [
        // Leo Watson
        [1, 1, 8500.00, 8500.00, 'paid'],
        [1, 2, 2500.00, 2500.00, 'paid'],
        [1, 3, 1800.00, 900.00, 'partially_paid'],
        [1, 4, 1200.00, 0.00, 'unpaid'],
        // Mia Davis
        [2, 1, 8500.00, 4250.00, 'partially_paid'],
        [2, 2, 2500.00, 2500.00, 'paid'],
        [2, 3, 1800.00, 0.00, 'unpaid'],
        // Ethan Tan
        [3, 1, 8500.00, 8500.00, 'paid'],
        [3, 2, 2500.00, 0.00, 'unpaid']
    ];
    $sfStmt = $pdo->prepare("INSERT INTO student_fees (student_id, fee_id, amount_due, amount_paid, status) VALUES (?, ?, ?, ?, ?)");
    foreach ($studentFees as $sf) {
        $sfStmt->execute($sf);
    }

    // Seed Payment Logs
    $payments = [
        [1, 1, 8500.00, 'Cash', 'CASH-REC-000', 'REC-2026-001', 1, 'Full tuition payment for Term 1 paid at the school admin office.', date('Y-m-d H:i:s', strtotime('-15 days'))],
        [2, 1, 2500.00, 'Cash', 'CASH-REC-001', 'REC-2026-002', 1, 'Cash payment receipt issued at school admin office.', date('Y-m-d H:i:s', strtotime('-10 days'))],
        [3, 1, 900.00, 'Cash', 'CASH-REC-003', 'REC-2026-003', 1, 'Partial cash payment for nutrition program.', date('Y-m-d H:i:s', strtotime('-5 days'))],
        [5, 2, 4250.00, 'Cash', 'CASH-REC-004', 'REC-2026-004', 1, '50% cash installment for tuition.', date('Y-m-d H:i:s', strtotime('-8 days'))]
    ];
    $pLogStmt = $pdo->prepare("INSERT INTO payment_logs (student_fee_id, student_id, amount, payment_method, reference_no, receipt_no, logged_by, notes, payment_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($payments as $p) {
        $pLogStmt->execute($p);
    }

    // 13. Seed School Events & Activities
    $events = [
        ['Grandparents Day & Story Circle', 'Special morning tea and storybook reading celebration with grandparents.', date('Y-m-d', strtotime('+3 days')), '09:00:00', '11:00:00', 'Preschool Amphitheater', 'Celebration', 1],
        ['Teddy Bear Healthy Picnic', 'Outdoor sensory playtime, healthy snack packing, and puppet storytelling.', date('Y-m-d', strtotime('+8 days')), '08:30:00', '11:30:00', 'School Courtyard & Garden', 'School Activity', 2],
        ['Parent-Teacher Milestone Conference', 'One-on-one progress review of developmental milestones for Term 1.', date('Y-m-d', strtotime('+14 days')), '13:00:00', '17:00:00', 'Classrooms 101-103', 'Meeting', 1],
        ['National Childrens Book Day', 'Costume parade and favorite character dress-up activity.', date('Y-m-d', strtotime('+21 days')), '08:30:00', '11:30:00', 'Activity Hall', 'School Activity', 2],
        ['Little Scientists Color Discovery Day', 'Safe, hands-on bubble and water rainbow science experiments.', date('Y-m-d', strtotime('+28 days')), '09:00:00', '11:30:00', 'Science Learning Center', 'School Activity', 2]
    ];
    $eStmt = $pdo->prepare("INSERT INTO events (title, description, event_date, start_time, end_time, location, event_type, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($events as $e) {
        $eStmt->execute($e);
    }

    // 14. Seed Emergency Alerts
    $alerts = [
        ['Inclement Weather Alert: Heavy Afternoon Rains', 'Signal No. 1 has been raised. Preschool classes will dismiss promptly at 11:00 AM today. Please arrange authorized pickups accordingly.', 'urgent', 1, 1],
        ['Health Advisory: Hand-Foot-Mouth Hygiene Protocol', 'Please review the enhanced sanitization measures in place. Remind children about proper handwashing at home.', 'warning', 1, 0]
    ];
    $alertStmt = $pdo->prepare("INSERT INTO emergency_alerts (title, message, severity, posted_by, is_active, created_at) VALUES (?, ?, ?, ?, ?, datetime('now', '-1 days'))");
    foreach ($alerts as $al) {
        $alertStmt->execute($al);
    }

    // 15. Seed Messages (Parent-Teacher Communication)
    $msgs = [
        [4, 2, 1, "Good morning Teacher Sarah! Leo has a mild runny nose today but no fever. I packed his extra jacket.", 1, date('Y-m-d 07:45:00', strtotime('-2 days'))],
        [2, 4, 1, "Good morning Mrs. Watson! Thank you for the note. We will keep an extra eye on Leo and let you know if he needs any rest.", 1, date('Y-m-d 08:00:00', strtotime('-2 days'))],
        [4, 2, 1, "Hi Teacher Sarah! His uncle Robert will be picking him up at 11:30 AM today using PIN 8921.", 1, date('Y-m-d 10:15:00', strtotime('-1 days'))],
        [2, 4, 1, "Understood Mrs. Watson! Uncle Robert was verified with his PIN code and Leo was safely released.", 1, date('Y-m-d 11:36:00', strtotime('-1 days'))],
        [5, 2, 2, "Hello Teacher Sarah, how did Mia do with her sharing activities today?", 0, date('Y-m-d 12:10:00')]
    ];
    $msgStmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, student_id, message, is_read, created_at) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($msgs as $m) {
        $msgStmt->execute($m);
    }

    // 16. Seed Notifications
    $notifications = [
        [4, 'Event Reminder', 'Grandparents Day & Story Circle is scheduled for this coming Friday!', 'event', 'parent/calendar.php', 0],
        [4, 'Outstanding Fee Reminder', 'Reminder: Second installment for Nutrition Program is due next week.', 'fee_reminder', 'parent/fees.php', 0],
        [4, 'Pickup Release Confirmed', 'Leo was safely picked up by authorized guardian Robert Watson.', 'pickup', 'parent/pickups.php', 1],
        [5, 'Emergency Weather Advisory', 'Inclement weather: Classes dismiss early at 11:00 AM.', 'emergency', 'parent/notifications.php', 1],
        [1, 'Pending Parent Registration', 'Clara Reyes has requested an account and is awaiting approval.', 'system', 'admin/approvals.php', 0]
    ];
    $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, link, is_read) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($notifications as $n) {
        $notifStmt->execute($n);
    }

    // 17. Seed Activity Logs
    $logs = [
        [1, 'System Initialized', 'Database tables and initial system configuration migrated.', '127.0.0.1'],
        [1, 'Fee Payment Logged', 'Logged payment of ₱8,500.00 for Leo Watson (Receipt REC-2026-001)', '127.0.0.1'],
        [2, 'Attendance Marked', 'Recorded attendance for Pre-K - Little Explorers (6 students present)', '127.0.0.1'],
        [2, 'Authorized Pickup Verified', 'Verified guardian Robert Watson for student Leo Watson with PIN', '127.0.0.1'],
        [1, 'Emergency Alert Posted', 'Broadcasted weather alert for early dismissal', '127.0.0.1']
    ];
    $logStmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, datetime('now', '-1 hours'))");
    foreach ($logs as $l) {
        $logStmt->execute($l);
    }
}
