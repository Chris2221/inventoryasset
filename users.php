<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

include "config.php";

$createdBy = $_SESSION['user_id'];
$statusFilter = isset($_GET['Status']) ? $_GET['Status'] : '';

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['addUser'])) {

    $username = trim($_POST['Username']);
    $password = password_hash($_POST['Password'], PASSWORD_DEFAULT);
    $name = trim($_POST['Name']);
    $role = trim($_POST['Role']); // New line to get Role
    $employee_id = isset($_POST['FK_Employee']) ? intval($_POST['FK_Employee']) : null;

    $stmt = $conn->prepare("INSERT INTO Users (Username, Password, Name, Role, FK_Employees) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssi", $username, $password, $name, $role, $employee_id); // Add role to binding

    if ($stmt->execute()) {
        header("Location: users.php?Status=" . urlencode($statusFilter) . "&status=useradded");
        exit;
    } else {
        echo "Error adding user: " . $stmt->error;
    }

    $stmt->close();
}


if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['editUser'])) {
    $id = intval($_POST['UserID']);
    $username = trim($_POST['Username']);
    $name = trim($_POST['Name']);
    $role = trim($_POST['Role']);
    $password = trim($_POST['password']);
    $status  = intval($_POST['status']);
    $FK_Employee = intval($_POST['FK_Employee']);

    echo $role;

    if (!empty($password)) {
        // Hash the new password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // Update including password
        $stmt = $conn->prepare("UPDATE Users SET Username = ?, Name = ?, Role = ?, Password = ?, Status = ? WHERE PK_Users = ?");
        $stmt->bind_param("ssssii", $username, $name, $role, $hashedPassword, $status, $id);
    } else {
        // Update without changing the password
        $stmt = $conn->prepare("UPDATE Users SET Username = ?, Name = ?, Role = ?, Status = ? WHERE PK_Users = ?");
        $stmt->bind_param("sssii", $username, $name, $role, $status, $id);
    }


    $stmts = $conn->prepare("UPDATE Users SET FK_Employees = ? WHERE PK_Users = ?");
    $stmts->bind_param("ii", $FK_Employee, $id);
    $stmts->execute();


    if ($stmt->execute()) {
        header("Location: users.php?status=userupdated&Status=$statusFilter");
        exit;
    } else {
        echo "Error updating user: " . $stmt->error;
    }

    $stmt->close();
}

function getInitials($name)
{
    $parts = explode(' ', trim($name));
    $initials = '';

    // Get first character of the first word
    if (isset($parts[0])) {
        $initials .= strtoupper($parts[0][0]);
    }

    // Get first character of the second word (if exists)
    if (isset($parts[1])) {
        $initials .= strtoupper($parts[1][0]);
    }

    return $initials;
}


$queryUsers = "SELECT ax.*, bx.Name as EmployeeName, bx.EmployeeID, ifnull(bx.PK_Employees, 0) as PK_Employees
               FROM Users ax
               left join Employees bx
               on ax.FK_Employees = bx.PK_Employees";

if ($statusFilter !== '') {
    $queryUsers .= " WHERE ax.Status = " . intval($statusFilter);
}

$resultUsers = $conn->query($queryUsers);


$fkEmployees = [];
$resultemp = mysqli_query($conn, "SELECT FK_Employees FROM Users WHERE FK_Employees IS NOT NULL and FK_Employees != 0 and Status = 1");
while ($row = mysqli_fetch_assoc($resultemp)) {
    $fkEmployees[] = $row['FK_Employees'];
}

// Step 2: Create a comma-separated list
$excludedEmployees = !empty($fkEmployees) ? implode(',', $fkEmployees) : '0'; // fallback to '0' if empty
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users</title>
    <link rel="icon" href="inventory.png" type="image/x-icon">
    <?php include "tools/head-plugin.php" ?>

</head>

