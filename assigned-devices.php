<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

include "config.php";

$createdBy = $_SESSION['user_id'];

if (!isset($_GET['employee'])) {
    echo "Employee ID not provided.";
    exit;
}

if (!isset($_GET['name'])) {
    echo "Employee ID not provided.";
    exit;
}

$employeeId = intval($_GET['employee']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unassign'])) {
    $assetId = intval($_POST['FK_AssetMaster']);

    // Begin transaction for safety
    $conn->begin_transaction();

    try {
        // Delete from AssetInventory where FK_AssetMaster = assetId
        $stmtDelete = $conn->prepare("DELETE FROM AssetInventory WHERE FK_AssetMaster = ?");
        $stmtDelete->bind_param("i", $assetId);
        $stmtDelete->execute();

        // Update AssetMaster AssignedTo to 0 (unassigned)
        $stmtUpdate = $conn->prepare("UPDATE AssetMaster SET AssignedTo = 0 WHERE PK_AssetMaster = ?");
        $stmtUpdate->bind_param("i", $assetId);
        $stmtUpdate->execute();

        $conn->commit();

        // Redirect back with success message (adjust URL as needed)
        header("Location: assigned-devices.php?status=unassigned&employee=$employeeId&name=" . urlencode($_GET['name']));
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        // Handle error - maybe redirect with error message
        header("Location: assets.php?status=error&message=" . urlencode($e->getMessage()));
        exit;
    }
}



