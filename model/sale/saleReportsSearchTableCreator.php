<?php
	require_once('../../inc/config/constants.php');
	require_once('../../inc/config/db.php');
	
	$uPrice = 0;
	$qty = 0;
	$totalPrice = 0;

	// Fetch sales data
	$saleDetailsSearchSql = 'SELECT * FROM sale';
	$saleDetailsSearchStatement = $conn->prepare($saleDetailsSearchSql);
	$saleDetailsSearchStatement->execute();

	// Initialize chart data arrays
	$salesByItem = [];
	$salesByMonth = [];

	// Build table output
	$output = '<table id="saleReportsTable" class="table table-sm table-striped table-bordered table-hover" style="width:100%">
				<thead>
					<tr>
						<th>Sale ID</th>
						<th>Item Number</th>
						<th>Customer ID</th>
						<th>Customer Name</th>
						<th>Item Name</th>
						<th>Sale Date</th>
						<th>Discount %</th>
						<th>Quantity</th>
						<th>Unit Price</th>
						<th>Total Price</th>
					</tr>
				</thead>
				<tbody>';
	
	// Loop for table and chart data
	while($row = $saleDetailsSearchStatement->fetch(PDO::FETCH_ASSOC)) {
		$uPrice = $row['unitPrice'];
		$qty = $row['quantity'];
		$discount = $row['discount'];
		$totalPrice = $uPrice * $qty * ((100 - $discount)/100);

		// Table row
		$output .= '<tr>' .
						'<td>' . $row['saleID'] . '</td>' .
						'<td>' . $row['itemNumber'] . '</td>' .
						'<td>' . $row['customerID'] . '</td>' .
						'<td>' . $row['customerName'] . '</td>' .
						'<td>' . $row['itemName'] . '</td>' .
						'<td>' . $row['saleDate'] . '</td>' .
						'<td>' . $row['discount'] . '</td>' .
						'<td>' . $row['quantity'] . '</td>' .
						'<td>' . $row['unitPrice'] . '</td>' .
						'<td>' . number_format($totalPrice, 2) . '</td>' .
					'</tr>';

		// Collect Pie chart data - Sales by Item
		$item = $row['itemName'];
		if (!isset($salesByItem[$item])) {
			$salesByItem[$item] = 0;
		}
		$salesByItem[$item] += $totalPrice;

		// Collect Bar chart data - Sales by Month
		$month = date('Y-m', strtotime($row['saleDate']));
		if (!isset($salesByMonth[$month])) {
			$salesByMonth[$month] = 0;
		}
		$salesByMonth[$month] += $totalPrice;
	}

	$saleDetailsSearchStatement->closeCursor();

	$output .= '</tbody>
				<tfoot>
					<tr>
						<th>Total</th>
						<th></th><th></th><th></th><th></th>
						<th></th><th></th><th></th><th></th><th></th>
					</tr>
				</tfoot>
			</table>';

	// Output the table
	echo $output;
?>

<!-- Chart containers -->
<div class="mt-5">
	<h5>Sales by Item (Pie Chart)</h5>
	<canvas id="salesPieChart" height="100"></canvas>
</div>

<div class="mt-5">
	<h5>Monthly Sales (Bar Chart)</h5>
	<canvas id="salesBarChart" height="100"></canvas>
</div>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Pass PHP data to JS -->
<script>
	const salesByItem = <?php echo json_encode($salesByItem); ?>;
	const salesByMonth = <?php echo json_encode($salesByMonth); ?>;

	// Pie Chart - Sales by Item
	const pieCtx = document.getElementById('salesPieChart').getContext('2d');
	const pieChart = new Chart(pieCtx, {
		type: 'pie',
		data: {
			labels: Object.keys(salesByItem),
			datasets: [{
				label: 'Sales by Item',
				data: Object.values(salesByItem),
				backgroundColor: [
					'#007bff', '#28a745', '#ffc107', '#dc3545', '#6f42c1', '#17a2b8', '#20c997', '#6610f2'
				],
				borderWidth: 1
			}]
		}
	});

	// Bar Chart - Sales by Month
	const barCtx = document.getElementById('salesBarChart').getContext('2d');
	const barChart = new Chart(barCtx, {
		type: 'bar',
		data: {
			labels: Object.keys(salesByMonth),
			datasets: [{
				label: 'Total Sales',
				data: Object.values(salesByMonth),
				backgroundColor: '#007bff'
			}]
		},
		options: {
			scales: {
				y: {
					beginAtZero: true
				}
			}
		}
	});
</script>
