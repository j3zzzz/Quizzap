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

//to fetch profile pic
$loggedInUser = $_SESSION['account_number'];

$sql = "SELECT profile_pic FROM teachers WHERE account_number = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $loggedInUser);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $profilePic = $row['profile_pic'] ?: "uploads/default_profile.png"; // Pang display ng default profile pic pag wala pang profile pic na nakaset
} else {
    $profilePic = "uploads/default_profile.png"; // Default picture path if no custom picture found
}

$stmt->close();

// Fetch subjects taught by the teacher
$subjects_query = "SELECT subject_id, subject_name FROM subjects WHERE teacher_id = ?";
$subjects_stmt = $conn->prepare($subjects_query);
$subjects_stmt->bind_param("s", $teacher_id);
$subjects_stmt->execute();
$subjects_result = $subjects_stmt->get_result();

// Get selected subject filter (if any)
$selected_subject = isset($_GET['subject']) ? intval($_GET['subject']) : null;

// Construct main query with optional subject filtering
$sql = "
    SELECT DISTINCT s.account_number, s.fname, s.lname, s.glevel, s.strand 
    FROM students AS s
    JOIN enrollments AS e ON s.student_id = e.student_id
    JOIN subjects AS sub ON sub.subject_id = e.subject_id
    WHERE sub.teacher_id = ?
";

// Add subject filter if a specific subject is selected
if ($selected_subject) {
    $sql .= " AND sub.subject_id = ?";
}

$sql .= " GROUP BY s.account_number";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Prepared Failed: " . $conn->error);
}

// Bind parameters based on whether a subject is selected
if ($selected_subject) {
    $stmt->bind_param("ss", $teacher_id, $selected_subject);
} else {
    $stmt->bind_param("s", $teacher_id);
}

$stmt->execute();
$result = $stmt->get_result();

