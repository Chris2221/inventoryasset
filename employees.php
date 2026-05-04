<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

include "config.php";
$statusFilter = isset($_GET['Status']) ? mysqli_real_escape_string($conn, $_GET['Status']) : 'Active';
$createdBy = $_SESSION['user_id'];

if (isset($_POST['editEmployee'])) {
    $PK_Employees = $_POST['PK_Employees'];
    $EmployeeID = $_POST['EmployeeID'];
    $Name = $_POST['Name'];
    $Department = $_POST['Department'];
    $Position = $_POST['Position'];
    $Email = $_POST['Email'];
    $PhoneNumber = $_POST['PhoneNumber'];
    $DateHired = $_POST['DateHired'];
    $Status = $_POST['Status'];

    $query = "UPDATE Employees SET 
    EmployeeID=?, Name=?, Department=?, Position=?, Email=?, PhoneNumber=?, DateHired=?, Status=?
    WHERE PK_Employees=?";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("ssssssssi", $EmployeeID, $Name, $Department, $Position, $Email, $PhoneNumber, $DateHired, $Status, $PK_Employees);
    $stmt->execute();

    header("Location: employees.php?status=employeeupdated&Status=$statusFilter");
    exit();
}


if (isset($_POST['addEmployee'])) {

    $EmployeeID = $_POST['EmployeeID'];
    $Name = $_POST['Name'];
    $Department = $_POST['Department'];
    $Position = $_POST['Position'];
    $Email = $_POST['Email'];
    $PhoneNumber = $_POST['PhoneNumber'];
    $DateHired = $_POST['DateHired'];
    $Status = $_POST['Status'];

    $query = "INSERT INTO Employees (EmployeeID, Name, Department, Position, Email, PhoneNumber, DateHired, Status)
          VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("ssssssss", $EmployeeID, $Name, $Department, $Position, $Email, $PhoneNumber, $DateHired, $Status);
    $stmt->execute();

    header("Location: employees.php?status=employeeadded&Status=$statusFilter");
}

if ($statusFilter) {
    $sql = "SELECT * FROM Employees WHERE Status = '$statusFilter' ORDER BY Name ASC";
} else {
    $sql = "SELECT * FROM Employees ORDER BY Name ASC";
}

$resultEmployees = mysqli_query($conn, $sql);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employees</title>
    <link rel="icon" href="inventory.png" type="image/x-icon">
    <?php include "tools/head-plugin.php" ?>

</head>

<body>
    <?php include 'header.php'; ?>
    <div class="container">
        <h2 class="mb-4">Employees</h2>

        <div class="d-flex justify-content-end mb-3">
            <button type="button" class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#addEmployeeModal">
                + Add Employee
            </button>

        </div>
        <?php include 'modal.php'; ?>


        <form method="GET" class="row g-2 mb-3">
            <div class="col-auto">
                <label for="status-select" class="col-form-label">Status: </label>
            </div>
            <div class="col-auto">
                <select name="Status" class="form-select" onchange="this.form.submit()">
                    <option value="Active" <?= $statusFilter === 'Active' ? 'selected' : '' ?>>Active</option>
                    <option value="Inactive" <?= $statusFilter === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
        </form>


        <div class="table-responsive">
            <table id="myTableUsers" class="table table-bordered table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Employee ID</th>
                        <th>Name</th>
                        <th>Department</th>
                        <th>Position</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($resultEmployees && $resultEmployees->num_rows > 0): ?>
                        <?php while ($row = $resultEmployees->fetch_assoc()): ?>
                            <tr>

                                <td><?= htmlspecialchars($row['EmployeeID']) ?></td>
                                <td><?= htmlspecialchars($row['Name']) ?></td>
                                <td><?= htmlspecialchars($row['Department']) ?></td>
                                <td><?= htmlspecialchars($row['Position']) ?></td>
                                <td><?= htmlspecialchars($row['PhoneNumber']) ?></td>
                                <td>
                                    <span class="badge 
                                <?= $row['Status'] === 'Active' ? 'bg-success' : ($row['Status'] === 'Inactive' ? 'bg-warning text-dark' : 'bg-danger') ?>">
                                        <?= $row['Status'] ?>
                                    </span>
                                </td>

                                <td>
                                    <button type="button"
                                        class="btn btn-sm btn-warning btn-edit-employee"
                                        data-bs-toggle="modal"
                                        data-bs-target="#editEmployeeModal"
                                        data-employee='<?= json_encode($row) ?>'
                                        title="Edit Employee">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>

                                    <a href="assigned-devices.php?employee=<?= urlencode($row['PK_Employees']) ?>&name=<?= urlencode($row['Name']) ?>"
                                        class="btn btn-sm btn-info"
                                        title="Device History">
                                        <i class="bi bi-list"></i>
                                    </a>
                                </td>

                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>

    <script src="libs/jquery-3.6.0.min.js"></script>
    <script src="libs/bootstrap.bundle.min.js"></script>
    <script src="libs/jspdf.umd.min.js"></script>
    <script src="libs/jspdf.plugin.autotable.min.js"></script>
    <script src="libs/jquery.dataTables.min.js"></script>

    <!-- Buttons extension -->
    <link rel="stylesheet" href="css/buttons.bootstrap5.min.css">
    <script src="libs/dataTables.buttons.min.js"></script>
    <script src="libs/buttons.bootstrap5.min.js"></script>
    <script src="libs/jszip.min.js"></script>
    <script src="libs/pdfmake.min.js"></script>
    <script src="libs/vfs_fonts.js"></script>
    <script src="libs/buttons.html5.min.js"></script>

    <script src="script.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <?php include "tools/alert-message.php"; ?>

    <script>
        $(document).ready(function() {
            $('#myTableUsers').DataTable({
                dom: '<"justify-content-between align-items-center mb-2"lfB>tip',
                pagingType: 'simple',
                buttons: [{
                        extend: 'csvHtml5',
                        text: '<i class="bi bi-filetype-csv"></i>CSV',
                        className: 'btn btn-sm btn-primary',
                        title: 'Employees',
                        exportOptions: {
                            columns: function(idx, data, node) {
                                return idx !== 7; // Exclude column indexes 6 and 7
                            }
                        }
                    },
                    {
                        extend: 'excelHtml5',
                        text: '<i class="bi bi-file-earmark-excel"></i>Excel',
                        className: 'btn btn-sm btn-success',
                        title: 'Employees',
                        exportOptions: {
                            columns: function(idx, data, node) {
                                return idx !== 7;
                            }
                        }
                    },
                    {
                        extend: 'pdfHtml5',
                        text: '<i class="bi bi-filetype-pdf"></i>PDF',
                        className: 'btn btn-sm btn-danger',
                        title: 'Employees',
                        exportOptions: {
                            columns: function(idx, data, node) {
                                return idx !== 7;
                            }
                        },
                        customize: function(doc) {
                            // Auto-adjust column widths evenly
                            var tableBody = doc.content[1].table.body;
                            var colCount = tableBody[0].length;

                            // Set even column widths
                            doc.content[1].table.widths = Array(colCount).fill('*');

                            // Optional: Set page margins or styles
                            doc.pageMargins = [20, 20, 20, 20]; // [left, top, right, bottom]
                        }
                    }
                ],

                language: {
                    lengthMenu: "Show _MENU_ entries",
                    search: "",
                    searchPlaceholder: "Search..."
                }
            });
        });
    </script>

</body>

<?php
$conn->close();
?>

</html>