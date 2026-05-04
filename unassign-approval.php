<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

include "config.php";
include "tools/sender.php";

$createdBy = $_SESSION['user_id'];

$ApproveStatus = $_GET['ApproveStatus'] ?? 0;

if (isset($_POST['submitApproval'])) {
    $approval_id = $_POST['approval_id'];
    $asset_id = $_POST['asset_id'];

    //Validate first if its approved
    $stmtCheck = $conn->prepare("SELECT IsApproved, Approvers FROM assignapprovals WHERE PK_Approvals = ?");
    $stmtCheck->bind_param("i", $approval_id);
    $stmtCheck->execute();
    $stmtCheck->bind_result($isApproved, $approversJson);
    $stmtCheck->fetch();
    $stmtCheck->close();

    if ($isApproved == 1 || $isApproved == 2) {
        // Redirect and exit if already approved
        header("Location: unassign-approval.php");
        exit;
    }

    // Decode the JSON into a PHP array
    $approvers = json_decode($approversJson, true);

    $updated = false;
    $allNowApproved = false;

    foreach ($approvers as &$approver) {
        if ($approver['status'] == 0) {
            $approver['status'] = 1;
            $updated = true;
            break; // Only update the first one found
        }
    }

    // Re-encode and update in DB
    if ($updated) {
        $updatedJson = json_encode($approvers);

        $allNowApproved = empty(array_filter($approvers, fn($a) => $a['status'] != 1));

        $stmtUpdate = $conn->prepare("UPDATE assignapprovals SET Approvers = ? WHERE PK_Approvals = ?");
        $stmtUpdate->bind_param("si", $updatedJson, $approval_id);
        $stmtUpdate->execute();
        $stmtUpdate->close();

        AssignApproval($approval_id, $conn);
    }

    if ($allNowApproved) {
        // 3 is Assigned in assetinventory
        $stmtUpdateStatus = $conn->prepare("UPDATE assetinventory SET AssignStatus = 3 WHERE FK_AssetMaster = ? and AssignStatus = 1");
        $stmtUpdateStatus->bind_param("i", $asset_id);
        $stmtUpdateStatus->execute();
        $stmtUpdateStatus->close();

        // 1 is approved in assignapprovals
        $stmtUpdateStatus2 = $conn->prepare("UPDATE assignapprovals SET IsApproved = 1 WHERE PK_Approvals = ?");
        $stmtUpdateStatus2->bind_param("i", $approval_id);
        $stmtUpdateStatus2->execute();
        $stmtUpdateStatus2->close();

        $sql = "
            SELECT * 
            FROM AssignApprovals ax
            LEFT JOIN assetinventory bx
                ON ax.FK_AssetMaster = bx.FK_AssetMaster
            WHERE ax.PK_Approvals = $approval_id
            ORDER BY bx.PK_AssetInventory DESC
            LIMIT 1
        ";

        $result = $conn->query($sql);
        $row = $result->fetch_assoc();

        $FK_Employees   = $row['FK_Employees'];
        $DateAcquired = date('Y-m-d');
        $Conditions     = $row['Conditions'];
        $ApprovalType   = $row['ApprovalType'];


        $insertHistoryStmt = $conn->prepare("INSERT INTO HistoryAsset (FK_AssetMaster, FK_Employees, DateAcquired, `Conditions`, Status) VALUES (?, ?, ?, ?, ?)");
        $insertHistoryStmt->bind_param("iisss", $asset_id, $FK_Employees, $DateAcquired, $Conditions, $ApprovalType);
        $insertHistoryStmt->execute();
        $insertHistoryStmt->close();

        // Update AssetMaster AssignedTo to 0 (unassigned)
        $stmtUpdate = $conn->prepare("UPDATE AssetMaster SET AssignedTo = 0 WHERE PK_AssetMaster = ?");
        $stmtUpdate->bind_param("i", $asset_id);
        $stmtUpdate->execute();
        $stmtUpdate->close();
    }

    header("Location: unassign-approval.php");
    exit;
}

if (isset($_POST['submitRejection'])) {
    $approval_id = $_POST['approval_id'];
    $asset_id = $_POST['asset_id'];
    $rejectionReason = $_POST['RejectionReason'];

    // 1 is Assigned in assetinventory
    $stmtUpdateStatus = $conn->prepare("UPDATE assetinventory SET AssignStatus = 1 WHERE FK_AssetMaster = ?");
    $stmtUpdateStatus->bind_param("i", $asset_id);
    $stmtUpdateStatus->execute();
    $stmtUpdateStatus->close();

    // 1 is approved in assignapprovals
    $stmtUpdateStatus2 = $conn->prepare("UPDATE assignapprovals SET IsApproved = 2,  ReasonForRejection = ? WHERE PK_Approvals = ?");
    $stmtUpdateStatus2->bind_param("si", $rejectionReason, $approval_id);
    $stmtUpdateStatus2->execute();
    $stmtUpdateStatus2->close();
}

