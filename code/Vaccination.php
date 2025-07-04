<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Baby Vaccination Schedule | BabyCare - Protect Your Child's Health</title>
  <meta name="description" content="View the recommended baby vaccination schedule to keep your child safe from preventable diseases. Trusted by parents everywhere.">

  <!-- Google Font -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">

  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Poppins', sans-serif;
      background: #eafafc;
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

    /* Main Content */
    main {
      padding: 40px 20px;
      text-align: center;
      animation: fadeIn 1s ease;
    }

    main h1 {
      font-size: 32px;
      margin-bottom: 10px;
    }

    main p {
      margin-bottom: 30px;
      font-size: 18px;
    }

    /* Container */
    .container {
      max-width: 1100px;
      margin: auto;
      padding: 20px;
    }

    /* Table Styles */
    table {
      width: 100%;
      border-collapse: collapse;
      background: white;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
      margin-top: 20px;
    }

    th, td {
      padding: 15px;
      text-align: left;
      border-bottom: 1px solid #ddd;
    }

    th {
      background-color: #00c6ff;
      color: white;
    }

    tr:hover {
      background-color: #f1f9ff;
    }

    /* Footer Section */
#footer {
  background: #34495e;
  color: #ecf0f1;
  padding: 2rem 0;
  margin-top: 3rem;
}

.container {
  width: 90%;
  margin: auto;
}

.row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 2rem;
}

.col h4 {
  margin-bottom: 1rem;
  font-size: 1.2rem;
  color: #ffffff;
}

.col p,
.col a {
  font-size: 0.9rem;
  color: #bdc3c7;
  line-height: 1.5;
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


    /* Responsive Table for Mobile */
    @media (max-width: 768px) {
      nav ul {
        flex-direction: column;
        align-items: center;
      }

      table, thead, tbody, th, td, tr {
        display: block;
      }

      th {
        text-align: right;
        padding-right: 10px;
      }

      td {
        padding-left: 10px;
        position: relative;
        text-align: right;
      }

      td::before {
        content: attr(data-label);
        position: absolute;
        left: 10px;
        font-weight: bold;
        text-align: left;
      }

      footer .container {
        flex-direction: column;
        align-items: center;
        text-align: center;
      }
    }

    /* Animation */
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(20px); }
      to { opacity: 1; transform: translateY(0); }
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
  <div>
    <h1>Baby Vaccination Schedule</h1>
    <p>Protect your child from preventable diseases with timely vaccinations.</p>
  </div>

  <div class="container">
    <p>Vaccines are essential for your baby’s health and immunity. Below is a general schedule for the most common vaccinations during the first year and beyond. Please consult your pediatrician for the exact timing and local guidelines.</p>

    <table>
      <thead>
        <tr>
          <th>Age</th>
          <th>Vaccination</th>
          <th>Purpose</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td data-label="Age">Birth</td>
          <td data-label="Vaccination">BCG, Hepatitis B (1st dose)</td>
          <td data-label="Purpose">Tuberculosis, Liver infection prevention</td>
        </tr>
        <tr>
          <td data-label="Age">6 Weeks</td>
          <td data-label="Vaccination">DTaP, IPV, Hib, Hep B (2nd dose), PCV</td>
          <td data-label="Purpose">Diphtheria, Tetanus, Polio, Hepatitis B, Pneumonia</td>
        </tr>
        <tr>
          <td data-label="Age">10 Weeks</td>
          <td data-label="Vaccination">DTaP, IPV, Hib, PCV</td>
          <td data-label="Purpose">Booster for previous vaccines</td>
        </tr>
        <tr>
          <td data-label="Age">14 Weeks</td>
          <td data-label="Vaccination">DTaP, IPV, Hib, Hep B (3rd dose), PCV</td>
          <td data-label="Purpose">Continued protection from early diseases</td>
        </tr>
        <tr>
          <td data-label="Age">6 Months</td>
          <td data-label="Vaccination">Influenza (yearly)</td>
          <td data-label="Purpose">Flu protection</td>
        </tr>
        <tr>
          <td data-label="Age">9 Months</td>
          <td data-label="Vaccination">MMR (1st dose)</td>
          <td data-label="Purpose">Measles, Mumps, Rubella</td>
        </tr>
        <tr>
          <td data-label="Age">12 Months</td>
          <td data-label="Vaccination">Hepatitis A (1st dose)</td>
          <td data-label="Purpose">Liver infection prevention</td>
        </tr>
        <tr>
          <td data-label="Age">15–18 Months</td>
          <td data-label="Vaccination">MMR (2nd dose), DTaP, Hib, PCV</td>
          <td data-label="Purpose">Booster for previous vaccines</td>
        </tr>
      </tbody>
    </table>
  </div>
</main>

<!-- Footer Section -->
<footer id="footer">
  <div class="container">
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
        <p>BabyCare offers services for children aged 1 month to 5 years.<br>
        <a href="about.php">Details</a></p>
      </div>
    </div> <!-- row ends here -->
  </div> <!-- container ends here -->

  <div class="footer-bottom">
    <p>&copy; 2025 Daycare Management System. All rights reserved.</p>
  </div>
</footer>


</body>
</html>