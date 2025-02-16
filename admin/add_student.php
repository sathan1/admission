<?php
include '../include/db.php';
session_start();

// Check admin authentication
if (!isset($_SESSION['userId']) || $_SESSION['userRole'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

$message = '';
$success = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->begin_transaction();

        // User credentials
        $username = $_POST['username'];
        $email = $_POST['email'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $userRole = 'student'; // Default role

        // Create user first
        $stmt = $conn->prepare("INSERT INTO users 
            (userName, userEmail, userPassword, userRole, createdAt) 
            VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("ssss", $username, $email, $password, $userRole);
        $stmt->execute();
        $studentUserId = $stmt->insert_id;

        // Student details
        $stmt = $conn->prepare("INSERT INTO studentdetails 
            (studentUserId, studentFirstName, studentLastName, studentFatherName, studentMotherName, 
            studentDateOfBirth, studentGender, studentCaste, studentCaste_2, studentReligion, 
            studentMotherTongue, studentPhoneNumber) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssssssssss", 
            $studentUserId,
            $_POST['firstName'],
            $_POST['lastName'],
            $_POST['fatherName'],
            $_POST['motherName'],
            $_POST['dob'],
            $_POST['gender'],
            $_POST['caste'],
            $_POST['otherCaste'],
            $_POST['religion'],
            $_POST['motherTongue'],
            $_POST['phone']
        );
        $stmt->execute();

        // Academic details
        $stmt = $conn->prepare("INSERT INTO academic 
            (academicUserId, school_name, yearOfPassing, tamilMarks, englishMarks, 
            mathsMarks, scienceMarks, socialScienceMarks, otherLanguageMarks, totalMarks) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $totalMarks = array_sum([
            $_POST['tamil'],
            $_POST['english'],
            $_POST['maths'],
            $_POST['science'],
            $_POST['socialScience'],
            $_POST['otherLanguage']
        ]);
        $stmt->bind_param("isiiiiiiii", 
            $studentUserId,
            $_POST['school_name'],
            $_POST['yearOfPassing'],
            $_POST['tamil'],
            $_POST['english'],
            $_POST['maths'],
            $_POST['science'],
            $_POST['socialScience'],
            $_POST['otherLanguage'],
            $totalMarks
        );
        $stmt->execute();

        // Preferences
        $stmt = $conn->prepare("INSERT INTO preference 
            (preferenceUserId, preferenceOrder, preferenceDepartment, preferenceStatus) 
            VALUES (?, ?, ?, 'pending')");
        $order = 1;
        $stmt->bind_param("iis", $studentUserId, $order, $_POST['department1']);
        $stmt->execute();
        $order = 2;
        $stmt->bind_param("iis", $studentUserId, $order, $_POST['department2']);
        $stmt->execute();

        // File upload handling
        $uploadDir = "../documents/";
        $documentPaths = [];
        foreach (['aadhaar', 'marksheet', 'photo'] as $fileType) {
            if ($_FILES[$fileType]['error'] === UPLOAD_ERR_OK) {
                $fileName = uniqid() . '_' . basename($_FILES[$fileType]['name']);
                $targetPath = $uploadDir . $fileName;
                move_uploaded_file($_FILES[$fileType]['tmp_name'], $targetPath);
                $documentPaths[$fileType] = $fileName;
            }
        }

        // Document references
        $stmt = $conn->prepare("INSERT INTO document 
            (documentUserId, documentType, documentName) 
            VALUES (?, 'documents', ?)");
        $documentData = json_encode($documentPaths);
        $stmt->bind_param("is", $studentUserId, $documentData);
        $stmt->execute();

        $conn->commit();
        $message = "Student record created successfully! Login credentials sent to " . htmlspecialchars($email);
        $success = true;
    } catch (Exception $e) {
        $conn->rollback();
        $message = "Error creating student record: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Student Entry</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
            
      /* General Reset */
 body {
     font-family: 'Roboto', sans-serif;
     background-color: #f4f6f9;
     color: #333;
     margin: 0;
     padding: 0;
     line-height: 1.6;
 }
 
 /* Sidebar Styles */
 .sidebar {
     height: 100vh;
     position: fixed;
     top: 0;
     left: 0;
     width: 250px;
     background-color: #2c3e50;
     color: #ecf0f1;
     border-right: 1px solid #34495e;
     box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
     overflow-y: auto;
     padding-top: 70px; /* Align sidebar content with header */
 }
 
 .sidebar a {
     color: #bdc3c7;
     text-decoration: none;
     padding: 15px 20px;
     display: block;
     border-bottom: 1px solid #34495e;
     font-weight: 500;
     transition: all 0.3s ease;
 }
 
 .sidebar a:hover {
     background-color: #34495e;
     color: #1abc9c;
 }
 
 /* Content Area */
 .content {
     margin-left: 250px;
     padding: 30px;
     margin-top: 70px; /* Adjust for header space */
     background-color: #f4f6f9;
     min-height: calc(100vh - 70px);
 }
 
 /* Header Styles */
 .header {
     background-color: #ffffff;
     border-bottom: 1px solid #ddd;
     padding: 10px 20px;
     box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
     position: fixed;
     width: 100%;
     top: 0;
     z-index: 1000;
     display: flex;
     justify-content: space-between;
     align-items: center;
 }
 
 .header .title {
     font-size: 24px;
     color: #2c3e50;
     font-weight: 700;
 }
 
 .header .logout-btn {
     color: #ffffff;
     background-color: #e74c3c;
     border: none;
     padding: 10px 15px;
     font-size: 14px;
     border-radius: 5px;
     transition: background-color 0.3s ease;
 }
 
 .header .logout-btn:hover {
     background-color: #c0392b;
 }
 
 /* Buttons */
 .btn-primary {
     background-color: #3498db;
     border: none;
     font-weight: 600;
     padding: 10px 15px;
     border-radius: 5px;
     color: #ffffff;
     transition: background-color 0.3s ease, transform 0.2s ease;
 }
 
 .btn-primary:hover {
     background-color: #2980b9;
     transform: translateY(-2px);
 }
 
 .btn {
     font-size: 0.95rem;
     padding: 10px 15px;
     border-radius: 5px;
 }
 
 /* Table Styles */
 .table {
     margin-top: 20px;
     border-collapse: collapse;
     width: 100%;
     background-color: #ffffff;
     border-radius: 8px;
     overflow: hidden;
     box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
 }
 
 .table thead th {
     background-color: #2980b9;
     color: #ffffff;
     text-align: center;
     font-weight: 700;
     padding: 15px;
     border-bottom: 2px solid #1c5980;
 }
 
 .table tbody tr {
     transition: background-color 0.3s ease;
 }
 
 .table tbody tr:nth-child(even) {
     background-color: #f9f9f9;
 }
 
 .table tbody tr:hover {
     background-color: #ecf0f1;
 }
 
 .table tbody td {
     vertical-align: middle;
     text-align: center;
     padding: 12px;
     border-bottom: 1px solid #ddd;
 }
 
 /* Badge Styles */
 .badge {
     display: inline-block;
     padding: 0.4em 0.8em;
     font-size: 0.85rem;
     font-weight: 600;
     text-align: center;
     border-radius: 0.25rem;
 }
 
 .bg-success {
     background-color: #2ecc71 !important;
     color: white !important;
 }
 
 .bg-danger {
     background-color: #e74c3c !important;
     color: white !important;
 }
 
 .bg-warning {
     background-color: #f1c40f !important;
     color: black !important;
 }
 
 .bg-secondary {
     background-color: #7f8c8d !important;
     color: white !important;
 }
 
 /* Form Styling */
 form {
     margin-top: 20px;
 }
 
 form .form-control {
     border-radius: 5px;
     padding: 10px;
     border: 1px solid #ccc;
     transition: border-color 0.3s ease;
 }
 
 form .form-control:focus {
     border-color: #3498db;
     box-shadow: 0 0 5px rgba(52, 152, 219, 0.5);
 }
 
 form .btn {
     font-size: 0.9rem;
     font-weight: 600;
     padding: 10px 20px;
 }
 
 /* Dropdown */
 select.form-select {
     max-width: 300px;
     margin: 10px auto;
     padding: 10px;
     border-radius: 5px;
     border: 1px solid #ccc;
 }
 
 /* Responsive Design */
 @media (max-width: 768px) {
     .sidebar {
         position: relative;
         height: auto;
         width: 100%;
         padding-top: 0;
     }
 
     .content {
         margin-left: 0;
         margin-top: 100px;
     }
 
     .table {
         font-size: 0.9rem;
     }
 
     .btn {
         font-size: 0.8rem;
         padding: 8px 12px;
     }
 
     .header {
         padding: 10px 15px;
     }
 }
        .form-container {
            background: #ffffff;
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            padding: 30px;
            margin-top: 30px;
        }
        .nav-tabs .nav-link {
            border: none;
            color: #6c757d;
            font-weight: 500;
            transition: all 0.3s;
        }
        .nav-tabs .nav-link.active {
            color: #0d6efd;
            border-bottom: 3px solid #0d6efd;
            background: transparent;
        }
        .section-title {
            color: #2c3e50;
            border-left: 4px solid #0d6efd;
            padding-left: 15px;
            margin: 25px 0;
        }
        .file-upload {
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            transition: border-color 0.3s;
        }
        .file-upload:hover {
            border-color: #0d6efd;
        }
        .preview-image {
            max-width: 200px;
            margin-top: 10px;
            border-radius: 5px;
        }
    </style>
</head>
<body>






<?php
include '../header_admin.php';
?>
<!-- Sidebar for larger screens -->
<nav class="sidebar d-none d-md-block">
    <h4 class="text-center mt-3">Student Forms</h4>
    <a href="dashboard.php">Dashboard</a>
    <a href="form_a.php">Form A</a>
    <a href="form_b.php">Form B</a>
    <a href="form_c.php">Form C</a>
    <a href="form_d.php">Form D</a>
    <a href="form_e.php">Form E</a>
</nav>

<!-- Mobile menu toggle button -->
<div class="mobile-menu-btn d-md-none p-2 bg-dark text-white text-center">
    <button class="btn btn-light" type="button" data-bs-toggle="collapse" data-bs-target="#mobileMenu" aria-expanded="false" aria-controls="mobileMenu">
        Menu
    </button>
</div>

<!-- Mobile menu -->
<div class="collapse d-md-none" id="mobileMenu">
    <nav class="bg-dark">
        <a href="dashboard.php" class="text-white">Dashboard</a>
        <a href="form_a.php" class="text-white">Form A</a>
        <a href="form_b.php" class="text-white">Form B</a>
        <a href="form_c.php" class="text-white">Form C</a>
        <a href="form_d.php" class="text-white">Form D</a>
        <a href="form_e.php" class="text-white">Form E</a>
    </nav>
</div>

<!-- Main content -->
<div class="content">
    <div class="container mt-4">  
<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="form-container">
                <h2 class="mb-4"><i class="fas fa-user-plus me-2"></i>Create New Student Record</h2>
                
                <?php if ($message): ?>
                <div class="alert alert-<?= $success ? 'success' : 'danger' ?>"><?= $message ?></div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <!-- New User Credentials Section -->
                    <div class="credential-section">
                        <h4 class="section-title mb-4"><i class="fas fa-user-lock me-2"></i>Login Credentials</h4>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Username</label>
                                <input type="text" name="username" class="form-control" required 
                                    pattern="[a-zA-Z0-9_]{5,20}" title="5-20 characters (letters, numbers, underscores)">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Password</label>
                                <input type="password" name="password" id="password" 
                                    class="form-control" required minlength="8">
                                <div class="password-strength mt-1"></div>
                            </div>
                        </div>
                    </div>

                <?php if ($message): ?>
                <div class="alert alert-<?= $success ? 'success' : 'danger' ?>"><?= $message ?></div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <ul class="nav nav-tabs mb-4" id="formTabs">
                        <li class="nav-item">
                            <a class="nav-link active" data-bs-toggle="tab" href="#personal">Personal</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#academic">Academic</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#preferences">Preferences</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#documents">Documents</a>
                        </li>
                    </ul>

                    <div class="tab-content">
                        <!-- Personal Details Tab -->
                        <div class="tab-pane fade show active" id="personal">
                            <h4 class="section-title">Personal Information</h4>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="firstName" class="form-label">First Name</label>
                                    <input type="text" name="firstName" id="firstName" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="lastName" class="form-label">Last Name</label>
                                    <input type="text" name="lastName" id="lastName" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="fatherName" class="form-label">Father's Name</label>
                                    <input type="text" name="fatherName" id="fatherName" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="motherName" class="form-label">Mother's Name</label>
                                    <input type="text" name="motherName" id="motherName" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="dob" class="form-label">Date of Birth</label>
                                    <input type="date" name="dob" id="dob" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="gender" class="form-label">Gender</label>
                                    <select name="gender" id="gender" class="form-control" required>
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                <label for="community" class="form-label">Community</label>
                                <select name="caste" id="caste" class="form-control" required>
                                    <option value="BC">BC - Backward Class</option>
                                    <option value="MBC">MBC - Most Backward Class</option>
                                    <option value="SC">SC - Scheduled Caste</option>
                                    <option value="ST">ST - Scheduled Tribe</option>
                                    <option value="OC">OC - Open Category</option>
                                    <option value="BC(M)">BC(M) - Backward Class (Muslim)</option>
                                    <option value="MBC(M)">MBC(M) - Most Backward Class (Muslim)</option>
                                    <option value="BC(Christian)">BC(Christian) - Backward Class (Christian)</option>
                                    <option value="MBC(Christian)">MBC(Christian) - Most Backward Class (Christian)</option>
                                    <option value="Others">Others - Any other types</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="extraCaste" class="form-label">caste</label>
                                <input type="text" name="otherCaste" id="otherCaste" class="form-control">
                            </div>
                            <div class="col-md-6">
                            <label for="religion" class="form-label">Religion</label>
                            <select name="religion" id="religion" class="form-control" required>
                                <option value="Hindu">Hindu</option>
                                <option value="Muslim">Muslim</option>
                                <option value="Christian">Christian</option>
                                <option value="Jain">Jain</option>
                                <option value="Sikh">Sikh</option>
                                <option value="Buddhist">Buddhist</option>
                                <option value="Others">Others</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="motherTongue" class="form-label">Mother Tongue</label>
                            <select name="motherTongue" id="motherTongue" class="form-control" required>
                                <option value="Tamil">Tamil</option>
                                <option value="Telugu">Telugu</option>
                                <option value="Kannada">Kannada</option>
                                <option value="Sowrashtra">Sowrashtra</option>
                                <option value="Malayalam">Malayalam</option>
                                <option value="Hindi">Hindi</option>
                                <option value="Urdu">Urdu</option>
                                <option value="Others">Others</option>
                            </select>
                        </div>

                                <div class="col-md-6">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input type="text" name="phone" id="phone" class="form-control" required>
                                </div>
                                <!-- Add other personal details fields -->
                            </div>
                        </div>

                        <!-- Academic Details Tab -->
                        <div class="tab-pane fade" id="academic">
                            <h4 class="section-title">Academic Information</h4>
                            <div class="row g-3">
                             <div class="mb-3">
                                <label for="school_name" class="form-label">School Name</label>
                                <input type="text" name="school_name" id="school_name" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label for="yearOfPassing" class="form-label">Year of Passing</label>
                                <input type="number" name="yearOfPassing" id="yearOfPassing" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label for="tamil" class="form-label">Tamil Marks</label>
                                <input type="number" name="tamil" id="tamil" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label for="english" class="form-label">English Marks</label>
                                <input type="number" name="english" id="english" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label for="maths" class="form-label">Maths Marks</label>
                                <input type="number" name="maths" id="maths" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label for="science" class="form-label">Science Marks</label>
                                <input type="number" name="science" id="science" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label for="socialScience" class="form-label">Social Science Marks</label>
                                <input type="number" name="socialScience" id="socialScience" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label for="otherLanguage" class="form-label">Other Language Marks</label>
                                <input type="number" name="otherLanguage" id="otherLanguage" class="form-control">
                            </div>
                            <div class="total-marks" id="totalMarksDisplay">
                            Total Marks: <span id="totalMarks">0</span>
                            </div>
                                <!-- Add academic marks fields -->
                            </div>
                        </div>

                        <!-- Preferences Tab -->
                        <div class="tab-pane fade" id="preferences">
                            <h4 class="section-title">Department Preferences</h4>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">First Preference</label>
                                    <select name="department1" class="form-select" required>
                                        <option value="">Select a Department</option>
                                        <option value="Civil Engineering">Civil Engineering</option>
                                        <option value="Mechanical Engineering">Mechanical Engineering</option>
                                        <option value="Electrical and Electronics Engineering">Electrical and Electronics Engineering</option>
                                        <option value="Electrical and Communication Engineering">Electrical and Communication Engineering</option>
                                        <option value="Automobile Engineering">Automobile Engineering</option>
                                        <option value="Textile Technology">Textile Technology</option>
                                        <option value="Computer Technology">Computer Technology</option>
                                        <option value="Printing Technology">Printing Technology</option>
                                        <option value="Mechanical Engineering (R&AC)">Mechanical Engineering (R&AC)</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Second Preference</label>
                                    <select name="department2" class="form-select" required>
                                    <option value="">Select a Department</option>
                                        <option value="Civil Engineering">Civil Engineering</option>
                                        <option value="Mechanical Engineering">Mechanical Engineering</option>
                                        <option value="Electrical and Electronics Engineering">Electrical and Electronics Engineering</option>
                                        <option value="Electrical and Communication Engineering">Electrical and Communication Engineering</option>
                                        <option value="Automobile Engineering">Automobile Engineering</option>
                                        <option value="Textile Technology">Textile Technology</option>
                                        <option value="Computer Technology">Computer Technology</option>
                                        <option value="Printing Technology">Printing Technology</option>
                                        <option value="Mechanical Engineering (R&AC)">Mechanical Engineering (R&AC)</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Documents Tab -->
                        <div class="tab-pane fade" id="documents">
                            <h4 class="section-title">Document Upload</h4>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <div class="file-upload">
                                        <i class="fas fa-id-card fa-2x mb-3"></i>
                                        <input type="file" name="aadhaar" id="aadhar" class="form-control" accept=".pdf,.jpg,.png" required>
                                        <small class="text-muted">Aadhaar Card (PDF/Image)</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="file-upload">
                                        <i class="fas fa-id-card fa-2x mb-3"></i>
                                        <input type="file" name="marksheet" id="marksheet" class="form-control" accept=".pdf,.jpg,.png" required>
                                        <small class="text-muted">Mark sheet(PDF/Image)</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="file-upload">
                                        <i class="fas fa-id-card fa-2x mb-3"></i>
                                        <input type="file" name="photo" id="photo" class="form-control" accept=".pdf,.jpg,.png" required>
                                        <small class="text-muted">Photo(PDF/Image)</small>
                                    </div>
                                </div>
                                <!-- Add other document upload fields -->
                            </div>
                        </div>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                        <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save me-2"></i>Save Student Record</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Enhanced form validation
document.querySelector('form').addEventListener('submit', function(e) {
    let valid = true;
    
    // Check all required fields
    this.querySelectorAll('[required]').forEach(field => {
        if (!field.value.trim()) {
            valid = false;
            field.classList.add('is-invalid');
        }
    });

    // Check department preferences
    const dept1 = document.querySelector('[name="department1"]');
    const dept2 = document.querySelector('[name="department2"]');
    if (dept1.value === dept2.value) {
        valid = false;
        dept2.classList.add('is-invalid');
    }

    if (!valid) {
        e.preventDefault();
        alert('Please fill all required fields and check department preferences!');
    }
});

// File preview functionality
document.querySelectorAll('[type="file"]').forEach(input => {
    input.addEventListener('change', function() {
        const preview = document.createElement('img');
        preview.className = 'preview-image';
        
        if (this.files && this.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.src = e.target.result;
                input.parentNode.appendChild(preview);
            }
            reader.readAsDataURL(this.files[0]);
        }
    });
});
</script>
</body>
</html>