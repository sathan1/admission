<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

ob_start(); // Start output buffering
include '../include/db.php';
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';

// Define constants for email credentials
define('SMTP_EMAIL', 'sathancreator@gmail.com'); // Replace with your email
define('SMTP_PASSWORD', 'tatqezizskzqjgqg');  // Replace with your app password

if (!isset($_SESSION['userId'])) {
    header("Location: ../auth/login.php");
    exit();
}

$adminId = $_SESSION['userId'];
$studentUserId = filter_input(INPUT_GET, 'view', FILTER_VALIDATE_INT);

if ($studentUserId) {
    // Fetch student details
    $query = "
    SELECT sd.studentUserId, sd.studentFirstName, sd.studentLastName, sd.studentPhoneNumber, 
           sd.studentReligion, sd.studentCaste_2, sd.studentMotherTongue, sd.studentFatherName, 
           sd.studentMotherName, sd.studentDateOfBirth, sd.studentGender, sd.studentCaste,
           a.school_name, a.totalMarks, a.yearOfPassing, a.tamilMarks, a.englishMarks, 
           a.mathsMarks, a.scienceMarks, a.socialScienceMarks, a.otherLanguageMarks, 
           u.userEmail
    FROM studentdetails sd
    LEFT JOIN academic a ON sd.studentUserId = a.academicUserId
    LEFT JOIN users u ON sd.studentUserId = u.userId
    WHERE sd.studentUserId = ?";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $studentUserId);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $documentQuery = "SELECT * FROM document WHERE documentUserId = ?";
    $docStmt = $conn->prepare($documentQuery);
    $docStmt->bind_param("i", $studentUserId);
    $docStmt->execute();
    $documentsResult = $docStmt->get_result();

    // Fetch preferences for the student
    $preferencesQuery = "
    SELECT p.preferenceId, p.preferenceDepartment, p.preferenceStatus 
    FROM preference p 
    WHERE p.preferenceUserId = ? 
    ORDER BY p.preferenceOrder ASC";

    $prefStmt = $conn->prepare($preferencesQuery);
    $prefStmt->bind_param("i", $studentUserId);
    $prefStmt->execute();
    $preferences = $prefStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $prefStmt->close();
} else {
    header("Location: dashboard.php");
    exit();
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['preference_action'])) {
        $anyUpdates = false;
        
        foreach ($_POST['preference_action'] as $preferenceId => $action) {
            // Validate preference ID
            $preferenceId = filter_var($preferenceId, FILTER_VALIDATE_INT);
            if (!$preferenceId) continue;

            $preferenceStatus = match ($action) {
                'approve' => 'success',
                'reject' => 'rejected',
                'reset' => 'reset',
                default => null,
            };

            if (!$preferenceStatus) continue;

            $departmentAllocation = $_POST['department_allocation'][$preferenceId] ?? null;
            $statusMessage = $_POST['status_message'][$preferenceId] ?? null;

            // Validation
            $errors = [];
            if ($preferenceStatus === 'success' && empty($departmentAllocation)) {
                $errors[] = "Department allocation required for approval";
            }
            
            if (in_array($preferenceStatus, ['rejected', 'reset']) && empty($statusMessage)) {
                $errors[] = "Reason required for rejection/reset";
            }

            if (!empty($errors)) {
                $_SESSION['action_message'] = implode("<br>", $errors);
                $_SESSION['action_status'] = 'error';
                header("Location: " . $_SERVER['REQUEST_URI']);
                exit();
            }

            // Update database
            $updateQuery = "UPDATE preference SET 
                preferenceStatus = ?, 
                department_status = ?,
                status_message = ?
                WHERE preferenceId = ?";
            
            $stmt = $conn->prepare($updateQuery);
          // Update database
$updateQuery = "UPDATE preference SET 
    preferenceStatus = ?, 
    department_status = ?,
    status_message = ?
    WHERE preferenceId = ?";

// Prepare the statement
$stmt = $conn->prepare($updateQuery);

// Assign values to variables
$departmentStatus = ($preferenceStatus === 'success') ? $departmentAllocation : null;

// Bind parameters
$stmt->bind_param("sssi", 
    $preferenceStatus,
    $departmentStatus, // Use the variable instead of the ternary operator directly
    $statusMessage,
    $preferenceId
);

// Execute the statement
if ($stmt->execute()) {
    $anyUpdates = true;
    // Send email
    sendStatusEmail(
        $student['userEmail'],
        $student['studentFirstName'],
        $student['studentLastName'],
        $preferenceStatus,
        $preference['preferenceDepartment'],
        $departmentAllocation,
        $statusMessage
    );
}
$stmt->close();
            
            if ($stmt->execute()) {
                $anyUpdates = true;
                // Send email
                sendStatusEmail(
                    $student['userEmail'],
                    $student['studentFirstName'],
                    $student['studentLastName'],
                    $preferenceStatus,
                    $preference['preferenceDepartment'],
                    $departmentAllocation,
                    $statusMessage
                );
            }
            $stmt->close();
        }

        if ($anyUpdates) {
            $_SESSION['action_message'] = "Preferences updated successfully!";
            $_SESSION['action_status'] = 'success';
        }
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit();
    }
}

