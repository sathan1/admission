<?php
include '../include/db.php';
session_start();

// Redirect to login page if the user is not logged in
if (!isset($_SESSION['userId'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Logout functionality
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header("Location: ../auth/login.php");
    exit();
}

$userId = $_SESSION['userId'];
$message = "";
$formType = isset($_GET['form']) ? $_GET['form'] : ''; // Default form type is empty

// Check if forms are already submitted
$query = "SELECT * FROM studentdetails WHERE studentUserId = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $userId);
$stmt->execute();
$detailsSubmitted = $stmt->get_result()->num_rows > 0;

$query = "SELECT * FROM academic WHERE academicUserId = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $userId);
$stmt->execute();
$academicSubmitted = $stmt->get_result()->num_rows > 0;

$query = "SELECT * FROM preference WHERE preferenceUserId = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $userId);
$stmt->execute();
$preferenceSubmitted = $stmt->get_result()->num_rows > 0;

$query = "SELECT * FROM document WHERE documentUserId = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $userId);
$stmt->execute();
$documentSubmitted = $stmt->get_result()->num_rows > 0;

// Determine the next form to display
if (!$detailsSubmitted) {
    $nextForm = 'details';
} elseif (!$academicSubmitted) {
    $nextForm = 'academic';
} elseif (!$preferenceSubmitted) {
    $nextForm = 'preference';
} elseif (!$documentSubmitted) {
    $nextForm = 'document';
} else {
    $nextForm = 'complete';
}

// Redirect to the next form if no specific form is selected
if (empty($formType) || $formType !== $nextForm) {
    if ($nextForm === 'complete') {
        header("Location: status.php");
    } else {
        header("Location: forms.php?form=$nextForm");
    }
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($formType === 'details' && !$detailsSubmitted) {
        // Handle student details form submission
        $firstName = $_POST['firstName'];
        $lastName = $_POST['lastName'];
        $fatherName = $_POST['fatherName'];
        $motherName = $_POST['motherName'];
        $dob = $_POST['dob'];
        $gender = $_POST['gender'];
        $caste = $_POST['caste'];
        $otherCaste = isset($_POST['otherCaste']) ? $_POST['otherCaste'] : '';
        $religion = $_POST['religion'];
        $motherTongue = $_POST['motherTongue'];
        $phone = $_POST['phone'];
        $aadhaar = $_POST['aadhaar'];
        $pan = $_POST['pan'];
        $bankName = $_POST['bankName'];
        $accountNumber = $_POST['accountNumber'];
        $ifscCode = $_POST['ifscCode'];

        if (empty($caste) || (empty($otherCaste) && $caste === 'Others')) {
            $message = "Both caste and other caste fields are required.";
        } else {
            $query = "INSERT INTO studentdetails 
                      (studentUserId, studentFirstName, studentLastName, studentFatherName, studentMotherName, studentDateOfBirth, studentGender, studentCaste, studentCaste_2, studentReligion, studentMotherTongue, studentPhoneNumber, studentAadhaar, studentPAN, studentBankName, studentAccountNumber, studentIFSC) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("issssssssssssssss", $userId, $firstName, $lastName, $fatherName, $motherName, $dob, $gender, $caste, $otherCaste, $religion, $motherTongue, $phone, $aadhaar, $pan, $bankName, $accountNumber, $ifscCode);
            if ($stmt->execute()) {
                header("Location: forms.php?form=academic");
                exit();
            } else {
                $message = "Error saving student details.";
            }
        }
    } elseif ($formType === 'academic' && !$academicSubmitted) {
        // Handle academic form submission
        $school_name = $_POST['school_name'];
        $yearOfPassing = $_POST['yearOfPassing'] ?? 0;
        $tamil = isset($_POST['tamil']) ? (int)$_POST['tamil'] : 0;
        $english = isset($_POST['english']) ? (int)$_POST['english'] : 0;
        $maths = isset($_POST['maths']) ? (int)$_POST['maths'] : 0;
        $science = isset($_POST['science']) ? (int)$_POST['science'] : 0;
        $socialScience = isset($_POST['socialScience']) ? (int)$_POST['socialScience'] : 0;
        $otherLanguage = isset($_POST['otherLanguage']) ? (int)$_POST['otherLanguage'] : 0;

        $totalMarks = $tamil + $english + $maths + $science + $socialScience + $otherLanguage;

        $query = "INSERT INTO academic 
                  (academicUserId, school_name, yearOfPassing, tamilMarks, englishMarks, mathsMarks, scienceMarks, socialScienceMarks, otherLanguageMarks) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("isiiiiiii", $userId, $school_name, $yearOfPassing, $tamil, $english, $maths, $science, $socialScience, $otherLanguage);
        if ($stmt->execute()) {
            header("Location: forms.php?form=preference");
            exit();
        } else {
            $message = "Error saving academic details.";
        }
    } elseif ($formType === 'preference' && !$preferenceSubmitted) {
        // Handle preference form submission
        $department1 = $_POST['department1'] ?? null;
        $department2 = $_POST['department2'] ?? null;

        if (!$department1 || !$department2 || $department1 === $department2) {
            $message = "Invalid department preferences. Both preferences must be different.";
            header("Location: forms.php?form=preference&error=" . urlencode($message));
            exit();
        }

        $query = "INSERT INTO preference 
                  (preferenceUserId, preferenceOrder, preferenceDepartment, preferenceStatus) 
                  VALUES (?, ?, ?, 'pending')";
        $stmt = $conn->prepare($query);

        // Insert first preference
        $order = 1;
        $stmt->bind_param("iis", $userId, $order, $department1);
        $stmt->execute();

        // Insert second preference
        $order = 2;
        $stmt->bind_param("iis", $userId, $order, $department2);
        $stmt->execute();

        header("Location: forms.php?form=document");
        exit();
    } elseif ($formType === 'document' && !$documentSubmitted) {
        // Handle document upload form submission
        $targetDir = "../documents/";
        $documents = ['aadhaar', 'pan', 'marksheet', 'photo', 'certificate1', 'certificate2']; // 6 documents (3 required, 3 optional)
        $uploadedFiles = [];
        $message = "";

        foreach ($documents as $docType) {
            if (isset($_FILES[$docType]) && $_FILES[$docType]['error'] === 0) {
                $fileName = $_FILES[$docType]['name'];
                $fileTmpName = $_FILES[$docType]['tmp_name'];
                $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                $allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf'];
                if (!in_array($fileExtension, $allowedExtensions)) {
                    $message .= "Invalid file type for $docType. Allowed types: JPG, JPEG, PNG, PDF.<br>";
                    continue;
                }

                $newFileName = uniqid($docType . '_', true) . '.' . $fileExtension;
                $targetFilePath = $targetDir . $newFileName;

                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0755, true);
                }

                if (move_uploaded_file($fileTmpName, $targetFilePath)) {
                    $uploadedFiles[$docType] = $newFileName;
                } else {
                    $message .= "Error uploading $docType.<br>";
                }
            } elseif (in_array($docType, ['aadhaar', 'marksheet', 'photo'])) {
                $message .= ucfirst($docType) . " file is missing.<br>";
            }
        }

        if (count($uploadedFiles) >= 3) { // At least 3 required documents
            $documentNames = json_encode($uploadedFiles);

            $query = "INSERT INTO document (documentUserId, documentType, documentName) VALUES (?, 'documents', ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("is", $userId, $documentNames);

            if ($stmt->execute()) {
                $message = "Documents uploaded successfully.";
                header("Location: forms.php?form=complete");
                exit();
            } else {
                $message = "Error saving documents to the database.";
            }
        } else {
            $message .= "Please upload all required files.";
        }
    }
}

