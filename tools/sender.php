<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

$htmlBody = "";
$subject = "";

function AssignApproval($PK_Approvals, $conn)
{

    $sql = "
            SELECT 
                ax.PK_Approvals,
                ax.FK_AssetMaster,
                bx.AssetTagNumber,
                cx.Name AS EmployeeName,
                ax.CreatedOn,
                ax.ApprovalType,
                ax.IsApproved,
                ax.FK_AssetInventory,
                ax.Approvers
            FROM assignapprovals ax
            LEFT JOIN assetmaster bx ON ax.FK_AssetMaster = bx.PK_AssetMaster
            LEFT JOIN employees cx ON cx.PK_Employees = ax.FK_Employees
            WHERE ax.PK_Approvals = ?
            LIMIT 1
        ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $PK_Approvals);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $approval = $result->fetch_assoc();

        $approvalId = intval($approval['PK_Approvals']);
        $assetTag = htmlspecialchars($approval['AssetTagNumber']);
        $employeeName = htmlspecialchars($approval['EmployeeName']);
        $createdOn = htmlspecialchars($approval['CreatedOn']);
        $approvalType = htmlspecialchars($approval['ApprovalType']);
        $isApproved = $approval['IsApproved'] ? 'Approved' : 'Pending';

        $approvers = json_decode($approval['Approvers'], true);
        $nextApproverId = null;

        foreach ($approvers as $step) {
            if ($step['status'] == 0) {
                $nextApproverId = $step['approver_id'];
                break; // Stop at the first pending step
            }
        }

        if ($nextApproverId === null) {
            return; // Or handle it however you need (e.g., log, exit(), throw, etc.)
        }

        $approverName = null;
        $toEmail = null;
        $sqlApprover = "SELECT Name, Email FROM Employees WHERE PK_Employees = ?";
        $stmt = $conn->prepare($sqlApprover);
        $stmt->bind_param("i", $nextApproverId);
        $stmt->execute();
        $stmt->bind_result($approverName, $toEmail);
        $stmt->fetch();
        $stmt->close();

        $htmlBody = "
                <div style='font-family: Arial, sans-serif; color: #333; max-width: 600px; margin: auto; border: 1px solid #e0e0e0; padding: 20px; border-radius: 8px; background-color: #f9f9f9;'>
                    <h2 style='color: #0056b3; border-bottom: 2px solid #0056b3; padding-bottom: 5px;'>Approval Request Details</h2>
                    
                    <table width='100%' cellpadding='10' cellspacing='0' style='border-collapse: collapse; font-size: 14px;'>
                        <tr>
                            <td style='width: 40%; font-weight: bold; text-align: right; background-color: #f0f4f8;'>Approval ID:</td>
                            <td style='background-color: #ffffff;'>{$approvalId}</td>
                        </tr>
                        <tr>
                            <td style='font-weight: bold; text-align: right; background-color: #f0f4f8;'>Asset Tag:</td>
                            <td style='background-color: #ffffff;'>{$assetTag}</td>
                        </tr>
                        <tr>
                            <td style='font-weight: bold; text-align: right; background-color: #f0f4f8;'>Employee:</td>
                            <td style='background-color: #ffffff;'>{$employeeName}</td>
                        </tr>
                        <tr>
                            <td style='font-weight: bold; text-align: right; background-color: #f0f4f8;'>Date Requested:</td>
                            <td style='background-color: #ffffff;'>{$createdOn}</td>
                        </tr>
                        <tr>
                            <td style='font-weight: bold; text-align: right; background-color: #f0f4f8;'>Type:</td>
                            <td style='background-color: #ffffff;'>{$approvalType}</td>
                        </tr>
                        <tr>
                            <td style='font-weight: bold; text-align: right; background-color: #f0f4f8;'>Status:</td>
                            <td style='background-color: #ffffff;'>{$isApproved}</td>
                        </tr>
                    </table>

                    <p style='margin-top: 20px;'><strong>Action Required:</strong> Please log in to the system to review and approve this request.</p>

                    <p style='text-align: center; margin-top: 30px;'>
                        <a href='https://ipx.16mb.com/login.php' style='background-color: #0056b3; color: #ffffff; text-decoration: none; padding: 10px 20px; border-radius: 5px; font-weight: bold; display: inline-block;'>Go to Approval Page</a>
                    </p>

                    <p style='font-size: 12px; color: #777; text-align: center; margin-top: 30px;'>This is an automated message. Please do not reply.</p>
                </div>
            ";

        $telegramBody = "✅ *New Approval Request*\n\n"
            . "```"
            . "\nApproval ID       : $approvalId"
            . "\nApprover          : $approverName "
            . "\nAsset Tag         : $assetTag"
            . "\nEmployee          : $employeeName"
            . "\nDate Requested    : $createdOn"
            . "\nType              : $approvalType"
            . "\nStatus            : $isApproved"
            . "```\n"
            . "🔗 *Action Required:*\n"
            . "Please log in to review this request.\n"
            . "🔒 [Go to Approval Page](https://ipx.16mb.com/login.php)";

        $subject = "Approval Request #{$approvalId} - {$assetTag} for {$employeeName}.";
        echo $employeeName;

        if (!empty($htmlBody) && !empty($subject)) {
            $sendResult = sendCustomEmail($htmlBody, $toEmail, $subject, $telegramBody);

            if ($sendResult === true) {
                echo "✅ Email sent successfully.";
            } else {
                echo $sendResult;
            }
        }
    } else {
        $htmlBody = "<p>❌ No approval record found with ID {$PK_Approvals}.</p>";
    }
}

