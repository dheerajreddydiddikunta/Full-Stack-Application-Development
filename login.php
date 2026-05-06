<?php
session_start();
$conn = new mysqli("localhost", "root", "", "ecommerce_db");

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = $_POST['email'];
    $password = $_POST['password'];

    $sql = "SELECT * FROM users WHERE email='$email'";
    $result = $conn->query($sql);

    if ($result->num_rows == 1) {

        $user = $result->fetch_assoc();

        if (password_verify($password, $user['password'])) {

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];

            header("Location: index.php");
            exit();

        } else {
            $error = "Wrong password.";
        }

    } else {
        $error = "Email not found.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Login</title>
<style>
body {
    font-family: Arial;
    background: #111;
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
.error {
    color: red;
    margin-bottom: 10px;
}
</style>
</head>
<body>

<div class="form-box">
<h2>Login</h2>

<?php
if ($error != "") {
    echo "<div class='error'>$error</div>";
}
?>

<form method="POST">
<input type="email" name="email" placeholder="Email" required>
<input type="password" name="password" placeholder="Password" required>
<button type="submit">Login</button>
</form>

<p style="margin-top:10px;">
Don't have an account? <a href="register.php">Register</a>
</p>

</div>

</body>
</html>