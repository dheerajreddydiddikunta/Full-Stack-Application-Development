<?php include 'db.php'; ?>

<!DOCTYPE html>
<html>
<head>
    <title>Your Cart</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<h1>Your Cart</h1>

<div class="products">

<?php
$sql = "SELECT products.name, products.price, cart.quantity 
        FROM cart 
        JOIN products ON cart.product_id = products.id";

$result = $conn->query($sql);
$total = 0;

if ($result->num_rows > 0) {

    echo "<table class='cart-table'>";
    echo "<tr>
            <th>Product</th>
            <th>Price</th>
            <th>Quantity</th>
            <th>Subtotal</th>
          </tr>";

    while ($row = $result->fetch_assoc()) {

        $subtotal = $row['price'] * $row['quantity'];
        $total += $subtotal;

        echo "<tr>";
        echo "<td>".$row['name']."</td>";
        echo "<td>₹".$row['price']."</td>";
        echo "<td>".$row['quantity']."</td>";
        echo "<td>₹".$subtotal."</td>";
        echo "</tr>";
    }

    echo "</table>";

    echo "<h2 class='total'>Total: ₹".$total."</h2>";

} else {
    echo "<p>Your cart is empty.</p>";
}
?>

<br>
<a href="index.php" class="back-btn">← Continue Shopping</a>

</body>
</html>