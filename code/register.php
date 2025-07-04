<?php
include 'db_connect.php'; // Database Connection
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Fetch and Sanitize Inputs
    $name = trim($_POST["name"]);
    $cnic = trim($_POST["cnic"]);
    $email = trim($_POST["email"]);
    $password = trim($_POST["password"]);
    $confirm_password = trim($_POST["confirm_password"]);
    $phone = trim($_POST["phone"]);
    $age = trim($_POST["age"]);
    $address = trim($_POST["address"]);
    $town = trim($_POST["town"]);
    $region = trim($_POST["region"]);
    $postcode = trim($_POST["postcode"]);
    $country = trim($_POST["country"]);

    // Error Handling
    $errors = [];

    // Check Passwords Match
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }

    // Check Email Already Exists
    $checkEmail = "SELECT id FROM users WHERE email = ?";
    $stmt = mysqli_prepare($conn, $checkEmail);
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);

    if (mysqli_stmt_num_rows($stmt) > 0) {
        $errors[] = "Email is already registered.";
    }
    mysqli_stmt_close($stmt);

    // Check CNIC Already Exists (Optional but recommended)
    $checkCNIC = "SELECT id FROM users WHERE cnic = ?";
    $stmt = mysqli_prepare($conn, $checkCNIC);
    mysqli_stmt_bind_param($stmt, "s", $cnic);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);

    if (mysqli_stmt_num_rows($stmt) > 0) {
        $errors[] = "CNIC is already registered.";
    }
    mysqli_stmt_close($stmt);

    // If No Errors
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $insert = "INSERT INTO users (name, cnic, email, password, phone, age, address, town, region, postcode, country) 
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = mysqli_prepare($conn, $insert);
        mysqli_stmt_bind_param($stmt, "sssssssssss", $name, $cnic, $email, $hashed_password, $phone, $age, $address, $town, $region, $postcode, $country);

        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['success'] = "Registration successful! Please login.";
            header("Location: login.php"); // Redirect to login page
            exit();
        } else {
            $errors[] = "Something went wrong. Please try again.";
        }
        mysqli_stmt_close($stmt);
    }

    mysqli_close($conn);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create Account - Daycare Management System</title>
    <style>
        /* General Styles */
        body {
            font-family: Arial, sans-serif;
            background-color: #f8f9fa;
            margin: 0;
            padding: 0;
        }

        nav {
            background: #2c3e50;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        nav .logo a {
            color: white;
            font-size: 24px;
            text-decoration: none;
        }

        nav ul {
            display: flex;
            list-style: none;
            gap: 1.5rem;
        }

        nav ul li a {
            color: #ecf0f1;
            text-decoration: none;
        }

        .register-container {
            background: white;
            width: 40%;
            margin: 50px auto;
            padding: 30px;
            box-shadow: 0px 0px 10px rgba(0,0,0,0.1);
            border-radius: 8px;
        }

        h2 {
            text-align: center;
        }

        form {
            display: flex;
            flex-direction: column;
        }

        label {
            margin: 10px 0 5px;
        }

        input {
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }

        button {
            margin-top: 20px;
            padding: 10px;
            background:  #1abc9c;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        
        button:hover {
            background: rgb(4, 83, 67);
        }

        .error-box {
            background-color: #ffdddd;
            padding: 10px;
            margin-bottom: 15px;
            border-left: 6px solid #f44336;
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
            color: white;
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
        <div class="logo"><a href="index.php">Daycare Management System</a></div>
        <ul>
            <li><a href="BabyCare.php">BabyCare</a></li>
            <li><a href="Diseases.php">Diseases</a></li>
            <li><a href="Vaccination.php">Vaccination</a></li>
            <li><a href="Food_Nutrition.php">Food & Nutrition</a></li>
            <li><a href="about.php">About Us</a></li>
            <li><a href="login.php">Login / Sign In</a></li>
        </ul>
    </nav>
</header>

<main>
    <section class="register-container">
        <h2>Create an Account</h2>

        <!-- Error Display -->
        <?php if (!empty($errors)): ?>
            <div class="error-box">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form action="register.php" method="POST">
            <label>Full Name:</label>
            <input type="text" name="name" required autocomplete="name">

            <label>CNIC:</label>
            <input type="text" name="cnic" required>

            <label>Email:</label>
            <input type="email" name="email" required autocomplete="email">

            <label>Password:</label>
            <input type="password" name="password" required autocomplete="new-password">

            <label>Confirm Password:</label>
            <input type="password" name="confirm_password" required autocomplete="new-password">

            <label>Phone:</label>
            <input type="text" name="phone" required autocomplete="tel">

            <label>Age:</label>
            <input type="number" name="age" min="0" required>

            <label>Address:</label>
            <input type="text" name="address" required autocomplete="street-address">

            <label>Town:</label>
            <input type="text" name="town">

            <label>Region:</label>
            <input type="text" name="region">

            <label>Postcode:</label>
            <input type="text" name="postcode">

            <label>Country:</label>
            <input type="text" name="country">

            <button type="submit">Create Account</button>
        </form>

        <p>Already have an account? <a href="login.php">Login here</a></p>
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