function OutboundApproval($outboundId, $conn)
{

    $stmt = $conn->prepare("SELECT * FROM OutboundAssets WHERE PK_OutboundAssets = ?");
    $stmt->bind_param("i", $outboundId);
    $stmt->execute();
    $result = $stmt->get_result();
    $outbound = $result->fetch_assoc();

    $employeeID = $outbound['FK_Users'] ?? '';

    $employeeName = getEmployeeName($conn, $employeeID);
    $departureDate = $outbound['DepartureDate'] ?? '';
    $descriptions = $outbound['Descriptions'] ?? '';
    $dateAcquired = $outbound['DateAcquired'] ?? '';
    $departureDate = $outbound['DepartureDate'] ?? '';
    $expectedReturnDate = $outbound['ExpectedReturnDate'] ?? '';
    $expectedReceiver = $outbound['ExpectedReceiver'] ?? '';

    $approvers = json_decode($outbound['Approvers'], true);
    $nextApproverId = null;

    foreach ($approvers as $step) {
        if ($step['status'] == 0) {
            $nextApproverId = $step['approver_id'];
            break; // Stop at the first pending step
        }
    }

    if ($nextApproverId === null) {
        return; // Or handle it however you need (e.g., log, exit(), throw, etc.)
    }

    $approverName = null;
    $toEmail = null;
    $sqlApprover = "SELECT Name, Email FROM Employees WHERE PK_Employees = ?";
    $stmt = $conn->prepare($sqlApprover);
    $stmt->bind_param("i", $nextApproverId);
    $stmt->execute();
    $stmt->bind_result($approverName, $toEmail);
    $stmt->fetch();
    $stmt->close();

    $query = "
        SELECT
            CASE
                WHEN ax.FK_AssetMaster = 0 THEN 'GAM'
                ELSE 'AM'
            END AS Type,
            COALESCE(AM.AssetTagNumber, GAM.Name) AS Name,
            CASE
                WHEN ax.FK_AssetMaster > 0 THEN ax.FK_AssetMaster
                ELSE ax.FK_GeneralAssetMaster
            END AS ID,
            CASE
                WHEN ax.FK_AssetMaster > 0 THEN
                    CASE 
                        WHEN ax.Quantity > 0 THEN 0
                        ELSE 1
                    END
                ELSE
                    GAM.Quantity - IFNULL((
                        SELECT SUM(Quantity)
                        FROM OutboundAssetsList
                        WHERE FK_GeneralAssetMaster = ax.FK_GeneralAssetMaster
                        AND IsReturned = 0
                    ), 0)
            END AS CurrentStock,
            ax.Quantity AS Quantity,
            COALESCE(AT_AM.AssetTypeName, AT_GAM.AssetTypeName) AS AssetType,
            AM.Descriptions as ItemDescription
        FROM OutboundAssetsList ax
        LEFT JOIN AssetMaster AM ON ax.FK_AssetMaster = AM.PK_AssetMaster
        LEFT JOIN GeneralAssetMaster GAM ON ax.FK_GeneralAssetMaster = GAM.GeneralAssetMaster
        LEFT JOIN AssetType AT_AM ON AM.FK_AssetType = AT_AM.PK_AssetType
        LEFT JOIN AssetType AT_GAM ON GAM.FK_AssetType = AT_GAM.PK_AssetType
        WHERE ax.FK_OutboundAssets = ?
    ";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $outboundId);
    $stmt->execute();
    $result2 = $stmt->get_result();


    $subject = "Outbound Approval Request #{$outboundId}";

    $htmlBody = "
        <h2>Outbound Asset Details</h2>
        <table cellpadding='6' cellspacing='0' border='0'>
            <tr><td><strong>Prepared By:</strong></td><td>{$employeeName}</td></tr>
            <tr><td><strong>Departure Date:</strong></td><td>{$departureDate}</td></tr>
            <tr><td><strong>Expected Return Date:</strong></td><td>{$expectedReturnDate}</td></tr>
            <tr><td><strong>Expected Receiver:</strong></td><td>{$expectedReceiver}</td></tr>
            <tr><td><strong>Description:</strong></td><td>{$descriptions}</td></tr>
        </table>
        <h3>Asset List</h3>
        <table cellpadding='6' cellspacing='0' border='1'>
            <tr>
                <th>Name</th>
                <th>Asset Type</th>
                <th>Quantity</th>
            </tr>";

    while ($row = $result2->fetch_assoc()) {
        $htmlBody .= "<tr>
            <td>{$row['Name']} : {$row['ItemDescription']}</td>
            <td>{$row['AssetType']}</td>
            <td>{$row['Quantity']}</td>
        </tr>";
    }

    $htmlBody .= "</table>
        <p><strong>Action:</strong> Please review the outbound asset request in the system.</p>
        <p><a href='https://ipx.16mb.com/login.php'>Login to the system</a></p>";

    $telegramBody = "📦 *Outbound Asset Request*\n\n"
        . "*Approver:* {$approverName}\n"
        . "*Prepared By:* {$employeeName}\n"
        . "*Departure:* {$departureDate}\n"
        . "*Expected Return:* {$expectedReturnDate}\n"
        . "*Expected Receiver:* {$expectedReceiver}\n"
        . "*Description:* {$descriptions}\n\n"
        . "*Assets:*\n"
        . "```\n"
        . "Type  Name                 Asset Type        Qty\n"
        . "----- -------------------- ----------------- ----\n";

    $result2->data_seek(0); // rewind result set
    while ($row = $result2->fetch_assoc()) {
        $nameWithDescription = substr($row['Name'], 0, 20) . ': ' . substr($row['ItemDescription'], 0, 30);

        $telegramBody .= sprintf(
            "%-5s %-20s %-17s %4s\n",
            $row['Type'],
            $nameWithDescription,
            substr($row['AssetType'], 0, 17),
            $row['Quantity']
        );
    }

    $telegramBody .= "```\n"
        . "🔗 [Review Request](https://ipx.16mb.com/login.php)";

    if (!empty($htmlBody) && !empty($subject)) {
        $sendResult = sendCustomEmail($htmlBody, $toEmail, $subject, $telegramBody);

        if ($sendResult === true) {
            echo "✅ Email sent successfully.";
        } else {
            echo $sendResult;
        }
    }
}

