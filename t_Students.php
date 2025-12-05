<?php
session_start();

if (strpos($_SESSION['account_number'], 'T') !== 0) {
    header("Location: login.php");
    exit();
}

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "rawrit";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$teacher_id = $_SESSION['account_number'];
$subject_id = isset($_GET['subject']) ? intval($_GET['subject']) : null;

// Handle CSV template download
if (isset($_GET['download_template']) && $subject_id) {
    // Verify the subject belongs to the current teacher
    $verify_subject_sql = "SELECT 1 FROM subjects WHERE subject_id = ? AND teacher_id = ?";
    $verify_stmt = $conn->prepare($verify_subject_sql);
    $verify_stmt->bind_param("is", $subject_id, $teacher_id);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();
    
    if ($verify_result->num_rows > 0) {
        // Get subject details (grade level, section, and school_id)
        $subject_details_sql = "SELECT grade_level, section, school_id FROM subjects WHERE subject_id = ?";
        $subject_details_stmt = $conn->prepare($subject_details_sql);
        $subject_details_stmt->bind_param("i", $subject_id);
        $subject_details_stmt->execute();
        $subject_details = $subject_details_stmt->get_result()->fetch_assoc();
        $subject_details_stmt->close();
        
        $subject_grade_level = $subject_details['grade_level'];
        $subject_section = $subject_details['section'];
        $subject_school_id = $subject_details['school_id'];
        
        // Fetch students with same school_id, grade level, and section who are not enrolled in this subject
        $csv_query = "SELECT s.account_number, s.fname, s.lname, s.glevel, s.strand, s.section
                    FROM students s
                    WHERE s.school_id = ?
                    AND s.glevel = ?
                    AND (s.section = ? OR ? IS NULL)
                    AND NOT EXISTS (
                        SELECT 1 FROM enrollments e 
                        WHERE e.student_id = s.student_id 
                        AND e.subject_id = ?
                    )
                    ORDER BY s.lname, s.fname";
        
        $csv_stmt = $conn->prepare($csv_query);
        
        // Handle NULL section properly
        if ($subject_section === null) {
            $csv_stmt->bind_param("iiisi", $subject_school_id, $subject_grade_level, $subject_section, $subject_section, $subject_id);
        } else {
            $csv_stmt->bind_param("iissi", $subject_school_id, $subject_grade_level, $subject_section, $subject_section, $subject_id);
        }
        
        $csv_stmt->execute();
        $csv_result = $csv_stmt->get_result();
        
        // Set headers for download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="student_enrollment_template.csv"');
        
        // Open output stream
        $output = fopen('php://output', 'w');
        
        // Write CSV headers
        fputcsv($output, ['Account Number', 'First Name', 'Last Name', 'Grade Level', 'Strand', 'Section']);
        
        // Write student data
        while ($student = $csv_result->fetch_assoc()) {
            fputcsv($output, [
                $student['account_number'],
                $student['fname'],
                $student['lname'],
                $student['glevel'],
                $student['strand'] ?? '',
                $student['section'] ?? ''
            ]);
        }
        
        fclose($output);
        $csv_stmt->close();
        $verify_stmt->close();
        exit();
    }
    $verify_stmt->close();
}

// Fetch profile pic
$loggedInUser = $_SESSION['account_number'];
$sql = "SELECT profile_pic FROM teachers WHERE account_number = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $loggedInUser);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $profile_pic = $row['profile_pic'] ? $row['profile_pic'] : 'default-profile.jpg';
} else {
    $profile_pic = 'default-profile.jpg';
}

$stmt->close();

// ==================== CSV IMPORT FUNCTIONALITY ====================
if (isset($_POST['import_csv'])) {
    $file = $_FILES['csv_file'];
    $allowed_ext = ['csv'];
    $filename = $file['name'];
    $file_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    if (in_array($file_ext, $allowed_ext)) {
        $file_tmp = $file['tmp_name'];
        $handle = fopen($file_tmp, "r");
        
        // Read the header row
        $header = fgetcsv($handle);
        $header = array_map(function($column) {
            return strtolower(trim($column));
        }, $header);
        
        // Find the indices of required columns
        $account_number_index = array_search('account number', $header);
        $fname_index = array_search('first name', $header);
        $lname_index = array_search('last name', $header);
        $glevel_index = array_search('grade level', $header);
        $strand_index = array_search('strand', $header);
        $section_index = array_search('section', $header);

        // Validate mandatory columns
        if ($account_number_index === false || $fname_index === false || 
            $lname_index === false || $glevel_index === false) {
            $message = "Error: CSV file must contain 'Account Number', 'First Name', 'Last Name', and 'Grade Level' columns.";
        } else {
            $imported_count = 0;
            $updated_count = 0;
            $skipped_count = 0;
            $conn->begin_transaction();
            
            // First, verify the subject belongs to the current teacher
            $verify_subject_sql = "SELECT 1 FROM subjects WHERE subject_id = ? AND teacher_id = ?";
            $verify_stmt = $conn->prepare($verify_subject_sql);
            $verify_stmt->bind_param("is", $subject_id, $teacher_id);
            $verify_stmt->execute();
            $verify_result = $verify_stmt->get_result();
            
            if ($verify_result->num_rows == 0) {
                $message = "You should select a subject first in the subject filters.";
                $conn->rollback();
            } else {
                try {
                    while (($data = fgetcsv($handle)) !== FALSE) {
                        // Skip empty rows
                        if (empty(array_filter($data))) {
                            continue;
                        }
                        
                        // Extract required fields
                        $account_number = trim($data[$account_number_index]);
                        $fname = trim($data[$fname_index]);
                        $lname = trim($data[$lname_index]);
                        $glevel = intval(trim($data[$glevel_index]));
                    
                        // Handle optional strand
                        $strand = null;
                        if ($strand_index !== false && isset($data[$strand_index])) {
                            $strand_value = trim($data[$strand_index]);
                            // Only set strand for grades 11 and 12
                            if (in_array($glevel, [11, 12]) && !empty($strand_value)) {
                                $strand = $strand_value;
                            }
                        }
                        
                        // Handle optional section
                        $section = null;
                        if ($section_index !== false && isset($data[$section_index])) {
                            $section_value = trim($data[$section_index]);
                            if (!empty($section_value)) {
                                $section = $section_value;
                            }
                        }

                        // Validate mandatory fields
                        if (empty($account_number) || empty($fname) || empty($lname) || empty($glevel)) {
                            $skipped_count++;
                            continue;
                        }

                        // Check if the student is already registered in the system
                        $check_student_sql = "SELECT student_id FROM students WHERE account_number = ?";
                        $check_student_stmt = $conn->prepare($check_student_sql);
                        $check_student_stmt->bind_param("s", $account_number);
                        $check_student_stmt->execute();
                        $check_student_result = $check_student_stmt->get_result();

                        // If student is not registered, skip this record
                        if ($check_student_result->num_rows == 0) {
                            $skipped_count++;
                            $check_student_stmt->close();
                            continue;
                        }
                        
                        $student_id = $check_student_result->fetch_assoc()['student_id'];
                        $check_student_stmt->close();

                        // Check if already enrolled in this subject
                        $check_enrollment_sql = "SELECT 1 FROM enrollments WHERE student_id = ? AND subject_id = ?";
                        $check_enrollment_stmt = $conn->prepare($check_enrollment_sql);
                        $check_enrollment_stmt->bind_param("ii", $student_id, $subject_id);
                        $check_enrollment_stmt->execute();
                        $check_enrollment_result = $check_enrollment_stmt->get_result();
                        
                        if ($check_enrollment_result->num_rows > 0) {
                            $skipped_count++;
                            $check_enrollment_stmt->close();
                            continue;
                        }
                        $check_enrollment_stmt->close();

                        // Insert enrollment
                        $enrollment_sql = "INSERT INTO enrollments (student_id, subject_id) VALUES (?, ?)";
                        $enrollment_stmt = $conn->prepare($enrollment_sql);
                        $enrollment_stmt->bind_param("ii", $student_id, $subject_id);

                        if ($enrollment_stmt->execute()) {
                            $imported_count++;
                        }
                        
                        $enrollment_stmt->close();
                    }
                
                    $conn->commit();
                    fclose($handle);

                    // Fetch the actual subject name
                    $subject_name_sql = "SELECT subject_name FROM subjects WHERE subject_id = ? AND teacher_id = ?";
                    $subject_name_stmt = $conn->prepare($subject_name_sql);
                    $subject_name_stmt->bind_param("is", $subject_id, $teacher_id);
                    $subject_name_stmt->execute();
                    $subject_name_result = $subject_name_stmt->get_result();
                    $subject_name = $subject_name_result->fetch_assoc()['subject_name'] ?? 'Unknown Subject';
                    $subject_name_stmt->close();

                    // Count total enrolled students for this subject
                    $count_sql = "SELECT COUNT(*) as count FROM enrollments WHERE subject_id = ?";
                    $count_stmt = $conn->prepare($count_sql);
                    $count_stmt->bind_param("i", $subject_id);
                    $count_stmt->execute();
                    $count_result = $count_stmt->get_result();
                    $total_students = $count_result->fetch_assoc()['count'];
                    $count_stmt->close();

                    $_SESSION['enroll_message'] = "Import completed for subject: {$subject_name}.
                        Total students imported: $imported_count.
                        Skipped students (already enrolled or invalid): $skipped_count.
                        Total students in subject: $total_students.";

                    header("Location: t_Students.php?subject=$subject_id");
                    exit();

                } catch (Exception $e) {
                    $conn->rollback();
                    $message = "Error: " . $e->getMessage();
                }
            }
            $verify_stmt->close();
        }    
    } else {
        $message = "Invalid file format. Please upload a CSV file.";
    }
}

