<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>About Us - BabyCare</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background-color: #f9f9f9;
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


    /* Main Section */
    .container {
      max-width: 1200px;
      margin: 30px auto;
      padding: 20px;
    }

    .row {
      display: flex;
      flex-wrap: wrap;
      margin-bottom: 40px;
      align-items: center;
    }

    .col-3, .col-6 {
      padding: 20px;
      box-sizing: border-box;
    }

    .col-3 {
      flex: 1 1 25%;
      text-align: center;
    }

    .col-6 {
      flex: 1 1 50%;
    }

    .icon {
      font-size: 40px;
      margin-bottom: 10px;
      color: #2575fc;
    }

    h1, h2, h4 {
      margin-bottom: 15px;
      color: #2c3e50;
    }

    p {
      color: #555;
      margin-bottom: 20px;
    }

    img {
      width: 100%;
      max-width: 500px;
      border-radius: 10px;
    }

    .progress {
      background-color: #e0e0e0;
      border-radius: 5px;
      overflow: hidden;
      margin-bottom: 20px;
    }

    .progress-bar {
      background-color: #28a745;
      height: 25px;
      color: white;
      text-align: center;
      line-height: 25px;
      width: 0;
      transition: width 2s ease;
    }

    /* Footer Section */
/* Footer Section */
#footer {
  background: #34495e; /* Dark grey-blue background */
  color: #ecf0f1; /* Light text color */
  padding: 2rem 0; /* Padding for top and bottom */
  margin-top: 3rem; /* Space above the footer */
}

#footer .container {
  display: grid; /* Use a grid layout */
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); /* Create flexible columns */
  gap: 2rem; /* Space between columns */
}

#footer .col h4 {
  margin-bottom: 1rem; /* Space below the heading */
  font-size: 1.2rem; /* Font size for headings */
  color: #ffffff; /* White text for headings */
}

#footer .col p, #footer .col a {
  font-size: 0.9rem; /* Font size for text and links */
  color: #bdc3c7; /* Light grey color for text and links */
  line-height: 1.5; /* Space between lines of text */
}

#footer .col a:hover {
  color: #1abc9c; /* Green color on hover for links */
  text-decoration: underline; /* Underline the link on hover */
}

#footer .footer-bottom {
  text-align: center; /* Center align the footer bottom */
  margin-top: 2rem; /* Space above the footer bottom */
  font-size: 0.8rem; /* Font size for footer text */
  color: #95a5a6; /* Light grey text color */
  border-top: 1px solid #7f8c8d; /* Border line above the footer bottom */
  padding-top: 1rem; /* Padding at the top of footer bottom */
}

/* Responsive Footer */
@media (max-width: 768px) {
  #footer .container {
    flex-direction: column; /* Stack the columns vertically */
    text-align: center; /* Center-align text */
  }
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
      .col-3, .col-6 {
        flex: 1 1 100%;
      }
      #footer .container {
    flex-direction: column; /* Stack the columns vertically */
    text-align: center; /* Center-align text */
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
<main class="container">

  <!-- About Section -->
  <div class="row">
    <div class="col-6">
      <img src="img/e276d33cc0.png" alt="About BabyCare">
    </div>
    <div class="col-6">
      <h1>About BabyCare</h1>
      <p>BabyCare is a free daycare management system designed to simplify the operations of child care centers. We help organize children's records, attendance, meal plans, and communication with parents through a user-friendly platform.</p>
      <p>Our mission is to provide a nurturing environment where children thrive and parents feel confident. We believe in playful learning, healthy living, and building strong communities for a brighter future.</p>
    </div>
  </div>

  <!-- Goals Section -->
  <div class="row">
    <div class="col-3">
      <div class="icon">📘</div>
      <h4>Early Education</h4>
      <p>Focusing on learning through play and curiosity.</p>
    </div>
    <div class="col-3">
      <div class="icon">👤</div>
      <h4>Care & Safety</h4>
      <p>Maintaining safe, secure environments for children.</p>
    </div>
    <div class="col-3">
      <div class="icon">🔥</div>
      <h4>Passionate Team</h4>
      <p>Qualified caregivers committed to excellence.</p>
    </div>
    <div class="col-3">
      <div class="icon">🌐</div>
      <h4>Global Standards</h4>
      <p>Adopting best practices in childcare worldwide.</p>
    </div>
  </div>

  <!-- Thinking and Progress -->
  <div class="row">
    <div class="col-6">
      <h2>Our Philosophy</h2>
      <p>We nurture a child's mind, body, and spirit through structured activities, healthy nutrition, and a loving environment. Every child deserves a chance to shine!</p>
    </div>
    <div class="col-6">
      <h2>Our Success</h2>

      <p>BabyCare Coverage</p>
      <div class="progress">
        <div class="progress-bar" style="width: 90%;">90%</div>
      </div>

      <p>Healthy Growth Support</p>
      <div class="progress">
        <div class="progress-bar" style="width: 80%;">80%</div>
      </div>

      <p>Happy Families</p>
      <div class="progress">
        <div class="progress-bar" style="width: 95%;">95%</div>
      </div>
    </div>
  </div>

</main>

<!-- Footer Section -->
<div id="footer">
  <div class="container">
    <div class="col">
      <h4>Address</h4>
      <p>123 BabyCare Street<br>Happy Town, Kidsland</p>
    </div>
    <div class="col">
      <h4>Pages</h4>
      <p>
        <a href="about.php">About Us</a><br>
        <a href="contact.php">Contact Us</a><br>
        <a href="blog.php">Blogs</a>
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
      <p>BabyCare offers trusted services for children aged 1 month to 5 years. <a href="about.php">Read More</a></p>
    </div>
  </div>
  <div class="footer-bottom">
    &copy; 2025 BabyCare. All Rights Reserved.
  </div>
</div>

<script>
// Animate Progress Bars on Load
window.onload = function() {
  const bars = document.querySelectorAll('.progress-bar');
  bars.forEach(bar => {
    bar.style.width = bar.textContent;
  });
};
</script>

</body>
</html>
