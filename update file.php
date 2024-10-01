<?php
$servername = "localhost"; // Replace with your server name
$username = "root"; // Replace with your username
$password = ""; // Replace with your password
$dbname = "view_more";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    print "Unable to connect sorry unable to load db";
    die("Connection failed: " . $conn->connect_error);
}

// Fetch summary data
$summary_query = "SELECT
                    SUM(item_cost + labour_cost) AS total_maintenance_amount,
                    COUNT(DISTINCT vehicle_no) AS num_vehicles_maintained,
                    SUM(item_cost) AS used_stock_value,
                    SUM(quantity) AS stock_units_used
                  FROM maintenance_done";
$summary_result = $conn->query($summary_query);
$summary_data = $summary_result->fetch_assoc();

// Fetch maintenance data
$maintenance_query = "SELECT
                        md.vehicle_no,
                        md.vname AS vehicle_name,
                        MAX(md.maintenance_date) AS last_service_date,
                        MAX(CASE 
                            WHEN se.item_category = 'Engine Oil' OR md.maintenance_details = 'Engine Oil' THEN md.maintenance_date
                            ELSE NULL
                        END) AS last_oil_change_date,
                        DATEDIFF(CURDATE(), MAX(CASE 
                            WHEN se.item_category = 'Engine Oil' OR md.maintenance_details = 'Engine Oil' THEN md.maintenance_date
                            ELSE NULL
                        END)) AS days_since_oil_changed,
                        DATEDIFF(CURDATE(), MAX(md.maintenance_date)) AS days_since_last_service,
                        COALESCE(AVG(DATEDIFF(next_change.maintenance_date, md.maintenance_date)), 0) AS avg_days_between_oil_changes,
                        DATE_ADD(MAX(md.maintenance_date), INTERVAL COALESCE(AVG(DATEDIFF(next_change.maintenance_date, md.maintenance_date)), 0) DAY) AS recommended_oil_change_date,
                        t.customer_name,
                        t.customer_mobile,
                        MAX(CASE 
                            WHEN se.item_name IN ('Kharad', 'Wall', 'Timing chain', 'Wall seal', 'Half kit', 'Piston 50', 'Piston 100', 'Piston 25', 'Piston 75') THEN md.maintenance_date
                            ELSE NULL
                        END) AS last_engine_change_date
                      FROM maintenance_done md
                      JOIN stock_entry se ON md.item_id = se.sku_id
                      LEFT JOIN maintenance_done next_change ON md.vehicle_no = next_change.vehicle_no
                        AND next_change.maintenance_date > md.maintenance_date
                      LEFT JOIN trip t ON md.vehicle_no = t.bikes_id
                      GROUP BY md.vehicle_no, md.vname, t.customer_name, t.customer_mobile";