// ==================== INDIVIDUAL ENROLLMENT FUNCTIONALITY ====================
// Handle enrollment removal
if (isset($_POST['remove_student'])) {
    $student_account = $_POST['student_account'];
    $subject_id = $_POST['subject_id'];
    
    // Verify the subject belongs to the current teacher
    $verify_subject_sql = "SELECT 1 FROM subjects WHERE subject_id = ? AND teacher_id = ?";
    $verify_stmt = $conn->prepare($verify_subject_sql);
    $verify_stmt->bind_param("is", $subject_id, $teacher_id);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();
    
    if ($verify_result->num_rows > 0) {
        // Get student_id from account_number
        $student_sql = "SELECT student_id FROM students WHERE account_number = ?";
        $student_stmt = $conn->prepare($student_sql);
        $student_stmt->bind_param("s", $student_account);
        $student_stmt->execute();
        $student_result = $student_stmt->get_result();
        
        if ($student_result->num_rows > 0) {
            $student_id = $student_result->fetch_assoc()['student_id'];
            
            // Delete the enrollment
            $delete_sql = "DELETE FROM enrollments WHERE student_id = ? AND subject_id = ?";
            $delete_stmt = $conn->prepare($delete_sql);
            $delete_stmt->bind_param("ii", $student_id, $subject_id);
            
            if ($delete_stmt->execute()) {
                // Get subject name for success message
                $subject_name_sql = "SELECT subject_name FROM subjects WHERE subject_id = ?";
                $subject_name_stmt = $conn->prepare($subject_name_sql);
                $subject_name_stmt->bind_param("i", $subject_id);
                $subject_name_stmt->execute();
                $subject_name_result = $subject_name_stmt->get_result();
                $subject_name = $subject_name_result->fetch_assoc()['subject_name'] ?? 'the subject';
                
                $_SESSION['enroll_message'] = "Student successfully removed from $subject_name.";
                header("Location: t_Students.php?subject=$subject_id");
                exit();
            } else {
                $message = "Error removing student: " . $conn->error;
            }
        } else {
            $message = "Student not found.";
        }
    } else {
        $message = "Invalid subject selection.";
    }
}

// Fetch subjects taught by the teacher
$subjects_query = "SELECT subject_id, subject_name FROM subjects WHERE teacher_id = ?";
$subjects_stmt = $conn->prepare($subjects_query);
$subjects_stmt->bind_param("s", $teacher_id);
$subjects_stmt->execute();
$subjects_result = $subjects_stmt->get_result();

// Get selected subject filter (if any)
$selected_subject = isset($_GET['subject']) ? intval($_GET['subject']) : null;

// Handle enrollment form submission
if (isset($_POST['enroll_students'])) {
    if (!empty($selected_subject)) {
        $student_accounts = $_POST['students'] ?? [];
        $enrolled_count = 0;
        
        // Verify the subject belongs to the current teacher
        $verify_subject_sql = "SELECT 1 FROM subjects WHERE subject_id = ? AND teacher_id = ?";
        $verify_stmt = $conn->prepare($verify_subject_sql);
        $verify_stmt->bind_param("is", $selected_subject, $teacher_id);
        $verify_stmt->execute();
        $verify_result = $verify_stmt->get_result();
        
        if ($verify_result->num_rows > 0) {
            $conn->begin_transaction();
            try {
                foreach ($student_accounts as $account_number) {
                    // Check if student exists
                    $check_student_sql = "SELECT student_id FROM students WHERE account_number = ?";
                    $check_student_stmt = $conn->prepare($check_student_sql);
                    $check_student_stmt->bind_param("s", $account_number);
                    $check_student_stmt->execute();
                    $check_student_result = $check_student_stmt->get_result();
                    
                    if ($check_student_result->num_rows > 0) {
                        $student_id = $check_student_result->fetch_assoc()['student_id'];
                        
                        // Check if already enrolled to prevent duplicates
                        $check_enrollment_sql = "SELECT 1 FROM enrollments WHERE student_id = ? AND subject_id = ?";
                        $check_enrollment_stmt = $conn->prepare($check_enrollment_sql);
                        $check_enrollment_stmt->bind_param("ii", $student_id, $selected_subject);
                        $check_enrollment_stmt->execute();
                        $check_enrollment_result = $check_enrollment_stmt->get_result();
                        
                        if ($check_enrollment_result->num_rows == 0) {
                            // Insert enrollment
                            $enrollment_sql = "INSERT INTO enrollments (student_id, subject_id) VALUES (?, ?)";
                            $enrollment_stmt = $conn->prepare($enrollment_sql);
                            $enrollment_stmt->bind_param("ii", $student_id, $selected_subject);
                            $enrollment_stmt->execute();
                            $enrolled_count++;
                        }
                    }
                }
                
                $conn->commit();
                
                // Get subject name for success message
                $subject_name_sql = "SELECT subject_name FROM subjects WHERE subject_id = ?";
                $subject_name_stmt = $conn->prepare($subject_name_sql);
                $subject_name_stmt->bind_param("i", $selected_subject);
                $subject_name_stmt->execute();
                $subject_name_result = $subject_name_stmt->get_result();
                $subject_name = $subject_name_result->fetch_assoc()['subject_name'] ?? 'the subject';
                
                $_SESSION['enroll_message'] = "Successfully enrolled $enrolled_count students to $subject_name.";
                header("Location: t_Students.php?subject=$selected_subject");
                exit();
            } catch (Exception $e) {
                $conn->rollback();
                $message = "Error enrolling students: " . $e->getMessage();
            }
        } else {
            $message = "Invalid subject selection.";
        }
    } else {
        $message = "Please select a subject first.";
    }
}