// Function to send status email
function sendStatusEmail($recipientEmail, $firstName, $lastName, $preferenceStatus, 
                        $departmentName, $departmentCategory = null, $statusMessage = null)
{
    $mail = new PHPMailer(true);
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_EMAIL;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Recipients
        $mail->setFrom(SMTP_EMAIL, 'Admissions Office');
        $mail->addAddress($recipientEmail);

        // Status-specific gradient background color
        $gradientColor = match ($preferenceStatus) {
            'success' => 'linear-gradient(to right, #28a745, #77dd77)', // Green gradient
            'rejected' => 'linear-gradient(to right, #dc3545, #ff6f61)', // Red gradient
            'reset' => 'linear-gradient(to right, #ffc107, #ffdd59)', // Yellow gradient
            default => 'linear-gradient(to right, #6c757d, #adb5bd)', // Gray gradient for fallback
        };

        // Email Body
        $emailBody = "
        <div style='font-family: Arial, sans-serif; line-height: 1.6; background: #f8f9fa; padding: 20px; border-radius: 8px; max-width: 600px; margin: 0 auto;'>
            <div style='text-align: center; padding: 20px; background: {$gradientColor}; color: white; border-radius: 8px 8px 0 0;'>
                <h2>Status Update</h2>
            </div>
            <div style='padding: 20px; background: white; border-radius: 0 0 8px 8px;'>
                <h3 style='color: #343a40;'>Dear {$firstName} {$lastName},</h3>
                <p>We are writing to inform you about the status of your preference:</p>
                <table style='width: 100%; border-collapse: collapse; margin: 20px 0;'>
                    <tr style='background: #f8f9fa;'>
                        <td style='padding: 10px; font-weight: bold; border: 1px solid #dee2e6;'>Preference Department:</td>
                        <td style='padding: 10px; border: 1px solid #dee2e6;'>{$departmentName}</td>
                    </tr>";

        if ($preferenceStatus === 'success') {
            $emailBody .= "
                    <tr style='background: #f8f9fa;'>
                        <td style='padding: 10px; font-weight: bold; border: 1px solid #dee2e6;'>Department Category:</td>
                        <td style='padding: 10px; border: 1px solid #dee2e6;'>{$departmentCategory}</td>
                    </tr>";
        }

        if ($statusMessage) {
            $emailBody .= "
                    <tr style='background: #f8f9fa;'>
                        <td style='padding: 10px; font-weight: bold; border: 1px solid #dee2e6;'>Reason:</td>
                        <td style='padding: 10px; border: 1px solid #dee2e6;'>{$statusMessage}</td>
                    </tr>";
        }

        $emailBody .= "
                    <tr style='background: #f8f9fa;'>
                        <td style='padding: 10px; font-weight: bold; border: 1px solid #dee2e6;'>Status:</td>
                        <td style='padding: 10px; border: 1px solid #dee2e6; text-transform: capitalize;'>{$preferenceStatus}</td>
                    </tr>
                </table>
                <p>If you have any questions, please contact our support team.</p>
                <p>Best regards,<br>Admissions Office</p>
            </div>
            <div style='text-align: center; font-size: 12px; color: #6c757d; margin-top: 20px;'>
                <p>&copy; " . date('Y') . " NPTC - ADMISSION OFFICE . All rights reserved.</p>
            </div>
        </div>";

        // Email settings
        $mail->isHTML(true);
        $mail->Subject = 'Update on Your Preference Status';
        $mail->Body = $emailBody;

        $mail->send();
        error_log("Status email sent to {$recipientEmail}");
    } catch (Exception $e) {
        error_log("Mailer Error: " . $mail->ErrorInfo);
    }
}

