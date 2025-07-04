<?php
session_start();
include 'db_connect.php'; // Database connection

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST["email"]);
    $password = trim($_POST["password"]);

    // Fetch user data from the database
    $sql = "SELECT id, name, password, role FROM users WHERE email = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($row = mysqli_fetch_assoc($result)) {
        if (password_verify($password, $row["password"])) {
            $_SESSION["user_id"] = $row["id"];
            $_SESSION["user_name"] = $row["name"];
            $_SESSION["role"] = $row["role"];

            // Redirect based on role
            if ($row["role"] === "admin") {
                header("Location: admin/admin_dashboard.php");
                exit();
            } elseif ($row["role"] === "staff") {
                header("Location: staff/staff_dashboard.php");
                exit();
            } else { // Default is parent
                header("Location: user/parent_dashboard.php");
                exit();
            }
        } else {
            echo "Invalid password. <a href='index.php'>Try again</a>";
        }
    } else {
        echo "No user found with this email. <a href='index.php'>Try again</a>";
    }

    mysqli_stmt_close($stmt);
    mysqli_close($conn);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daycare Management System</title>
    <style>
        /* Reset and Base Styles */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Poppins', 'Arial', sans-serif;
}

body {
    background: linear-gradient(135deg, #f5f7fa, #c3cfe2);
    color: #333;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
}

/* Navigation Styles */
nav {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #34495e;
    padding: 1rem 3rem;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.logo a {
    color: #fff;
    font-size: 2rem;
    font-weight: bold;
    text-decoration: none;
    letter-spacing: 1px;
    transition: color 0.3s ease;
}

.logo a:hover {
    color: #1abc9c;
}

nav ul {
    display: flex;
    list-style: none;
    gap: 2rem;
}

nav ul li a {
    color: #ecf0f1;
    text-decoration: none;
    font-size: 1rem;
    font-weight: 500;
    transition: color 0.3s, border-bottom 0.3s;
}

nav ul li a:hover,
nav ul li a.active {
    color: #1abc9c;
    border-bottom: 2px solid #1abc9c;
    padding-bottom: 2px;
}

/* Main Section */
main {
    flex: 1;
    padding: 40px 20px;
}

section h2 {
    font-size: 2rem;
    margin-bottom: 10px;
}

section p {
    font-size: 1.1rem;
    margin-bottom: 30px;
    color: #555;
}

/* Main Content Card */
.main-content {
    background: #fff;
    max-width: 500px;
    margin: auto;
    padding: 30px;
    border-radius: 15px;
    box-shadow: 0px 10px 25px rgba(0, 0, 0, 0.1);
    animation: fadeIn 0.6s ease;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Login Box */
.login-box h3 {
    font-size: 1.8rem;
    margin-bottom: 20px;
    color: #2c3e50;
}

.login-box label {
    display: block;
    text-align: left;
    font-weight: 600;
    margin: 8px 0 4px;
    color: #333;
}

.login-box input {
    width: 100%;
    padding: 12px 10px;
    border: 1px solid #ccc;
    border-radius: 8px;
    margin-bottom: 15px;
    transition: border-color 0.3s;
}

.login-box input:focus {
    border-color: #1abc9c;
    outline: none;
}

/* Error Message */
.error-message {
    background-color: #ffe0e0;
    color: #c0392b;
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 15px;
}

/* Button */
button {
    width: 100%;
    padding: 12px;
    background: #1abc9c;
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.3s;
}

button:hover {
    background: #16a085;
}

/* Footer Styles */
footer {
    background: #2c3e50;
    color: #ecf0f1;
    padding: 40px 20px 20px;
    text-align: center;
    margin-top: auto;
}

.footer-container {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-around;
    gap: 30px;
    max-width: 1200px;
    margin: 0 auto 30px;
}

.footer-section {
    min-width: 220px;
}

.footer-section h4 {
    margin-bottom: 15px;
    font-size: 1.3rem;
    color: white ;
}

.footer-section p,
.footer-section a {
    color: #bdc3c7;
    font-size: 0.95rem;
    line-height: 1.6;
    text-decoration: none;
    transition: color 0.3s;
}

.footer-section a:hover {
    color: green;
    text-decoration: underline;
}

.footer-bottom {
    border-top: 1px solid #7f8c8d;
    padding-top: 10px;
    font-size: 0.9rem;
}

/* Responsive Design */
@media (max-width: 768px) {
    nav ul {
        gap: 1rem;
    }
    .footer-container {
        flex-direction: column;
        align-items: center;
    }
}

   

    </style>
</head>
<body>

    <header>
        <nav>
            <div class="logo">
                <a href="index.php">Daycare Management</a>
            </div>
            <ul>
                <li><a href="BabyCare.php">BabyCare</a></li>
                <li><a href="Diseases.php">Diseases</a></li>
                <li><a href="Vaccination.php">Vaccination</a></li>
                <li><a href="Food_Nutrition.php">Food & Nutrition</a></li>
                <li><a href="about.php">About Us</a></li>
                <li><a href="register.php">Register</a></li>
            </ul>
        </nav>
    </header>

    <main>
        <section>
        <h2>Welcome to Our Daycare Management System</h2>
        <p>We provide the best care for your children with trusted staff and a safe environment.</p>
        </section>
        <section class="main-content">
            <div class="login-box">
                <h3>Login</h3>
                <?php if (!empty($error)): ?>
                    <div class="error-message"><?php echo $error; ?></div>
                <?php endif; ?>
                <form action="" method="POST">
                    <label for="email">Email:</label>
                    <input type="email" name="email" required>

                    <label for="password">Password:</label>
                    <input type="password" name="password" required>

                    <button type="submit">Login</button>
                </form>
                <p>New user? <a href="register.php">Register here</a></p>
            </div>
        </section>
    </main>

    <footer>
        <div class="footer-container">
            <div class="footer-section">
                <h4>Address</h4>
                <p>123 Babycare Street<br>Happy Town, Kidsland</p>
            </div>
            <div class="footer-section">
                <h4>Pages</h4>
                <p>
                    <a href="about.php">About Us</a><br>
                    <a href="#">Contact Us</a><br>
                    <a href="#">Blogs</a>
                </p>
            </div>
            <div class="footer-section">
                <h4>Social Links</h4>
                <p>
                    <a href="http://facebook.com" target="_blank">Facebook</a><br>
                    <a href="http://twitter.com" target="_blank">Twitter</a><br>
                    <a href="http://instagram.com" target="_blank">Instagram</a>
                </p>
            </div>
            <div class="footer-section">
                <h4>About BabyCare</h4>
                <p>BabyCare offers services for children aged 1 month to 5 years.<br>
                <a href="about.php">Details</a></p>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?php echo date("Y"); ?> Daycare Management System. All rights reserved.</p>
        </div>
    </footer>

</body>
</html>
