<?php
$conn = new mysqli("localhost", "root", "", "ecommerce_db");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Hash password
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    // Insert user
    $sql = "INSERT INTO users (username, email, password) 
            VALUES ('$username', '$email', '$passwordHash')";

    if ($conn->query($sql) === TRUE) {
        // Redirect to login page
        header("Location: login.php");
        exit();
    } else {
        $error = "Email already exists!";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Register</title>
<style>
body {
    font-family: Arial;
    background: #f4f4f4;
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100vh;
}
.form-box {
    background: white;
    padding: 30px;
    width: 300px;
    border-radius: 8px;
    box-shadow: 0 3px 10px rgba(0,0,0,0.1);
}
input {
    width: 100%;
    padding: 8px;
    margin-bottom: 10px;
}
button {
    width: 100%;
    padding: 8px;
    background: orange;
    border: none;
    color: white;
    cursor: pointer;
}
button:hover {
    background: darkorange;
}
.error {
    color: red;
    margin-bottom: 10px;
}
</style>
</head>
<body>

<div class="form-box">
<h2>Register</h2>

<?php
if ($error != "") {
    echo "<div class='error'>$error</div>";
}
?>

<form method="POST">
<input type="text" name="username" placeholder="Username" required>
<input type="email" name="email" placeholder="Email" required>
<input type="password" name="password" placeholder="Password" required>
<button type="submit">Register</button>
</form>

<p style="margin-top:10px;">
Already have an account? <a href="login.php">Login</a>
</p>

</div>

</body>
</html>