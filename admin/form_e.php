<?php
include '../include/db.php';
session_start();

if (!isset($_SESSION['userId'])) {
    header("Location: ../index.php");
    exit();
}

// Fetch all departments
$departmentQuery = "SELECT DISTINCT preferenceDepartment FROM preference";
$departmentResult = $conn->query($departmentQuery);
$departments = [];
while ($dept = $departmentResult->fetch_assoc()) {
    $departments[] = $dept['preferenceDepartment'];
}

// Initialize table structure
$tableData = [];
foreach ($departments as $department) {
    $tableData[$department] = [
        'shift' => 'First',
        'Hindu' => ['boys' => 0, 'girls' => 0],
        'Muslim' => ['boys' => 0, 'girls' => 0],
        'Christian' => ['boys' => 0, 'girls' => 0],
        'Jain' => ['boys' => 0, 'girls' => 0],
        'Sikh' => ['boys' => 0, 'girls' => 0],
        'Buddhist' => ['boys' => 0, 'girls' => 0],
        'Others' => ['boys' => 0, 'girls' => 0],
        'total' => ['boys' => 0, 'girls' => 0],
    ];
}

// Fetch student data grouped by religion
$query = "
SELECT p.preferenceDepartment, sd.studentGender, sd.studentReligion, COUNT(*) AS studentCount
FROM studentdetails sd
LEFT JOIN preference p ON sd.studentUserId = p.preferenceUserId
WHERE p.preferenceStatus = 'success'
GROUP BY p.preferenceDepartment, sd.studentReligion, sd.studentGender
";
$result = $conn->query($query);

// Populate table data
while ($row = $result->fetch_assoc()) {
    $department = $row['preferenceDepartment'];
    $religion = $row['studentReligion'];
    $gender = strtolower($row['studentGender']); // Convert to lowercase for consistency
    $count = $row['studentCount'];

    // Map gender values to expected keys
    if ($gender === 'male' || $gender === 'm') {
        $gender = 'boys';
    } elseif ($gender === 'female' || $gender === 'f') {
        $gender = 'girls';
    } else {
        continue; // Skip invalid gender values
    }

    // Add counts to the table data
    if (isset($tableData[$department][$religion][$gender])) {
        $tableData[$department][$religion][$gender] += $count;
        $tableData[$department]['total'][$gender] += $count;
    }
}

// Calculate overall totals (for the bottom row)
$overallTotals = [
    'Hindu' => ['boys' => 0, 'girls' => 0],
    'Muslim' => ['boys' => 0, 'girls' => 0],
    'Christian' => ['boys' => 0, 'girls' => 0],
    'Jain' => ['boys' => 0, 'girls' => 0],
    'Sikh' => ['boys' => 0, 'girls' => 0],
    'Buddhist' => ['boys' => 0, 'girls' => 0],
    'Others' => ['boys' => 0, 'girls' => 0],
    'total' => ['boys' => 0, 'girls' => 0],
];
foreach ($tableData as $data) {
    foreach ($data as $key => $values) {
        if (isset($overallTotals[$key])) {
            $overallTotals[$key]['boys'] += $values['boys'];
            $overallTotals[$key]['girls'] += $values['girls'];
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Form E</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
       
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
</style>
<?php include '../header_admin.php'; ?>
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
<div class="content">
<h2 class="text-center">NPTC</h2>
    <p class="text-center"> GIRLS BOYS STATISTICS - ADMITTED(Community)</p>
    <p class="text-center">Form E</p>
    <h4 class="text-center">Admission to First Year Diploma Courses (2024-2025)</h4>
<table class="table table-bordered">
        <thead class="thead-dark">

        <tr>
            <th rowspan = 2 >S.No</th>
            <th rowspan = 2 >Department</th>
            <th rowspan = 2 >Shit</th>
            <th colspan =2>Hindu </th>
            <th  colspan =2>Muslim </th>
            <th  colspan =2>Christian </th>
            <th colspan =2>Jain</th>
            <th colspan =2>Sikh</th>
            <th colspan =2>Buddhist</th>
            <th colspan =2>Others</th>
            <th colspan =2>Total </th>
        </tr>
        <tr>
        <th> (B)</th>
        <th> (G)</th>
        <th> (B)</th>
        <th> (G)</th>
        <th> (B)</th>
        <th> (G)</th>
        <th> (B)</th>
        <th> (G)</th>
        <th> (B)</th>
        <th> (G)</th>
        <th> (B)</th>
        <th> (G)</th>
        <th> (B)</th>
        <th> (G)</th>
        <th> (B)</th>
        <th> (G)</th>
        </tr>
        </thead>
        <tbody>
        <?php
        $serialNumber = 1;
        foreach ($tableData as $department => $data) {
            echo "<tr>";
            echo "<td>{$serialNumber}</td>";
            echo "<td>{$department}</td>";
            echo "<td>{$data['shift']}</td>";
            echo "<td>{$data['Hindu']['boys']}</td>";
            echo "<td>{$data['Hindu']['girls']}</td>";
            echo "<td>{$data['Muslim']['boys']}</td>";
            echo "<td>{$data['Muslim']['girls']}</td>";
            echo "<td>{$data['Christian']['boys']}</td>";
            echo "<td>{$data['Christian']['girls']}</td>";
            echo "<td>{$data['Jain']['boys']}</td>";
            echo "<td>{$data['Jain']['girls']}</td>";
            echo "<td>{$data['Sikh']['boys']}</td>";
            echo "<td>{$data['Sikh']['girls']}</td>";
            echo "<td>{$data['Buddhist']['boys']}</td>";
            echo "<td>{$data['Buddhist']['girls']}</td>";
            echo "<td>{$data['Others']['boys']}</td>";
            echo "<td>{$data['Others']['girls']}</td>";
            echo "<td>{$data['total']['boys']}</td>";
            echo "<td>{$data['total']['girls']}</td>";
            echo "</tr>";
            $serialNumber++;
        }
        ?>
        <!-- Total row -->
        <tr class="table-success">
            <td colspan="3">Overall Totals</td>
            <td><?= $overallTotals['Hindu']['boys'] ?></td>
            <td><?= $overallTotals['Hindu']['girls'] ?></td>
            <td><?= $overallTotals['Muslim']['boys'] ?></td>
            <td><?= $overallTotals['Muslim']['girls'] ?></td>
            <td><?= $overallTotals['Christian']['boys'] ?></td>
            <td><?= $overallTotals['Christian']['girls'] ?></td>
            <td><?= $overallTotals['Jain']['boys'] ?></td>
            <td><?= $overallTotals['Jain']['girls'] ?></td>
            <td><?= $overallTotals['Sikh']['boys'] ?></td>
            <td><?= $overallTotals['Sikh']['girls'] ?></td>
            <td><?= $overallTotals['Buddhist']['boys'] ?></td>
            <td><?= $overallTotals['Buddhist']['girls'] ?></td>
            <td><?= $overallTotals['Others']['boys'] ?></td>
            <td><?= $overallTotals['Others']['girls'] ?></td>
            <td><?= $overallTotals['total']['boys'] ?></td>
            <td><?= $overallTotals['total']['girls'] ?></td>
        </tr>
        </tbody>
    </table>
</div>
</body>
</html>