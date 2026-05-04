<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

include "config.php";
include "tools/sender.php";

$createdBy = $_SESSION['user_id'];
date_default_timezone_set('Asia/Manila');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['MarkReturned'])) {
    $returnedData = $_POST['returnedData'];

    $returnedDate = date('Y-m-d');

    $assetId = intval($_POST['PK_OutboundAssets']);

    $isReturnedAll = true;

    // Decode JSON into PHP array
    $dataArray = json_decode($returnedData, true);

    if ($dataArray && is_array($dataArray)) {
        foreach ($dataArray as $row) {
            $isReturned = 0; // Default value for isReturned
            if (((int)$row['quantityReceived'] + (int)$row['amountInput']) == (int)$row['quantityDispatched']) {
                $isReturned = 1;
            } else {
                $isReturnedAll = false;
            }

            $dateReturned = !empty($row['returnDate']) ? $row['returnDate'] : null;

            $QuantityReceived = $row['quantityReceived'] + $row['amountInput'];

            $sql = "Update outboundassetslist set
                    QuantityReceived = ?, 
                    isReturned = ?,
                    ReturnedDate = ?
                WHERE PK_OutboundAssetsList = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iisi", $QuantityReceived, $isReturned, $dateReturned, $row['id']);
            if (!$stmt->execute()) {
                echo "Error updating record: " . $stmt->error;
            }
            $stmt->close();


            if ($row['amountInput'] > 0) {
                $stmt = $conn->prepare("SELECT 
                            CASE 
                                WHEN FK_AssetMaster = 0 THEN FK_GeneralAssetMaster
                                ELSE FK_AssetMaster 
                            END AS AssetId,
                            CASE 
                                WHEN FK_AssetMaster = 0 THEN 'GAM'
                                ELSE 'AM'
                            END AS AssetType
                        FROM outboundassetslist 
                        WHERE PK_OutboundAssetsList = ?");
                $stmt->bind_param("i", $row['id']);
                $stmt->execute();

                $insertAssetID = null;
                $insertAssetType = null;
                $result = $stmt->get_result();
                if ($result && $data = $result->fetch_assoc()) {
                    $insertAssetID = $data['AssetId'];
                    $insertAssetType = $data['AssetType'];
                }

                $stmt->close();

                $stmt = $conn->prepare("SELECT PK_OutboundAssets, History FROM OutboundAssets WHERE PK_OutboundAssets = ?");
                $stmt->bind_param("i", $assetId);
                $stmt->execute();
                $result = $stmt->get_result();
                $currentHistory = $result->fetch_assoc()['History'] ?? '[]';
                $stmt->close();

                // ✅ Decode, append, encode history
                $historyArray = json_decode($currentHistory, true) ?: [];
                $historyArray[] = [
                    'AssetID' => $insertAssetID,
                    'ReturnedDate' => $dateReturned,
                    'QuantityReturned' => $row['amountInput'],
                    'AssetType' => $insertAssetType
                ];
                $newHistoryJSON = json_encode($historyArray);

                // ✅ Update history in DB
                $stmt = $conn->prepare("UPDATE OutboundAssets SET History = ? WHERE PK_OutboundAssets = ?");
                $stmt->bind_param("si", $newHistoryJSON, $assetId);
                $stmt->execute();
                $stmt->close();
            }
        }
    }

    if ($isReturnedAll) {
        $stmt = $conn->prepare("UPDATE OutboundAssets SET ReturnedDate = ?, Status = 'Returned' WHERE PK_OutboundAssets = ?");
        $stmt->bind_param("si", $returnedDate, $assetId);

        if ($stmt->execute()) {
            OutboundReceived($assetId, $conn);
            header("Location: inbound-assets.php?status=returned");
            exit;
        } else {
            echo "Error updating return date: " . $stmt->error;
        }
    }
    header("Location: inbound-assets.php");
    exit;
}

// default to 'Pending' if not set
$statusFilter = $_GET['Status'] ?? 'Approved';

$sqlOutbounds = "SELECT 
            oa.PK_OutboundAssets,
            oa.Descriptions,
            oa.DateAcquired,
            oa.ExpectedReturnDate,
            ua.name AS UserName,
            oa.Status,
            oa.CreatedOn,
            oa.ReturnedDate,
            (
                Select sum(Quantity) from outboundassetslist where FK_OutboundAssets = oa.PK_OutboundAssets
            ) as Quantity,
            (
                Select sum(QuantityReceived) from outboundassetslist where FK_OutboundAssets = oa.PK_OutboundAssets
            ) as QuantityReceived,
            oa.History
        FROM OutboundAssets oa
        LEFT JOIN Employees ua ON oa.FK_Users = ua.PK_Employees
        WHERE oa.Status = ?
        ORDER BY oa.CreatedOn DESC";

$stmt = $conn->prepare($sqlOutbounds);
$stmt->bind_param("s", $statusFilter);
$stmt->execute();
$resultOutbounds = $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inbound Assets</title>
    <link rel="icon" href="inventory.png" type="image/x-icon">
    <?php include "tools/head-plugin.php" ?>

    <style>
        /* Animation Container */
        .loading-animation-container {
            position: relative;
            height: 150px;
            width: 100%;
            overflow: hidden;
        }

        /* Conveyor Belt */
        .conveyor-belt {
            position: absolute;
            bottom: 30px;
            left: 0;
            width: 100%;
            height: 10px;
            background-color: #6c757d;
            border-radius: 5px;
        }

        .conveyor-belt::before {
            content: '';
            position: absolute;
            top: 2px;
            left: 0;
            width: 200%;
            height: 6px;
            background: repeating-linear-gradient(-45deg,
                    #868e96,
                    #868e96 10px,
                    #6c757d 10px,
                    #6c757d 20px);
            animation: move-belt 2s linear infinite;
        }

        /* Warehouse/Receiving Bay Icon */
        .receiving-bay {
            position: absolute;
            right: 10%;
            bottom: 35px;
            font-size: 4rem;
            color: #495057;
        }

        /* Animated Boxes */
        .box-icon {
            position: absolute;
            bottom: 35px;
            font-size: 2.5rem;
            color: #a4704b;
            /* Brown color for box */
            animation: move-box 4s linear infinite;
        }

        .box-1 {
            animation-delay: 0s;
        }

        .box-2 {
            animation-delay: 1.5s;
        }

        .box-3 {
            animation-delay: 3s;
        }

        /* Keyframe Animations */
        @keyframes move-belt {
            from {
                transform: translateX(0);
            }

            to {
                transform: translateX(-50%);
            }
        }

        @keyframes move-box {
            0% {
                left: -10%;
                transform: scale(0.8) rotate(0deg);
                opacity: 0;
            }

            10% {
                left: 10%;
                transform: scale(1) rotate(0deg);
                opacity: 1;
            }

            80% {
                left: 75%;
                transform: scale(1) rotate(0deg);
                opacity: 1;
            }

            90% {
                left: 80%;
                transform: scale(0.5) rotate(10deg);
                opacity: 0;
            }

            100% {
                left: 80%;
                opacity: 0;
            }
        }
    </style>
</head>

<body>
    <?php include 'header.php'; ?>
    <div class="container">
        <h2 class="mb-4">Inbound Assets</h2>

        <?php include 'modal.php'; ?>
        <a href="?Status=Approved" class="btn btn-outline-primary">
            <i class="bi bi-arrow-clockwise"></i> Pending Return
        </a>

        <a href="?Status=Returned" class="btn btn-outline-success">
            <i class="bi bi-arrow-return-left"></i> Returned
        </a>
        <br><br>
        <?php
        if ($statusFilter === 'Approved') {
            $statusFilter = "For Return";
        }
        ?>

        <h5 class="mt-3">
            Status: <span class="badge bg-light text-dark border border-secondary"><?php echo $statusFilter; ?></span>
        </h5>

        <br>

        <div class="table-responsive">
            <table id="myTable" class="table table-bordered table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Description</th>
                        <th>Date Requested</th>
                        <?php if ($statusFilter == "Returned") { ?>
                            <th> Returned Date</th>
                            <th> Out Duration</th>
                        <?php } else { ?>
                            <th>Expected Return Date</th>
                        <?php } ?>

                        <th>Prepared By</th>
                        <th>Returned</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($resultOutbounds && $resultOutbounds->num_rows > 0): ?>
                        <?php while ($row = $resultOutbounds->fetch_assoc()): ?>
                            <tr>
                                <td>REQ-<?= $row['PK_OutboundAssets'] ?></td>
                                <td><?= htmlspecialchars($row['Descriptions']) ?></td>
                                <td><?= htmlspecialchars($row['DateAcquired']) ?></td>

                                <?php if ($statusFilter == "Returned") { ?>
                                    <td><?= htmlspecialchars($row['ReturnedDate']) ?></td>

                                    <?php
                                    $dateAcquired = new DateTime($row['DateAcquired']);
                                    $returnedDate = new DateTime($row['ReturnedDate']);

                                    $interval = $dateAcquired->diff($returnedDate);
                                    $daysOut = $interval->days;
                                    ?>
                                    <td><?= $daysOut ?> day(s)</td>
                                <?php } else { ?>
                                    <td><?= htmlspecialchars($row['ExpectedReturnDate']) ?></td>
                                <?php } ?>


                                <td><?= htmlspecialchars($row['UserName'] ?? 'Unknown') ?></td>

                                <?php
                                $qtyReceived = htmlspecialchars($row['QuantityReceived']);
                                $qtyTotal = htmlspecialchars($row['Quantity']);

                                $receivedClass = ($qtyReceived == $qtyTotal) ? 'bg-success' : 'bg-danger';
                                $totalClass = ($qtyReceived == $qtyTotal) ? 'bg-success' : 'bg-success';
                                ?>

                                <td>
                                    <span class="badge <?= $receivedClass ?>">
                                        <?= $qtyReceived ?>
                                    </span>
                                    /
                                    <span class="badge <?= $totalClass ?>">
                                        <?= $qtyTotal ?>
                                    </span>
                                </td>

                                <td>

                                    <a href="edit-outbound.php?id=<?= $row['PK_OutboundAssets'] ?>&status=<?= hash('sha256', $row['Status']) ?>&type=<?= hash('sha256', 'Inbound') ?>" class="btn btn-sm btn-primary" title="View Request">

                                        <?php if (htmlspecialchars($row['Status']) == "Approved" || htmlspecialchars($row['Status']) == "Rejected" || htmlspecialchars($row['Status']) == "Returned") { ?>
                                            <i class="bi bi-eye"></i>
                                        <?php } else { ?>
                                            <i class="bi bi-pencil-square"></i>
                                        <?php } ?>
                                    </a>

                                    <?php if (htmlspecialchars($row['Status']) == "Approved") { ?>
                                        <button class="btn btn-sm btn-warning" onclick="openReturnModal(<?= $row['PK_OutboundAssets'] ?>)"\
                                        title="Return Assets">
                                            <i class="bi bi-box-arrow-in-left"></i>
                                        </button>


                                    <?php } ?>

                                    <!-- History button -->
                                    <button class="btn btn-sm btn-info"
                                        onclick='viewHistory(<?= htmlspecialchars(json_encode($row["History"])) ?>)'
                                        title="Returned History">
                                        <i class="bi bi-clock-history"></i>
                                    </button>


                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>

                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>

    <div class="modal fade" id="historyModal" tabindex="-1" aria-labelledby="historyModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="historyModalLabel">Return History</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <table class="table table-bordered table-sm">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Asset Name / Tag</th>
                                <th>Returned Date</th>
                                <th>Quantity Returned</th>
                            </tr>
                        </thead>
                        <tbody id="historyTableBody">
                            <!-- rows will be injected here -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>


    <!-- Receiving Loading Modal -->
    <div class="modal fade" id="receivingModal" tabindex="-1" aria-labelledby="receivingModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="receivingModalLabel">
                        Receiving Items...
                    </h5>
                </div>
                <div class="modal-body text-center p-4">
                    <div class="loading-animation-container mb-3">
                        <div class="conveyor-belt"></div>
                        <i class="bi bi-box-seam-fill box-icon box-1"></i>
                        <i class="bi bi-box-seam-fill box-icon box-2"></i>
                        <i class="bi bi-box-seam-fill box-icon box-3"></i>
                        <i class="bi bi-door-closed-fill receiving-bay"></i>
                    </div>
                    <p class="mb-0 text-muted">Please wait while the items are being processed.</p>
                    <div class="progress mt-3" role="progressbar" aria-label="Animated striped example" aria-valuenow="75" aria-valuemin="0" aria-valuemax="100" style="height: 5px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 100%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function viewHistory(historyJson) {
            const history = JSON.parse(historyJson);
            const tableBody = document.getElementById("historyTableBody");
            tableBody.innerHTML = ""; // Clear previous

            if (!Array.isArray(history) || history.length === 0) {
                tableBody.innerHTML = "<tr><td colspan='5' class='text-center'>No history available.</td></tr>";
                return new bootstrap.Modal(document.getElementById('historyModal')).show();
            }

            // Fetch asset names in batch (optional optimization)
            const requests = history.map(item => {
                return fetch(`tools/get-asset-name.php?id=${item.AssetID}&type=${item.AssetType}`)
                    .then(res => res.text())
                    .then(name => ({
                        ...item,
                        assetName: name
                    }));
            });

            Promise.all(requests).then(fullData => {
                fullData.forEach((item, index) => {
                    const row = `<tr>
                <td>${index + 1}</td>
                <td>${item.assetName}</td>
                <td>${item.ReturnedDate}</td>
                <td>${item.QuantityReturned}</td>
            </tr>`;
                    tableBody.innerHTML += row;
                });

                new bootstrap.Modal(document.getElementById('historyModal')).show();
            });
        }
    </script>




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

    </script>

    <script>
        $(document).ready(function() {
            $('#myTable').DataTable({
                dom: '<"justify-content-between align-items-center mb-2"lfB>tip',
                pagingType: 'simple',
                buttons: [{
                        extend: 'csvHtml5',
                        text: '<i class="bi bi-filetype-csv"></i>CSV',
                        className: 'btn btn-sm btn-primary',
                        title: 'Outbound Assets',
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
                        title: 'Outbound Assets',
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
                        title: 'Outbound Assets',
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


    <script>
        function openReturnModal(outboundId) {
            document.getElementById('returnAssetId').value = outboundId;

            const tableBody = document.getElementById('returnModalTableBody');
            tableBody.innerHTML = '<tr><td colspan="6">Loading...</td></tr>';

            fetch('tools/load-returnItems.php?id=' + outboundId)
                .then(res => res.json())
                .then(data => {
                    if (data.error) {
                        tableBody.innerHTML = `<tr><td colspan="6">${data.error}</td></tr>`;
                        return;
                    }

                    if (data.length === 0) {
                        tableBody.innerHTML = '<tr><td colspan="6">No items found.</td></tr>';
                        return;
                    }

                    tableBody.innerHTML = '';

                    const today = new Date();
                    const yyyy = today.getFullYear();
                    const mm = String(today.getMonth() + 1).padStart(2, '0'); // Months are 0-based
                    const dd = String(today.getDate()).padStart(2, '0');
                    const minDate = `${yyyy}-${mm}-${dd}`;

                    data.forEach(item => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                                    <td>${item.PK_OutboundAssetsList}</td>
                                    <td>${item.SerialNumber || '-'}</td>
                                    <td>${item.Name}</td>
                                    <td>${item.AssetType || '-'}</td>
                                    <td>${item.Quantity}</td>
                                    <td>${item.QuantityReceived}</td>
                                    <td>
                                        <input 
                                            type="number" 
                                            max="${item.Quantity}" 
                                            min="0" 
                                            value="0" 
                                            ${item.Quantity === item.QuantityReceived ? 'disabled' : ''} 
                                            >
                                    </td>
                                    <td>
                                    <input 
                                        type="date" 
                                        class="form-control"
                                        ${item.ReturnedDate ? `value="${item.ReturnedDate}"` : ''}
                                        min=""
                                        disabled
                                    >
                                    
                                    </td>
                                    <td>
                                        <input type="checkbox" readonly disabled
                                            ${item.isReturned == 1 ? 'checked' : ''} style="opacity:1; cursor:default;">

                                    </td>
                                    `;
                        tableBody.appendChild(row);
                    });

                    // Show the modal
                    new bootstrap.Modal(document.getElementById('returnModal')).show();
                })
                .catch(err => {
                    console.error(err);
                    tableBody.innerHTML = '<tr><td colspan="6">Error loading data.</td></tr>';
                });
        }
    </script>

    <script>
        document.getElementById('returnForm').addEventListener('submit', function(e) {
            const rows = document.querySelectorAll('#returnModalTableBody tr');
            const data = [];

            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                const id = cells[0].textContent.trim();
                const quantityDispatched = parseInt(cells[4].textContent.trim(), 10);
                const quantityReceived = parseInt(cells[5].textContent.trim(), 10);
                const AmountInput = cells[6].querySelector('input[type="number"]');
                const AmountReceived = AmountInput ? parseInt(AmountInput.value, 10) : 0;
                const dateInput = cells[7].querySelector('input[type="date"]');
                const returnDate = dateInput ? dateInput.value : '';

                if ((Number(AmountReceived) + Number(quantityReceived)) > Number(quantityDispatched)) {
                    e.preventDefault();

                    // Highlight the input field with a red background
                    AmountInput.style.backgroundColor = '#f8d7da'; // Bootstrap danger background (light red)
                    AmountInput.style.borderColor = '#dc3545'; // Optional: red border

                    // Optional: show a simple message near the field
                    if (!AmountInput.nextElementSibling || !AmountInput.nextElementSibling.classList.contains('error-msg')) {
                        const error = document.createElement('div');
                        error.className = 'error-msg';
                        error.style.color = '#dc3545';
                        error.style.fontSize = '0.85em';
                        error.textContent = 'Total received exceeds dispatched quantity';
                        AmountInput.parentNode.appendChild(error);
                    }

                    AmountInput.focus();
                    return;
                } else {
                    // Remove error state if previously shown
                    AmountInput.style.backgroundColor = '';
                    AmountInput.style.borderColor = '';
                    const next = AmountInput.nextElementSibling;
                    if (next && next.classList.contains('error-msg')) {
                        next.remove();
                    }
                }


                data.push({
                    id,
                    quantityDispatched,
                    quantityReceived,
                    amountInput: AmountReceived,
                    returnDate
                });
            });

            document.getElementById('returnedData').value = JSON.stringify(data);
            const returnModalElement = document.getElementById('returnModal');
            const returnModalInstance = bootstrap.Modal.getInstance(returnModalElement);

            if (returnModalInstance) {
                returnModalInstance.hide();
            }

            const receivingModal = new bootstrap.Modal(document.getElementById('receivingModal'));
            receivingModal.show();
        });
    </script>


    <script>
        document.querySelector('#returnModalTableBody').addEventListener('input', function(e) {
            const target = e.target;

            // Example: When a number input changes
            if (target.matches('input[type="number"]')) {
                const row = target.closest('tr');
                const cells = row.querySelectorAll('td');
                const quantityDispatched = parseInt(cells[4].textContent.trim(), 10);
                const quantityReceived = parseInt(cells[5].textContent.trim(), 10);
                const AmountReceived = parseInt(target.value || 0, 10);
                const dateInput = cells[7].querySelector('input[type="date"]');

                // Reset any previous error visuals
                target.style.backgroundColor = '';
                target.style.borderColor = '';
                dateInput.classList.remove('is-invalid');
                dateInput.removeAttribute('required');

                // Check if over-dispatch
                if ((AmountReceived + quantityReceived) > quantityDispatched) {
                    target.style.backgroundColor = '#f8d7da';
                    target.style.borderColor = '#dc3545';
                }

                if (AmountReceived > 0) {
                    dateInput.disabled = false;
                    dateInput.setAttribute('required', 'required');

                    if (!dateInput.value) {
                        dateInput.classList.add('is-invalid');
                    } else {
                        dateInput.classList.remove('is-invalid');
                    }
                } else {
                    dateInput.disabled = true;
                    dateInput.value = '';
                    dateInput.classList.remove('is-invalid');
                    dateInput.removeAttribute('required');
                }

            }
        });
    </script>

</body>

<?php $conn->close(); ?>

</html>