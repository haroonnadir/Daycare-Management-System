<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>BabyCare</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    /* Basic Reset */
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Arial', sans-serif;
      background-color: #fff9f9;
      color: #333;
      line-height: 1.6;
    }
/* Header Styles */
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
      transition: color 0.3s;
    }

    .logo a:hover {
      color: #1abc9c;
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

    nav ul li a:hover, nav ul li a.active {
      color: #1abc9c;
      border-bottom: 2px solid #1abc9c;
    }

/* main css  */
    .container {
      max-width: 1200px;
      margin: 30px auto;
      padding: 20px;
    }

    main > .intro-section {
      text-align: center;
      padding: 40px 20px;
    }

    .intro-section img {
      max-width: 100%;
      height: auto;
      margin-bottom: 20px;
      border-radius: 10px;
      box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }

    .intro-section h1 {
      font-size: 36px;
      margin-bottom: 15px;
      color:rgb(15, 14, 15);
    }

    .intro-section p {
      font-size: 18px;
      color: #666;
      max-width: 900px;
      margin: auto;
    }

    .gallery {
      display: flex;
      flex-wrap: wrap;
      gap: 20px;
      justify-content: center;
      margin: 50px 0;
    }

    .gallery img {
      width: 300px;
      height: 200px;
      object-fit: cover;
      border-radius: 10px;
      box-shadow: 0 4px 8px rgba(0,0,0,0.1);
      transition: transform 0.3s;
    }

    .gallery img:hover {
      transform: scale(1.05);
    }

    .section {
      margin-top: 50px;
      text-align: center;
    }

    .section h2 {
      color:0 4px 8px rgba(0,0,0,0.1);;
      margin-bottom: 20px;
      font-size: 28px;
    }

    .section p {
      max-width: 800px;
      margin: 0 auto;
      color: #555;
    }

    footer {
      background: #2c3e50;
      color: white;
      padding: 50px 20px 20px 20px;
      margin-top: 50px;
    }

    .footer-container {
      max-width: 1200px;
      margin: auto;
      display: flex;
      flex-wrap: wrap;
      gap: 30px;
      justify-content: space-between;
      text-align: center;
    }

    .footer-container .col {
      flex: 1 1 200px;
    }

    .footer-container a {
      color: #fff;
      text-decoration: none;
    }

    .footer-container a:hover {
      text-decoration: underline;
      color: #1abc9c;

    }

    .footer-bottom {
      margin-top: 30px;
      text-align: center;
      font-size: 14px;
      color: #eee;
    }

    @media (max-width: 768px) {
      nav {
        flex-direction: column;
        align-items: flex-start;
      }

      nav ul {
        flex-direction: column;
        width: 100%;
      }

      .gallery img {
        width: 90%;
        height: auto;
      }

      .footer-container {
        flex-direction: column;
        gap: 20px;
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
      <li><a href="Food_Nutrition.php">Food & Nutrition</a></li>
      <li><a href="about.php">About Us</a></li>
      <li><a href="login.php">Login / Sign In</a></li>
    </ul>
  </nav>
</header>

<!-- Main Content -->
<main>

  <div class="intro-section">
    <img src="img/e276d33cc0.png" alt="Welcome to BabyCare">
    <h1>Welcome to BabyCare</h1>
    <p>It is a long established fact that a reader will be distracted by the readable content of a page when looking at its layout. Providing the best care, love, and nutrition for your little ones.</p>
  </div>

  <!-- Image Gallery -->
  <div class="gallery">
    <img src="https://images.unsplash.com/photo-1592194996308-7b43878e84a6?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixlib=rb-1.2.1&q=80&w=400" alt="Baby Care">
    <img src="img/portfolio/vaccination.jpg" alt="Happy Baby">
    <img src="img/portfolio/3a5bb06918.jpg" alt="Baby Smile">
  </div>

  <!-- About Section -->
  <div class="section">
    <h2>About BabyCare</h2>
    <p>
      At BabyCare, we believe every child deserves the best start in life. Our mission is to provide
      world-class care, nutritious meals, fun learning activities, and a safe environment where your
      child can grow, explore, and be happy.
    </p>
  </div>

  <!-- Services Section -->
  <div class="section">
    <h2>Our Services</h2>
    <p>
      We offer professional daycare services, early learning programs, health check-ups, and nutritious
      meal planning for children from 6 months to 5 years old. Our experienced team is dedicated to
      nurturing your child's early development with love and expertise.
    </p>
  </div>

</main>

<!-- Footer Section -->
<footer>
  <div class="footer-container">
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
