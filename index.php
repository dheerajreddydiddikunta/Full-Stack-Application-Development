<?php
session_start();
include 'db.php';

$category = "";

if (isset($_GET['category'])) {
    $category = $_GET['category'];
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>ShopVerse</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar">
    <div class="logo">ShopVerse</div>

    <ul class="menu">
        <li><a href="index.php">All</a></li>
        <li><a href="index.php?category=Electronics">Electronics</a></li>
        <li><a href="index.php?category=Accessories">Accessories</a></li>
        <li><a href="index.php?category=Gadgets">Gadgets</a></li>
        <li><a href="index.php?category=Clothing">Clothing</a></li>
        <li><a href="index.php?category=Shoes">Shoes</a></li>
        <li><a href="index.php?category=Books">Books</a></li>
    </ul>

    <div>
        <?php if (isset($_SESSION['user'])) { ?>
            <span style="color:white; margin-right:10px;">
                Welcome <?php echo $_SESSION['user']; ?>
            </span>
            <a href="logout.php" class="cart-btn">Logout</a>
        <?php } else { ?>
            <a href="login.php" class="cart-btn">Login</a>
        <?php } ?>
        <a href="cart.php" class="cart-btn">Cart</a>
    </div>
</nav>

<!-- PRODUCTS -->
<div class="products">

<?php

if ($category == "") {
    $result = $conn->query("SELECT * FROM products");
} else {
    $result = $conn->query("SELECT * FROM products WHERE category='$category'");
}

while ($row = $result->fetch_assoc()) {

    echo "<div class='product'>";
    
    echo "<img src='images/".$row['name'].".jpg'>";
    
    echo "<h3>".$row['name']."</h3>";
    echo "<p>₹".$row['price']."</p>";
    
    echo "<form method='POST' action='add_to_cart.php'>";
    echo "<input type='hidden' name='product_id' value='".$row['id']."'>";
    echo "<button type='submit'>Add to Cart</button>";
    echo "</form>";
    
    echo "</div>";
}
?>

</div>

</body>
</html>