$stmt = $conn->prepare("
  Select 
	cx.PK_AssetMaster,
    cx.AssetTagNumber,
    cx.SerialNumber,
    cx.Model,
    dx.AssetTypeName,
    bx.ApprovalType,
    ax.PK_AssetInventory,
    ax.DateAcquired,
    Date(bx.CreatedOn) as CreatedOn
from assetinventory ax
left join assignapprovals bx
on ax.PK_AssetInventory = bx.FK_AssetInventory
left join assetmaster cx
on ax.FK_AssetMaster = cx.PK_AssetMaster
left join assettype dx
on cx.FK_AssetType = dx.PK_AssetType
where ax.AssignedTo = ? and bx.IsApproved = 1
order by ax.PK_AssetInventory, bx.PK_Approvals desc
");
$stmt->bind_param("i", $employeeId);
$stmt->execute();
$resultDevices = $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assigned Devices</title>
    <link rel="icon" href="inventory.png" type="image/x-icon">
    <?php include "tools/head-plugin.php" ?>


</head>

<body>
    <?php include 'header.php'; ?>
    <div class="container">
        <h2 class="mb-4">Assigned devices to <b><?php echo $_GET['name'] ?></b></h2>

        <div class="d-flex justify-content-start mb-3">
            <a href="employees.php" class="btn btn-dark">
                <i class="bi bi-arrow-left me-1"></i>Back
            </a>


        </div>

        <div class="d-flex justify-content-end mb-3">
            <a class="btn btn-primary" id="generateMrBtn">
                <i class="bi bi-file-earmark-plus me-1"></i>Generate MR
            </a>
        </div>
        <br>

        <?php include 'modal.php'; ?>
        <?php include "tools/alert-message.php"; ?>

        <table class="table table-bordered table-hover align-middle">
            <thead>
                <tr>
                    <th>Asset Name</th>
                    <th>Serial Number</th>
                    <th>Model</th>
                    <th>Asset Type</th>
                    <th>Date</th>
                    <th>Actions</th>

                </tr>
            </thead>
            <tbody>
                <?php while ($row = $resultDevices->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['AssetTagNumber']) ?></td>
                        <td><?= htmlspecialchars($row['SerialNumber']) ?></td>
                        <td><?= htmlspecialchars($row['Model']) ?></td>
                        <td><?= htmlspecialchars($row['AssetTypeName']) ?></td>


                        <?php if ($row['ApprovalType'] === 'Assigned') { ?>
                            <td><?= htmlspecialchars($row['DateAcquired']) ?></td>
                        <?php } else { ?>
                            <td><?= htmlspecialchars($row['CreatedOn']) ?></td>
                        <?php } ?>

                        <td>
                            <?php if ($row['ApprovalType'] === 'Assigned') { ?>

                                <a href="#"
                                    class="btn btn-sm btn-primary me-1 btn-unassign-assetView"
                                    data-id="<?= $row['PK_AssetMaster'] ?>"
                                    data-invID="<?= $row['PK_AssetInventory'] ?>"
                                    data-bs-toggle="modal"
                                    data-bs-target="#unassignAssetModalView"
                                    data-asset='<?= json_encode($row) ?>'
                                    title="Unassign">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <?php
                                $status = htmlspecialchars($row['ApprovalType']);
                                $badgeClass = ($status === 'Assigned') ? 'bg-success' : 'bg-secondary';
                                ?>

                                Status:
                                <span class="badge <?= $badgeClass ?>">
                                    <?= $status ?>
                                </span>


                            <?php } else { ?>

                                <a href="#"
                                    class="btn btn-sm btn-primary me-1 btn-unassign-assetView"
                                    data-id="<?= $row['PK_AssetMaster'] ?>"
                                    data-invID="<?= $row['PK_AssetInventory'] ?>"
                                    data-bs-toggle="modal"
                                    data-bs-target="#unassignAssetModalView"
                                    data-asset='<?= json_encode($row) ?>'
                                    title="Unassign">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <?php
                                $status = htmlspecialchars($row['ApprovalType']);
                                $badgeClass = ($status === 'Assigned') ? 'bg-success' : 'bg-secondary';
                                ?>

                                Status:
                                <span class="badge <?= $badgeClass ?>">
                                    <?= $status ?>
                                </span>


                            <?php } ?>
                        </td>

                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
    <script src="script.js"></script>

    <script>
        document.querySelectorAll('.btn-unassign-assetView').forEach(btn => {
            btn.addEventListener('click', () => {
                const assetId = btn.getAttribute('data-id');
                const invId = btn.getAttribute('data-invID');

                // Set asset ID in modal hidden input
                document.getElementById('unassignAssetIdView').value = assetId;

                // Clear old data while fetching
                document.getElementById('unassignToInputView').value = '';
                document.getElementById('unassignToIdView').value = '';
                document.getElementById('unassignLocationView').value = '';
                document.getElementById('unassignDateAcquiredView').value = '';
                document.getElementById('unassignConditionView').value = '';
                document.getElementById('unassignRemarksView').value = '';

                const imgPreview = document.getElementById('unassignImagePreviewView');

                fetch('tools/load-assigned.php?id=' + encodeURIComponent(assetId) + '&employee=' + encodeURIComponent(<?= $employeeId ?>) + '&invId=' + encodeURIComponent(invId))

                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const asset = data.asset;

                            document.getElementById('unassignToInputView').value = asset.AssignedToName || '';
                            document.getElementById('unassignToIdView').value = asset.AssignedTo || 0;
                            document.getElementById('unassignLocationView').value = asset.Location || '';
                            document.getElementById('unassignDateAcquiredView').value = asset.DateAcquired || '';
                            document.getElementById('unassignConditionView').value = asset.Conditions || '';
                            document.getElementById('unassignRemarksView').value = asset.Remarks || '';

                            if (asset.Image) {
                                imgPreview.src = 'image/assignimages/' + asset.Image;
                                imgPreview.style.display = 'block';
                            } else {
                                imgPreview.style.display = 'none';
                                imgPreview.src = '#';
                            }
                        } else {
                            alert('Error: ' + data.message);
                        }
                    })
                    .catch(err => {
                        console.error('Fetch error:', err);
                        alert('Failed to fetch asset inventory data.');
                    });
            });
        });
    </script>

    <script>
        let tableData = [];

        document.getElementById('generateMrBtn').addEventListener('click', async function() {
            try {
                const employeeId = <?php echo json_encode($employeeId); ?>;
                const response = await fetch(`tools/getDataMR.php?id=${employeeId}`);
                const data = await response.json();
                tableData = data.tableData;
                generateMR();

                // Optional: trigger rendering or processing of the data
                // renderTable(tableData);
            } catch (error) {
                console.error('Error fetching table data:', error);
            }
        });
    </script>


    <script>
        function generateMR() {
            const employeeName = <?php echo json_encode($_GET['name']) ?>;
            const {
                jsPDF
            } = window.jspdf;

            const doc = new jsPDF({
                orientation: "portrait",
                unit: "in",
                format: [8.5, 11] // Short bond paper size
            });

            // Header
            doc.setFont("helvetica", "bold");
            doc.setFontSize(14);
            doc.text("PROPERTY ACKNOWLEDGEMENT RECEIPT", 4.25, 1, {
                align: "center"
            });

            doc.setFontSize(11);
            doc.setFont("helvetica", "normal");
            doc.text(
                "ICARUSFASTONE TELECOMMUNICATION EQUIPMENT & ACCESSORIES TRADING, OPC",
                4.25,
                1.4, {
                    align: "center"
                }
            );

            // Box starts just below the last header line (say 1.6 in) to bottom margin (e.g. 10.5 in)
            const startY = 1.6;
            const marginX = 0.5;
            const boxWidth = 7.5;
            const boxHeight = 11 - startY - 0.5; // End at 10.5 in for bottom margin

            doc.setDrawColor(0);
            doc.setLineWidth(0.02);
            doc.rect(marginX, startY, boxWidth, boxHeight);

            doc.text(
                "Accountable Person:   ______________________________           PAR No.:   _______________",
                0.6, // x position with left margin
                1.9 // y position a bit below the top edge of the box (1.6)
            );

            doc.setFont("helvetica", "bold"); // Set font to Helvetica italic
            doc.text(employeeName, 2.3, 1.9);



            doc.setFont("helvetica", "italic"); // Set font to Helvetica italic

            doc.setFontSize(9); // Optional: reduce font size slightly for paragraph
            doc.text(employeeName, 1.2, 2.2); // Adjust x and y to match underline position
            doc.text([
                "       I. __________________________________ hereby acknowledge and agree to be responsible for all Company Property,",
                "including but not limited to, equipment, tools, vehicles, devices, & other materials provided by the Company for use in the",
                "performance of my duties. I hereby agree to take reasonable care in safeguarding and maintaining such property, and to",
                "immediately report any loss, theft, damage, or malfunction of the property to the Company.",
                "",
                "       In the event of damage to, loss of, or failure to return any Company property upon the termination of employment,",
                "I may be held financially for repair or replacement costs, as may be determined by the Company provided the damage or",
                "loss results from negligence, misuse, or failure to comply with Company policies regarding the care and use of Company property.",
                "",
                "                                                                                                                _______________________________________"
            ], 0.7, 2.2); // x = 0.6, y = 2.1 (right below the Accountable Person line)

            // Table header position
            const tableStartY = 3.8;
            const headerHeight = 0.4; // height of the header row

            doc.setFontSize(10);
            doc.setFont("helvetica", "bold");

            // Draw header boxes and add text
            doc.rect(0.5, tableStartY, 0.6, headerHeight); // Item #
            doc.text("Item #", 0.6, tableStartY + 0.25);

            doc.rect(1.1, tableStartY, 0.4, headerHeight); // Qty
            doc.text("Qty", 1.15, tableStartY + 0.25);

            doc.rect(1.5, tableStartY, 0.6, headerHeight); // UOM
            doc.text("UOM", 1.6, tableStartY + 0.25);

            doc.rect(2.1, tableStartY, 1.1, headerHeight); // Description
            doc.text("Description", 2.3, tableStartY + 0.25);

            doc.rect(3.2, tableStartY, 1.0, headerHeight); // Brand
            doc.text("Brand", 3.5, tableStartY + 0.25);

            doc.rect(4.2, tableStartY, 1.0, headerHeight); // Serial No.
            doc.text("Serial No.", 4.4, tableStartY + 0.25);

            doc.rect(5.2, tableStartY, 1.0, headerHeight); // Unit Cost
            doc.text("Unit Cost", 5.4, tableStartY + 0.25);

            doc.rect(6.2, tableStartY, 1.0, headerHeight); // Total Cost
            doc.text("Total Cost", 6.4, tableStartY + 0.25);

            doc.rect(7.2, tableStartY, 0.8, headerHeight); // Date Acquired
            doc.text("Date", 7.3, tableStartY + 0.18);
            doc.text("Acquired", 7.3, tableStartY + 0.33);


            //Data

            doc.setFont("helvetica", "normal");
            let currentY = tableStartY + headerHeight; // Start below the header
            const rowHeight = 0.4;

            tableData.forEach(row => {
                // Draw boxes (same widths as header)
                doc.rect(0.5, currentY, 0.6, rowHeight); // Item #
                doc.rect(1.1, currentY, 0.4, rowHeight); // Qty
                doc.rect(1.5, currentY, 0.6, rowHeight); // UOM
                doc.rect(2.1, currentY, 1.1, rowHeight); // Description
                doc.rect(3.2, currentY, 1.0, rowHeight); // Brand
                doc.rect(4.2, currentY, 1.0, rowHeight); // Serial No.
                doc.rect(5.2, currentY, 1.0, rowHeight); // Unit Cost
                doc.rect(6.2, currentY, 1.0, rowHeight); // Total Cost
                doc.rect(7.2, currentY, 0.8, rowHeight); // Date Acquired

                // Add text inside each cell
                doc.text(String(row.item), 0.52, currentY + 0.25);
                doc.text(String(row.qty), 1.12, currentY + 0.25);
                doc.text(String(row.uom), 1.52, currentY + 0.25);
                doc.text(String(row.description), 2.12, currentY + 0.25);
                doc.text(String(row.brand), 3.22, currentY + 0.25);
                doc.text(String(row.serial), 4.22, currentY + 0.25);
                doc.text(String(row.unitCost), 5.22, currentY + 0.25);
                doc.text(String(row.totalCost), 6.22, currentY + 0.25);
                doc.text(String(row.dateAcquired), 7.22, currentY + 0.25);

                currentY += rowHeight; // Move to next row
            });


            // Start position below the last table row
            const boxY = 8.3;
            const boxHeight1 = 2.2;
            const boxWidth1 = 3.75;

            doc.setFontSize(10);
            doc.setFont("helvetica", "bold");

            // Draw outer box
            doc.rect(0.5, boxY, boxWidth1, boxHeight1);

            // Text content inside the box
            doc.text("Received By:", 0.7, boxY + 0.4);

            doc.setFont("helvetica", "normal");
            // Signature line
            doc.line(0.7, boxY + 1.1, 3.5, boxY + 1.1); // Signature line
            doc.text("Signature over Printed Name", 1.2, boxY + 1.3);
            doc.text("Recipient/User", 1.6, boxY + 1.5);

            // Date line
            doc.line(1.3, boxY + 2.0, 3.0, boxY + 2.0); // Date line
            doc.text("(Date)", 1.9, boxY + 2.15);



            // Issued By Box (next to Received By)
            const boxX2 = 0.4 + boxWidth1 + 0.1; // Small space between the boxes
            const boxWidth2 = 3.75; // Same width
            const boxHeight2 = 2.2; // Same height

            // Draw outer box
            doc.rect(boxX2, boxY, boxWidth2, boxHeight2);

            // Text content inside the box
            doc.setFont("helvetica", "bold");
            doc.text("Issued By:", boxX2 + 0.2, boxY + 0.4);

            // Signature line
            doc.setFont("helvetica", "normal");
            doc.line(boxX2 + 0.5, boxY + 1.1, boxX2 + boxWidth2 - 0.3, boxY + 1.1); // Signature line
            doc.text("Signature over Printed Name", boxX2 + 1.1, boxY + 1.3);

            // Position line
            doc.text("Position", boxX2 + 1.7, boxY + 1.7);
            doc.line(boxX2 + 0.5, boxY + 1.5, boxX2 + boxWidth2 - 0.3, boxY + 1.5); // Position line

            // Date line
            doc.text("(Date)", boxX2 + 1.7, boxY + 2.15);
            doc.line(boxX2 + 0.9, boxY + 2.0, boxX2 + 2.8, boxY + 2.0); // Date line


            // Open in new tab as Blob
            const blob = doc.output("blob");
            const url = URL.createObjectURL(blob);
            window.open(url, "_blank");
        }
    </script>


</body>

<?php
$conn->close();
?>

</html>