// Fetch all registered students (not yet enrolled in the selected subject)
if ($selected_subject) {
    $all_students_query = "SELECT s.account_number, s.fname, s.lname, s.glevel, s.strand, s.section 
                        FROM students s
                        WHERE NOT EXISTS (
                            SELECT 1 FROM enrollments e 
                            JOIN subjects sub ON e.subject_id = sub.subject_id
                            WHERE e.student_id = s.student_id 
                            AND sub.subject_id = ? 
                            AND sub.teacher_id = ?
                        )
                        AND s.glevel = (
                            SELECT grade_level FROM subjects WHERE subject_id = ?
                        )
                        AND (s.section = (
                            SELECT section FROM subjects WHERE subject_id = ?
                        ) OR (
                            SELECT section FROM subjects WHERE subject_id = ?
                        ) IS NULL)";
    $all_students_stmt = $conn->prepare($all_students_query);
    $all_students_stmt->bind_param("isiii", $selected_subject, $teacher_id, $selected_subject, $selected_subject, $selected_subject);
    $all_students_stmt->execute();
    $all_students_result = $all_students_stmt->get_result();
}

// Fetch enrolled students - different query based on whether subject is selected
if ($selected_subject) {
    // Query for specific subject (no subject column needed)
    $enrolled_students_query = "
        SELECT s.account_number, s.fname, s.lname, s.glevel, s.strand, s.section
        FROM students s
        JOIN enrollments e ON s.student_id = e.student_id
        JOIN subjects sub ON sub.subject_id = e.subject_id
        WHERE sub.subject_id = ? AND sub.teacher_id = ?
        ORDER BY s.lname, s.fname
    ";
    $enrolled_students_stmt = $conn->prepare($enrolled_students_query);
    $enrolled_students_stmt->bind_param("is", $selected_subject, $teacher_id);
} else {
    // Query for all subjects (include subject column)
    $enrolled_students_query = "
        SELECT s.account_number, s.fname, s.lname, s.glevel, s.strand, s.section, sub.subject_name
        FROM students s
        JOIN enrollments e ON s.student_id = e.student_id
        JOIN subjects sub ON sub.subject_id = e.subject_id
        WHERE sub.teacher_id = ?
        ORDER BY sub.subject_name, s.lname, s.fname
    ";
    $enrolled_students_stmt = $conn->prepare($enrolled_students_query);
    $enrolled_students_stmt->bind_param("s", $teacher_id);
}

$enrolled_students_stmt->execute();
$enrolled_students_result = $enrolled_students_stmt->get_result();