function TransferApproval($transferId, $conn)
{
    $stmt = $conn->prepare("SELECT * FROM TransferAssets WHERE PK_TransferAssets = ?");
    $stmt->bind_param("i", $transferId);
    $stmt->execute();
    $result = $stmt->get_result();
    $outbound = $result->fetch_assoc();

    $employeeID = $outbound['FK_Users'] ?? '';

    $employeeName = getEmployeeName($conn, $employeeID);
    $departureDate = $outbound['DepartureDate'] ?? '';
    $descriptions = $outbound['Descriptions'] ?? '';
    $dateAcquired = $outbound['DateAcquired'] ?? '';
    $departureDate = $outbound['DepartureDate'] ?? '';
    $expectedReceiver = $outbound['ExpectedReceiver'] ?? '';

    $approvers = json_decode($outbound['Approvers'], true);
    $nextApproverId = null;

    foreach ($approvers as $step) {
        if ($step['status'] == 0) {
            $nextApproverId = $step['approver_id'];
            break; // Stop at the first pending step
        }
    }

    if ($nextApproverId === null) {
        return; // Or handle it however you need (e.g., log, exit(), throw, etc.)
    }

    $approverName = null;
    $toEmail = null;
    $sqlApprover = "SELECT Name, Email FROM Employees WHERE PK_Employees = ?";
    $stmt = $conn->prepare($sqlApprover);
    $stmt->bind_param("i", $nextApproverId);
    $stmt->execute();
    $stmt->bind_result($approverName, $toEmail);
    $stmt->fetch();
    $stmt->close();

    $query = "
        SELECT
            CASE
                WHEN ax.FK_AssetMaster = 0 THEN 'GAM'
                ELSE 'AM'
            END AS Type,
            COALESCE(AM.AssetTagNumber, GAM.Name) AS Name,
            CASE
                WHEN ax.FK_AssetMaster > 0 THEN ax.FK_AssetMaster
                ELSE ax.FK_GeneralAssetMaster
            END AS ID,
            CASE
                WHEN ax.FK_AssetMaster > 0 THEN
                    CASE 
                        WHEN ax.Quantity > 0 THEN 0
                        ELSE 1
                    END
                ELSE
                    GAM.Quantity - IFNULL((
                        SELECT SUM(Quantity)
                        FROM TransferAssetsList
                        WHERE FK_GeneralAssetMaster = ax.FK_GeneralAssetMaster
                    ), 0)
            END AS CurrentStock,
            ax.Quantity AS Quantity,
            COALESCE(AT_AM.AssetTypeName, AT_GAM.AssetTypeName) AS AssetType,
            AM.Descriptions as ItemDescription
        FROM TransferAssetsList ax
        LEFT JOIN AssetMaster AM ON ax.FK_AssetMaster = AM.PK_AssetMaster
        LEFT JOIN GeneralAssetMaster GAM ON ax.FK_GeneralAssetMaster = GAM.GeneralAssetMaster
        LEFT JOIN AssetType AT_AM ON AM.FK_AssetType = AT_AM.PK_AssetType
        LEFT JOIN AssetType AT_GAM ON GAM.FK_AssetType = AT_GAM.PK_AssetType
        WHERE ax.FK_TransferAssets = ?
    ";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $transferId);
    $stmt->execute();
    $result2 = $stmt->get_result();


    $subject = "Transfer Approval Request #{$transferId}";

    $htmlBody = "
        <h2>Transfer Asset Details</h2>
        <table cellpadding='6' cellspacing='0' border='0'>
            <tr><td><strong>Prepared By:</strong></td><td>{$employeeName}</td></tr>
            <tr><td><strong>Departure Date:</strong></td><td>{$departureDate}</td></tr>
            <tr><td><strong>Expected Receiver:</strong></td><td>{$expectedReceiver}</td></tr>
            <tr><td><strong>Description:</strong></td><td>{$descriptions}</td></tr>
        </table>
        <h3>Asset List</h3>
        <table cellpadding='6' cellspacing='0' border='1'>
            <tr>
                <th>Name</th>
                <th>Asset Type</th>
                <th>Quantity</th>
            </tr>";

    while ($row = $result2->fetch_assoc()) {
        $htmlBody .= "<tr>
            <td>{$row['Name']} : {$row['ItemDescription']}</td>
            <td>{$row['AssetType']}</td>
            <td>{$row['Quantity']}</td>
        </tr>";
    }

    $htmlBody .= "</table>
        <p><strong>Action:</strong> Please review the outbound asset request in the system.</p>
        <p><a href='https://ipx.16mb.com/login.php'>Login to the system</a></p>";

    $telegramBody = "📦 *Transfer Asset Request*\n\n"
        . "*Approver:* {$approverName}\n"
        . "*Prepared By:* {$employeeName}\n"
        . "*Departure:* {$departureDate}\n"
        . "*Expected Receiver:* {$expectedReceiver}\n"
        . "*Description:* {$descriptions}\n\n"
        . "*Assets:*\n"
        . "```\n"
        . "Type  Name                 Asset Type        Qty\n"
        . "----- -------------------- ----------------- ----\n";

    $result2->data_seek(0); // rewind result set
    while ($row = $result2->fetch_assoc()) {
        $nameWithDescription = substr($row['Name'], 0, 20) . ': ' . substr($row['ItemDescription'], 0, 30);
        $telegramBody .= sprintf(
            "%-5s %-20s %-17s %4s\n",
            $row['Type'],
            $nameWithDescription,
            substr($row['AssetType'], 0, 17),
            $row['Quantity']
        );
    }

    $telegramBody .= "```\n"
        . "🔗 [Review Request](https://ipx.16mb.com/login.php)";

    if (!empty($htmlBody) && !empty($subject)) {
        $sendResult = sendCustomEmail($htmlBody, $toEmail, $subject, $telegramBody);

        if ($sendResult === true) {
            echo "✅ Email sent successfully.";
        } else {
            echo $sendResult;
        }
    }
}