ob_end_flush(); // Flush the output buffer
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Request Details</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .reason-input {
            transition: all 0.3s ease;
            margin-top: 5px;
        }
body {
    font-family: 'Roboto', sans-serif;
    background: linear-gradient(180deg, #fdfdfd, #f8f9fc);
    color: #4a4a4a;
    margin: 0;
    padding: 0;
}

/* Container Styling */
.container {
    background: linear-gradient(135deg, #ffffff, #f8f9fa);
    border-radius: 12px;
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
    padding: 30px;
    margin: 20px auto;
    max-width: 90%;
}

/* Headers */
h2 {
    color: #2c3e50;
    font-weight: bold;
    font-size: 1.8rem;
    margin-bottom: 20px;
    border-bottom: 2px solid #007bff;
    padding-bottom: 10px;
    display: inline-block;
}

h4 {
    color: #34495e;
    margin-top: 25px;
    margin-bottom: 15px;
    font-weight: 600;
    border-left: 4px solid #007bff;
    padding-left: 10px;
}

/* Tables */
.table {
    width: 100%;
    margin-top: 20px;
    border-collapse: collapse;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.08);
}

.table th {
    background: linear-gradient(90deg, #e3f2fd, #90caf9);
    color: #003c8f;
    font-weight: bold;
    text-align: center;
    padding: 15px;
    border-bottom: 2px solid #dee2e6;
}

.table td {
    background-color: #ffffff;
    color: #4a4a4a;
    padding: 12px;
    text-align: center;
    border-bottom: 1px solid #f1f1f1;
}

.table tbody tr:nth-child(even) {
    background-color: #f8f9fc;
}

.table tbody tr:hover {
    background-color: #eef3fa;
    transform: scale(1.01);
    transition: all 0.2s ease-in-out;
}

/* Side Detail Section */
.detail-section {
    background: linear-gradient(135deg, #fdfdfd, #f7f7f7);
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
    margin-top: 20px;
    line-height: 1.5;
    font-size: 1rem;
    color: #5a5a5a;
}

.detail-section h4 {
    font-size: 1.3rem;
    color: #2c3e50;
    margin-bottom: 15px;
}

/* Buttons */
.btn-primary {
    background: linear-gradient(45deg, #007bff, #66b2ff);
    border: none;
    border-radius: 5px;
    padding: 10px 20px;
    font-weight: bold;
    color: #fff;
    transition: all 0.3s ease-in-out;
}

.btn-primary:hover {
    background: linear-gradient(45deg, #0056b3, #004080);
    box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.2);
    transform: translateY(-2px);
}

/* Badges */
.badge {
    padding: 0.5em 0.8em;
    font-size: 0.9rem;
    font-weight: 600;
    border-radius: 5px;
    display: inline-block;
}

.badge-success {
    background: linear-gradient(90deg, #28a745, #5bd882);
    color: #fff;
}

.badge-danger {
    background: linear-gradient(90deg, #dc3545, #ff6b6b);
    color: #fff;
}

.badge-warning {
    background: linear-gradient(90deg, #ffc107, #ffe57f);
    color: #212529;
}

.badge-secondary {
    background: linear-gradient(90deg, #6c757d, #b0bec5);
    color: #fff;
}

/* Forms */
form {
    margin-top: 15px;
}

.form-select,
.form-control {
    border-radius: 8px;
    border: 1px solidrgb(10, 11, 12);
    padding: 10px 12px;
    transition: all 0.3s ease-in-out;
}

.form-select:focus,
.form-control:focus {
    border-color: #007bff;
    box-shadow: 0 0 5px rgba(0, 123, 255, 0.5);
    outline: none;
}

/* Uploaded Documents Section */
.img-thumbnail {
    border-radius: 10px;
    box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.1);
    cursor: pointer;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.img-thumbnail:hover {
    transform: scale(1.05);
    box-shadow: 0px 6px 12px rgba(0, 0, 0, 0.2);
}

/* Modal for Full-Screen View */
.fullscreen-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background-color: rgba(0, 0, 0, 0.8);
    z-index: 1050;
    justify-content: center;
    align-items: center;
    padding: 20px;
}

.fullscreen-modal img {
    max-width: 90%;
    max-height: 90%;
    border-radius: 10px;
    box-shadow: 0px 8px 20px rgba(0, 0, 0, 0.5);
}

/* Close Button */
.close-btn {
    position: absolute;
    top: 20px;
    right: 20px;
    font-size: 1.5rem;
    color: #fff;
    background-color: transparent;
    border: none;
    cursor: pointer;
    z-index: 1100;
}

.close-btn:hover {
    color: #ff6b6b;
    transform: scale(1.2);
}

    </style>
 
</head>
<body>
<?php include '../header_admin.php'; ?>

<div class="container mt-4">
    <h2>Action page - Student</h2>
    <h4>Personal Information</h4>
    <table class="table">
        <tr><th>First Name</th><td><?= htmlspecialchars($student['studentFirstName']) ?></td></tr>
        <tr><th>Last Name</th><td><?= htmlspecialchars($student['studentLastName']) ?></td></tr>
        <tr><th>Father's Name</th><td><?= htmlspecialchars($student['studentFatherName']) ?></td></tr>
        <tr><th>Mother's Name</th><td><?= htmlspecialchars($student['studentMotherName']) ?></td></tr>
        <tr><th>Date of Birth</th><td><?= htmlspecialchars($student['studentDateOfBirth']) ?></td></tr>
        <tr><th>Gender</th><td><?= htmlspecialchars($student['studentGender']) ?></td></tr>
        <tr><th>Religion</th><td><?= htmlspecialchars($student['studentReligion']) ?></td></tr>
        <tr><th>Community</th><td><?= htmlspecialchars($student['studentCaste']) ?></td></tr>    
        <tr><th>Caste</th><td><?= htmlspecialchars($student['studentCaste_2']) ?></td></tr>
        <tr><th>Mother Tongue</th><td><?= htmlspecialchars($student['studentMotherTongue']) ?></td></tr>
        <tr><th>Email</th><td><?= htmlspecialchars($student['userEmail']) ?></td></tr>
        <tr><th>Phone Number</th><td><?= htmlspecialchars($student['studentPhoneNumber']) ?></td></tr> 
    </table>

    <h4>Academic Information</h4>
    <table class="table">
        <tr><th>School</th><td><?= htmlspecialchars($student['school_name']) ?></td></tr>
        <tr><th>Year Of passing</th><td><?= htmlspecialchars($student['yearOfPassing']) ?></td></tr>
        <tr><th>Tamil</th><td><?= htmlspecialchars($student['tamilMarks']) ?></td></tr>
        <tr><th>English</th><td><?= htmlspecialchars($student['englishMarks']) ?></td></tr>
        <tr><th>Maths</th><td><?= htmlspecialchars($student['mathsMarks']) ?></td></tr>
        <tr><th>Science</th><td><?= htmlspecialchars($student['scienceMarks']) ?></td></tr>
        <tr><th>Social Science</th><td><?= htmlspecialchars($student['socialScienceMarks']) ?></td></tr>
        <tr><th>Other Language</th><td><?= htmlspecialchars($student['otherLanguageMarks']) ?></td></tr>
        <tr><th>Total Marks</th><td><?= htmlspecialchars($student['totalMarks']) ?></td></tr>
    </table>
    
    <h4>Uploaded Documents</h4>
    <?php if ($documentsResult->num_rows > 0): ?>
        <div class="row">
            <?php while ($document = $documentsResult->fetch_assoc()): ?>
                <?php 
                $files = json_decode($document['documentName'], true); 
                ?>
                <?php foreach ($files as $type => $fileName): ?>
                    <div class="col-md-4 text-center mb-3">
                        <?php if (preg_match('/\.(jpg|jpeg|png)$/i', $fileName)): ?>
                            <img src="../documents/<?= htmlspecialchars($fileName) ?>" 
                                 alt="<?= htmlspecialchars($type) ?>" 
                                 class="img-thumbnail border rounded shadow-sm">
                            <p class="text-muted"><?= ucfirst($type) ?></p>
                        <?php else: ?>
                            <p class="text-muted"><?= ucfirst($type) ?>: <?= htmlspecialchars($fileName) ?></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?> 
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <p>No documents uploaded.</p>
    <?php endif; ?>


    <h4>Preferences</h4>
    <form action="" method="POST">
       <!-- HTML Form Section -->
<table class="table table-bordered">
    <thead>
        <tr>
            <th>Department</th>
            <th>Current Status</th>
            <th>Action</th>
            <th>Allocation</th>
            <th>Reason</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($preferences as $preference): ?>
        <tr>
            <td><?= htmlspecialchars($preference['preferenceDepartment']) ?></td>
            <td>
                <span class="badge 
                    <?= match($preference['preferenceStatus']) {
                        'success' => 'bg-success',
                        'rejected' => 'bg-danger',
                        'reset' => 'bg-warning',
                        default => 'bg-secondary'
                    } ?>">
                    <?= ucfirst($preference['preferenceStatus']) ?>
                </span>
            </td>
            <td>
                <div class="btn-group" role="group">
                    <input type="radio" 
                           class="btn-check"
                           name="preference_action[<?= $preference['preferenceId'] ?>]"
                           value="approve"
                           id="approve_<?= $preference['preferenceId'] ?>]"
                           autocomplete="off">
                    <label class="btn btn-outline-success" 
                           for="approve_<?= $preference['preferenceId'] ?>]">Approve</label>

                    <input type="radio" 
                           class="btn-check"
                           name="preference_action[<?= $preference['preferenceId'] ?>]"
                           value="reject"
                           id="reject_<?= $preference['preferenceId'] ?>]"
                           autocomplete="off">
                    <label class="btn btn-outline-danger" 
                           for="reject_<?= $preference['preferenceId'] ?>]">Reject</label>

                    <input type="radio" 
                           class="btn-check"
                           name="preference_action[<?= $preference['preferenceId'] ?>]"
                           value="reset"
                           id="reset_<?= $preference['preferenceId'] ?>]"
                           autocomplete="off">
                    <label class="btn btn-outline-warning" 
                           for="reset_<?= $preference['preferenceId'] ?>]">Reset</label>
                </div>
            </td>
            <td>
                <select name="department_allocation[<?= $preference['preferenceId'] ?>]"
                        class="form-select allocation-select"
                        <?= $preference['preferenceStatus'] === 'success' ? '' : 'disabled' ?>>
                    <option value="">Select</option>
                    <option value="MGMT" <?= ($preference['department_status'] ?? '') === 'MGMT' ? 'selected' : '' ?>>MGMT</option>
                    <option value="GOVT" <?= ($preference['department_status'] ?? '') === 'GOVT' ? 'selected' : '' ?>>GOVT</option>
                </select>
            </td>
            <td>
                <input type="text" 
                       name="status_message[<?= $preference['preferenceId'] ?>]"
                       class="form-control reason-input"
                       value="<?= htmlspecialchars($preference['status_message'] ?? '') ?>"
                       <?= in_array($preference['preferenceStatus'], ['rejected', 'reset']) ? '' : 'disabled' ?>
                       placeholder="Enter reason...">
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
        <button type="submit" class="btn btn-primary">Submit</button>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle radio button changes
    document.querySelectorAll('input[type="radio"]').forEach(radio => {
        radio.addEventListener('change', function() {
            const row = this.closest('tr');
            const allocationSelect = row.querySelector('.allocation-select');
            const reasonInput = row.querySelector('.reason-input');

            if (this.value === 'approve') {
                allocationSelect.disabled = false;
                reasonInput.disabled = true;
                reasonInput.value = '';
                reasonInput.removeAttribute('required');
                allocationSelect.setAttribute('required', 'true');
            } else {
                allocationSelect.disabled = true;
                allocationSelect.value = '';
                reasonInput.disabled = false;
                reasonInput.setAttribute('required', 'true');
            }
        });
    });

    // Initialize form state
    document.querySelectorAll('tr').forEach(row => {
        const status = row.querySelector('.badge').textContent.toLowerCase();
        if (status === 'success') {
            row.querySelector('.allocation-select').disabled = false;
            row.querySelector('.reason-input').disabled = true;
        } else if (['rejected', 'reset'].includes(status)) {
            row.querySelector('.reason-input').disabled = false;
        }
    });
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