$sql = "SELECT 
            ax.PK_Approvals,
            ax.FK_AssetMaster,
            bx.AssetTagNumber,
            cx.Name,
            ax.CreatedOn,
            ax.ApprovalType,
            ax.IsApproved,
            ax.Reason,
            ax.OtherReason,
            ax.FK_AssetInventory,
            ax.ReasonForRejection
        FROM assignapprovals ax
        LEFT JOIN assetmaster bx ON ax.FK_AssetMaster = bx.PK_AssetMaster
        LEFT JOIN employees cx ON cx.PK_Employees = ax.FK_Employees
        WHERE ax.IsApproved = $ApproveStatus and ax.ApprovalType = 'Unassigned'
        ORDER BY ax.CreatedOn DESC";

$resultApprovals = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unassign Approval</title>
    <link rel="icon" href="inventory.png" type="image/x-icon">
    <?php include "tools/head-plugin.php" ?>
    <style>
        /* Animation Container */
        .loading-animation-container {
            position: relative;
            height: 120px;
            width: 100%;
            overflow: hidden;
        }

        /* Icons */
        .asset-icon-unassign,
        .employee-icon-unassign,
        .storage-icon-unassign {
            position: absolute;
            font-size: 4rem;
            bottom: 20px;
        }

        /* Employee Icon (Static) */
        .employee-icon-unassign {
            left: 15%;
            color: #495057;
            transition: color 0.3s ease;
        }

        .employee-icon-unassign.sending {
            animation: pulse-send-icon 0.5s ease-in-out;
        }

        /* Storage Icon (Static) */
        .storage-icon-unassign {
            right: 15%;
            color: #495057;
        }

        /* Asset Icon (Animated) */
        .asset-icon-unassign {
            color: #dc3545;
            /* Red for unassign/return */
            animation: move-asset-from-employee 3s linear infinite;
        }

        /* Keyframe Animations */
        @keyframes move-asset-from-employee {
            0% {
                left: 25%;
                opacity: 0;
                transform: scale(0.5);
            }

            15% {
                left: 30%;
                opacity: 1;
                transform: scale(1);
            }

            85% {
                left: 65%;
                opacity: 1;
                transform: scale(1);
            }

            100% {
                left: 70%;
                opacity: 0;
                transform: scale(0.8);
            }
        }

        @keyframes pulse-send-icon {
            0% {
                transform: scale(1) translateX(0);
            }

            50% {
                transform: scale(1.1) translateX(-5px);
            }

            100% {
                transform: scale(1) translateX(0);
            }
        }
    </style>

</head>