function OutboundReceived($outboundId, $conn)
{

    $stmt = $conn->prepare("SELECT * FROM OutboundAssets WHERE PK_OutboundAssets = ?");
    $stmt->bind_param("i", $outboundId);
    $stmt->execute();
    $result = $stmt->get_result();
    $outbound = $result->fetch_assoc();

    $employeeID = $outbound['FK_Users'] ?? '';

    $employeeName = getEmployeeName($conn, $employeeID);
    $departureDate = $outbound['DepartureDate'] ?? '';
    $descriptions = $outbound['Descriptions'] ?? '';
    $dateAcquired = $outbound['DateAcquired'] ?? '';
    $departureDate = $outbound['DepartureDate'] ?? '';
    $expectedReturnDate = $outbound['ExpectedReturnDate'] ?? '';
    $returnedDate = $outbound['ReturnedDate'] ?? '';

    $approvers = json_decode($outbound['Approvers'], true);
    $nextApproverId = null;

    $query = "
        SELECT
            CASE
                WHEN ax.FK_AssetMaster = 0 THEN 'GAM'
                ELSE 'AM'
            END AS Type,
            COALESCE(AM.AssetTagNumber, GAM.Name) AS Name,
            CASE
                WHEN ax.FK_AssetMaster > 0 THEN ax.FK_AssetMaster
                ELSE ax.FK_GeneralAssetMaster
            END AS ID,
            CASE
                WHEN ax.FK_AssetMaster > 0 THEN
                    CASE 
                        WHEN ax.Quantity > 0 THEN 0
                        ELSE 1
                    END
                ELSE
                    GAM.Quantity - IFNULL((
                        SELECT SUM(Quantity)
                        FROM OutboundAssetsList
                        WHERE FK_GeneralAssetMaster = ax.FK_GeneralAssetMaster
                        AND IsReturned = 0
                    ), 0)
            END AS CurrentStock,
            ax.Quantity AS Quantity,
            COALESCE(AT_AM.AssetTypeName, AT_GAM.AssetTypeName) AS AssetType,
            AM.Descriptions as ItemDescription
        FROM OutboundAssetsList ax
        LEFT JOIN AssetMaster AM ON ax.FK_AssetMaster = AM.PK_AssetMaster
        LEFT JOIN GeneralAssetMaster GAM ON ax.FK_GeneralAssetMaster = GAM.GeneralAssetMaster
        LEFT JOIN AssetType AT_AM ON AM.FK_AssetType = AT_AM.PK_AssetType
        LEFT JOIN AssetType AT_GAM ON GAM.FK_AssetType = AT_GAM.PK_AssetType
        WHERE ax.FK_OutboundAssets = ?
    ";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $outboundId);
    $stmt->execute();
    $result2 = $stmt->get_result();


    $subject = "Outbound Asset Returned #{$outboundId}";

    $htmlBody = "
        <h2>Outbound Asset Details</h2>
        <table cellpadding='6' cellspacing='0' border='0'>
            <tr><td><strong>Prepared By:</strong></td><td>{$employeeName}</td></tr>
            <tr><td><strong>Departure Date:</strong></td><td>{$departureDate}</td></tr>
            <tr><td><strong>Expected Return Date:</strong></td><td>{$expectedReturnDate}</td></tr>
            <tr><td><strong>Returned Date:</strong></td><td>{$returnedDate}</td></tr>
            <tr><td><strong>Description:</strong></td><td>{$descriptions}</td></tr>
        </table>
        <h3>Asset List</h3>
        <table cellpadding='6' cellspacing='0' border='1'>
            <tr>
                <th>Name</th>
                <th>Asset Type</th>
                <th>Quantity</th>
            </tr>";

    while ($row = $result2->fetch_assoc()) {
        $htmlBody .= "<tr>
            <td>{$row['Name']} : {$row['ItemDescription']}</td>
            <td>{$row['AssetType']}</td>
            <td>{$row['Quantity']}</td>
        </tr>";
    }

    $htmlBody .= "</table>
        <p><strong>Action:</strong> Please review the outbound asset request in the system.</p>
        <p><a href='https://ipx.16mb.com/login.php'>Login to the system</a></p>";

    $telegramBody = "📦 *Outbound Asset Returned*\n\n"
        . "*Prepared By:* {$employeeName}\n"
        . "*Departure:* {$departureDate}\n"
        . "*Expected Return:* {$expectedReturnDate}\n"
        . "*Returned Date:* {$returnedDate}\n"
        . "*Description:* {$descriptions}\n\n"
        . "*Assets:*\n"
        . "```\n"
        . "Type  Name                 Asset Type        Qty\n"
        . "----- -------------------- ----------------- ----\n";

    $result2->data_seek(0); // rewind result set
    while ($row = $result2->fetch_assoc()) {
        $nameWithDescription = substr($row['Name'], 0, 20) . ': ' . substr($row['ItemDescription'], 0, 30);
        $telegramBody .= sprintf(
            "%-5s %-20s %-17s %4s\n",
            $row['Type'],
            $nameWithDescription,
            substr($row['AssetType'], 0, 17),
            $row['Quantity']
        );
    }

    $telegramBody .= "```\n"
        . "🔗 [Review Request](https://ipx.16mb.com/login.php)";

    $isSent = false;

    if (!empty($htmlBody) && !empty($subject)) {

        foreach ($approvers as $step) {

            $nextApproverId = null;
            $nextApproverId = $step['approver_id'];

            $toEmail = null;
            $approver = getApproverInfo($conn, $nextApproverId);
            $toEmail = $approver['email'];
            if ($isSent) {
                $telegramBody = null;
            }

            sendCustomEmail($htmlBody, $toEmail, $subject, $telegramBody);
            $isSent = true;
        }
    }
}