$maintenance_result = $conn->query($maintenance_query);

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vehicle Maintenance Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body { font-family: Arial, sans-serif; background-color: #f5f5f5; margin: 0; padding: 20px; }
        .dashboard { max-width: 1500px; margin: 0 auto; background: white; padding: 20px; box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); }
        .summary-cards { display: flex; justify-content: space-between; margin-bottom: 20px; }
        .card { background-color: #000; color: #fff; padding: 20px; text-align: center; border-radius: 8px; flex: 1; margin-right: 10px; }
        .card:last-child { margin-right: 0; }
        .search-bar { display: flex; flex-direction: column; align-items: center; margin-bottom: 20px; }
        .search-bar input { padding: 10px; width: 45%; border: 1px solid #ccc; border-radius: 4px; margin-bottom: 10px; }
        .search-bar button { padding: 10px 20px; background-color: #f0ad4e; color: white; border: none; border-radius: 4px; cursor: pointer; }
        table { width: 100%; border-collapse: collapse; }
        table, th, td { border: 1px solid #ddd; }
        th, td { padding: 15px; text-align: left; }
        th { background-color: #f4f4f4; }
        .whatsapp-btn { padding: 10px 15px; background-color: #25D366; color: white; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; }
        .pagination { text-align: center; margin-top: 20px; }
        .pagination a { padding: 10px 15px; margin: 0 5px; text-decoration: none; background-color: #f0ad4e; color: white; border-radius: 4px; }
        .pagination a.active { background-color: #5cb85c; }
    </style>
</head>
<body>
    <div class="dashboard">
         <!-- Date Filter Form -->
         <div class="search-bar">
            <form method="get" action="">
                <input type="date" name="date_filter">
                <button type="submit">Search</button>
            </form>
        </div>
        
        <div class="summary-cards">
                <div class="card"><p>Total maintenance amount</p><h3><?php echo number_format($summary_data['total_maintenance_amount']); ?></h3></div>
                <div class="card"><p>No. of vehicles maintained</p><h3><?php echo $summary_data['num_vehicles_maintained']; ?></h3></div>
                <div class="card"><p>Used stock value</p><h3>Rs. <?php echo number_format($summary_data['used_stock_value']); ?></h3></div>
                <div class="card"><p>Stock units used</p><h3><?php echo $summary_data['stock_units_used']; ?></h3></div>
            </div>
        <table>
            <thead>
                <tr>
                    <th>Vehicle Number</th>
                    <th>Vehicle Name</th>
                    <th>Last Oil Change Date</th>
                    <th>Days Since Oil Changed</th>
                    <th>Avg Days Between Oil Changes</th>
                    <th>Recommended Oil Change Date</th>
                    <th>Last Engine Change Date</th>
                    <th>Customer Name</th>
                    <th>Customer Mobile</th>
                    <th>View</th>
                    <th>Contact</th>
                </tr>
            </thead>
            <tbody>
            <?php while($row = $maintenance_result->fetch_assoc()): ?>
                <tr class="<?php echo ($row['days_since_oil_changed'] < 30) ? 'light-green' : ''; ?>">
                    <td><?php echo htmlspecialchars($row['vehicle_no']); ?></td>
                    <td><?php echo htmlspecialchars($row['vehicle_name']); ?></td>
                    <td><?php echo htmlspecialchars($row['last_oil_change_date']); ?></td>
                    <td><?php echo htmlspecialchars($row['days_since_oil_changed']); ?></td>
                    <td><?php echo htmlspecialchars($row['avg_days_between_oil_changes']); ?></td>
                    <td><?php echo htmlspecialchars($row['recommended_oil_change_date']); ?></td>
                    <td><?php echo htmlspecialchars($row['last_engine_change_date']); ?></td>
                    <td><?php echo htmlspecialchars($row['customer_name']); ?></td>
                    <td><?php echo htmlspecialchars($row['customer_mobile']); ?></td> 
                    <td>
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addCommentModal" data-trans-id="<?php echo htmlspecialchars($row['vehicle_no']); ?>">
                                <i class="fas fa-plus-circle"></i>
                            </button>
                            <button type="button" class="btn btn-info" data-toggle="modal" data-target="#viewCommentModal" data-trans-id="<?php echo htmlspecialchars($row['vehicle_no']); ?>">
                                <i class="fas fa-list"></i>
                            </button>
                        </div>
                    </td>
                    <td>
                        <a href="https://wa.me/<?php echo htmlspecialchars($row['customer_mobile']); ?>" class="whatsapp-btn" target="_blank">
                            <i class="fab fa-whatsapp"></i>
                        </a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <!-- Pagination controls -->
        <div class="pagination">
            <a href="?page=1">&laquo; Previous</a>
            <a href="?page=1" class="active">1</a>
            <a href="?page=2">2</a>
            <a href="?page=3">3</a>
            <!-- Add more page links as needed -->
            <a href="?page=2">Next &raquo;</a>
        </div>
    </div>

    <!-- Add Comment Modal -->
    <div class="modal fade" id="addCommentModal" tabindex="-1" role="dialog" aria-labelledby="addCommentModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addCommentModalLabel">Add Comment</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form method="post" action="">
                        <div class="form-group">
                            <label for="trans_id">Vehicle Number</label>
                            <input type="text" class="form-control" id="trans_id" name="vehicle_no" readonly>
                        </div>
                        <div class="form-group">
                            <label for="customer_name">Customer Name</label>
                            <input type="text" class="form-control" id="customer_name" name="customer_name" readonly>
                        </div>
                        <div class="form-group">
                            <label for="comment">Comment</label>
                            <textarea class="form-control" id="comment" name="comment" rows="3"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary" name="add_comment">Submit</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- View Comment Modal -->
    <div class="modal fade" id="viewCommentModal" tabindex="-1" role="dialog" aria-labelledby="viewCommentModalLabel" aria-hidden="true">
        <div class="modal-dialog" style="max-width: 80%;" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewCommentModalLabel">View Comments for Vehicle <span id="vehicleNumber"></span></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Customer Name</th>
                                <th>Comment</th>
                                <th>Date</th>
                                <th>Vehicle Number</th>
                            </tr>
                        </thead>
                        <tbody id="commentList">
                            <!-- Comments will be loaded here -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        // Populate the trans_id and customer_name fields in the add comment modal
        $('#addCommentModal').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget);
            var transId = button.data('trans-id');
            var customerName = button.closest('tr').find('td:nth-child(8)').text();
            var modal = $(this);
            modal.find('.modal-body #trans_id').val(transId);
            modal.find('.modal-body #customer_name').val(customerName);
        });

        // Load comments in the view comment modal
        $('#viewCommentModal').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget);
            var transId = button.data('trans-id');
            var vehicleNumber = button.closest('tr').find('td:nth-child(1)').text();
            var modal = $(this);
            modal.find('.modal-title #vehicleNumber').text(vehicleNumber);
            var commentList = $('#commentList');
            commentList.empty();

            $.ajax({
                type: 'GET',
                url: '<?php echo $_SERVER['PHP_SELF']; ?>',
                data: { view_comments: transId },
                dataType: 'json',
                success: function(comments) {
                    $.each(comments, function(index, comment) {
                        var row = '<tr>' +
                            '<td>' + comment.customer_name + '</td>' +
                            '<td>' + comment.comment + '</td>' +
                            '<td>' + comment.created_at + '</td>' +
                            '<td>' + comment.vehicle_no + '</td>' +
                            '</tr>';
                        commentList.append(row);
                    });
                },
                error: function(xhr, status, error) {
                    console.error('Error fetching comments:', error);
                }
            });
        });
    </script>
</body>
