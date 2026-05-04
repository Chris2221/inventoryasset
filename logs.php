<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
include 'config.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs</title>
    <link rel="icon" href="inventory.png" type="image/x-icon">
    <?php include "tools/head-plugin.php"; ?>

    <style>
        td.details-control {
            cursor: pointer;
        }

        tr.shown td.details-control::before {
            content: "▼";
        }

        td.details-control::before {
            content: "▶";
            padding-right: 10px;
        }
    </style>
</head>

<body>
    <?php include 'header.php'; ?>

    <div class="container">
        <h2>Activity Logs</h2>

        <table id="logsTable" class="table table-bordered table-hover table-striped table-responsive">
            <thead class="table-light">
                <tr>
                    <th></th>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Action</th>
                    <th>IP Address</th>
                    <th>Timestamp</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $sql = "SELECT ax.*, bx.Username 
                FROM ActivityLogs ax
                LEFT JOIN Users bx on ax.FK_Users =  bx.PK_Users
                ORDER BY Timestamp DESC";
                $result = $conn->query($sql);

                if ($result && $result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        $id = htmlspecialchars($row['id']);
                        $user_id = !empty($row['Username']) ? htmlspecialchars($row['Username']) : 'unknown';

                        $action = htmlspecialchars($row['Action']);
                        $details = htmlspecialchars($row['Details'], ENT_QUOTES);
                        $timestamp = date("Y-m-d h:i A", strtotime($row['Timestamp']));
                        $ipAddress = htmlspecialchars($row['IpAddress']);

                        echo "<tr data-details=\"$details\">
                            <td class='details-control'></td>
                            <td>$id</td>
                            <td>$user_id</td>
                           
                            <td>$action</td>
                            <td>$ipAddress</td>
                            <td>$timestamp</td>
                          </tr>";
                    }
                }
                $conn->close();
                ?>
            </tbody>
        </table>

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
    <script src="script/stopper.js"></script>


    <script>
        function format(details) {
            return '<div style="padding:10px;"><strong>Details:</strong> ' + details + '</div>';
        }

        $(document).ready(function() {
            const table = $('#logsTable').DataTable({
                dom: '<"justify-content-between align-items-center mb-2"lfB>tip',
                pagingType: 'simple',
                responsive: true,
                buttons: [{
                        extend: 'csvHtml5',
                        text: '<i class="bi bi-filetype-csv"></i>CSV',
                        className: 'btn btn-sm btn-primary',
                        title: 'Activity Logs',
                        exportOptions: {
                            columns: function(idx) {
                                return idx !== 6; // Exclude details column
                            }
                        }
                    },
                    {
                        extend: 'excelHtml5',
                        text: '<i class="bi bi-file-earmark-excel"></i>Excel',
                        className: 'btn btn-sm btn-success',
                        title: 'Activity Logs',
                        exportOptions: {
                            columns: function(idx) {
                                return idx !== 6;
                            }
                        }
                    },
                    {
                        extend: 'pdfHtml5',
                        text: '<i class="bi bi-filetype-pdf"></i>PDF',
                        className: 'btn btn-sm btn-danger',
                        title: 'Activity Logs',
                        exportOptions: {
                            columns: function(idx) {
                                return idx !== 6;
                            }
                        },
                        customize: function(doc) {
                            var tableBody = doc.content[1].table.body;
                            var colCount = tableBody[0].length;
                            doc.content[1].table.widths = Array(colCount).fill('*');
                            doc.pageMargins = [20, 20, 20, 20];
                        }
                    }
                ],
                language: {
                    lengthMenu: "Show _MENU_ entries",
                    search: "",
                    searchPlaceholder: "Search..."
                }
            });

            $('#logsTable tbody').on('click', 'td.details-control', function() {
                var tr = $(this).closest('tr');
                var row = table.row(tr);

                if (row.child.isShown()) {
                    row.child.hide();
                    tr.removeClass('shown');
                } else {
                    var details = tr.data('details');
                    row.child(format(details)).show();
                    tr.addClass('shown');
                }
            });
        });
    </script>



</body>

</html>