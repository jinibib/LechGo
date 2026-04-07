<?php
require_once __DIR__ . '/../config/db.php';

echo "<h2>📊 Database Check</h2>";

// Check livestock_feed_orders
echo "<h3>livestock_feed_orders table:</h3>";
$result = $conn->query("SELECT COUNT(*) as count FROM livestock_feed_orders");
$row = $result->fetch_assoc();
echo "Total orders: <strong>" . $row['count'] . "</strong><br>";

if ($row['count'] > 0) {
    $result = $conn->query("SELECT id, order_number, total_amount, order_status, created_at FROM livestock_feed_orders LIMIT 5");
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Order Number</th><th>Amount</th><th>Status</th><th>Created</th></tr>";
    while ($order = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $order['id'] . "</td>";
        echo "<td>" . htmlspecialchars($order['order_number']) . "</td>";
        echo "<td>₱" . number_format($order['total_amount'], 2) . "</td>";
        echo "<td>" . $order['order_status'] . "</td>";
        echo "<td>" . $order['created_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Check livestock_feed_order_items
echo "<h3><br>livestock_feed_order_items table:</h3>";
$result = $conn->query("SELECT COUNT(*) as count FROM livestock_feed_order_items");
$row = $result->fetch_assoc();
echo "Total items: <strong>" . $row['count'] . "</strong><br>";

if ($row['count'] > 0) {
    $result = $conn->query("SELECT id, feed_order_id, product_name, feed_type, quantity_kg, unit_price FROM livestock_feed_order_items LIMIT 10");
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Order ID</th><th>Product</th><th>Type</th><th>Qty (kg)</th><th>Price</th></tr>";
    while ($item = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $item['id'] . "</td>";
        echo "<td>" . $item['feed_order_id'] . "</td>";
        echo "<td>" . htmlspecialchars($item['product_name']) . "</td>";
        echo "<td>" . htmlspecialchars($item['feed_type']) . "</td>";
        echo "<td>" . number_format($item['quantity_kg'], 2) . "</td>";
        echo "<td>₱" . number_format($item['unit_price'], 2) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Check old tables (for reference)
echo "<h3><br>Old Tables (for legacy reference):</h3>";
$result = $conn->query("SELECT COUNT(*) as count FROM feed_orders");
$row = $result->fetch_assoc();
echo "feed_orders: <strong>" . $row['count'] . "</strong> rows<br>";

$result = $conn->query("SELECT COUNT(*) as count FROM feed_order_items");
$row = $result->fetch_assoc();
echo "feed_order_items: <strong>" . $row['count'] . "</strong> rows<br>";

echo "<hr>";
echo "<p><a href='/LechGo_Final/public/'>← Back to Home</a></p>";
?>
