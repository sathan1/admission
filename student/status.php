<?php
include '../include/db.php';
session_start();

if (!isset($_SESSION['userId'])) {
    header("Location: ../auth/login.php");
    exit();
}

$userId = $_SESSION['userId'];

// Initialize statuses
$status = [
    'student' => 'not-submitted',
    'academic' => 'not-submitted',
    'preferences' => 'not-submitted',
    'documents' => 'not-submitted'
];

// 1. Check Student Details Status
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM studentdetails WHERE studentUserId = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$status['student'] = ($result->fetch_assoc()['count'] > 0) ? 'submitted' : 'not-submitted';

// 2. Check Academic Details Status
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM academic WHERE academicUserId = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$status['academic'] = ($result->fetch_assoc()['count'] > 0) ? 'submitted' : 'not-submitted';

// 3. Check Preferences Status (using preferencestatus column)
$stmt = $conn->prepare("SELECT preferencestatus FROM preference WHERE preferenceUserId = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $status['preferences'] = $result->fetch_assoc()['preferencestatus'] ?? 'submitted';
}

// 4. Check Documents Status
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM document WHERE documentUserId = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$status['documents'] = ($result->fetch_assoc()['count'] > 0) ? 'submitted' : 'not-submitted';

// Status labels
$statusLabels = [
    'approved' => ['label' => 'Approved', 'color' => 'success'],
    'rejected' => ['label' => 'Rejected', 'color' => 'danger'],
    'reset' => ['label' => 'Pending Reset', 'color' => 'warning'],
    'submitted' => ['label' => 'Under Review', 'color' => 'info'],
    'not-submitted' => ['label' => 'Not Submitted', 'color' => 'secondary']
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application Status</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .status-container {
            max-width: 800px;
            margin: 2rem auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }

        .status-item {
            padding: 1.5rem;
            border-bottom: 1px solid #dee2e6;
            transition: background-color 0.2s;
        }

        .status-item:last-child {
            border-bottom: none;
        }

        .status-icon {
            font-size: 1.5rem;
            width: 40px;
            text-align: center;
        }

        .status-badge {
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .edit-btn {
            padding: 0.25rem 1rem;
            border-radius: 20px;
            transition: all 0.2s;
        }

        .status-details {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
        }
    </style>
</head>
<body>
    <?php include '../header_student.php'; ?>

    <div class="status-container">
        <div class="p-4 border-bottom">
            <h2 class="mb-0">Application Status Overview</h2>
        </div>

        <!-- Student Information -->
        <div class="status-item">
            <div class="d-flex align-items-center gap-3 mb-2">
                <div class="status-icon">
                    <?php if($status['student'] === 'submitted'): ?>
                        <i class="bi bi-check-circle-fill text-success"></i>
                    <?php else: ?>
                        <i class="bi bi-x-circle-fill text-secondary"></i>
                    <?php endif; ?>
                </div>
                <div class="flex-grow-1">
                    <h5 class="mb-0">Personal Information</h5>
                </div>
                <?php if($status['student'] === 'not-submitted'): ?>
                    <a href="edit_student.php" class="edit-btn btn btn-outline-primary">
                        <i class="bi bi-pencil-square me-2"></i>Edit
                    </a>
                <?php endif; ?>
            </div>
            <div class="status-details">
                <span class="status-badge bg-<?= $statusLabels[$status['student']]['color'] ?>">
                    <?= $statusLabels[$status['student']]['label'] ?>
                </span>
            </div>
        </div>

        <!-- Academic Details -->
        <div class="status-item">
            <div class="d-flex align-items-center gap-3 mb-2">
                <div class="status-icon">
                    <?php if($status['academic'] === 'submitted'): ?>
                        <i class="bi bi-check-circle-fill text-success"></i>
                    <?php else: ?>
                        <i class="bi bi-x-circle-fill text-secondary"></i>
                    <?php endif; ?>
                </div>
                <div class="flex-grow-1">
                    <h5 class="mb-0">Academic Details</h5>
                </div>
                <?php if($status['academic'] === 'not-submitted'): ?>
                    <a href="edit_academic.php" class="edit-btn btn btn-outline-primary">
                        <i class="bi bi-pencil-square me-2"></i>Edit
                    </a>
                <?php endif; ?>
            </div>
            <div class="status-details">
                <span class="status-badge bg-<?= $statusLabels[$status['academic']]['color'] ?>">
                    <?= $statusLabels[$status['academic']]['label'] ?>
                </span>
            </div>
        </div>

        <!-- Preferences -->
        <div class="status-item">
            <div class="d-flex align-items-center gap-3 mb-2">
                <div class="status-icon">
                    <?php if($status['preferences'] === 'approved'): ?>
                        <i class="bi bi-check-circle-fill text-success"></i>
                    <?php elseif($status['preferences'] === 'rejected'): ?>
                        <i class="bi bi-x-circle-fill text-danger"></i>
                    <?php else: ?>
                        <i class="bi bi-dash-circle-fill text-warning"></i>
                    <?php endif; ?>
                </div>
                <div class="flex-grow-1">
                    <h5 class="mb-0">Preferences</h5>
                </div>
                <?php if(in_array($status['preferences'], ['rejected', 'reset', 'not-submitted'])): ?>
                    <a href="edit_preferences.php" class="edit-btn btn btn-outline-primary">
                        <i class="bi bi-pencil-square me-2"></i>Edit
                    </a>
                <?php endif; ?>
            </div>
            <div class="status-details">
                <span class="status-badge bg-<?= $statusLabels[$status['preferences']]['color'] ?>">
                    <?= $statusLabels[$status['preferences']]['label'] ?>
                </span>
                <?php if($status['preferences'] === 'rejected'): ?>
                    <p class="mt-2 mb-0 text-muted small">Please update your preferences based on the feedback.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Documents -->
        <div class="status-item">
            <div class="d-flex align-items-center gap-3 mb-2">
                <div class="status-icon">
                    <?php if($status['documents'] === 'submitted'): ?>
                        <i class="bi bi-check-circle-fill text-success"></i>
                    <?php else: ?>
                        <i class="bi bi-x-circle-fill text-secondary"></i>
                    <?php endif; ?>
                </div>
                <div class="flex-grow-1">
                    <h5 class="mb-0">Documents</h5>
                </div>
                <?php if($status['documents'] === 'not-submitted'): ?>
                    <a href="edit_documents.php" class="edit-btn btn btn-outline-primary">
                        <i class="bi bi-pencil-square me-2"></i>Edit
                    </a>
                <?php endif; ?>
            </div>
            <div class="status-details">
                <span class="status-badge bg-<?= $statusLabels[$status['documents']]['color'] ?>">
                    <?= $statusLabels[$status['documents']]['label'] ?>
                </span>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>