function TransferReceived($transferId, $conn)
{
    $stmt = $conn->prepare("SELECT * FROM TransferAssets WHERE PK_TransferAssets = ?");
    $stmt->bind_param("i", $transferId);
    $stmt->execute();
    $result = $stmt->get_result();
    $outbound = $result->fetch_assoc();

    $employeeID = $outbound['FK_Users'] ?? '';

    $employeeName = getEmployeeName($conn, $employeeID);
    $departureDate = $outbound['DepartureDate'] ?? '';
    $descriptions = $outbound['Descriptions'] ?? '';
    $dateAcquired = $outbound['DateAcquired'] ?? '';
    $departureDate = $outbound['DepartureDate'] ?? '';
    $expectedReceiver = $outbound['ExpectedReceiver'] ?? '';
    $receivedBy = $outbound['ReceivedBy'] ?? '';
    $receivedRemarks = $outbound['ReceivedRemarks'] ?? '';
    
    $approvers = json_decode($outbound['Approvers'], true);
    $nextApproverId = null;


    $query = "
        SELECT
            CASE
                WHEN ax.FK_AssetMaster = 0 THEN 'GAM'
                ELSE 'AM'
            END AS Type,
            COALESCE(AM.AssetTagNumber, GAM.Name) AS Name,
            CASE
                WHEN ax.FK_AssetMaster > 0 THEN ax.FK_AssetMaster
                ELSE ax.FK_GeneralAssetMaster
            END AS ID,
            CASE
                WHEN ax.FK_AssetMaster > 0 THEN
                    CASE 
                        WHEN ax.Quantity > 0 THEN 0
                        ELSE 1
                    END
                ELSE
                    GAM.Quantity - IFNULL((
                        SELECT SUM(Quantity)
                        FROM TransferAssetsList
                        WHERE FK_GeneralAssetMaster = ax.FK_GeneralAssetMaster
                    ), 0)
            END AS CurrentStock,
            ax.Quantity AS Quantity,
            COALESCE(AT_AM.AssetTypeName, AT_GAM.AssetTypeName) AS AssetType,
            AM.Descriptions as ItemDescription
        FROM TransferAssetsList ax
        LEFT JOIN AssetMaster AM ON ax.FK_AssetMaster = AM.PK_AssetMaster
        LEFT JOIN GeneralAssetMaster GAM ON ax.FK_GeneralAssetMaster = GAM.GeneralAssetMaster
        LEFT JOIN AssetType AT_AM ON AM.FK_AssetType = AT_AM.PK_AssetType
        LEFT JOIN AssetType AT_GAM ON GAM.FK_AssetType = AT_GAM.PK_AssetType
        WHERE ax.FK_TransferAssets = ?
    ";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $transferId);
    $stmt->execute();
    $result2 = $stmt->get_result();


    $subject = "Transfer Assets Received #{$transferId}";

    $htmlBody = "
        <h2>Transfer Assets Received</h2>
        <table cellpadding='6' cellspacing='0' border='0'>
            <tr><td><strong>Prepared By:</strong></td><td>{$employeeName}</td></tr>
            <tr><td><strong>Departure Date:</strong></td><td>{$departureDate}</td></tr>
            <tr><td><strong>Expected Receiver:</strong></td><td>{$expectedReceiver}</td></tr>
            <tr><td><strong>Received By:</strong></td><td>{$receivedBy}</td></tr>
            <tr><td><strong>Description:</strong></td><td>{$descriptions}</td></tr>
            <tr><td><strong>Received Remarks:</strong></td><td>{$receivedRemarks}</td></tr>
        </table>
        <h3>Asset List</h3>
        <table cellpadding='6' cellspacing='0' border='1'>
            <tr>
                <th>Name</th>
                <th>Asset Type</th>
                <th>Quantity</th>
            </tr>";

    while ($row = $result2->fetch_assoc()) {
        $htmlBody .= "<tr>
            <td>{$row['Name']} : {$row['ItemDescription']}</td>
            <td>{$row['AssetType']}</td>
            <td>{$row['Quantity']}</td>
        </tr>";
    }

    $htmlBody .= "</table>
        <p><strong>Action:</strong> Please review the outbound asset request in the system.</p>
        <p><a href='https://ipx.16mb.com/login.php'>Login to the system</a></p>";

    $telegramBody = "📦 *Transfer Assets Received*\n\n"
        . "*Prepared By:* {$employeeName}\n"
        . "*Departure:* {$departureDate}\n"
        . "*Expected Receiver:* {$expectedReceiver}\n"
        . "*Received By:* {$receivedBy}\n"
        . "*Description:* {$descriptions}\n"
        . "*Received Remarks:* {$receivedRemarks}\n\n"
        . "*Assets:*\n"
        . "```\n"
        . "Type  Name                 Asset Type        Qty\n"
        . "----- -------------------- ----------------- ----\n";

    $result2->data_seek(0); // rewind result set
    while ($row = $result2->fetch_assoc()) {
        $nameWithDescription = substr($row['Name'], 0, 20) . ': ' . substr($row['ItemDescription'], 0, 30);
        $telegramBody .= sprintf(
            "%-5s %-20s %-17s %4s\n",
            $row['Type'],
            $nameWithDescription,
            substr($row['AssetType'], 0, 17),
            $row['Quantity']
        );
    }

    $telegramBody .= "```\n"
        . "🔗 [Review Request](https://ipx.16mb.com/login.php)";

    $isSent = false;

    if (!empty($htmlBody) && !empty($subject)) {

        foreach ($approvers as $step) {

            $nextApproverId = null;
            $nextApproverId = $step['approver_id'];

            $toEmail = null;
            $approver = getApproverInfo($conn, $nextApproverId);
            $toEmail = $approver['email'];
            if ($isSent) {
                $telegramBody = null;
            }

            sendCustomEmail($htmlBody, $toEmail, $subject, $telegramBody);
            $isSent = true;
        }
    }
}

