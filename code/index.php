<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Daycare Management System</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
  <style>
    /* Reset and Base Styles */
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    body {
      font-family: 'Poppins', sans-serif;
      background: #f4f7fa;
      color: #333;
      line-height: 1.6;
    }
    a {
      color: #3498db;
      text-decoration: none;
    }
    a:hover {
      text-decoration: underline;
    }
    img {
      width: 100%;
      height: auto;
      border-radius: 8px;
    }

    /* Navbar */
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
    main {
      padding: 2rem;
    }
    section {
      margin-bottom: 3rem;
    }
    section h1 {
      font-size: 2rem;
      color: #2c3e50;
      margin-bottom: 1rem;
      text-align: center;
    }
    section p {
      font-size: 1rem;
      color: #555;
      text-align: center;
      max-width: 800px;
      margin: 0 auto 1rem;
    }
    section > div {
      margin-bottom: 2rem;
    }

    /* Cards Section */
    .cards {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 2rem;
      margin-top: 2rem;
    }
    .card {
      background: #fff;
      border-radius: 10px;
      padding: 1rem;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
      transition: transform 0.3s;
    }
    .card:hover {
      transform: translateY(-5px);
    }
    .card img {
      border-radius: 10px;
      margin-bottom: 1rem;
    }
    .card p {
      text-align: left;
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
        <li><a href="Food & Nutrition.php">Food & Nutrition</a></li>
        <li><a href="about.php">About Us</a></li>
        <li><a href="login.php">Login / Sign In</a></li>
      </ul>
    </nav>
  </header>

  <!-- Main Content -->
  <main>
    <!-- Welcome Section -->
    <section>
      <div>
        <img src="https://assets.futuregenerali.in/blogs-image/health/day-care-treatment-explained-what-your-health-insurance-should-cover.webp" alt="Welcome Image">
      </div>
      <div>
        <p>Hello everybody. I'm Stanley, a free handsome bootstrap theme coded by BlackTie.co. A really simple theme for those wanting to showcase their work with a cute & clean style. <a href="./about.php">See More</a></p>
      </div>
    </section>

    <!-- Cards Section -->
    <section class="cards">
      <div class="card">
        <img src="img/babycare.png" alt="Card Image">
        <p>Information about baby care, safety measures, hygiene practices, and daily routine planning to ensure a nurturing environment.</p>
      </div>
      <div class="card">
        <img src="img/portfolio/vaccination.jpg" alt="Card Image">
        <p>Early identification and management of childhood diseases; monitoring signs, symptoms, and health advice for young children.</p>
      </div>
      <div class="card">
        <img src="img/portfolio/port02.jpg" alt="Card Image">
        <p>Nutrition tips for children including meal plans, vitamin requirements, and growth tracking essentials for healthy development.</p>
      </div>
    </section>
<hr>
    <!-- Special Children Section -->
    <section>
      <h1>Special Children</h1>
      <p>It is a long established fact that a reader will be distracted by the readable content of a page when looking at its layout. <a href="./about.php">See More</a></p>
    </section>

    <!-- Add this to your service pages or footer -->
<div class="social-sharing">
    <h3>Love our services? Share with friends!</h3>
    <div class="share-buttons">
        <!-- Facebook -->
        <a href="#" class="share-btn facebook" data-platform="facebook">
            <i class="fab fa-facebook-f"></i>
        </a>
        
        <!-- Twitter -->
        <a href="#" class="share-btn twitter" data-platform="twitter">
            <i class="fab fa-twitter"></i>
        </a>
        
        <!-- WhatsApp -->
        <a href="#" class="share-btn whatsapp" data-platform="whatsapp">
            <i class="fab fa-whatsapp"></i>
        </a>
        
        <!-- Email -->
        <a href="#" class="share-btn email" data-platform="email">
            <i class="fas fa-envelope"></i>
        </a>
        
        <!-- Copy Link -->
        <a href="#" class="share-btn link" data-platform="copy">
            <i class="fas fa-link"></i>
        </a>
    </div>
</div>

<style>
    .social-sharing {
        margin: 20px 0;
        padding: 15px;
        background: #f8f9fa;
        border-radius: 8px;
        text-align: center;
    }
    
    .social-sharing h3 {
        margin-bottom: 15px;
        color: #333;
        font-size: 1.1rem;
    }
    
    .share-buttons {
        display: flex;
        justify-content: center;
        gap: 10px;
    }
    
    .share-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        color: white;
        text-decoration: none;
        transition: transform 0.2s;
    }
    
    .share-btn:hover {
        transform: scale(1.1);
    }
    
    .facebook { background: #3b5998; }
    .twitter { background: #1da1f2; }
    .whatsapp { background: #25d366; }
    .email { background: #666666; }
    .link { background: #6c757d; }
    
    /* Tooltip for copy link */
    .tooltip {
        position: relative;
        display: inline-block;
    }
    
    .tooltip .tooltiptext {
        visibility: hidden;
        width: 120px;
        background-color: #555;
        color: #fff;
        text-align: center;
        border-radius: 6px;
        padding: 5px;
        position: absolute;
        z-index: 1;
        bottom: 125%;
        left: 50%;
        margin-left: -60px;
        opacity: 0;
        transition: opacity 0.3s;
    }
    
    .tooltip .tooltiptext::after {
        content: "";
        position: absolute;
        top: 100%;
        left: 50%;
        margin-left: -5px;
        border-width: 5px;
        border-style: solid;
        border-color: #555 transparent transparent transparent;
    }
    
    .tooltip:hover .tooltiptext {
        visibility: visible;
        opacity: 1;
    }
</style>

<!-- Font Awesome for icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">

<script>
document.addEventListener('DOMContentLoaded', function() {
    const shareButtons = document.querySelectorAll('.share-btn');
    const pageUrl = encodeURIComponent(window.location.href);
    const pageTitle = encodeURIComponent(document.title);
    const daycareName = "ABC Daycare"; // Replace with your daycare name
    
    shareButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const platform = this.getAttribute('data-platform');
            
            let shareUrl;
            switch(platform) {
                case 'facebook':
                    shareUrl = `https://www.facebook.com/sharer/sharer.php?u=${pageUrl}`;
                    window.open(shareUrl, 'facebook-share-dialog', 'width=800,height=600');
                    break;
                    
                case 'twitter':
                    shareUrl = `https://twitter.com/intent/tweet?url=${pageUrl}&text=${pageTitle}&hashtags=Daycare,ChildCare`;
                    window.open(shareUrl, 'twitter-share-dialog', 'width=800,height=600');
                    break;
                    
                case 'whatsapp':
                    shareUrl = `https://wa.me/?text=${pageTitle}%20${pageUrl}`;
                    window.open(shareUrl, 'whatsapp-share-dialog', 'width=800,height=600');
                    break;
                    
                case 'email':
                    shareUrl = `mailto:?subject=${daycareName}%20-%20${pageTitle}&body=Check%20out%20this%20great%20daycare%20service:%20${pageUrl}`;
                    window.location.href = shareUrl;
                    break;
                    
                case 'copy':
                    navigator.clipboard.writeText(window.location.href).then(() => {
                        // Show tooltip or alert
                        alert('Link copied to clipboard!');
                    }).catch(err => {
                        console.error('Could not copy text: ', err);
                    });
                    break;
            }
        });
    });
});
</script>
  </main>

  <!-- Footer Section -->
  <div id="footer">
    <div class="container">
      <div class="row">
        <!-- Address -->
        <div class="col">
          <h4>Address</h4>
          <p>123 Babycare Street<br>Happy Town, Kidsland</p>
        </div>
        <!-- Pages -->
        <div class="col">
          <h4>Pages</h4>
          <p>
            <a href="about.php">About Us</a><br/>
            <a href="contact.php">Contact Us</a><br/>
            <a href="blog.php">Blogs</a>
          </p>
        </div>
        <!-- Social Links -->
        <div class="col">
          <h4>Social Links</h4>
          <p>
            <a href="http://facebook.com" target="_blank">Facebook</a><br/>
            <a href="http://twitter.com" target="_blank">Twitter</a><br/>
            <a href="http://instagram.com" target="_blank">Instagram</a>
          </p>
        </div>
        <!-- About -->
        <div class="col">
          <h4>About Babycare</h4>
          <p>Babycare offers services for children aged 1 month to 5 years. <a href="about.php">Details</a></p>
        </div>
      </div>

      <!-- Footer Bottom -->
      <div class="footer-bottom">
        <p>&copy; <?php echo date("Y"); ?> Daycare Management System. All rights reserved.</p>
      </div>
    </div>
  </div>

</body>
</html>
