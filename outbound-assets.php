<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

include "config.php";
include "tools/sender.php";

$createdBy = $_SESSION['user_id'];
$emp_id = $_SESSION['emp_id'];

function updateApproverStatus(&$approvers, $createdBy)
{
    $createdBy = (int)$createdBy;
    $found = false;
    $alreadyApproved = false;

    // Check if user is in approver list and already approved
    foreach ($approvers as $approver) {
        if ((int)$approver['approver_id'] === $createdBy) {
            $found = true;

            if ((int)$approver['status'] === 1) {
                $alreadyApproved = true;
            }

            break;
        }
    }

    // Exit if approver not found
    if (!$found) {
        header("Location: outbound-assets.php?status=unauthorized");
        exit;
    }

    // Exit if already approved
    if ($alreadyApproved) {
        return [
            'updated' => false,
            'blocked' => true
        ];
    }

    // Only allow updating if createdBy is next pending
    foreach ($approvers as &$approver) {
        if ((int)$approver['status'] === 0) {
            if ((int)$approver['approver_id'] === $createdBy) {
                $approver['status'] = 1;
                return [
                    'updated' => true,
                    'blocked' => false
                ];
            } else {
                return [
                    'updated' => false,
                    'blocked' => true,
                    'reason' => 'Not your turn to approve'
                ];
            }
        }
    }

    return [
        'updated' => false,
        'blocked' => false
    ];
}




if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['Approve'])) {
    $assetId = intval($_POST['PK_OutboundAssets']);

    // Check if the current status is "Pending"
    $stmtCheck = $conn->prepare("SELECT Status, Approvers FROM OutboundAssets WHERE PK_OutboundAssets = ?");
    $stmtCheck->bind_param("i", $assetId);
    $stmtCheck->execute();
    $stmtCheck->bind_result($status, $approversJson);
    $stmtCheck->fetch();
    $stmtCheck->close();

    // If not Pending, redirect and exit
    if ($status !== 'Pending') {
        header("Location: outbound-assets.php");
        exit;
    }

    $approvers = json_decode($approversJson, true);

    $approvers2 = json_decode($approversJson, true);

    $result = updateApproverStatus($approvers2, (int)$emp_id);

    if ($result['blocked']) {
        header("Location: outbound-assets.php?status=already_approved");
        exit;
    }

    $updated = false;
    $allNowApproved = false;


    foreach ($approvers as &$approver) {
        if ($approver['status'] == 0) {
            $approver['status'] = 1;
            $updated = true;
            break; // Only update the first one found
        }
    }

    if ($updated) {
        $updatedJson = json_encode($approvers);

        $allNowApproved = empty(array_filter($approvers, fn($a) => $a['status'] != 1));

        $stmtUpdate = $conn->prepare("UPDATE OutboundAssets SET Approvers = ? WHERE PK_OutboundAssets = ?");
        $stmtUpdate->bind_param("si", $updatedJson, $assetId);
        $stmtUpdate->execute();
        $stmtUpdate->close();

        OutboundApproval($assetId, $conn);
    }

    $currentUser = $_SESSION['user_id'];
    $actionUser = "Approved Outbound";
    $logDetails = "Approved Outbound ID: REQ-$assetId";
    logActivity($conn, $currentUser, $actionUser, $logDetails);

    if ($allNowApproved) {
        $stmt = $conn->prepare("UPDATE OutboundAssets SET Status = 'Approved' WHERE PK_OutboundAssets = ?");
        $stmt->bind_param("i", $assetId);

        if ($stmt->execute()) {
            header("Location: outbound-assets.php?status=outboundapproved");
            exit;
        } else {
            echo "Error approving asset: " . $stmt->error;
        }

        $stmt->close();
    }



    header("Location: outbound-assets.php?status=outboundapproved");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['Disapprove'])) {
    $assetId = intval($_POST['PK_OutboundAssets']);
    $rejectionReason = $_POST['RejectionReason'] ?? '';

    // Check if the current status is "Pending"
    $stmtCheck = $conn->prepare("SELECT Status FROM OutboundAssets WHERE PK_OutboundAssets = ?");
    $stmtCheck->bind_param("i", $assetId);
    $stmtCheck->execute();
    $stmtCheck->bind_result($status);
    $stmtCheck->fetch();
    $stmtCheck->close();

    // If not Pending, redirect and exit
    if ($status !== 'Pending') {
        header("Location: outbound-assets.php");
        exit;
    }

    $currentUser = $_SESSION['user_id'];
    $actionUser = "Disapproved Outbound";
    $logDetails = "Disapproved Outbound ID: REQ-$assetId";
    logActivity($conn, $currentUser, $actionUser, $logDetails);

    $stmt = $conn->prepare("UPDATE OutboundAssets SET Status = 'Rejected', ReasonForRejection = ? WHERE PK_OutboundAssets = ?");
    $stmt->bind_param("si", $rejectionReason, $assetId);

    if ($stmt->execute()) {
        $stmt = $conn->prepare("UPDATE outboundassetslist SET IsReturned = 2 WHERE FK_OutboundAssets = ?");
        $stmt->bind_param("i", $assetId);
        $stmt->execute();

        header("Location: outbound-assets.php?status=outboundrejected");
        exit;
    } else {
        echo "Error approving asset: " . $stmt->error;
    }

    $stmt->close();
}