<body>
    <?php include 'header.php'; ?>
    <div class="container">
        <h2 class="mb-4">Users</h2>
        <?php include 'modal.php'; ?>

        <div class="d-flex justify-content-end mb-3">
            <?php if ($_SESSION['role'] == 'Admin') { ?>
                <button type="button" class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#addUserModal">
                    <i class="bi bi-person-plus-fill me-1"></i> Add User
                </button>

            <?php } ?>

        </div>

        <form method="GET" class="mb-3">
            <label for="statusFilter" class="form-label">Filter by Status:</label>
            <select name="Status" id="statusFilter" class="form-select" onchange="this.form.submit()">
                <option value="">-- All Users --</option>
                <option value="1" <?= isset($_GET['Status']) && $_GET['Status'] === '1' ? 'selected' : '' ?>>Active</option>
                <option value="0" <?= isset($_GET['Status']) && $_GET['Status'] === '0' ? 'selected' : '' ?>>Inactive</option>
            </select>
        </form>


        <div class="table-responsive">
            <table id="myTableUsers" class="table table-bordered table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th scope="col">#</th>
                        <th scope="col">Username</th>
                        <th scope="col">Full Name</th>
                        <th scope="col">Role</th>
                        <th scope="col">Employee ID</th>
                        <th scope="col">Employee</th>
                        <?php if ($_SESSION['role'] == 'Admin') { ?>
                            <th scope="col">Action</th>
                        <?php } ?>
                    </tr>
                </thead>
                <tbody>
                    <?php $count = 0;
                    if ($resultUsers && $resultUsers->num_rows > 0): ?>
                        <?php while ($row = $resultUsers->fetch_assoc()): ?>
                            <?php $count++ ?>
                            <tr>
                                <td><?php echo $count; ?></td>
                                <td><?= htmlspecialchars($row['Username']) ?></td>

                                <?php
                                $initials = getInitials($row['Name']);

                                ?>

                                <td>
                                    <img style="height: 20px; width: 20px; border-radius: 50%;"
                                        src="https://placehold.co/40x40/CBD5E0/4A5568?text=<?= $initials ?>"
                                        alt="User Avatar">

                                    <?= htmlspecialchars($row['Name']) ?>

                                </td>
                                <td>
                                    <span class="badge 
                                        <?= $row['Role'] === 'Admin' ? 'bg-danger' : ($row['Role'] === 'User' ? 'bg-primary' : ($row['Role'] === 'Manager' ? 'bg-success' : ($row['Role'] === 'Supervisor' ? 'bg-warning text-dark' : 'bg-secondary'))) ?>">
                                        <?= htmlspecialchars($row['Role']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?= htmlspecialchars($row['EmployeeID']) ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($row['EmployeeName']) ?>
                                </td>



                                <?php if ($_SESSION['role'] == 'Admin') { ?>
                                    <td>
                                        <button type="button"
                                            class="btn btn-sm btn-primary btn-edit-user"
                                            data-id="<?= $row['PK_Users'] ?>"
                                            data-username="<?= htmlspecialchars($row['Username']) ?>"
                                            data-name="<?= htmlspecialchars($row['Name']) ?>"
                                            data-role="<?= htmlspecialchars($row['Role']) ?>"
                                            data-status="<?= htmlspecialchars($row['Status']) ?>"
                                            data-emp_id="<?= htmlspecialchars($row['PK_Employees']) ?>"
                                            data-bs-toggle="modal"
                                            data-bs-target="#editUserModal"
                                            title="Edit Account">
                                            <i class="bi bi-pencil"></i>
                                        </button>

                                    </td>
                                <?php } ?>

                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>

                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-------------Users-------------->

    <!-- Add New User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addUserModalLabel">
                            <i class="bi bi-person-plus-fill me-2"></i>Add New User
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" name="Username" id="username" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" name="Password" id="password" class="form-control" required>
                        </div>

                        <!-- Employee Dropdown -->
                        <div class="mb-3">
                            <label for="employee_id" class="form-label">Select Employee</label>
                            <select name="FK_Employee" id="employee_id" class="form-select">
                                <option value="" selected>Select an employee</option>
                                <?php
                                $empQuery = "SELECT PK_Employees, EmployeeID, Name FROM Employees WHERE Status = 'Active'  AND PK_Employees NOT IN ($excludedEmployees) ORDER BY Name ASC";
                                $empResult = mysqli_query($conn, $empQuery);
                                if ($empResult && mysqli_num_rows($empResult) > 0) {
                                    while ($emp = mysqli_fetch_assoc($empResult)) {
                                        echo '<option value="' . $emp['PK_Employees'] . '" data-name="' . htmlspecialchars($emp['Name'], ENT_QUOTES) . '">' . $emp['EmployeeID'] . ' - ' . $emp['Name'] . '</option>';
                                    }
                                } else {
                                    echo '<option disabled>No active employees found</option>';
                                }
                                ?>
                            </select>
                        </div>


                        <div class="mb-3">
                            <label for="name" class="form-label">Full Name</label>
                            <input type="text" name="Name" id="nameAddUser" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label for="role" class="form-label">Role</label>
                            <select name="Role" id="role" class="form-select" required>
                                <option value="" disabled selected>Select a role</option>
                                <option value="Admin">Admin</option>
                                <option value="User">User</option>
                                <option value="Supervisor">Supervisor</option>
                                <option value="Manager">Manager</option>
                            </select>
                        </div>

                    </div>
                    <div class="modal-footer">
                        <button type="submit" name="addUser" class="btn btn-dark">
                            <i class="bi bi-save me-1"></i>Save User
                        </button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle me-1"></i>Cancel
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST"> <!-- Adjust action path if needed -->
                    <div class="modal-header">
                        <h5 class="modal-title" id="editUserModalLabel">
                            <i class="bi bi-pencil-square me-1"></i>Edit User
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>


                    <div class="modal-body">
                        <input type="hidden" name="UserID" id="edit-user-id">

                        <div class="mb-3">
                            <label for="edit-username" class="form-label">Username</label>
                            <input type="text" name="Username" id="edit-username" class="form-control" required>
                        </div>

                        <!-- Employee Dropdown -->
                        <div class="mb-3">
                            <label for="employee_id" class="form-label">Select Employee</label>
                            <select name="FK_Employee" id="edit_employee_id" class="form-select">
                                <option value="" disabled selected>Loading employees...</option>
                            </select>
                        </div>


                        <div class="mb-3">
                            <label for="edit-name" class="form-label">Full Name</label>
                            <input type="text" name="Name" id="edit-name" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label for="edit-role" class="form-label">Role</label>
                            <select name="Role" id="edit-role" class="form-select" required>
                                <option value="Admin">Admin</option>
                                <option value="User">User</option>
                                <option value="Supervisor">Supervisor</option>
                                <option value="Manager">Manager</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="edit-password" class="form-label">Password (Leave if no update)</label>
                            <input type="password" name="password" id="edit-password" class="form-control">
                        </div>


                        <div class="mb-3">
                            <label for="edit-role" class="form-label">Status</label>
                            <select name="status" id="edit-status" class="form-select" required>
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>

                    </div>
                    <div class="modal-footer">
                        <button type="submit" name="editUser" class="btn btn-dark">
                            <i class="bi bi-save me-1"></i>Save Changes
                        </button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle me-1"></i>Cancel
                        </button>
                    </div>

                </form>
            </div>
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
        document.addEventListener("DOMContentLoaded", function() {
            const editEmployeeSelect = document.getElementById('edit_employee_id');
            const modal = document.getElementById('editUserModal');

            modal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const selectedEmpId = button.getAttribute('data-emp_id');

                fetch('tools/fetch_employees.php?selectedEmpId=' + encodeURIComponent(selectedEmpId))
                    .then(res => res.json())
                    .then(data => {
                        editEmployeeSelect.innerHTML = '<option value="">Select an employee</option>';

                        data.forEach(emp => {
                            const option = document.createElement('option');
                            option.value = emp.PK_Employees;
                            option.textContent = `${emp.EmployeeID} - ${emp.Name}`;
                            option.setAttribute('data-name', emp.Name);

                            if (emp.PK_Employees == selectedEmpId) {
                                option.selected = true;
                            }

                            editEmployeeSelect.appendChild(option);
                        });
                    })
                    .catch(error => {
                        console.error('Failed to load employee list:', error);
                        editEmployeeSelect.innerHTML = '<option disabled>Error loading employees</option>';
                    });
            });
        });
    </script>



    <script>
        document.getElementById('employee_id').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const employeeName = selectedOption.getAttribute('data-name');
            if (employeeName) {
                document.getElementById('nameAddUser').value = employeeName;
            }
        });
    </script>


    <script>
        document.getElementById('edit_employee_id').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const employeeName = selectedOption.getAttribute('data-name');
            if (employeeName) {
                document.getElementById('edit-name').value = employeeName;
            }
        });
    </script>


    <script>
        $(document).ready(function() {
            $('#myTableUsers').DataTable({
                dom: '<"justify-content-between align-items-center mb-2"lfB>tip',
                pagingType: 'simple',
                buttons: [{
                        extend: 'csvHtml5',
                        text: '<i class="bi bi-filetype-csv"></i>CSV',
                        className: 'btn btn-sm btn-primary',
                        title: 'Users',
                        exportOptions: {
                            columns: function(idx, data, node) {
                                return idx !== 4; // Exclude column indexes 6 and 7
                            }
                        }
                    },
                    {
                        extend: 'excelHtml5',
                        text: '<i class="bi bi-file-earmark-excel"></i>Excel',
                        className: 'btn btn-sm btn-success',
                        title: 'Users',
                        exportOptions: {
                            columns: function(idx, data, node) {
                                return idx !== 4;
                            }
                        }
                    },
                    {
                        extend: 'pdfHtml5',
                        text: '<i class="bi bi-filetype-pdf"></i>PDF',
                        className: 'btn btn-sm btn-danger',
                        title: 'Users',
                        exportOptions: {
                            columns: function(idx, data, node) {
                                return idx !== 4;
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