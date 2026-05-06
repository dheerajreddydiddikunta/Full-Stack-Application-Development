<?php
include 'db.php';

$product_id = $_POST['product_id'];

// Check if product already exists in cart
$check = $conn->query("SELECT * FROM cart WHERE product_id = $product_id");

if ($check->num_rows > 0) {
    // If exists, increase quantity
    $conn->query("UPDATE cart SET quantity = quantity + 1 WHERE product_id = $product_id");
} else {
    // If not exists, insert new
    $conn->query("INSERT INTO cart (product_id, quantity) VALUES ($product_id, 1)");
}

header("Location: index.php");
exit();
?>