// Display success message if exists
if (isset($_SESSION['enroll_message'])) {
    $message = $_SESSION['enroll_message'];
    unset($_SESSION['enroll_message']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="other resources\fontawesome-free-6.5.2-web\css\all.min.css">
    <title>Students List</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Fredoka';
        }

        body, html {
            height: 100%;
            transition: background-color 0.3s, color 0.3s;
            overflow-x: hidden;
        }

        body.dark-mode {
            background-color: #1a1a1a;
            color: #e0e0e0;
        }

        .container {
            display: flex;
            min-height: 100vh;
            flex-direction: column;
            width: 100%;
        }

        /* Top Navigation - Hidden on desktop, shown on mobile */
        .top-nav {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            background-color: #f8b500;
            padding: 1rem;
            z-index: 1000;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            align-items: center;
            justify-content: space-between;
            height: 70px;
        }
        
        body.dark-mode .top-nav {
            background-color: #333;
        }
        
        .top-nav .logo {
            display: flex;
            align-items: center;
        }
        
        .top-nav .logo img {
            height: 40px;
            width: auto;
        }
        
        .top-nav .menu {
            display: flex !important;
            position: static;
            flex-direction: row;
            background: none;
            box-shadow: none;
            width: auto;
            padding: 0;
            margin: 0;
            gap: 1.5rem;
        }
        
        .top-nav .menu a {
            color: #ffffff;
            text-decoration: none;
            padding: 0.75rem;
            display: flex;
            align-items: center;
            font-size: 1rem;
            border-radius: 8px;
            transition: background 0.3s;
            min-height: 44px;
            min-width: 44px;
            justify-content: center;
            position: relative;
        }
        
        .top-nav .menu a i {
            font-size: 1.4rem;
            margin-right: 0;
        }
        
        .top-nav .menu a span {
            display: none;
        }
        
        .top-nav .menu a:hover,
        .top-nav .menu a.active {
            background-color: rgba(255, 255, 255, 0.2);
        }
        
        .top-nav .menu a::after {
            content: attr(title);
            position: absolute;
            bottom: -30px;
            left: 50%;
            transform: translateX(-50%);
            background-color: #333;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            white-space: nowrap;
            opacity: 0;
            transition: opacity 0.3s;
            pointer-events: none;
        }
        
        .top-nav .menu a:hover::after {
            opacity: 1;
        }
        
        /* Profile in top nav */
        .top-nav-profile {
            display: flex;
            align-items: center;
            position: relative;
        }
        
        .top-nav-profile .profile {
            width: 45px;
            height: 45px;
            background-color: #ffffff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #f5a623;
            font-size: 1.5rem;
            cursor: pointer;
            flex-shrink: 0;
            position: relative;
            border: 2px solid white;
        }
        
        body.dark-mode .top-nav-profile .profile {
            background-color: #333;
        }

        /* Sidebar styling - Hidden on mobile */
        .sidebar {
            position: fixed;
            width: 250px;
            height: 100vh;
            background-color: #f8b500;
            color: #ffffff;
            padding: 2rem 1rem;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            transition: all 0.3s ease;
            z-index: 1000;
        }

        body.dark-mode .sidebar {
            background-color: #333;
        }

        .sidebar.collapsed {
            width: 70px;
            padding: 2rem 0.5rem;
        }

        .sidebar .logo {
            margin-bottom: 1rem;
            margin-left: 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
        }

        .sidebar.collapsed .logo {
            margin-left: 0;
            justify-content: center;
        }

        .toggle-btn {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 5px;
            border-radius: 4px;
            transition: background 0.2s;
            min-height: 44px;
            min-width: 44px;
        }

        .toggle-btn:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .sidebar .menu {
            margin-top: 40%;
            display: flex;
            flex-direction: column;
            flex-grow: 1;
        }

        .sidebar.collapsed .menu{
            align-items: center;
            margin-top: 45%;
        }

        .sidebar .menu a {
            color: #ffffff;
            text-decoration: none;
            padding: 1rem;
            display: flex;
            align-items: center;
            font-size: 1rem;
            border-radius: 5px;
            transition: background 0.3s;
            font-family: 'Fredoka';
            letter-spacing: 1px;
            margin-bottom: .5rem;
            width: 100%;
            min-height: 50px;
        }

        .sidebar.collapsed .menu a {
            justify-content: center;
            padding: 1rem 0;
            width: 90%;
        }

        .sidebar .menu a span {
            margin-left: 0.5rem;
            transition: opacity 0.2s;
            font-family: 'Fredoka';
            font-weight: bold;
            font-size: clamp(16px, 1.5vw, 20px);
        }

        .sidebar.collapsed .menu a span {
            opacity: 0;
            width: 0;
            height: 0;
            overflow: hidden;
            display: none;
        }

        .sidebar .menu a:hover,
        .sidebar .menu a.active {
            background-color: white;
            color: #f8b500;
        }

        body.dark-mode .sidebar .menu a:hover,
        body.dark-mode .sidebar .menu a.active {
            background-color: #444;
            color: #f8b500;
        }

        .sidebar .menu a i {
            margin-right: 0.5rem;
            min-width: 20px;
            text-align: center;
            font-size: clamp(1rem, 1.2vw, 1.5rem);
            flex-shrink: 0;
        }

        .sidebar.collapsed .menu a i {
            margin-right: 0;
            font-size: 1.2rem;
        }

        .sidebar.collapsed .logo-img {
            display: none;
        }

        .sidebar.collapsed .logo-icon {
            display: block !important;
        }

        .sidebar.collapsed hr {
            margin: 0.5rem auto;
            width: 50%;
        }

        /* Dashboard content area */
        .content {
            flex: 1;
            background-color: #ffffff;
            padding: 2rem;
            margin-left: 250px;
            transition: margin-left 0.3s ease, background-color 0.3s;
            width: calc(100% - 250px);
            min-height: 100vh;
        }

        body.dark-mode .content {
            background-color: #1a1a1a;
            color: #e0e0e0;
        }

        .content.expanded {
            margin-left: 70px;
            width: calc(100% - 70px);
        }

        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
            width: 100%;
        }

        .content-header h1 {
            font-size: clamp(1.5rem, 4vw, 2rem);
            color: #333333;
            font-family: 'Fredoka';
            margin-bottom: 0.5rem;
            line-height: 1.2;
        }

        body.dark-mode .content-header h1 {
            color: #e0e0e0;
        }

        /* Profile in content header for larger screens */
        .content-header .actions {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-shrink: 0;
        }

        .content-header .actions .profile {
            width: 50px;
            height: 50px;
            background-color: #ffffff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #f5a623;
            font-size: 1.5rem;
            cursor: pointer;
            flex-shrink: 0;
            position: relative;
        }

        body.dark-mode .content-header .actions .profile {
            background-color: #333;
        }

        .message {
            font-family: Fredoka;
            font-weight: 500;
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
            background-color: #f8f8f8;
            border-left: 4px solid #f8b500;
            width: 100%;
        }

        body.dark-mode .message {
            background-color: #2d2d2d;
            color: #e0e0e0;
        }

        /* Subject Filter Styles */
        .subject-filter-container {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            width: 100%;
        }

        #subject-filter {
            font-size: clamp(14px, 1.5vw, 15px);
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            flex-grow: 1;
            min-width: 200px;
        }

        body.dark-mode #subject-filter {
            background-color: #2d2d2d;
            border-color: #444;
            color: #e0e0e0;
        }

        /* Bulk Enrollment Toggle Button */
        .bulk-enrollment-toggle {
            margin-bottom: 15px;
            width: 100%;
        }

        .toggle-bulk-btn {
            background-color: #f8b500;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-family: 'Fredoka';
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.3s;
            width: 100%;
            font-size: clamp(14px, 1.5vw, 16px);
            min-height: 44px;
        }

        .toggle-bulk-btn:hover {
            background-color: #e5941f;
        }

        .toggle-bulk-btn i:first-child {
            margin-right: 8px;
        }

        #toggleIcon {
            transition: transform 0.3s ease;
        }

        .bulk-visible #toggleIcon {
            transform: rotate(180deg);
        }

        /* Bulk Enrollment Section */
        .csv-upload-section {
            background-color: #f9f9f9;
            border-radius: 10px;
            padding: clamp(1rem, 2vw, 1.5rem);
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border: 1px solid #eee;
            width: 100%;
        }

        body.dark-mode .csv-upload-section {
            background-color: #2d2d2d;
            border-color: #444;
        }

        .upload-header {
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid #f0f0f0;
        }

        body.dark-mode .upload-header {
            border-bottom-color: #444;
        }

        .upload-header h2 {
            color: #333;
            font-size: clamp(1.2rem, 2vw, 1.5rem);
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        body.dark-mode .upload-header h2 {
            color: #e0e0e0;
        }

        .upload-header p {
            color: #666;
            font-size: clamp(0.85rem, 1.5vw, 0.95rem);
        }

        body.dark-mode .upload-header p {
            color: #b0b0b0;
        }

        .upload-steps {
            display: flex;
            flex-direction: column;
            gap: 20px;
            margin-bottom: 25px;
        }

        .step {
            display: flex;
            gap: 15px;
            align-items: flex-start;
        }

        .step-number {
            background-color: #f8b500;
            color: white;
            width: clamp(25px, 3vw, 30px);
            height: clamp(25px, 3vw, 30px);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            flex-shrink: 0;
            margin-top: 3px;
            font-size: clamp(0.8rem, 1.2vw, 1rem);
        }

        .step-content {
            flex-grow: 1;
        }

        .step-content h3 {
            color: #333;
            font-size: clamp(1rem, 1.5vw, 1.1rem);
            margin-bottom: 8px;
        }

        body.dark-mode .step-content h3 {
            color: #e0e0e0;
        }

        .step-content p {
            color: #555;
            font-size: clamp(0.85rem, 1.5vw, 0.95rem);
            margin-bottom: 10px;
        }

        body.dark-mode .step-content p {
            color: #b0b0b0;
        }

        .step-button {
            background-color: #f8b500;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-family: 'Fredoka';
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: background-color 0.3s;
            font-size: clamp(0.85rem, 1.5vw, 1rem);
            min-height: 44px;
        }

        .step-button:hover {
            background-color: #e5941f;
        }

        .hint-box {
            background-color: #fff8e1;
            border-left: 4px solid #ffc107;
            padding: 10px 15px;
            border-radius: 4px;
            font-size: clamp(0.8rem, 1.2vw, 0.9rem);
            color: #5d4037;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        body.dark-mode .hint-box {
            background-color: #3a3a2a;
            border-left-color: #ffc107;
            color: #e0e0e0;
        }

        .upload-form {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }

        .file-upload-wrapper {
            position: relative;
            flex-grow: 1;
            min-width: 200px;
        }

        .file-upload-label {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 15px;
            background-color: white;
            border: 2px dashed #f8b500;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
            width: 100%;
            font-size: clamp(0.85rem, 1.5vw, 1rem);
            min-height: 44px;
        }

        body.dark-mode .file-upload-label {
            background-color: #2d2d2d;
            color: #e0e0e0;
        }

        .file-upload-label:hover {
            background-color: #fffdf6;
            border-color: #e5941f;
        }

        body.dark-mode .file-upload-label:hover {
            background-color: #3a3a3a;
        }

        #csv-upload {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0,0,0,0);
            border: 0;
        }

        .upload-button {
            background-color: #4caf50;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-family: 'Fredoka';
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: background-color 0.3s;
            font-size: clamp(0.85rem, 1.5vw, 1rem);
            min-height: 44px;
        }

        .upload-button:hover {
            background-color: #3d8b40;
        }

        .upload-notes {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 20px;
        }

        .note {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: clamp(0.8rem, 1.2vw, 0.9rem);
            color: #555;
            background-color: #f5f5f5;
            padding: 10px 15px;
            border-radius: 5px;
            flex: 1;
            min-width: 250px;
        }

        body.dark-mode .note {
            background-color: #3a3a3a;
            color: #b0b0b0;
        }

        .note i {
            color: #f8b500;
        }

        /* Enrollment Container */
        .enrollment-container {
            display: flex;
            flex-direction: column;
            gap: 20px;
            margin-bottom: 20px;
            width: 100%;
        }

        /* Enroll New Button */
        .enroll-new-btn {
            background-color: #f8b500;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: clamp(14px, 1.5vw, 16px);
            font-weight: 500;
            margin-bottom: 8px;
            font-family: 'Fredoka';
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 44px;
        }
        
        .enroll-new-btn:hover {
            background-color: #e5941f;
        }

        /* Student Tables Container */
        .student-table-container {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: clamp(1rem, 2vw, 1.5rem);
            background-color: #f9f9f9;
            overflow-x: auto;
            width: 100%;
        }

        body.dark-mode .student-table-container {
            background-color: #2d2d2d;
            border-color: #444;
        }
        
        .student-table-container h3 {
            margin-top: 0;
            color: #333;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
            font-size: clamp(1rem, 1.8vw, 1.2rem);
            margin-bottom: 15px;
        }

        body.dark-mode .student-table-container h3 {
            color: #e0e0e0;
            border-bottom-color: #444;
        }

        /* Search Inputs */
        #available-search, #enrolled-search {
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-family: 'Fredoka';
            font-size: clamp(14px, 1.5vw, 15px);
            width: 100%;
            max-width: 300px;
            margin-bottom: 15px;
            min-height: 44px;
        }

        body.dark-mode #available-search, 
        body.dark-mode #enrolled-search {
            background-color: #2d2d2d;
            border-color: #444;
            color: #e0e0e0;
        }

        #available-search:focus, #enrolled-search:focus{
            outline: none;
            border-color: #f8b500;
            box-shadow: 0 0 5px rgba(248, 181, 0, 0.5);
        }

        /* Student Tables */
        .student-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            min-width: 600px;
        }
        
        .student-table th {
            background-color: #f8b500;
            color: white;
            padding: 12px;
            text-align: left;
            font-weight: 500;
            font-size: clamp(14px, 1.5vw, 15px);
            white-space: nowrap;
        }
        
        .student-table td {
            padding: 12px;
            border-bottom: 1px solid #ddd;
            font-size: clamp(13px, 1.5vw, 14px);
        }

        body.dark-mode .student-table td {
            border-bottom-color: #444;
        }
        
        .student-table tr:nth-child(even) {
            background-color: #f2f2f2;
        }

        body.dark-mode .student-table tr:nth-child(even) {
            background-color: #333;
        }
        
        .student-table tr:hover {
            background-color: #e9e9e9;
        }

        body.dark-mode .student-table tr:hover {
            background-color: #3a3a3a;
        }

        /* Remove Button */
        .remove-btn {
            background-color: #e74c3c;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 5px;
            cursor: pointer;
            font-size: clamp(13px, 1.5vw, 14px);
            transition: background-color 0.3s;
            font-family: 'Fredoka';
            display: flex;
            align-items: center;
            gap: 5px;
            min-height: 36px;
        }

        .remove-btn:hover {
            background-color: #c0392b;
        }

        /* Checkbox styling */
        input[type="checkbox"] {
            transform: scale(1.3);
            cursor: pointer;
            min-height: 20px;
            min-width: 20px;
        }

        /* Enroll Actions */
        .enroll-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            flex-wrap: wrap;
        }
        
        .enroll-btn {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: clamp(14px, 1.5vw, 16px);
            display: flex;
            align-items: center;
            gap: 8px;
            min-height: 44px;
        }
        
        .enroll-btn:hover {
            background-color: #45a049;
        }
        
        .enroll-btn:disabled {
            background-color: #cccccc;
            cursor: not-allowed;
        }

        /* Empty message */
        .empty-message {
            color: #666;
            font-style: italic;
            padding: 20px;
            text-align: center;
            font-family: 'Fredoka';
            font-size: clamp(14px, 1.5vw, 16px);
        }

        body.dark-mode .empty-message {
            color: #b0b0b0;
        }

        /* Dropdown Content */
        .dropdown-content {
            width: min(300px, 90vw);
            right: 0;
            display: none;
            position: absolute;
            background-color: #F8B500;
            border-radius: 15px;
            box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
            z-index: 1001;
            padding: 10px 0;
            top: 100%;
            margin-top: 10px;
        }

        .dropdown-content:before {
            content: " ";
            position: absolute;
            background: #F8B500;
            width: 20px;
            height: 20px;
            top: -5px;
            right: 20px;
            transform: rotate(45deg);
            z-index: -1;
        }

        .dropdown-content button {
            background-color: white;
            font-family: 'Fredoka';
            color: white;
            font-size: clamp(15px, 1.8vw, 18px);
            font-weight: lighter;
            border: 2px solid white !important;
            width: 90% !important;
            padding: 12px 20px !important;
            margin: 8px auto !important;
            text-decoration: none;
            display: block;
            text-align: center;
            background-color: transparent;
            transition: background-color 0.3s, color 0.3s;
            border-radius: 10px;
            cursor: pointer;
            letter-spacing: 1px;
            box-sizing: border-box;
            min-height: 44px;
        }

        .dropdown-content button i{
            margin-right: 4px;
        }

        .dropdown-content a:hover, .dropdown-content button:hover {
            background-color: white !important;
            color: #F8B500;
        }

        .show {
            display: block;
        }

        /* Dark Mode Toggle Button */
        .dark-mode-toggle {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background-color: #f8b500;
            color: white;
            border: none;
            border-radius: 50%;
            width: clamp(50px, 8vw, 60px);
            height: clamp(50px, 8vw, 60px);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: clamp(1.2rem, 2.5vw, 1.5rem);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            z-index: 999;
            transition: background-color 0.3s;
            min-height: 44px;
            min-width: 44px;
        }

        .dark-mode-toggle:hover {
            background-color: #e5941f;
            transform: scale(1.05);
        }

        body.dark-mode .dark-mode-toggle {
            background-color: #444;
        }

        .profile-pic {
            border: 2px solid #f8b500;
            object-fit: cover;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .sidebar {
                display: none;
            }
            
            .top-nav {
                display: flex;
                height: 70px;
                padding: 0.75rem;
            }
            
            .top-nav .logo {
                flex: 1;
            }
            
            .top-nav .logo img {
                height: 35px;
            }
            
            .top-nav .menu {
                gap: 1rem;
            }
            
            .top-nav .menu a {
                padding: 0.6rem;
                min-height: 44px;
                min-width: 44px;
            }
            
            .top-nav .menu a i {
                font-size: 1.3rem;
            }
            
            .top-nav-profile .profile {
                width: 40px;
                height: 40px;
            }
            
            .content {
                padding: 1rem;
                margin-left: 0;
                width: 100%;
                margin-top: 70px;
            }
            
            .content.expanded {
                margin-left: 0;
                width: 100%;
            }
            
            .content-header h1 {
                font-size: 1.5rem;
            }
            
            .content-header .actions {
                display: none;
            }
            
            .subject-filter-container {
                flex-direction: column;
                align-items: flex-start;
            }
            
            #subject-filter {
                margin-left: 0;
                width: 100%;
            }
            
            .upload-form {
                flex-direction: column;
                align-items: stretch;
            }
            
            .file-upload-label {
                justify-content: center;
            }
            
            .upload-button {
                width: 100%;
                justify-content: center;
            }
            
            .upload-notes {
                flex-direction: column;
            }
            
            .note {
                min-width: 100%;
            }
            
            .enroll-actions {
                flex-direction: column;
            }
            
            .enroll-btn {
                width: 100%;
                justify-content: center;
            }
            
            .student-table-container {
                padding: 1rem;
            }
            
            .student-table {
                min-width: 100%;
            }
        }

        @media (min-width: 769px) {
            .top-nav {
                display: none;
            }
            
            .content-header .actions {
                display: flex;
            }
            
            .dropdown-content {
                width: min(260px, 80vw);
                right: 5px;
                margin-top: 5px;
            }
            
            .dropdown-content:before {
                right: 15px;
                width: 14px;
                height: 14px;
                top: -7px;
            }
            
            .dropdown-content button {
                font-size: 15px;
                padding: 9px 14px;
                min-height: 40px;
            }
        }

        @media (max-width: 576px) {
            .top-nav {
                height: 60px;
                padding: 0.5rem;
            }
            
            .top-nav .logo img {
                height: 30px;
            }
            
            .top-nav .menu {
                gap: 0.75rem;
            }
            
            .top-nav .menu a {
                padding: 0.5rem;
                min-height: 40px;
                min-width: 40px;
            }
            
            .top-nav .menu a i {
                font-size: 1.2rem;
            }
            
            .top-nav-profile .profile {
                width: 35px;
                height: 35px;
            }
            
            .content {
                padding: 0.75rem;
                margin-top: 60px;
            }
            
            .toggle-bulk-btn,
            .enroll-new-btn,
            .step-button,
            .upload-button,
            .enroll-btn,
            .remove-btn {
                font-size: 14px;
                padding: 10px 15px;
            }
            
            .student-table th,
            .student-table td {
                padding: 8px;
                font-size: 12px;
            }
            
            .dark-mode-toggle {
                bottom: 10px;
                right: 10px;
                width: 50px;
                height: 50px;
            }
        }

        @media (max-width: 480px) {
            .top-nav {
                padding: 0.4rem;
                height: 55px;
            }
            
            .top-nav .logo img {
                height: 25px;
            }
            
            .top-nav .menu {
                gap: 0.5rem;
            }
            
            .top-nav .menu a {
                padding: 0.4rem;
                min-height: 38px;
                min-width: 38px;
            }
            
            .top-nav .menu a i {
                font-size: 1.1rem;
            }
            
            .top-nav-profile .profile {
                width: 32px;
                height: 32px;
            }
            
            .content {
                padding: 0.5rem;
                margin-top: 55px;
            }

            .content-header h1 {
                font-size: 1.3rem;
            }
            
            .content-header p {
                font-size: 0.85rem;
            }
            
            .step {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .step-number {
                align-self: flex-start;
            }
            
            .dropdown-content {
                width: min(220px, 75vw);
                border-radius: 10px;
            }
            
            .dropdown-content:before {
                right: 12px;
                width: 12px;
                height: 12px;
                top: -6px;
            }
            
            .dropdown-content button {
                font-size: 14px;
                padding: 8px 12px;
                min-height: 38px;
                margin: 4px auto;
            }
            
            .student-table-container h3 {
                font-size: 1rem;
            }
            
            #available-search, #enrolled-search {
                max-width: 100%;
            }
        }

        @media (max-width: 375px) {
            .top-nav {
                padding: 0.3rem;
                height: 50px;
            }
            
            .top-nav .logo img {
                height: 30px;
            }
            
            .top-nav .menu {
                gap: 0.4rem;
            }
            
            .top-nav .menu a {
                padding: 0.3rem;
                min-height: 36px;
                min-width: 36px;
            }
            
            .top-nav .menu a i {
                font-size: 1rem;
            }
            
            .top-nav-profile .profile {
                width: 30px;
                height: 30px;
            }
            
            .content {
                padding: 0.5rem;
                margin-top: 50px;
            }
            
            .dropdown-content {
                width: min(200px, 70vw);
            }
            
            .dropdown-content button {
                font-size: 13px;
                padding: 7px 10px;
                min-height: 36px;
            }
            
            .student-table th,
            .student-table td {
                padding: 6px;
                font-size: 11px;
            }
        }

        /* Utility classes */
        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }

        /* Improve focus accessibility */
        button:focus-visible,
        a:focus-visible,
        input:focus-visible,
        select:focus-visible {
            outline: 2px solid #f8b500;
            outline-offset: 2px;
        }

        /* Smooth scrolling */
        html {
            scroll-behavior: smooth;
        }

        /* Scrollbar styling */
        ::-webkit-scrollbar {
            width: 10px;
            height: 10px;
        }

        ::-webkit-scrollbar-track {
            box-shadow: inset 0 0 5px grey; 
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: #d3d3d3ff; 
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #d3d3d3ff; 
        }
    </style>
