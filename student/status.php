<?php
include '../include/db.php';
session_start();

if (!isset($_SESSION['userId'])) {
    header("Location: ../auth/login.php");
    exit();
}

$userId = $_SESSION['userId'];
$showEditButtons = false;
$statusMessage = "";

// Fetch Student & Academic Details
$stmt = $conn->prepare("SELECT s.*, a.*, p.status_message
                      FROM studentdetails s
                      LEFT JOIN academic a ON s.studentUserId = a.academicUserId
                      LEFT JOIN preference p ON s.studentUserId = p.preferenceUserId
                      WHERE s.studentUserId = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

$studentData = $academicData = [];
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $studentData = $row;
    $academicData = $row;
    $statusMessage = $row['status_message'] ?? "";
}

// Fetch Documents
$docStmt = $conn->prepare("SELECT documentName FROM document WHERE documentUserId = ?");
$docStmt->bind_param("i", $userId);
$docStmt->execute();
$docResult = $docStmt->get_result();
$documents = $docResult->num_rows > 0 ? json_decode($docResult->fetch_assoc()['documentName'], true) ?? [] : [];

// Fetch Preferences
$prefStmt = $conn->prepare("SELECT * FROM preference WHERE preferenceUserId = ? ORDER BY preferenceOrder");
$prefStmt->bind_param("i", $userId);
$prefStmt->execute();
$prefResult = $prefStmt->get_result();

$preferences = [];
while ($pref = $prefResult->fetch_assoc()) {
    $preferences[] = [
        'order' => $pref['preferenceOrder'],
        'department' => $pref['preferenceDepartment'],
        'status' => $pref['preferenceStatus']
    ];
    if ($pref['preferenceStatus'] === 'reset') {
        $showEditButtons = true;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --accent-color: #e74c3c;
            --success-color: #28a745;
            --warning-color: #ffc107;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
        }

        .profile-container {
            max-width: 1400px;
            margin: 2rem auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .profile-header {
            background: linear-gradient(135deg, var(--primary-color), #1a252f);
            padding: 3rem 2rem;
            position: relative;
            color: white;
            text-align: center;
        }

        .profile-photo {
            width: 180px;
            height: 180px;
            border-radius: 50%;
            border: 5px solid white;
            object-fit: cover;
            margin-top: -90px;
        }

        .detail-card {
            background: #ffffff;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            transition: transform 0.2s;
        }

        .detail-card:hover {
            transform: translateY(-5px);
        }

        .status-message {
            text-align: center;
            padding: 1rem;
            font-weight: bold;
            border-radius: 5px;
            margin-bottom: 1rem;
        }

        .status-success { background-color: var(--success-color); color: white; }
        .status-warning { background-color: var(--warning-color); color: black; }
        .status-error { background-color: var(--accent-color); color: white; }

        @media (max-width: 768px) {
            .profile-header {
                padding: 2rem 1rem;
            }
            .profile-photo {
                width: 120px;
                height: 120px;
                margin-top: -60px;
            }
        }
    </style>
</head>
<body>

<?php include '../header_student.php'; ?>

<div class="container">
    <div class="profile-container">
        <!-- Profile Header -->
        <div class="profile-header">
            <?php if(isset($documents['photo'])): ?>
                <img src="../documents/<?= htmlspecialchars($documents['photo']) ?>?t=<?= filemtime('../documents/' . $documents['photo']) ?>" 
                     class="profile-photo" 
                     alt="Profile Photo">
            <?php endif; ?>
            <h1 class="mt-3"><?= htmlspecialchars($studentData['studentFirstName'] ?? '') ?> <?= htmlspecialchars($studentData['studentLastName'] ?? '') ?></h1>
        </div>

        <!-- Status Message -->
        <?php if ($statusMessage): ?>
            <div class="status-message 
                <?= strpos(strtolower($statusMessage), 'success') !== false ? 'status-success' : 
                   (strpos(strtolower($statusMessage), 'rejected') !== false ? 'status-error' : 'status-warning') ?>">
                <?= htmlspecialchars($statusMessage) ?>
            </div>
        <?php endif; ?>

        <!-- Personal Details -->
        <div class="container mt-4">
            <div class="row g-4">
                <div class="col-md-6">
                    <div class="detail-card">
                        <h5>Personal Information</h5>
                        <p><strong>Date of Birth:</strong> <?= htmlspecialchars($studentData['studentDateOfBirth'] ?? 'N/A') ?></p>
                        <p><strong>Contact:</strong> <?= htmlspecialchars($studentData['studentPhoneNumber'] ?? 'N/A') ?></p>
                        <p><strong>Gender:</strong> <?= htmlspecialchars($studentData['studentGender'] ?? 'N/A') ?></p>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="detail-card">
                        <h5>Academic Information</h5>
                        <p><strong>School:</strong> <?= htmlspecialchars($academicData['school_name'] ?? 'N/A') ?></p>
                        <p><strong>Year of Passing:</strong> <?= htmlspecialchars($academicData['yearOfPassing'] ?? 'N/A') ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Preferences -->
        <div class="container mt-4">
            <div class="detail-card">
                <h5>Preferences</h5>
                <ul>
                    <?php foreach ($preferences as $pref): ?>
                        <li><?= htmlspecialchars($pref['department']) ?> (Priority: <?= $pref['order'] ?>) - 
                            <strong class="<?= $pref['status'] === 'confirmed' ? 'text-success' : 'text-warning' ?>">
                                <?= htmlspecialchars($pref['status']) ?>
                            </strong>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