function getApproverInfo(mysqli $conn, int $employeeId): ?array
{
    $sql = "SELECT Name, Email FROM Employees WHERE PK_Employees = ?";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        return null;
    }

    $stmt->bind_param("i", $employeeId);
    $stmt->execute();
    $stmt->bind_result($name, $email);

    if ($stmt->fetch()) {
        $stmt->close();
        return [
            'name' => $name,
            'email' => $email
        ];
    } else {
        $stmt->close();
        return null;
    }
}

function getEmployeeName($conn, $empId)
{
    if (!$empId) return '';
    $stmt = $conn->prepare("SELECT Name FROM Employees WHERE PK_Employees = ?");
    $stmt->bind_param("i", $empId);
    $stmt->execute();
    $result = $stmt->get_result();
    $emp = $result->fetch_assoc();
    return $emp['Name'] ?? '';
}

function sendCustomEmail($htmlBody, $toEmail, $subject, $telegramBody)
{
    $smtpConfig = [
        'host' => 'smtp.gmail.com',
        'username' => 'christianck16.ipx@gmail.com',
        'password' => 'jgov cetx ofsx srwz',
        'port' => 587,
        'from_name' => 'Website Contact'
    ];

    $telegramToken = '7894803862:AAHGQBHQqJC57W2lwquz0SBikyTC6SGhqTY';
    $telegramChatId = '-1002632967796';

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = $smtpConfig['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $smtpConfig['username'];
        $mail->Password = $smtpConfig['password'];
        $mail->SMTPSecure = 'tls';
        $mail->Port = $smtpConfig['port'];

        $mail->setFrom($smtpConfig['username'], $smtpConfig['from_name']);
        $mail->addAddress($toEmail);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;

        $mail->send();

        sendTelegramMessage($telegramToken, $telegramChatId, $telegramBody);

        return true;
    } catch (Exception $e) {
        $errorMsg = "❌ Mail error: {$mail->ErrorInfo}";
        sendTelegramMessage($telegramToken, $telegramChatId, $errorMsg);
        return $errorMsg;
    }
}

function sendTelegramMessage($botToken, $chatId, $message)
{

    if (empty($message)) {
        return;
    }

    $url = "https://api.telegram.org/bot$botToken/sendMessage";
    $postData = [
        'chat_id' => $chatId,
        'text' => $message,
        'parse_mode' => 'Markdown'
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postData,
    ]);
    curl_exec($ch);
    curl_close($ch);
}
