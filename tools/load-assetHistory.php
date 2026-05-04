<?php
require '../config.php';

$assetId = intval($_GET['id']);

$sql = "SELECT ha.DateAcquired, ha.Conditions, e.Name AS EmployeeName, ha.Status
        FROM HistoryAsset ha
        LEFT JOIN Employees e ON ha.FK_Employees = e.PK_Employees
        WHERE ha.FK_AssetMaster = ?
        ORDER BY ha.PK_HistoryAsset DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $assetId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0): ?>
    <table class="table table-bordered table-sm">
        <thead>
            <tr>
                <th>Date Acquired</th>
                <th>Condition</th>
                <th>Employee</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['DateAcquired']) ?></td>
                    <td><?= htmlspecialchars($row['Conditions']) ?></td>
                    <td><?= htmlspecialchars($row['EmployeeName']) ?></td>
                    <td>
                        <?php if ($row['Status'] === 'Assigned'): ?>
                            <span class="badge bg-success">Assigned</span>
                        <?php elseif ($row['Status'] === 'Unassigned'): ?>
                            <span class="badge bg-secondary">Unassigned</span>
                        <?php else: ?>
                            <span class="badge bg-light text-dark"><?= htmlspecialchars($row['Status']) ?></span>
                        <?php endif; ?>
                    </td>

                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
<?php else: ?>
    <p class="text-muted">No history found for this asset.</p>
<?php endif;


$sql = "select 
	ax.*,
    bx.AssetTagNumber
from assetrepairedhistory ax
left join assetmaster bx
on ax.FK_AssetMaster = bx.PK_AssetMaster
where ax.FK_AssetMaster = ?
order by ax.PK_AssetRepairedHistory desc";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $assetId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0): ?>
    <h5 class="mb-0 fw-semibold">
        <i class="bi bi-tools me-2"></i>Repair History
    </h5>
    <table class="table table-bordered table-sm">
        <thead>
            <tr>
                <th>Asset Tag Number</th>
                <th>Cost</th>
                <th>Date Sent for Repair</th>
                <th>Repaired Date</th>
                <th>Repair Duration</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['AssetTagNumber']) ?></td>
                    <td><?= htmlspecialchars($row['Cost']) ?></td>
                    <td><?= htmlspecialchars($row['RepairDate']) ?></td>
                    <td><?= htmlspecialchars($row['RepairedDate']) ?></td>

                    <?php
                    $repairDate = new DateTime($row['RepairDate']);
                    $repairedDate = new DateTime($row['RepairedDate']);

                    $interval = $repairDate->diff($repairedDate);
                    $daysRepaired = $interval->days;
                    ?>
                    <td><?= isset($row['RepairedDate']) ? $daysRepaired . ' day(s)' : 'N/A' ?></td>

                    <td>
                        <?php if (!empty($row['ServiceOrderImage'])): ?>
                            <?php
                            $filePath = $row['ServiceOrderImage'];
                            $fileExt = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

                            $isImage = in_array($fileExt, ['jpg', 'jpeg', 'png', 'gif', 'bmp']);

                            // Determine icon class
                            switch ($fileExt) {
                                case 'jpg':
                                case 'jpeg':
                                case 'png':
                                case 'gif':
                                case 'bmp':
                                    $iconClass = 'bi-image';
                                    break;
                                case 'pdf':
                                    $iconClass = 'bi-file-earmark-pdf';
                                    break;
                                case 'doc':
                                case 'docx':
                                    $iconClass = 'bi-file-earmark-word';
                                    break;
                                case 'xls':
                                case 'xlsx':
                                case 'csv':
                                    $iconClass = 'bi-file-earmark-excel';
                                    break;
                                default:
                                    $iconClass = 'bi-file-earmark';
                                    break;
                            }
                            ?>

                            <?php if ($isImage): ?>
                                <!-- Image: View Button -->
                                <button type="button"
                                    class="btn btn-sm btn-primary view-image-btn"
                                    title="View Image"
                                    data-image="<?= htmlspecialchars($filePath) ?>">
                                    <i class="bi <?= $iconClass ?>"></i>
                                </button>
                            <?php else: ?>
                                <!-- Other files: Download Link -->
                                <a href="<?= htmlspecialchars($filePath) ?>"
                                    class="btn btn-sm btn-primary"
                                    title="Download File"
                                    download>
                                    <i class="bi <?= $iconClass ?>"></i>
                                </a>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-muted">No file</span>
                        <?php endif; ?>
                    </td>


                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
<?php else: ?>
    <p class="text-muted">No history repaired for this asset.</p>
<?php endif;
$stmt->close();
$conn->close();
?>