$statusFilter = $_GET['Status'] ?? 'Pending'; // default to 'Pending' if not set

$sqlOutbounds = "SELECT 
            oa.PK_OutboundAssets,
            oa.Descriptions,
            oa.DateAcquired,
            oa.ExpectedReturnDate,
            ua.name AS UserName,
            oa.Status,
            oa.CreatedOn,
            oa.ReturnedDate,
            oa.ReasonForRejection
        FROM OutboundAssets oa
        LEFT JOIN Employees ua ON oa.FK_Users = ua.PK_Employees
        WHERE oa.Status = ? and oa.ReturnedDate is null
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
    <title>Outbound Assets</title>
    <link rel="icon" href="inventory.png" type="image/x-icon">
    <?php include "tools/head-plugin.php" ?>
    <link rel="stylesheet" href="css/loading-approval.css">

    <script src="https://unpkg.com/pdf-lib/dist/pdf-lib.min.js"></script>
</head>

<body>
    <?php include 'header.php'; ?>
    <div class="container">
        <h2 class="mb-4">Outbound Assets</h2>

        <?php include 'modal.php'; ?>

        <div class="d-flex justify-content-end mb-3">
            <a href="create-outbound.php" class="btn btn-dark add-btn">
                <i class="bi bi-plus-circle me-2"></i> Add Request
            </a>
        </div>

        <a href="?Status=Pending" class="btn btn-outline-primary">
            <i class="bi bi-hourglass-split"></i> Pending
        </a>

        <a href="?Status=Approved" class="btn btn-outline-success">
            <i class="bi bi-check-circle"></i> Approved
        </a>

        <a href="?Status=Rejected" class="btn btn-outline-danger">
            <i class="bi bi-x-circle"></i> Rejected
        </a>

        <br><br>

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

                        <?php if ($statusFilter == "Rejected") { ?>
                            <th>Rejected Reason</th>
                        <?php } ?>
                        <th>Status</th>
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

                                <?php if ($statusFilter == "Rejected") { ?>
                                    <td><?= htmlspecialchars($row['ReasonForRejection'] ?? 'Unknown') ?></td>
                                <?php } ?>

                                <td>
                                    <?php
                                    $status = htmlspecialchars($row['Status']);
                                    $badgeClass = 'bg-secondary'; // Default

                                    if ($status === 'Approved') {
                                        $badgeClass = 'bg-success';
                                    } elseif ($status === 'Pending') {
                                        $badgeClass = 'bg-warning text-dark';
                                    } elseif ($status === 'Rejected') {
                                        $badgeClass = 'bg-danger';
                                    }
                                    ?>
                                    <span class="badge <?= $badgeClass ?>"><?= $status ?></span>
                                </td>

                                <td>

                                    <a href="edit-outbound.php?id=<?= $row['PK_OutboundAssets'] ?>&status=<?= hash('sha256', $row['Status']) ?>&type=<?= hash('sha256', 'Outbound') ?>" class="btn btn-sm btn-primary">

                                        <?php if (htmlspecialchars($row['Status']) == "Approved" || htmlspecialchars($row['Status']) == "Rejected" || htmlspecialchars($row['Status']) == "Returned") { ?>
                                            <i class="bi bi-eye" title="View"></i>
                                        <?php } else { ?>
                                            <i class="bi bi-pencil-square" title="Edit"></i>
                                        <?php } ?>
                                    </a>

                                    <?php if (htmlspecialchars($row['Status']) == "Approved") { ?>

                                        <!--
                                        <button class="btn btn-sm btn-success" onclick="generateGatePass(<?= $row['PK_OutboundAssets'] ?>)" title="Generate Gate Pass">
                                            <i class="bi bi-file-earmark-plus"></i>
                                        </button>
                                    -->
                                        <button class="btn btn-sm btn-success" onclick="showGatePassModal(<?= $row['PK_OutboundAssets'] ?>)" title="Generate Gate Pass">
                                            <i class="bi bi-file-earmark-plus"></i>
                                        </button>

                                    <?php } ?>

                                    <?php if ($_SESSION['role'] == 'Supervisor' || $_SESSION['role'] == 'Manager' || $_SESSION['role'] == 'Admin') { ?>

                                        <?php if (htmlspecialchars($row['Status']) != "Approved" && htmlspecialchars($row['Status']) != "Rejected" && htmlspecialchars($row['Status']) != "Returned") { ?>
                                            <button class="btn btn-sm btn-outline-success" onclick="showApproveModal(<?= $row['PK_OutboundAssets'] ?>)"
                                                title="Approve Request">
                                                <i class="bi bi-check-circle"></i>
                                            </button>
                                        <?php } ?>
                                    <?php } ?>


                                    <?php if ($_SESSION['role'] == 'Supervisor' || $_SESSION['role'] == 'Manager' || $_SESSION['role'] == 'Admin') { ?>

                                        <?php if (htmlspecialchars($row['Status']) != "Approved" && htmlspecialchars($row['Status']) != "Rejected" && htmlspecialchars($row['Status']) != "Returned") { ?>
                                            <button class="btn btn-sm btn-outline-danger" onclick="showDisapproveModal(<?= $row['PK_OutboundAssets'] ?>)"
                                                title="Disapprove Request">
                                                <i class="bi bi-x-circle"></i>
                                            </button>

                                        <?php } ?>
                                    <?php } ?>

                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>



    <!-- Gate Pass Options Modal -->
    <div class="modal fade" id="gatePassModal" tabindex="-1" aria-labelledby="gatePassModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header modal-header-custom border-0">
                    <h5 class="modal-title h5 fw-semibold" id="gatePassModalLabel">
                        <i class="bi bi-journal-arrow-up me-2"></i>Select Gate Pass Type
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body modal-body-custom">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="gate-pass-option-card h-100" onclick="generateRegularGatePassFromModal()">
                                <div class="icon text-primary"><i class="bi bi-pc-display"></i></div>
                                <h6>IT Assets Gate Pass</h6>
                                <p>Generates a gate pass that includes computers, peripherals, and other IT equipment.</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="gate-pass-option-card h-100" onclick="generateGeneralAssetGatePassFromModal()">
                                <div class="icon text-warning"><i class="bi bi-box-seam"></i></div>
                                <h6>General Assets Gate Pass</h6>
                                <p>For all other non-IT assets, such as furniture, fixtures, and general equipment.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Approval Loading Modal -->
    <div class="modal fade" id="approveLoadingModal" tabindex="-1" aria-labelledby="approveLoadingModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body modal-body-custom text-center">
                    <!-- SVG Animation for Approval -->
                    <svg class="approval-svg" viewBox="0 0 100 100">
                        <!-- The circle background -->
                        <circle cx="50" cy="50" r="45" fill="none" stroke="#e5e7eb" stroke-width="5" />
                        <!-- The drawing circle -->
                        <circle class="approval-circle" cx="50" cy="50" r="45" fill="none" stroke-width="5" stroke-linecap="round" transform="rotate(-90 50 50)" />
                        <!-- The checkmark path -->
                        <path class="approval-checkmark" d="M30 52 l15 15 l30 -30" fill="none" stroke-width="6" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>

                    <h5 class="h5 fw-semibold mt-4">Processing Approval</h5>
                    <p class="text-secondary mt-2">Please wait while we process the request...</p>
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

    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <?php include "tools/alert-message.php"; ?>

    <script>
        // Get the modal element
        const approveModalElement = document.getElementById('approveLoadingModal');
        // Create a Bootstrap Modal instance
        const approveModal = new bootstrap.Modal(approveModalElement);

        const showApproveModalBtn = document.getElementById('showApproveModalBtn');

        showApproveModalBtn.addEventListener('click', () => {
            const approveModals = bootstrap.Modal.getInstance(document.getElementById('approveAssetModal'));
            if (approveModals) {
                approveModals.hide();
            }
            approveModal.show();
        });
    </script>

    <script>
        let selectedAssetId = null;

        function showGatePassModal(assetId) {
            selectedAssetId = assetId;

            const modal = new bootstrap.Modal(document.getElementById('gatePassModal'));
            modal.show();
        }

        function generateRegularGatePassFromModal() {
            if (selectedAssetId !== null) {
                generateRegularGatePass(selectedAssetId);
                bootstrap.Modal.getInstance(document.getElementById('gatePassModal')).hide();
            }
        }

        function generateGeneralAssetGatePassFromModal() {
            if (selectedAssetId !== null) {
                generateGeneralAssetGatePass(selectedAssetId);
                bootstrap.Modal.getInstance(document.getElementById('gatePassModal')).hide();
            }
        }

        // Existing functions can stay the same
        function generateRegularGatePass(assetId) {
            // Your redirect or AJAX logic
            generateGatePass(assetId);
        }

        function generateGeneralAssetGatePass(assetId) {
            // Your redirect or AJAX logic
            generateNonITGatePass(assetId);
        }
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
                                return idx !== 6; // Exclude column indexes 6 and 7
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
                                return idx !== 6;
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
                                return idx !== 6;
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
        async function generateNonITGatePass(id) {
            // Fetch PHP data from the same generate.php file
            const response = await fetch(`generate-nonit.php?id=${id}`);
            const result = await response.json(); // ✅ proper JSON parsing

            const exampleAssets = result.data; // ✅ asset list
            const formDetails = {
                date: result.date,
                name: result.name,
                department: result.department,
                contact: result.contact,
                purpose: result.purpose
            };

            await generateGatePassPDF(exampleAssets, formDetails);
        }

        async function generateGatePass(id) {
            // Fetch PHP data from the same generate.php file
            const response = await fetch(`generate.php?id=${id}`);
            const result = await response.json(); // ✅ proper JSON parsing

            const exampleAssets = result.data; // ✅ asset list
            const formDetails = {
                date: result.date,
                name: result.name,
                department: result.department,
                contact: result.contact,
                purpose: result.purpose
            };

            await generateGatePassPDF(exampleAssets, formDetails);
        }

        async function generateGatePassPDF(data, details) {

            const gpNo = "";
            const dateInput = details.date;
            const employeeName = details.name;
            const department = details.department;
            const contact = details.contact;
            const passType = "";
            const purpose = details.purpose;
            const itManager = "Rodolfe Christian Espartero";
            const qaManager = "Maria Luisa Camacho";

            const date = dateInput ? new Date(dateInput).toLocaleDateString('en-US') : '';

            // Safely map asset items to required format
            const items = data.map(item => ({
                quantity: String(item.quantity ?? 'N/A'),
                description: String(item.description ?? 'N/A'),
                serial: String(item.serial ?? 'N/A'),
                remarks: '' // You can adjust this if needed
            }));

            // --- 2. SETUP PDF DOCUMENT ---
            const {
                PDFDocument,
                rgb,
                StandardFonts
            } = PDFLib;
            const pdfDoc = await PDFDocument.create();
            let page = pdfDoc.addPage([612, 936]);

            const font = await pdfDoc.embedFont(StandardFonts.Helvetica);
            const boldFont = await pdfDoc.embedFont(StandardFonts.HelveticaBold);
            const baseFontSize = 10;

            const MARGIN_LEFT = 50;
            const MARGIN_TOP = 50;
            const MARGIN_BOTTOM = 50;
            const CONTENT_WIDTH = page.getWidth() - MARGIN_LEFT * 2;
            let y = page.getHeight() - MARGIN_TOP;

            // --- 3. PDF DRAWING HELPERS ---
            const drawText = (text, x, yPos, options = {}) => {
                page.drawText(text, {
                    x,
                    y: yPos,
                    font: options.font || font,
                    size: options.size || baseFontSize,
                    color: options.color || rgb(0, 0, 0),
                    lineHeight: (options.size || baseFontSize) + 4,
                    maxWidth: options.maxWidth
                });
            };
            const drawCenteredText = (text, yPos, options = {}) => {
                const currentFont = options.font || font;
                const currentSize = options.size || baseFontSize;
                const textWidth = currentFont.widthOfTextAtSize(text, currentSize);
                const x = MARGIN_LEFT + (CONTENT_WIDTH - textWidth) / 2;
                drawText(text, x, yPos, options);
            };
            const drawRectangle = (x, yPos, width, height, options = {}) => {
                page.drawRectangle({
                    x,
                    y: yPos,
                    width,
                    height,
                    borderColor: options.borderColor || rgb(0, 0, 0),
                    borderWidth: options.borderWidth || 1,
                    color: options.color,
                });
            };
            const drawLine = (x1, y1, x2, y2, options = {}) => {
                page.drawLine({
                    start: {
                        x: x1,
                        y: y1
                    },
                    end: {
                        x: x2,
                        y: y2
                    },
                    thickness: options.thickness || 1,
                    color: options.color || rgb(0, 0, 0),
                });
            };

            // --- Reusable Drawing Functions ---
            const drawPageHeader = () => {
                y = page.getHeight() - MARGIN_TOP;
                drawCenteredText("GATE PASS", y, {
                    font: boldFont,
                    size: 18
                });
                y -= 20;
                drawCenteredText("(I.T OPERATIONS EQUIPMENT)", y, {
                    size: 11
                });
                y -= 25;

                const checkboxY = y + 2;
                drawRectangle(MARGIN_LEFT + 120, checkboxY, 12, 12);
                drawText("ONE-WAY", MARGIN_LEFT + 140, checkboxY);
                if (passType === 'one-way') {
                    drawText('X', MARGIN_LEFT + 122, checkboxY + 1, {
                        font: boldFont,
                        size: 10
                    });
                }
                drawRectangle(MARGIN_LEFT + 280, checkboxY, 12, 12);
                drawText("RETURNABLE", MARGIN_LEFT + 300, checkboxY);
                if (passType === 'returnable') {
                    drawText('X', MARGIN_LEFT + 282, checkboxY + 1, {
                        font: boldFont,
                        size: 10
                    });
                }
                y -= 30;

                drawText("GP No:", MARGIN_LEFT, y);
                drawLine(MARGIN_LEFT + 50, y - 2, MARGIN_LEFT + 250, y - 2);
                drawText(gpNo, MARGIN_LEFT + 52, y);
                drawText("Date:", MARGIN_LEFT + 300, y);
                drawLine(MARGIN_LEFT + 340, y - 2, CONTENT_WIDTH + MARGIN_LEFT, y - 2);
                drawText(date, MARGIN_LEFT + 342, y);
                y -= 25;

                drawText("Employee Name:", MARGIN_LEFT, y);
                drawLine(MARGIN_LEFT + 95, y - 2, MARGIN_LEFT + 250, y - 2);
                drawText(employeeName, MARGIN_LEFT + 97, y);
                drawText("Department:", MARGIN_LEFT + 300, y);
                drawLine(MARGIN_LEFT + 370, y - 2, CONTENT_WIDTH + MARGIN_LEFT, y - 2);
                drawText(department, MARGIN_LEFT + 372, y);
                y -= 25;

                drawText("Contact #:", MARGIN_LEFT, y);
                drawLine(MARGIN_LEFT + 65, y - 2, MARGIN_LEFT + 250, y - 2);
                drawText(contact, MARGIN_LEFT + 67, y);
                y -= 30;
            };

            const tableColumns = [{
                    x: MARGIN_LEFT,
                    width: 70,
                    title: 'QUANTITY'
                },
                {
                    x: MARGIN_LEFT + 70,
                    width: 180,
                    title: 'DESCRIPTION'
                },
                {
                    x: MARGIN_LEFT + 250,
                    width: 130,
                    title: 'SERIAL #'
                },
                {
                    x: MARGIN_LEFT + 380,
                    width: 132,
                    title: 'REMARKS'
                }
            ];
            const drawTableHeader = () => {
                drawRectangle(MARGIN_LEFT, y - 20, CONTENT_WIDTH, 20, {
                    color: rgb(0.9, 0.9, 0.9)
                });
                tableColumns.forEach(col => {
                    drawText(col.title, col.x + 5, y - 14, {
                        font: boldFont
                    });
                });
                y -= 20;
                return y + 20; // Return the top Y of the table
            };

            const drawFooter = () => {
                const FOOTER_HEIGHT = 450;
                if (y < MARGIN_BOTTOM + FOOTER_HEIGHT) {
                    page = pdfDoc.addPage([612, 936]);
                    drawPageHeader();
                }

                y -= 10;
                const purposeHeight = 60;
                drawRectangle(MARGIN_LEFT, y - purposeHeight, CONTENT_WIDTH, purposeHeight);
                drawText("PURPOSE:", MARGIN_LEFT + 5, y - 15, {
                    font: boldFont
                });
                drawText(purpose, MARGIN_LEFT + 5, y - 30, {
                    maxWidth: CONTENT_WIDTH - 10
                });
                y -= (purposeHeight + 10);

                drawText("Note: (Indicate Accountability / Attach copy of MR)", MARGIN_LEFT, y);
                y -= 30;

                drawText("Requested By:", MARGIN_LEFT, y, {
                    font: boldFont
                });
                y -= 40;
                const signatureLineWidth = 250;
                drawLine(MARGIN_LEFT, y, MARGIN_LEFT + signatureLineWidth, y);
                if (employeeName) {
                    const employeeNameWidth = font.widthOfTextAtSize(employeeName, baseFontSize);
                    const centeredEmployeeNameX = MARGIN_LEFT + (signatureLineWidth - employeeNameWidth) / 2;
                    drawText(employeeName, centeredEmployeeNameX, y + 5);
                }
                const signatureText = "Signature Over Printed Name of Employee";
                const signatureTextWidth = font.widthOfTextAtSize(signatureText, baseFontSize);
                const centeredSignatureTextX = MARGIN_LEFT + (signatureLineWidth - signatureTextWidth) / 2;
                drawText(signatureText, centeredSignatureTextX, y - 12);
                y -= 40;

                drawText("RECOMMENDING APPROVAL:", MARGIN_LEFT, y, {
                    font: boldFont
                });
                y -= 20;
                drawText("I.T Department", MARGIN_LEFT, y);
                y -= 40;

                const managerY = y;
                const managerLineWidth = 200;
                drawLine(MARGIN_LEFT, managerY, MARGIN_LEFT + managerLineWidth, managerY);
                if (itManager) {
                    const itManagerWidth = font.widthOfTextAtSize(itManager, baseFontSize);
                    const centeredItManagerX = MARGIN_LEFT + (managerLineWidth - itManagerWidth) / 2;
                    drawText(itManager, centeredItManagerX, managerY + 5);
                }
                drawText("IT MANAGER", MARGIN_LEFT + 60, managerY - 12);
                const qaManagerX = MARGIN_LEFT + 312;
                drawLine(qaManagerX, managerY, qaManagerX + managerLineWidth, managerY);
                if (qaManager) {
                    const qaManagerWidth = font.widthOfTextAtSize(qaManager, baseFontSize);
                    const centeredQaManagerX = qaManagerX + (managerLineWidth - qaManagerWidth) / 2;
                    drawText(qaManager, centeredQaManagerX, managerY + 5);
                }
                drawText("QA MANAGER", MARGIN_LEFT + 362, managerY - 12);
                y -= 40;

                const notesHeight = 50;
                drawRectangle(MARGIN_LEFT, y - notesHeight, CONTENT_WIDTH, notesHeight);
                drawText("Notes/Comments:", MARGIN_LEFT + 5, y - 15);
                y -= (notesHeight + 20);

                drawText("FINAL APPROVAL:", MARGIN_LEFT, y, {
                    font: boldFont
                });
                y -= 35;
                const finalApprovalY = y;
                drawLine(MARGIN_LEFT, finalApprovalY, MARGIN_LEFT + 200, finalApprovalY);
                drawText("Paul Abenes", MARGIN_LEFT + 60, finalApprovalY - 12);
                drawText("AND/OR", MARGIN_LEFT + 240, finalApprovalY);
                drawLine(MARGIN_LEFT + 312, finalApprovalY, CONTENT_WIDTH + MARGIN_LEFT, finalApprovalY);
                drawText("CSM", MARGIN_LEFT + 392, finalApprovalY - 12);
                y -= 40;

                const clearanceHeight = 150;
                const clearanceTopY = y;
                drawRectangle(MARGIN_LEFT, clearanceTopY - clearanceHeight, CONTENT_WIDTH, clearanceHeight);
                drawText("CLEARANCE (To be filled up by Guard on Duty)", MARGIN_LEFT + 5, clearanceTopY - 15, {
                    font: boldFont
                });
                let innerY = clearanceTopY - 35;
                drawText("Date out:", MARGIN_LEFT + 10, innerY);
                drawLine(MARGIN_LEFT + 65, innerY - 2, MARGIN_LEFT + 200, innerY - 2);
                drawText("Time out:", MARGIN_LEFT + 250, innerY);
                drawLine(MARGIN_LEFT + 305, innerY - 2, MARGIN_LEFT + 440, innerY - 2);
                innerY -= 25;
                drawText("Guard on Duty (OUT):", MARGIN_LEFT + 10, innerY);
                drawLine(MARGIN_LEFT + 130, innerY - 2, CONTENT_WIDTH + MARGIN_LEFT - 10, innerY - 2);
                drawText("Signature over Printed Name", MARGIN_LEFT + 250, innerY - 12);
                innerY -= 25;
                drawLine(MARGIN_LEFT, innerY, CONTENT_WIDTH + MARGIN_LEFT, innerY, {
                    thickness: 0.5
                });
                innerY -= 20;
                drawText("Date in:", MARGIN_LEFT + 10, innerY);
                drawLine(MARGIN_LEFT + 60, innerY - 2, MARGIN_LEFT + 200, innerY - 2);
                drawText("Time in:", MARGIN_LEFT + 250, innerY);
                drawLine(MARGIN_LEFT + 300, innerY - 2, MARGIN_LEFT + 440, innerY - 2);
                innerY -= 25;
                drawText("Guard on Duty (IN):", MARGIN_LEFT + 10, innerY);
                drawLine(MARGIN_LEFT + 125, innerY - 2, CONTENT_WIDTH + MARGIN_LEFT - 10, innerY - 2);
                drawText("Signature over Printed Name", MARGIN_LEFT + 250, innerY - 12);
            };

            // --- 4. DRAW PDF CONTENT ---
            drawPageHeader();
            let tableTopY = drawTableHeader();
            const rowHeight = 20;

            for (const item of items) {
                if (y - rowHeight <= MARGIN_BOTTOM) { // Check if new row fits
                    drawRectangle(MARGIN_LEFT, y, CONTENT_WIDTH, tableTopY - y); // Close current table
                    tableColumns.forEach(col => {
                        if (col.x > MARGIN_LEFT) drawLine(col.x, tableTopY, col.x, y);
                    });

                    page = pdfDoc.addPage([612, 936]);
                    drawPageHeader();
                    tableTopY = drawTableHeader();
                }

                drawText(item.quantity || '', tableColumns[0].x + 5, y - 14);
                drawText(item.description || '', tableColumns[1].x + 5, y - 14);
                drawText(item.serial || '', tableColumns[2].x + 5, y - 14);
                drawText(item.remarks || '', tableColumns[3].x + 5, y - 14);
                y -= rowHeight;
            }

            // Finalize the last table
            drawRectangle(MARGIN_LEFT, y, CONTENT_WIDTH, tableTopY - y);
            tableColumns.forEach(col => {
                if (col.x > MARGIN_LEFT) drawLine(col.x, tableTopY, col.x, y);
            });

            drawFooter();

            // --- 5. SAVE AND OPEN PDF ---
            const pdfBytes = await pdfDoc.save();
            const blob = new Blob([pdfBytes], {
                type: "application/pdf"
            });
            const url = URL.createObjectURL(blob);
            window.open(url, '_blank');
        }
    </script>
</body>

<?php $conn->close(); ?>

</html>