// Redirect if all forms are submitted
if ($detailsSubmitted && $academicSubmitted && $preferenceSubmitted && $documentSubmitted) {
    header("Location: status.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Forms</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f4f6f8;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            color: #333;
        }

        .container {
            background: #ffffff;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            padding: 20px;
            max-width: 600px;
            width: 100%;
        }

        h2 {
            text-align: center;
            color: #2c3e50;
            margin-bottom: 20px;
            font-weight: 600;
            letter-spacing: 1.5px;
            line-height: 1.4;
        }

        h2 u {
            text-decoration: none;
            border-bottom: 3px solid #3498db;
            padding-bottom: 5px;
        }

        .text-success {
            font-size: 14px;
            color: #28a745;
            text-align: center;
            margin-bottom: 15px;
            font-weight: 500;
        }

        .form-label {
            font-weight: 500;
            color: #555;
            font-size: 14px;
            margin-bottom: 5px;
        }

        .form-control {
            border-radius: 8px;
            border: 1px solid #ccc;
            padding: 8px 12px;
            font-size: 13px;
            transition: border-color 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
            width: 100%;
        }

        .form-control:focus {
            border-color: #007bff;
            box-shadow: 0 0 5px rgba(0, 123, 255, 0.2);
            outline: none;
        }

        .btn-primary {
            background-color: #007bff;
            color: #ffffff;
            padding: 8px 12px;
            font-size: 14px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: background-color 0.3s ease;
            width: 100%;
        }

        .btn-primary:hover {
            background-color: #0056b3;
        }

        .total-marks {
            font-size: 14px;
            font-weight: 500;
            color: #333;
            margin-top: 10px;
            text-align: center;
        }

        input[type="file"] {
            border: 1px solid #ccc;
            padding: 6px;
            font-size: 13px;
            border-radius: 8px;
        }

        input[type="file"]:focus {
            border: 1px solid #007bff;
            box-shadow: 0 0 5px rgba(0, 123, 255, 0.2);
            outline: none;
        }

        .go-back {
            text-align: center;
            margin-top: 15px;
        }

        .go-back a {
            color: #007bff;
            text-decoration: none;
            font-weight: 500;
        }

        .go-back a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <p class="text-success"><?= $message ?></p>
        <?php if ($formType === 'details' && !$detailsSubmitted): ?>
            <!-- Student Details Form -->
            <form method="post">
                <u><h2>Step - 1 Student Details</h2></u>
                <div class="mb-3">
                    <label for="firstName" class="form-label">First Name</label>
                    <input type="text" name="firstName" id="firstName" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label for="lastName" class="form-label">Last Name</label>
                    <input type="text" name="lastName" id="lastName" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label for="fatherName" class="form-label">Father's Name</label>
                    <input type="text" name="fatherName" id="fatherName" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label for="motherName" class="form-label">Mother's Name</label>
                    <input type="text" name="motherName" id="motherName" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label for="dob" class="form-label">Date of Birth</label>
                    <input type="date" name="dob" id="dob" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label for="gender" class="form-label">Gender</label>
                    <select name="gender" id="gender" class="form-control" required>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="caste" class="form-label">Caste</label>
                    <select name="caste" id="caste" class="form-control" required>
                        <option value="BC">BC - Backward Class</option>
                        <option value="MBC">MBC - Most Backward Class</option>
                        <option value="SC">SC - Scheduled Caste</option>
                        <option value="ST">ST - Scheduled Tribe</option>
                        <option value="OC">OC - Open Category</option>
                        <option value="Others">Others</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="otherCaste" class="form-label">Other Caste (if applicable)</label>
                    <input type="text" name="otherCaste" id="otherCaste" class="form-control">
                </div>
                <div class="mb-3">
                    <label for="religion" class="form-label">Religion</label>
                    <select name="religion" id="religion" class="form-control" required>
                        <option value="Hindu">Hindu</option>
                        <option value="Muslim">Muslim</option>
                        <option value="Christian">Christian</option>
                        <option value="Others">Others</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="motherTongue" class="form-label">Mother Tongue</label>
                    <select name="motherTongue" id="motherTongue" class="form-control" required>
                        <option value="Tamil">Tamil</option>
                        <option value="Telugu">Telugu</option>
                        <option value="Hindi">Hindi</option>
                        <option value="Others">Others</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="phone" class="form-label">Phone Number</label>
                    <input type="text" name="phone" id="phone" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label for="aadhaar" class="form-label">Aadhaar Number</label>
                    <input type="text" name="aadhaar" id="aadhaar" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label for="pan" class="form-label">PAN Number</label>
                    <input type="text" name="pan" id="pan" class="form-control">
                </div>
                <div class="mb-3">
                    <label for="bankName" class="form-label">Bank Name</label>
                    <input type="text" name="bankName" id="bankName" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label for="accountNumber" class="form-label">Account Number</label>
                    <input type="text" name="accountNumber" id="accountNumber" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label for="ifscCode" class="form-label">IFSC Code</label>
                    <input type="text" name="ifscCode" id="ifscCode" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary">Submit</button>
            </form>
        <?php elseif ($formType === 'academic' && !$academicSubmitted): ?>
            <!-- Academic Details Form -->
            <form method="post">
                <u><h2>Step - 2 Academic Details</h2></u>
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
                <button type="submit" class="btn btn-primary">Submit</button>
            </form>
            <script>
                // Calculate total marks
                function calculateTotal() {
                    const tamil = parseInt(document.getElementById("tamil").value) || 0;
                    const english = parseInt(document.getElementById("english").value) || 0;
                    const maths = parseInt(document.getElementById("maths").value) || 0;
                    const science = parseInt(document.getElementById("science").value) || 0;
                    const socialScience = parseInt(document.getElementById("socialScience").value) || 0;
                    const otherLanguage = parseInt(document.getElementById("otherLanguage").value) || 0;
                    const totalMarks = tamil + english + maths + science + socialScience + otherLanguage;
                    document.getElementById("totalMarks").textContent = totalMarks;
                }

                // Attach event listeners
                document.querySelectorAll('.form-control').forEach(input => {
                    input.addEventListener("input", calculateTotal);
                });
            </script>
        <?php elseif ($formType === 'preference' && !$preferenceSubmitted): ?>
            <!-- Preference Form -->
            <form method="post">
                <u><h2>Step - 3 Department Preference</h2></u>
                <div class="mb-3">
                    <label for="department1" class="form-label">First Preference</label>
                    <select name="department1" id="department1" class="form-control" required>
                        <option value="">Select a Department</option>
                        <option value="Civil Engineering">Civil Engineering</option>
                        <option value="Mechanical Engineering">Mechanical Engineering</option>
                        <option value="Electrical and Electronics Engineering">Electrical and Electronics Engineering</option>
                        <option value="Computer Science Engineering">Computer Science Engineering</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="department2" class="form-label">Second Preference</label>
                    <select name="department2" id="department2" class="form-control" required>
                        <option value="">Select a Department</option>
                        <option value="Civil Engineering">Civil Engineering</option>
                        <option value="Mechanical Engineering">Mechanical Engineering</option>
                        <option value="Electrical and Electronics Engineering">Electrical and Electronics Engineering</option>
                        <option value="Computer Science Engineering">Computer Science Engineering</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Submit</button>
            </form>
            <script>
                // Disable duplicate department selection
                document.getElementById("department1").addEventListener("change", function () {
                    const department2 = document.getElementById("department2");
                    department2.querySelectorAll("option").forEach(option => {
                        option.disabled = option.value === this.value;
                    });
                });

                document.getElementById("department2").addEventListener("change", function () {
                    const department1 = document.getElementById("department1");
                    department1.querySelectorAll("option").forEach(option => {
                        option.disabled = option.value === this.value;
                    });
                });
            </script>
        <?php elseif ($formType === 'document' && !$documentSubmitted): ?>
            <!-- Document Upload Form -->
            <form method="post" enctype="multipart/form-data">
                <u><h2>Step - 4 Document Upload</h2></u>
                <div class="mb-3">
                    <label for="aadhaar" class="form-label">Aadhaar Card (Required)</label>
                    <input type="file" name="aadhaar" id="aadhaar" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label for="pan" class="form-label">PAN Card (Optional)</label>
                    <input type="file" name="pan" id="pan" class="form-control">
                </div>
                <div class="mb-3">
                    <label for="marksheet" class="form-label">Marksheet (Required)</label>
                    <input type="file" name="marksheet" id="marksheet" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label for="photo" class="form-label">Photo (Required)</label>
                    <input type="file" name="photo" id="photo" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label for="certificate1" class="form-label">Certificate 1 (Optional)</label>
                    <input type="file" name="certificate1" id="certificate1" class="form-control">
                </div>
                <div class="mb-3">
                    <label for="certificate2" class="form-label">Certificate 2 (Optional)</label>
                    <input type="file" name="certificate2" id="certificate2" class="form-control">
                </div>
                <button type="submit" class="btn btn-primary">Submit</button>
            </form>
        <?php endif; ?>
        <div class="go-back">
            <a href="forms.php?form=<?= $prevForm ?>">Go Back</a>
        </div>
    </div>
</body>
</html>