//for bulk upload
$message = '';

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

        // Validate mandatory columns
        if ($account_number_index === false || $fname_index === false || 
            $lname_index === false || $glevel_index === false) {
            $message = "Error: CSV file must contain 'account_number', 'fname', 'lname', and 'glevel' columns.";
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
                        // Extract required fields
                        $account_number = trim($data[$account_number_index]);
                        $fname = trim($data[$fname_index]);
                        $lname = trim($data[$lname_index]);
                        $glevel = intval(trim($data[$glevel_index]));
                    
                        // Handle optional strand
                        $strand = null;
                        if ($strand_index !== false) {
                            $strand_value = trim($data[$strand_index]);
                            // Only set strand for grades 11 and 12
                            if (in_array($glevel, [11, 12]) && !empty($strand_value)) {
                                $strand = $strand_value;
                            }
                        }

                        // Validate mandatory fields
                        if (empty($account_number) || empty($fname) || empty($lname) || empty($glevel)) {
                            $skipped_count++;
                            continue;
                        }

                        // NEW: Check if the student is already registered in the system
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
                        $check_student_stmt->close();

                        // Prepare SQL for update existing student
                        $student_sql = "UPDATE students SET 
                            fname = ?, 
                            lname = ?, 
                            glevel = ?, 
                            strand = COALESCE(?, strand)
                            WHERE account_number = ?";

                        $student_stmt = $conn->prepare($student_sql);
                        $student_stmt->bind_param(
                            "ssiss", 
                            $fname, 
                            $lname, 
                            $glevel, 
                            $strand, 
                            $account_number
                        );

                        if ($student_stmt->execute()) {
                            // Insert enrollment
                            $enrollment_sql = "INSERT INTO enrollments (student_id, subject_id) 
                                SELECT student_id, ? FROM students 
                                WHERE account_number = ?
                                ON DUPLICATE KEY UPDATE subject_id = VALUES(subject_id)";

                            $enrollment_stmt = $conn->prepare($enrollment_sql);
                            $enrollment_stmt->bind_param("is", $subject_id, $account_number);

                            if ($enrollment_stmt->execute()) {
                                if ($student_stmt->affected_rows > 0) {
                                    $updated_count++;
                                }
                                
                                // Check if enrollment was successful
                                if ($enrollment_stmt->affected_rows > 0) {
                                    $imported_count++;
                                }
                            }
                            
                            $enrollment_stmt->close();
                        }

                        $student_stmt->close();
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

                    $_SESSION['import_message'] = "Import completed for subject: {$subject_name}.
                        Total students imported: $imported_count.
                        Total students updated: $updated_count.
                        Skipped students (not registered): $skipped_count.
                        Total students in subject: $total_students.";

                    header("Location: t_Students.php?subject_id={$subject_id}");
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

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <title>Students List</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        body, html {
            height: 100%;
        }

        .container {
            display: flex;
            height: 100vh;
        }

        .sidebar {
            position: fixed;
            width: 250px;
            height: 100vh;
            background-color: #F8B500;
            color: #ffffff;
            padding: 2rem 1rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .sidebar .logo {
            margin-bottom: 1rem;
            margin-left: 5%;
        }

        hr{
            border: 1px solid white;
        }

        .sidebar .menu {
            display: flex;
            flex-direction: column;
            margin-bottom: 18rem;
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
            font-family: Tilt Warp Regular;
            margin-bottom: .5rem;
        }

        .sidebar .menu a:hover, .sidebar .menu a.active {
            background-color: white;
            color: #F8B500;
        }

        .sidebar .menu a i {
            margin-right: 0.5rem;
        }

        /* Dashboard content area */
        .content {
            flex: 1;
            background-color: #ffffff;
            padding: 3rem;
            margin-left: 17%;
        }

        .content span {
            font-family: Tilt Warp Regular;
            font-size: larger;
        }

        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .content-header h1 {
            font-size: 2rem;
            color: #333333;
            font-family: Tilt Warp Regular;
        }

        .content-header p {
            color: #999;
            font-size: 1rem;
            margin-top: 0.5rem;
            font-family: Tilt Warp Regular;
        }

        .content-header .actions {
            display: flex;
            align-items: center;
        }

        .content-header .actions button {
            background-color: #F8B500;
            color: #ffffff;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            font-size: 1rem;
            cursor: pointer;
            margin-right: 1rem;
            font-family: Tilt Warp Regular;
        }

        .content-header .actions button:hover {
            background-color: #e5941f;
        }

        .content-header .actions .profile {
            width: 40px;
            height: 40px;
            background-color: #ffffff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #f5a623;
            font-size: 1.5rem;
        }

        .content-header hr{
            border: 1px solid #F8B500;
            width: 100%;
            margin-top: 5% !important;
            margin-left: -12%;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-family: Tilt Warp Regular;
        }

        th {
            background-color: #f8b500;
            color: white;
            font-weight: bold;
            padding: 20px;
            text-align: center;
            font-family: Tilt Warp Regular;
        }

        td {
            padding: 20px;
            text-align: center;
            font-family: Tilt Warp Regular;
        }

        tr:nth-child(even) {
            background-color: #f2f2f2;
        }

        table tr:first-child th:first-child {
            border-top-left-radius: 10px;
        }

        table tr:first-child th:last-child {
            border-top-right-radius: 10px;
        }

        table tr:last-child td:first-child {
            border-bottom-left-radius: 10px;
        }

        table tr:last-child td:last-child {
            border-bottom-right-radius: 10px;
        }

        .progress-bar-container {
            width: 100%;
            height: 20px;
            background-color: #e6e6e6;
            position: relative;
        }

        .progress-bar {
            height: 100%;
            background-color: #4CAF50;
        }

        .due-date {
            text-align: right;
            padding-right: 10px;
        }

        label{
            font-family: Tilt Warp Regular;
            letter-spacing: 1px;
        }

        .heading{
            font-size: 20px;
        }

        .delete-button {
            background-color: transparent;
            border: none;
            cursor: pointer;
            color: black;
            font-size: 20px;
            font-family: Tilt Warp Regular;
        }

        .filter-container {
            display: flex;
            align-items: center;
        }

        .filter-container select {
            margin-left: 20px;
            padding: 8px;
            font-size: 15px;
            font-family: Tilt Warp Regular;
            border: none;
        }

        .filter-container option{
            font-family: Tilt Warp Regular;
        }

        .bulk-actions {
            margin-bottom: 20px;
        }

        .bulk-actions button {
            background-color: #F8B500;
            color: white;
            border: none;
            padding: 10px 15px;
            margin-right: 10px;
            cursor: pointer;
        }

        #delete-selected-btn{
            font-family: Tilt Warp Regular;
            font-size: 15px;
            border-radius: 8px;
            margin-top: 2%;
        }

        #csv-cont {
            display: flex;
            gap: 25%;
        }

        #bulk {
            font-family: Tilt Warp Regular !important;
            width: 50%;
            border-radius: 8px;
            display: flex;
            line-height: 1;
            align-items: center !important;
            justify-items: center !important;
        }

        #bulk form {
            margin: auto;
        }

        .choose-file {
            border-radius: 5px;
            cursor: pointer;
            font-family: Tilt Warp Regular;
            background-color: #ccc;
        }

        .choose-file::-webkit-file-upload-button {
            visibility: hidden;
            width: 0;
        }

        .choose-file::before {
            content: 'Choose a CSV File';
            display: inline-block;
            color: #f8b500;
            background-color: whitesmoke;
            border: 2px solid #f8b500;
            padding: 10px 15px;
            border-radius: 5px;
            cursor: pointer;
        }

        .choose-file:hover::before {
            background-color: #e5941f;
            color: white;
            border: 2px solid #e5941f;
        }

        .submit-csv {
            padding: 0.6rem 0.8rem;
            border: 2px solid #f8b500;
            cursor: pointer;
            color: white;
            border-radius: 5px;
            font-family: Tilt Warp Regular;
            transition: 0.2s;
            background-color: #f8b500;
        }

        .submit-csv:hover {
            background-color: #f8b500;
            color: white;
            border: 2px solid #f8b500;
        }

        .message {
            font-family: Tilt Warp Regular;
        }

        .file-container {
            display: flex;
            align-items: center;
            background-color: white;
            padding: 5px 15px !important;
            border-radius: 8px;
            line-height: 1;
            margin-left: 10px;
        }
        .file-icon {
            font-size: 40px;
            color: #c59000;
            margin-right: 15px;
        }
        .download-btn {
            font-family: Tilt Warp Regular;
            background-color: #f8b500;
            color: white;
            border: none;
            padding: 10px 8px;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        .download-btn:hover {
            background-color: #f79b00;
        }    

        #subject-filter {
            border-radius: 8px;
        }

        .dropdown-content {
            width: 300px;
            right: 1%;
            display: none;
            position: absolute;
            background-color: #F8B500;
            border-radius: 15px;
            box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
            z-index: 1;
            padding: 10px 0;
            top: 15%;
        }

        .dropdown-content:before {
            content: " " ;
            position: absolute;
            background: #F8B500;
            width: 30px;
            height: 30px;
            top: 1px;
            right: 23px;
            transform: rotate(135deg);
            z-index: -1 !important;
        }

        .dropdown-content button {
            background-color: white;     
            justify-content: center;
            align-items: center;
            align-self: center;
            font-family: Purple Smile;
            font-size: 18px;
            font-weight: lighter;
            border: 2px solid white !important;
            color: black;
            width: 86% !important;
            padding: 13px 20px !important;
            margin: 8px 20px !important;
            text-decoration: none;
            display: block;
            float: none;
            text-align: center;
            background-color: transparent;
            transition: background-color 0.3s, color 0.3s;
            border-radius: 10px;
            cursor: pointer;
            letter-spacing: 1px;
            box-sizing: border-box;
            z-index: 1 !important;  
        }

        .dropdown-content a:hover, .dropdown-content button:hover{
            background-color: white !important;
            color: #F8B500;
        }

        .show {
             display: block;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <div class="sidebar">
            <header>
                <div class="logo"><img src="img/logo4.png" onclick="window.location.href='t_Profile.php'" width="200px" height="80px"></div>
            </header>
            <hr>
            <div class="menu">
                <a href="t_Home.php"><i class="fa-solid fa-house"></i>Dashboard</a>
                <a href="t_Students.php" class="active"><i class="fa-regular fa-address-book"></i>Students</a>
                <a href="t_SubjectsList.php"><i class="fa-solid fa-list"></i>Subjects</a>
            </div>
        </div>

        <!-- Content Area -->
        <div class="content">
            <div class="content-header">
                <h1>Students</h1><br>
                <hr>
                <div class="actions">
                    <div class="profile">
                        <img src="<?php echo $profilePic; ?>" onclick="profileDropdown()" width="50px" height="50px" class="dropdwn-btn">

                    <div id="dropdown" class="dropdown-content">
                        <button onclick="window.location.href='s_Profile.php'"><i class="fa-solid fa-user"></i> Profile</button> 
                        <form action="logout.php" method="post">
                            <button><i class="fa-solid fa-right-from-bracket"></i> Logout</button>
                        </form>
                    </div> 

                    </div>
                </div>
            </div>

            <span>Enroll Students into your Subjects:</span>

            <div id="csv-cont">
                 <div id="bulk">        
                    <!-- HTML Form for CSV Upload -->
                   
                    <form method="POST" enctype="multipart/form-data">
                        <input type="file" class="choose-file" name="csv_file" accept=".csv" required>
                        <input type="submit" class="submit-csv" name="import_csv" value="Import Students">
                    </form>  
                </div>


                <div class="file-container">
                    <i class="fa-solid fa-file-csv file-icon"></i>
                    <button id="downloadButton" class="download-btn">Download CSV Template</button>
                </div>
            </div>

            <?php 
            if (isset($_SESSION['import_message'])) {
                    $message = $_SESSION['import_message'];

                    unset($_SESSION['import_message']);
                }
            ?>    

            <?php if (!empty($message)): ?>
                <div class="message">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>  

            <!-- Bulk Actions -->
            <div class="bulk-actions">
                <button id="delete-selected-btn" style="display:none;">Delete Selected Students</button>
            </div>

           <table>
                <tr>
                    <th colspan="7">
                        <div class="filter-container">
                        <label for="subject-filter">Filter by Subject:</label>
                        <select id="subject-filter" onchange="filterSubject()">
                            <option value="">All Subjects</option>
                            <?php while ($subject = $subjects_result->fetch_assoc()) { ?>
                        <option value="<?php echo htmlspecialchars($subject['subject_id']); ?>" 
                                <?php echo ($selected_subject == $subject['subject_id'] ? 'selected' : ''); ?>>
                            <?php echo htmlspecialchars($subject['subject_name']); ?>
                        </option>
                            <?php } ?>
                        </select>
                        </div>
                    </th>
                </tr>

                <tr class="heading">
                   <td><input type="checkbox" id="select-all-checkbox"></td>
                   <td>Account Number</td>
                   <td>First Name</td>
                   <td>Last Name</td>
                   <td>Grade Level</td>
                   <td>Strand</td>
               </tr>

                <?php while ($row = $result->fetch_assoc()) { ?>
                <tr data-account-number="<?php echo htmlspecialchars($row['account_number']); ?>">
                    <td><input type="checkbox" class="student-checkbox"></td>
                    <td><?php echo htmlspecialchars($row['account_number']); ?></td>
                    <td><?php echo htmlspecialchars($row['fname']); ?></td>
                    <td><?php echo htmlspecialchars($row['lname']); ?></td>
                    <td><?php echo htmlspecialchars($row['glevel']); ?></td>
                    <td><?php echo htmlspecialchars($row['strand']); ?></td>
                </tr>
            <?php } 
                if ($result->num_rows === 0) { ?>
                <tr>
                    <td colspan="7" style="color: #6666;">You don't have any students yet.</td> 
                </tr>
            <?php } ?>
            </table> 
            <br><br>
        </div>
    </div>

<script>
    // Profile picture click handler
    document.querySelector('.profile').addEventListener('click', function() {
        window.location.href = 't_Profile.php';
    });

    // CSV template with only headers
    const csvTemplate = `"Account Number","First Name","Last Name","Grade Level","Strand"`;

    // Function to download CSV template
    function downloadCSVTemplate() {
        const blob = new Blob([csvTemplate], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        
        link.setAttribute('href', url);
        link.setAttribute('download', 'student_template.csv');
        link.style.visibility = 'hidden';
        
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    // Add download event listener
    document.getElementById('downloadButton').addEventListener('click', downloadCSVTemplate);

    // Subject filter function
    function filterSubject() {
        const selectedSubject = document.getElementById('subject-filter').value;
        window.location.href = `t_Students.php?subject=${selectedSubject}`;
    }

    // Select all checkbox handler
    const selectAllCheckbox = document.getElementById('select-all-checkbox');
    const studentCheckboxes = document.querySelectorAll('.student-checkbox');
    const deleteSelectedBtn = document.getElementById('delete-selected-btn');

    selectAllCheckbox.addEventListener('change', function() {
        studentCheckboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
        });
        toggleDeleteButton();
    });

    studentCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', toggleDeleteButton);
    });

    function toggleDeleteButton() {
        const selectedCheckboxes = document.querySelectorAll('.student-checkbox:checked');
        deleteSelectedBtn.style.display = selectedCheckboxes.length > 0 ? 'block' : 'none';
    }

    // Individual delete button handler
    document.querySelectorAll('.delete-button').forEach(button => {
        button.addEventListener('click', (event) => {
            const accountNumber = event.target.closest('tr').dataset.accountNumber;

            if (confirm('Are you sure you want to remove this student from your class?')) {
                deleteStudents([accountNumber]);
            }
        });
    });

    // Delete selected students button handler
    deleteSelectedBtn.addEventListener('click', () => {
        const selectedAccountNumbers = Array.from(
            document.querySelectorAll('.student-checkbox:checked')
        ).map(checkbox => 
            checkbox.closest('tr').dataset.accountNumber
        );

        if (confirm(`Are you sure you want to remove ${selectedAccountNumbers.length} students from your class?`)) {
            deleteStudents(selectedAccountNumbers);
        }
    });

    // Bulk delete function
    function deleteStudents(accountNumbers) {
        fetch('delete_multiple_users.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: `account_numbers=${accountNumbers.join(',')}`
        })
        .then(response => response.text())
        .then(result => {
            if (result === 'success') {
                alert('Selected students successfully removed from your class');
                accountNumbers.forEach(accountNumber => {
                    const row = document.querySelector(`tr[data-account-number="${accountNumber}"]`);
                    if (row) row.remove();
                });
                selectAllCheckbox.checked = false;
                toggleDeleteButton();
            } else {
                alert('Failed to remove students. ' + result);
            }
        })
        .catch(error => {
            console.error('Error:', error)
            alert('An error occurred while deleting students');
        });
    }
</script>

</body>
</html>

<?php 
$stmt->close();
$subjects_stmt->close();
$conn->close();
?>