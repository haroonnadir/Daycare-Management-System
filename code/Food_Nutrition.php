<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Food & Nutrition - Daycare Management System</title>

  <!-- Google Font -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">

  <style>
    body {
      font-family: 'Poppins', sans-serif;
      background-color: #fdfdfd;
      margin: 0;
      padding: 0;
      line-height: 1.6;
      color: #333;
    }

    /* Navbar Styles */
    nav {
      display: flex;
      justify-content: space-between;
      align-items: center;
      background: #2c3e50;
      padding: 1rem 2rem;
    }

    .logo a {
      color: #fff;
      font-size: 1.8rem;
      font-weight: bold;
      text-decoration: none;
    }

    nav ul {
      list-style: none;
      display: flex;
      gap: 1.5rem;
    }

    nav ul li a {
      color: #ecf0f1;
      font-weight: 500;
      text-decoration: none;
      transition: color 0.3s;
    }

    nav ul li a:hover {
      color: #1abc9c;
    }

    /* Main Content */
    .container {
      max-width: 900px;
      margin: 40px auto;
      padding: 20px;
    }

    h1 {
      font-size: 32px;
      margin-bottom: 20px;
      text-align: center;
    }

    h2 {
      color: #444;
      margin-top: 30px;
    }

    p {
      font-size: 16px;
      margin-bottom: 15px;
    }

    .highlight-box {
      background: #f2f9ff;
      border-left: 5px solid #3399ff;
      padding: 20px;
      margin-top: 30px;
      border-radius: 6px;
    }

    /* Footer Section */
    #footer {
      background: #34495e;
      color: #ecf0f1;
      padding: 2rem 0;
      margin-top: 3rem;
    }

    .row {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 2rem;
      max-width: 1200px;
      margin: 0 auto;
      padding: 0 20px;
    }

    .col h4 {
      margin-bottom: 1rem;
      font-size: 1.2rem;
      color: #ffffff;
    }

    .col p, .col a {
      font-size: 0.9rem;
      color: #bdc3c7;
      line-height: 1.5;
      text-decoration: none;
    }

    .col a:hover {
      color: #1abc9c;
      text-decoration: underline;
    }

    .footer-bottom {
      text-align: center;
      margin-top: 2rem;
      font-size: 0.8rem;
      color: #95a5a6;
      border-top: 1px solid #7f8c8d;
      padding-top: 1rem;
    }

    /* Responsive */
    @media (max-width: 768px) {
      nav ul {
        flex-direction: column;
        align-items: center;
      }

      .container {
        padding: 15px;
      }

      h1 {
        font-size: 26px;
      }
    }
  </style>

</head>
<body>

<!-- Header Section -->
<header>
  <nav>
    <div class="logo">
      <a href="index.php">Daycare Management System</a>
    </div>
    <ul>
      <li><a href="BabyCare.php">BabyCare</a></li>
      <li><a href="Diseases.php">Diseases</a></li>
      <li><a href="Vaccination.php">Vaccination</a></li>
      <li><a href="Food_Nutrition.php" class="active">Food & Nutrition</a></li>
      <li><a href="about.php">About Us</a></li>
      <li><a href="login.php">Login / Sign In</a></li>
    </ul>
  </nav>
</header>

<!-- Main Content -->
<main>
  <div class="container">
    <h1>Food & Nutrition</h1>

    <h2>Abstract / Introduction</h2>
    <p>
      Early education and care systems play a very significant role in the growth of children,
      preparing them for school and warranting parents the opportunity to engage in the workforce.
    </p>
    <p>
      It is believed that children need a warm, safe, colorful environment and diversified experiences
      that focus attention on <strong>‘play’</strong>. By making these things available, a child will grow
      and develop at a pace that is just right for them.
    </p>

    <div class="highlight-box">
      <p>
        In this project, you will create a website for a <strong>Daycare Management System</strong>. This system
        will organize the operations of a daycare facility by providing a centralized platform for
        managing children’s records, attendance, meal plans, activities, and staff schedules.
      </p>
      <p>
        The system will also allow parents to register their children, view daily updates,
        and communicate with the daycare staff efficiently. The goal is to simplify administrative tasks,
        enhance communication between the daycare and parents, and ensure the safety and well-being of the children.
      </p>
    </div>
  </div>
</main>

<!-- Footer -->
<footer id="footer">
  <div class="row">
    <div class="col">
      <h4>Address</h4>
      <p>123 Babycare Street<br>Happy Town, Kidsland</p>
    </div>

    <div class="col">
      <h4>Pages</h4>
      <p>
        <a href="about.php">About Us</a><br>
        <a href="#">Contact Us</a><br>
        <a href="#">Blogs</a>
      </p>
    </div>

    <div class="col">
      <h4>Social Links</h4>
      <p>
        <a href="http://facebook.com" target="_blank">Facebook</a><br>
        <a href="http://twitter.com" target="_blank">Twitter</a><br>
        <a href="http://instagram.com" target="_blank">Instagram</a>
      </p>
    </div>

    <div class="col">
      <h4>About BabyCare</h4>
      <p>
        BabyCare offers services for children aged 1 month to 5 years.<br>
        <a href="about.php">Details</a>
      </p>
    </div>
  </div>

  <div class="footer-bottom">
    <p>&copy; 2025 Daycare Management System. All rights reserved.</p>
  </div>
</footer>

</body>
</html>