</head>
<body>

    <!-- Top Navigation for Mobile -->
    <nav class="top-nav" id="topNav">
        <div class="logo">
            <img src="img/logo 6.png" alt="QuizZap Logo">
        </div>
        <div class="menu" id="topNavMenu">
            <a href="t_Home.php" title="Dashboard">
                <i class="fa-solid fa-house"></i>
                <span>Dashboard</span>
            </a>
            <a href="t_Students.php" class="active" title="Students">
                <i class="fa-regular fa-address-book"></i>
                <span>Students</span>
            </a>
            <a href="t_SubjectsList.php" title="Subjects">
                <i class="fa-solid fa-list"></i>
                <span>Subjects</span>
            </a>
        </div>
        <div class="top-nav-profile">
            <div class="profile" onclick="profileDropdown()">
                <img src="uploads/profiles/<?php echo htmlspecialchars($profile_pic); ?>" alt="Profile Picture" class="profile-pic" onerror="this.src='uploads/profiles/default-profile.jpg'" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
                <div id="dropdown" class="dropdown-content">
                    <button onclick="window.location.href='t_Profile.php'"><i class="fa-solid fa-user"></i> Profile</button> 
                    <form action="logout.php" method="POST">
                        <button><i class="fa-solid fa-right-from-bracket"></i> Logout</button>
                    </form>
                </div>
            </div>
        </div>
    </nav>

    <!-- Dark Mode Toggle Button -->
    <button class="dark-mode-toggle" id="darkModeToggle">
        <i class="fas fa-moon"></i>
    </button>

    <div class="container">
        <!-- Sidebar - Hidden on mobile -->
        <div class="sidebar" id="sidebar">
            <header>
                <button id="toggleSidebar" class="toggle-btn">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="logo">
                    <img src="img/logo4.png" width="200px" height="80px" class="logo-img">
                    <img src="img/logo 6.png" width="50px" height="50px" class="logo-icon" style="display: none; margin-top: 10%;">
                </div>
            </header>
            <hr style="border: 1px solid white;">
            <div class="menu">
                <a href="t_Home.php" title="Dashboard">
                    <i class="fa-solid fa-house"></i>
                    <span>Dashboard</span>
                </a>
                <a href="t_Students.php" class="active" title="Students">
                    <i class="fa-regular fa-address-book"></i>
                    <span>Students</span>
                </a>
                <a href="t_SubjectsList.php" title="Subjects">
                    <i class="fa-solid fa-list"></i>
                    <span>Subjects</span>
                </a>
            </div>
        </div>

        <!-- Content Area -->
        <div class="content">
            <div class="content-header">
                <h1>Students</h1>
                <!-- Profile in content header for larger screens -->
                <div class="actions">
                    <div class="profile" onclick="profileDropdown()">
                        <img src="uploads/profiles/<?php echo htmlspecialchars($profile_pic); ?>" alt="Profile Picture" class="profile-pic" onerror="this.src='uploads/profiles/default-profile.jpg'" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">
                        <div id="dropdown" class="dropdown-content">
                            <button onclick="window.location.href='t_Profile.php'"><i class="fa-solid fa-user"></i> Profile</button> 
                            <form action="logout.php" method="POST">
                                <button><i class="fa-solid fa-right-from-bracket"></i> Logout</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (!empty($message)): ?>
                <div class="message">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- Subject Filter -->
            <div class="subject-filter-container">
                <label for="subject-filter">Select Subject:</label>
                <select id="subject-filter" onchange="filterSubject()">
                    <option value="">All Subjects</option>
                    <?php 
                    // Reset pointer and fetch all subjects with their details
                    $subjects_result->data_seek(0);
                    while ($subject = $subjects_result->fetch_assoc()): 
                        // Get additional subject details for display
                        $subject_details_sql = "SELECT grade_level, section FROM subjects WHERE subject_id = ?";
                        $subject_details_stmt = $conn->prepare($subject_details_sql);
                        $subject_details_stmt->bind_param("i", $subject['subject_id']);
                        $subject_details_stmt->execute();
                        $subject_details = $subject_details_stmt->get_result()->fetch_assoc();
                        $subject_details_stmt->close();
                        
                        $grade_level = $subject_details['grade_level'] ?? '';
                        $section = $subject_details['section'] ?? '';
                        
                        // Format the display text
                        $display_text = htmlspecialchars($subject['subject_name']);
                        if (!empty($grade_level)) {
                            $display_text .= " - Grade " . $grade_level;
                        }
                        if (!empty($section)) {
                            $display_text .= " - " . $section;
                        }
                    ?>
                        <option value="<?php echo htmlspecialchars($subject['subject_id']); ?>" 
                            <?php echo ($selected_subject == $subject['subject_id'] ? 'selected' : ''); ?>>
                            <?php echo $display_text; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <!-- Bulk Enrollment Toggle Button -->
            <div class="bulk-enrollment-toggle">
                <button id="toggleBulkEnrollment" class="toggle-bulk-btn">
                    <i class="fas fa-users"></i> 
                    <span id="toggleText">Bulk Enrollment Options</span>
                    <i class="fas fa-chevron-down" id="toggleIcon"></i>
                </button>
            </div>

            <!-- Bulk Enrollment Section -->
            <div id="bulkEnrollmentSection" class="csv-upload-section" style="display: none;">
                <div class="upload-header">
                    <h2><i class="fas fa-file-import"></i> Bulk Student Enrollment</h2>
                    <p>Easily enroll multiple students at once using a CSV file</p>
                </div>
                
                <div class="upload-steps">
                    <div class="step">
                        <div class="step-number">1</div>
                        <div class="step-content">
                            <h3>Download the Template</h3>
                            <p>Get our pre-formatted CSV file containing all registered students not yet enrolled in your selected subject.</p>
                            <button id="downloadButton" class="step-button">
                                <i class="fas fa-download"></i> Download Student List
                            </button>
                        </div>
                    </div>
                    
                    <div class="step">
                        <div class="step-number">2</div>
                        <div class="step-content">
                            <h3>Select Students to Enroll</h3>
                            <p>Open the CSV file and keep only the rows of students you want to enroll (don't modify the column headers).</p>
                            <div class="hint-box">
                                <i class="fas fa-lightbulb"></i> Tip: You can delete rows for students you don't want to enroll
                            </div>
                        </div>
                    </div>
                    
                    <div class="step">
                        <div class="step-number">3</div>
                        <div class="step-content">
                            <h3>Upload Your File</h3>
                            <p>Select your modified CSV file and click "Import Students" to complete the enrollment.</p>
                            <form method="POST" enctype="multipart/form-data" class="upload-form">
                                <div class="file-upload-wrapper">
                                    <label for="csv-upload" class="file-upload-label">
                                        <i class="fas fa-cloud-upload-alt"></i>
                                        <span id="file-name">Choose your CSV file</span>
                                        <input type="file" id="csv-upload" name="csv_file" accept=".csv" required>
                                    </label>
                                </div>
                                <button type="submit" name="import_csv" class="upload-button">
                                    <i class="fas fa-users"></i> Import Students
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="upload-notes">
                    <div class="note">
                        <i class="fas fa-exclamation-circle"></i>
                        <p>Only CSV files downloaded from this system will be accepted</p>
                    </div>
                    <div class="note">
                        <i class="fas fa-check-circle"></i>
                        <p>The system will automatically skip students already enrolled in this subject</p>
                    </div>
                </div>
            </div>

            <div class="enrollment-container">
                <?php if ($selected_subject): ?>
                    <button id="enroll-new-btn" class="enroll-new-btn">
                        <i class="fas fa-user-plus"></i> Enroll New Students
                    </button>
                    
                    <!-- Available Students Table (hidden by default) -->
                    <div id="available-students-container" class="student-table-container" style="display: none;">
                        <form id="enroll-form" method="POST">
                            <input type="hidden" name="subject" value="<?php echo $selected_subject; ?>">
                            
                            <div style="display: flex; flex-direction: column; gap: 15px; margin-bottom: 15px;">
                                <h3 style="margin: 0;">Available Students</h3>
                                <div>
                                    <input type="text" id="available-search" placeholder="Search students...">
                                </div>
                            </div>
                            <?php if ($all_students_result->num_rows > 0): ?>
                                <table class="student-table" id="available-students-table">
                                    <thead>
                                        <tr>
                                            <th>Select</th>
                                            <th>Name</th>
                                            <th>Account Number</th>
                                            <th>Grade Level</th>
                                            <th>Strand</th>
                                            <th>Section</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $all_students_result->data_seek(0); // Reset pointer
                                        while ($student = $all_students_result->fetch_assoc()): ?>
                                            <tr>
                                                <td><input type="checkbox" name="students[]" value="<?php echo htmlspecialchars($student['account_number']); ?>"></td>
                                                <td><?php echo htmlspecialchars($student['lname'] . ', ' . $student['fname']); ?></td>
                                                <td><?php echo htmlspecialchars($student['account_number']); ?></td>
                                                <td><?php echo htmlspecialchars($student['glevel']); ?></td>
                                                <td><?php echo htmlspecialchars($student['strand'] ?? '-'); ?></td>
                                                <td><?php echo htmlspecialchars($student['section'] ?? '-'); ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <p class="empty-message">No students available to enroll.</p>
                            <?php endif; ?>
                            
                            <div class="enroll-actions">
                                <button type="submit" name="enroll_students" class="enroll-btn" 
                                    <?php echo ($all_students_result->num_rows == 0 ? 'disabled' : ''); ?>>
                                    <i class="fas fa-save"></i> Confirm Enrollment
                                </button>
                                <button type="button" id="cancel-enroll-btn" class="enroll-btn" style="background-color: #ccc;">
                                    <i class="fas fa-times"></i> Cancel
                                </button>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>

                <!-- Enrolled Students Table -->
                <div class="student-table-container">
                    <div style="display: flex; flex-direction: column; gap: 15px; margin-bottom: 15px;">
                        <h3 style="margin: 0;"><?php echo $selected_subject ? 'Currently Enrolled Students' : 'All Enrolled Students'; ?></h3>
                        <div>
                            <input type="text" id="enrolled-search" placeholder="Search enrolled students...">
                        </div>
                    </div>
                    <?php if ($enrolled_students_result->num_rows > 0): ?>
                        <table class="student-table" id="enrolled-students-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Account Number</th>
                                    <th>Grade Level</th>
                                    <th>Strand</th>
                                    <th>Section</th>
                                    <?php if (!$selected_subject): ?>
                                        <th>Subject</th>
                                    <?php endif; ?>
                                    <?php if ($selected_subject): ?>
                                        <th>Action</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $enrolled_students_result->data_seek(0);
                                while ($student = $enrolled_students_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($student['lname'] . ', ' . $student['fname']); ?></td>
                                        <td><?php echo htmlspecialchars($student['account_number']); ?></td>
                                        <td><?php echo htmlspecialchars($student['glevel']); ?></td>
                                        <td><?php echo htmlspecialchars($student['strand'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($student['section'] ?? '-'); ?></td>
                                        <?php if (!$selected_subject): ?>
                                            <td><?php echo htmlspecialchars($student['subject_name']); ?></td>
                                        <?php endif; ?>
                                        <?php if ($selected_subject): ?>
                                            <td>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="student_account" value="<?php echo htmlspecialchars($student['account_number']); ?>">
                                                    <input type="hidden" name="subject_id" value="<?php echo $selected_subject; ?>">
                                                    <button type="submit" name="remove_student" class="remove-btn" 
                                                        onclick="return confirm('Are you sure you want to remove this student from the subject?')">
                                                        <i class="fas fa-user-minus"></i> Remove
                                                    </button>
                                                </form>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="empty-message"><?php echo $selected_subject ? 'No students enrolled in this subject yet.' : 'No students enrolled in any of your subjects yet.'; ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.querySelector('.sidebar');
        const content = document.querySelector('.content');
        const toggleBtn = document.getElementById('toggleSidebar');
        const darkModeToggle = document.getElementById('darkModeToggle');
        const body = document.body;

        // Check if sidebar state is saved in localStorage
        const isSidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        
        // Set initial state based on localStorage
        if (isSidebarCollapsed) {
            sidebar.classList.add('collapsed');
            content.classList.add('expanded');
        }

        // Toggle sidebar when button is clicked
        if (toggleBtn) {
            toggleBtn.addEventListener('click', function() {
                sidebar.classList.toggle('collapsed');
                content.classList.toggle('expanded');
                
                // Save state to localStorage
                localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
            });
        }

        // Dark mode functionality
        const isDarkMode = localStorage.getItem('darkMode') === 'true';

        // Apply dark mode on page load if enabled
        if (isDarkMode) {
            document.body.classList.add('dark-mode');
            darkModeToggle.innerHTML = '<i class="fas fa-sun"></i>';
        }

        darkModeToggle.addEventListener('click', () => {
            body.classList.toggle('dark-mode');
            
            // Update button icon and save preference
            if (body.classList.contains('dark-mode')) {
                darkModeToggle.innerHTML = '<i class="fas fa-sun"></i>';
                localStorage.setItem('darkMode', 'true');
            } else {
                darkModeToggle.innerHTML = '<i class="fas fa-moon"></i>';
                localStorage.setItem('darkMode', 'false');
            }
        });

        // Bulk Enrollment Toggle Functionality
        const bulkToggleBtn = document.getElementById('toggleBulkEnrollment');
        const bulkSection = document.getElementById('bulkEnrollmentSection');
        const toggleText = document.getElementById('toggleText');
        const toggleIcon = document.getElementById('toggleIcon');
        
        // Check if state is saved in localStorage
        const isBulkVisible = localStorage.getItem('bulkEnrollmentVisible') === 'true';
        
        // Set initial state
        if (isBulkVisible) {
            bulkSection.style.display = 'block';
            toggleText.textContent = 'Hide Bulk Enrollment Options';
            bulkToggleBtn.classList.add('bulk-visible');
        }
        
        bulkToggleBtn.addEventListener('click', function() {
            if (bulkSection.style.display === 'none') {
                // Show section
                bulkSection.style.display = 'block';
                toggleText.textContent = 'Hide Bulk Enrollment Options';
                bulkToggleBtn.classList.add('bulk-visible');
                localStorage.setItem('bulkEnrollmentVisible', 'true');
            } else {
                // Hide section
                bulkSection.style.display = 'none';
                toggleText.textContent = 'Show Bulk Enrollment Options';
                bulkToggleBtn.classList.remove('bulk-visible');
                localStorage.setItem('bulkEnrollmentVisible', 'false');
            }
        });

        // File name display for CSV upload
        document.getElementById('csv-upload').addEventListener('change', function(e) {
            const fileName = e.target.files[0] ? e.target.files[0].name : 'Choose your CSV file';
            document.getElementById('file-name').textContent = fileName;
        });

        // Search functionality for available students
        const availableSearch = document.getElementById('available-search');
        if (availableSearch) {
            availableSearch.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                const rows = document.querySelectorAll('#available-students-table tbody tr');
                let hasVisibleRows = false;
                
                rows.forEach(row => {
                    const name = row.cells[1].textContent.toLowerCase();
                    const accountNumber = row.cells[2].textContent.toLowerCase();
                    const gradeLevel = row.cells[3].textContent.toLowerCase();
                    const strand = row.cells[4].textContent.toLowerCase();
                    const section = row.cells[5].textContent.toLowerCase();
                    
                    if (name.includes(searchTerm) || 
                        accountNumber.includes(searchTerm) || 
                        gradeLevel.includes(searchTerm) || 
                        strand.includes(searchTerm) ||
                        section.includes(searchTerm)) {
                        row.style.display = '';
                        hasVisibleRows = true;
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
        }

        // Search functionality for enrolled students
        const enrolledSearch = document.getElementById('enrolled-search');
        if (enrolledSearch) {
            enrolledSearch.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                const rows = document.querySelectorAll('#enrolled-students-table tbody tr');
                let hasVisibleRows = false;
                
                rows.forEach(row => {
                    let found = false;
                    // Check each cell in the row (except the action cell if it exists)
                    for (let i = 0; i < row.cells.length; i++) {
                        // Skip the action column if it exists
                        if (row.cells[i].querySelector('.remove-btn')) continue;
                        
                        const cellText = row.cells[i].textContent.toLowerCase();
                        if (cellText.includes(searchTerm)) {
                            found = true;
                            break;
                        }
                    }
                    
                    if (found) {
                        row.style.display = '';
                        hasVisibleRows = true;
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
        }

        // Toggle available students table
        const enrollNewBtn = document.getElementById('enroll-new-btn');
        const availableStudentsContainer = document.getElementById('available-students-container');
        const cancelEnrollBtn = document.getElementById('cancel-enroll-btn');
        
        if (enrollNewBtn && availableStudentsContainer) {
            enrollNewBtn.addEventListener('click', function() {
                availableStudentsContainer.style.display = 'block';
                enrollNewBtn.style.display = 'none';
                // Scroll to the available students section
                availableStudentsContainer.scrollIntoView({ behavior: 'smooth' });
            });
            
            if (cancelEnrollBtn) {
                cancelEnrollBtn.addEventListener('click', function() {
                    availableStudentsContainer.style.display = 'none';
                    enrollNewBtn.style.display = 'flex';
                    // Uncheck all checkboxes when canceling
                    document.querySelectorAll('#enroll-form input[type="checkbox"]').forEach(checkbox => {
                        checkbox.checked = false;
                    });
                    // Clear search
                    document.getElementById('available-search').value = '';
                    // Show all rows again
                    document.querySelectorAll('#available-students-table tbody tr').forEach(row => {
                        row.style.display = '';
                    });
                });
            }
        }

        // Add download event listener
        document.getElementById('downloadButton').addEventListener('click', function() {
            const selectedSubject = document.getElementById('subject-filter').value;
            if (!selectedSubject) {
                alert('Please select a subject first');
                return;
            }
            window.location.href = `t_Students.php?download_template=1&subject=${selectedSubject}`;
        });

        // Improve touch targets for mobile
        const touchElements = document.querySelectorAll('a, button, .dropdown-content button, input[type="checkbox"]');
        touchElements.forEach(element => {
            if (element.tagName === 'BUTTON' || element.tagName === 'A') {
                element.style.minHeight = '44px';
                element.style.minWidth = '44px';
                element.style.display = 'flex';
                element.style.alignItems = 'center';
                element.style.justifyContent = 'center';
            }
        });
    });

    function profileDropdown() {
        // Close all dropdowns first
        const allDropdowns = document.querySelectorAll('.dropdown-content.show');
        allDropdowns.forEach(drop => {
            drop.classList.remove('show');
        });
        
        // Toggle the clicked dropdown
        const dropdowns = document.querySelectorAll('.dropdown-content');
        dropdowns.forEach(dropdown => {
            dropdown.classList.toggle('show');
        });
    }

    // Close the dropdown if clicked outside
    window.onclick = function(event) {
        if (!event.target.matches('.profile') && !event.target.matches('.profile-pic') && !event.target.closest('.profile')) {
            var dropdowns = document.getElementsByClassName("dropdown-content");
            for (var i = 0; i < dropdowns.length; i++) {
                var openDropdown = dropdowns[i];
                if (openDropdown.classList.contains('show')) {
                    openDropdown.classList.remove('show');
                }
            }
        }
    }

    // Subject filter function
    function filterSubject() {
        const selectedSubject = document.getElementById('subject-filter').value;
        window.location.href = `t_Students.php?subject=${selectedSubject}`;
    }

    // Handle window resize for responsive behavior
    window.addEventListener('resize', function() {
        // Auto-hide sidebar on mobile when resizing to larger screen
        if (window.innerWidth >= 769) {
            // If we're on desktop and sidebar was hidden (mobile state), reset it
            const sidebar = document.querySelector('.sidebar');
            if (sidebar && sidebar.style.display === 'none') {
                sidebar.style.display = 'flex';
            }
        }
    });
    </script>
</body>
</html>

<?php 
$subjects_stmt->close();
if (isset($all_students_stmt)) {
    $all_students_stmt->close();
}
$enrolled_students_stmt->close();
$conn->close();
?>