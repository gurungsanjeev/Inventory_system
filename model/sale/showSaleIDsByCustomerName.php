<?php
require_once '../../inc/config/db.php';

header('Content-Type: application/json');

$customerName = isset($_POST['customerName']) ? trim($_POST['customerName']) : '';

if (!empty($customerName)) {
    $stmt = $conn->prepare("
        SELECT s.saleID 
        FROM sale s
        JOIN customer c ON s.customerID = c.customerID
        WHERE c.fullName LIKE ?
    ");
    $likeCustomerName = '%' . $customerName . '%';
    $stmt->bind_param('s', $likeCustomerName);
    $stmt->execute();
    $result = $stmt->get_result();

    $saleIDs = [];
    while ($row = $result->fetch_assoc()) {
        $saleIDs[] = ['saleID' => $row['saleID']];
    }

    echo json_encode($saleIDs);
    $stmt->close();
} else {
    echo json_encode([]);
}

$conn->close();
?>