<body>
    <?php include 'header.php'; ?>
    <div class="container">
        <h2 class="mb-4">Approve Unassignment</h2>
        <?php include 'modal.php'; ?>

        <div class="d-flex justify-content-end mb-3">
            <button type="button" class="btn btn-dark" onclick="window.location.href='dashboard.php'">
                <i class="bi bi-arrow-left me-1"></i> Back
            </button>
        </div>

        <a href="?ApproveStatus=0" class="btn btn-outline-primary">For Approval</a>
        <a href="?ApproveStatus=1" class="btn btn-outline-success">Approved</a>
        <a href="?ApproveStatus=2" class="btn btn-outline-danger">Rejected</a>

        <br><br>

        <?php
        if ($ApproveStatus == 0) {
            $statusFilter = "For Approval";
        } else if ($ApproveStatus == 1) {
            $statusFilter = "Approved";
        } else if ($ApproveStatus == 2) {
            $statusFilter = "Rejected";
        } else {
            $statusFilter = "Are you crazy?";
        }
        ?>

        <h5 class="mt-3">
            Status: <span class="badge bg-light text-dark border border-secondary"><?php echo $statusFilter; ?></span>
        </h5>

        <?php include "tools/alert-message.php"; ?>

        <table id="myTable" class="table table-bordered table-striped table-sm">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Asset Tag Number</th>
                    <th>Employee Name</th>
                    <th>Reason</th>
                    <th>Submitted On</th>
                    <?php
                    if ($ApproveStatus == 2) {
                    ?>
                        <th>Reason</th>
                    <?php
                    }
                    ?>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($resultApprovals && $resultApprovals->num_rows > 0): ?>
                    <?php while ($row = $resultApprovals->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['PK_Approvals']) ?></td>
                            <td><?= htmlspecialchars($row['AssetTagNumber']) ?></td>
                            <td><?= htmlspecialchars($row['Name']) ?></td>
                            <td><?= htmlspecialchars($row['Reason'] === 'Other' ? $row['OtherReason'] : $row['Reason']) ?></td>
                            <td><?= htmlspecialchars($row['CreatedOn']) ?></td>
                            <?php
                            if ($ApproveStatus == 2) {
                            ?>
                                <td><?= htmlspecialchars($row['ReasonForRejection']) ?></td>
                            <?php
                            }
                            ?>

                            <td class="text-center">
                                <!-- View Button -->
                                <a href="#" class="btn btn-sm btn-secondary me-1 btn-view-approval" title="View"
                                    data-bs-toggle="modal" data-bs-target="#viewApprovalModal"
                                    data-id="<?= $row['PK_Approvals'] ?>"
                                    data-invID="<?= $row['FK_AssetInventory'] ?>">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <!-- Approve Button -->
                                <?php if ($row['IsApproved'] == 0) { ?>

                                    <button type="button" class="btn btn-sm btn-success me-1 open-confirm-modal"
                                        data-id="<?= $row['PK_Approvals'] ?>"
                                        data-name="<?= $row['AssetTagNumber'] ?>"
                                        data-assetid="<?= $row['FK_AssetMaster'] ?>"
                                        title="Approve">
                                        <i class="bi bi-check-circle"></i>
                                    </button>

                                    <button type="button" class="btn btn-sm btn-danger me-1 open-reject-modal"
                                        data-id="<?= $row['PK_Approvals'] ?>"
                                        data-name="<?= $row['AssetTagNumber'] ?>"
                                        data-assetid="<?= $row['FK_AssetMaster'] ?>"
                                        title="Reject">
                                        <i class="bi bi-x-circle"></i>
                                    </button>

                                <?php } ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                <?php endif; ?>
            </tbody>
        </table>

    </div>

    <!-- Unassigning Asset Loading Modal -->
    <div class="modal fade" id="unassignLoadingModal" tabindex="-1" aria-labelledby="unassignLoadingModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="unassignLoadingModalLabel">
                        Processing Unassignment...
                    </h5>
                </div>
                <div class="modal-body text-center p-4">
                    <div class="loading-animation-container mb-3">
                        <i class="bi bi-person-fill employee-icon-unassign"></i>
                        <i class="bi bi-box-seam-fill asset-icon-unassign"></i>
                        <i class="bi bi-hdd-stack-fill storage-icon-unassign"></i>
                    </div>
                    <p class="mb-0 text-muted">Please wait while the asset is being returned to storage.</p>
                    <div class="progress mt-3" role="progressbar" aria-label="Animated striped example" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100" style="height: 5px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated bg-danger" style="width: 100%"></div>
                    </div>
                </div>
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




    <script>
        $(document).ready(function() {
            $('#myTable').DataTable({
                dom: '<"justify-content-between align-items-center mb-2"lfB>tip',
                pagingType: 'simple',
                buttons: [{
                        extend: 'csvHtml5',
                        text: '<i class="bi bi-filetype-csv"></i>CSV',
                        className: 'btn btn-sm btn-primary',
                        title: 'Asset Types',
                        exportOptions: {
                            columns: function(idx, data, node) {
                                return idx !== 5; // Exclude column indexes 6 and 7
                            }
                        }
                    },
                    {
                        extend: 'excelHtml5',
                        text: '<i class="bi bi-file-earmark-excel"></i>Excel',
                        className: 'btn btn-sm btn-success',
                        title: 'Asset Types',
                        exportOptions: {
                            columns: function(idx, data, node) {
                                return idx !== 5;
                            }
                        }
                    },
                    {
                        extend: 'pdfHtml5',
                        text: '<i class="bi bi-filetype-pdf"></i>PDF',
                        className: 'btn btn-sm btn-danger',
                        title: 'Asset Types',
                        exportOptions: {
                            columns: function(idx, data, node) {
                                return idx !== 5;
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
                },
                order: [
                    [2, 'desc'], // Sort by column index 2 (3rd column), ascending
                    [3, 'desc'] // Then by column index 3 (4th column), ascending
                ],
                columnDefs: [{
                        orderable: false,
                        targets: 0
                    } // disable sorting on index column
                ],
                drawCallback: function(settings) {
                    const api = this.api();
                    api.column(0, {
                            search: 'applied',
                            order: 'applied',
                            page: 'current'
                        })
                        .nodes()
                        .each(function(cell, i) {
                            cell.innerHTML = i + 1;
                        });
                }
            });
        });
    </script>
    <script>
        document.getElementById('confirmApprovalAssign').addEventListener('submit', function(e) {
            const modal = bootstrap.Modal.getInstance(document.getElementById('confirmApproveModal'));
            if (modal) {
                modal.hide();
            }

            const unassignModal = new bootstrap.Modal(document.getElementById('unassignLoadingModal'));
            const startBtn = document.getElementById('startUnassignmentBtn');
            const employeeIcon = document.querySelector('.employee-icon-unassign');

            unassignModal.show();

            // --- Logic for icon animation ---
            const animationDuration = 3000; // Must match the CSS animation duration (3s)
            const pulsePoint = animationDuration * 0.1; // 10% through the animation

            function triggerPulse() {
                setTimeout(() => {
                    employeeIcon.classList.add('sending');
                    setTimeout(() => {
                        employeeIcon.classList.remove('sending');
                    }, 500); // Duration of the pulse animation
                }, pulsePoint);
            }

            // Set up an interval to match the CSS animation
            let pulseInterval;
            const modalElement = document.getElementById('unassignLoadingModal');

            modalElement.addEventListener('shown.bs.modal', () => {
                triggerPulse(); // Trigger immediately on show
                pulseInterval = setInterval(triggerPulse, animationDuration);
            });

            modalElement.addEventListener('hidden.bs.modal', () => {
                clearInterval(pulseInterval); // Clean up the interval when the modal is hidden
            });

        });
    </script>

</body>

<?php
$conn->close();
?>

</html>