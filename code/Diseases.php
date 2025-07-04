<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Common Baby Diseases - BabyCare</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: #f0f8ff;
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
    .container {
      max-width: 1200px;
      margin: 40px auto;
      padding: 20px;
    }

    h1 {
      font-size: 32px;
      text-align: center;
      margin-bottom: 10px;
    }

    p {
      margin-bottom: 10px;
    }

    .disease-card {
      background: white;
      border-radius: 10px;
      box-shadow: 0 4px 8px rgba(0,0,0,0.1);
      margin-bottom: 30px;
      padding: 20px;
      display: flex;
      flex-wrap: wrap;
      align-items: center;
    }

    .disease-card img {
      width: 150px;
      height: 150px;
      object-fit: cover;
      margin-right: 20px;
      border-radius: 10px;
    }

    .disease-card-content {
      flex: 1;
    }

    h2 {
      margin-bottom: 10px;
      color: #ff7e5f;
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

    @media (max-width: 768px) {
      .disease-card {
        flex-direction: column;
        text-align: center;
      }

      .disease-card img {
        margin: 0 0 15px 0;
      }

      nav ul {
        flex-direction: column;
      }

      nav ul li {
        margin: 10px 0;
      }

      .footer-container {
        flex-direction: column;
        align-items: center;
      }

      .footer-container .col {
        margin-bottom: 30px;
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
      <li><a href="Diseases.php" class="active">Diseases</a></li>
      <li><a href="Vaccination.php">Vaccination</a></li>
      <li><a href="Food_Nutrition.php">Food & Nutrition</a></li>
      <li><a href="about.php">About Us</a></li>
      <li><a href="login.php">Login / Sign In</a></li>
    </ul>
  </nav>
</header>

<!-- Main Content -->
<main>
  <div class="container">
    <h1>Common Diseases in Babies</h1>
    <p style="text-align: center;">Learn about frequent illnesses and how to care for your little one.</p>

    <!-- Disease Cards -->
    <div class="disease-card">
      <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSJ4kfOfpDBRdgqNu2Jd6uhfkLaUpT1qYbfgsWpeYz_Wv13IgtZYwBvrXU89QhHVNWev9k&usqp=CAU" alt="Cold Image">
      <div class="disease-card-content">
        <h2>Common Cold</h2>
        <p>Babies often catch colds as their immune systems develop. Symptoms include a runny nose, cough, and mild fever.</p>
        <p><strong>Care:</strong> Keep them hydrated, use a humidifier, and consult a doctor if symptoms worsen.</p>
      </div>
    </div>

    <div class="disease-card">
      <img src="data:image/jpeg;base64,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" alt="Flu Image">
      <div class="disease-card-content">
        <h2>Influenza (Flu)</h2>
        <p>Flu is a contagious respiratory infection. It can cause fever, chills, and muscle aches in babies.</p>
        <p><strong>Care:</strong> Offer plenty of fluids, rest, and follow pediatrician advice on medication if needed.</p>
      </div>
    </div>

    <div class="disease-card">
      <img src="https://media.istockphoto.com/id/1366367173/vector/little-boy-sitting-on-the-toilet.jpg?s=612x612&w=0&k=20&c=QbCqZxoA_6cemn-XkdZ4ubVAooztTIOUVRZEUd-jGfY=" alt="Diarrhea Image">
      <div class="disease-card-content">
        <h2>Diarrhea</h2>
        <p>Loose and watery stools are common in babies due to infections, food intolerance, or teething.</p>
        <p><strong>Care:</strong> Maintain hydration with oral rehydration solutions and visit a doctor if severe.</p>
      </div>
    </div>

    <div class="disease-card">
      <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQ0c5nVnPRkGcRYQHUhCF5OXpY2CQAO11L3aw&s" alt="Chickenpox Image">
      <div class="disease-card-content">
        <h2>Chickenpox</h2>
        <p>Chickenpox causes itchy blisters and fever. It's highly contagious among young children.</p>
        <p><strong>Care:</strong> Keep nails trimmed to avoid skin infections and consult a doctor for care advice.</p>
      </div>
    </div>

  </div>
</main>

<!-- Footer -->
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
        <p>BabyCare offers services for children aged 1 month to 5 years.
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
