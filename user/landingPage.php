<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solano Mayor's Office Appointment System</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Montserrat:wght@700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-blue: #0055a4;
            --primary-green: #28a745;
            --primary-orange: #ff6b35;
            --primary-light: #f8f9fa;
            --primary-dark: #212529;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            color: var(--primary-dark);
            background-color: #f5f7fa;
            overflow-x: hidden;
        }
        
        h1, h2, h3, h4, h5 {
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
        }
        
        /* Navbar */
        .navbar {
            background: linear-gradient(180deg, var(--primary-blue) 0%, #003a75 100%);
            padding: 15px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .navbar-brand {
            display: flex;
            align-items: center;
            color: white !important;
            font-weight: 700;
            font-size: 1.5rem;
        }
        
        .navbar-brand i {
            margin-right: 10px;
            font-size: 1.8rem;
        }
        
        .navbar-nav .nav-link {
            color: rgba(255,255,255,0.85) !important;
            font-weight: 500;
            margin: 0 10px;
            transition: all 0.3s;
        }
        
        .navbar-nav .nav-link:hover,
        .navbar-nav .nav-link.active {
            color: white !important;
        }
        
        .btn-outline-light {
            border-color: white;
            color: white;
            transition: all 0.3s;
        }
        
        .btn-outline-light:hover {
            background: white;
            color: var(--primary-blue);
        }
        
        /* Hero Section */
        .hero {
            background: linear-gradient(135deg, rgba(0,85,164,0.9) 0%, rgba(40,167,69,0.85) 100%);
            background-size: cover;
            background-position: center;
            padding: 100px 0;
            color: white;
            text-align: center;
            position: relative;
        }
        
        .hero-overlay {
            background: rgba(0, 0, 0, 0.4);
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }
        
        .hero-content {
            position: relative;
            z-index: 10;
        }
        
        .hero h1 {
            font-size: 3.5rem;
            margin-bottom: 20px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        
        .hero p {
            font-size: 1.4rem;
            max-width: 700px;
            margin: 0 auto 40px;
            text-shadow: 0 1px 2px rgba(0,0,0,0.2);
        }
        
        .btn-hero {
            background: var(--primary-green);
            border: none;
            padding: 15px 35px;
            font-size: 1.2rem;
            font-weight: 600;
            border-radius: 50px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            transition: all 0.3s;
            color: white;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-hero:hover {
            background: #218838;
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.25);
            color: white;
        }
        
        /* Stats Section */
        .stats-section {
            background: white;
            padding: 60px 0;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        
        .stat-item {
            text-align: center;
            padding: 20px;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-blue);
            margin-bottom: 10px;
        }
        
        .stat-label {
            font-size: 1.1rem;
            color: #6c757d;
        }
        
        /* Services Section */
        .services {
            padding: 80px 0;
            background-color: #f8f9fa;
        }
        
        .section-title {
            position: relative;
            margin-bottom: 50px;
            padding-bottom: 15px;
            text-align: center;
        }
        
        .section-title:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 4px;
            background: var(--primary-green);
            border-radius: 3px;
        }
        
        .service-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            text-align: center;
            height: 100%;
            transition: all 0.3s;
            border: 1px solid rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }
        
        .service-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .service-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 2rem;
            background: rgba(0, 85, 164, 0.1);
            color: var(--primary-blue);
        }
        
        /* How It Works */
        .how-it-works {
            background: var(--primary-light);
            padding: 80px 0;
        }
        
        .step-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            text-align: center;
            height: 100%;
            position: relative;
            border: 1px solid rgba(0,0,0,0.05);
        }
        
        .step-number {
            position: absolute;
            top: -20px;
            left: 50%;
            transform: translateX(-50%);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary-blue);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.2rem;
        }
        
        /* Testimonials */
        .testimonials {
            padding: 80px 0;
            background: linear-gradient(to right, #f8f9fa, #e9ecef);
        }
        
        .testimonial-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            margin-bottom: 30px;
            border: 1px solid rgba(0,0,0,0.05);
            position: relative;
        }
        
        .testimonial-content {
            font-style: italic;
            margin-bottom: 20px;
            position: relative;
        }
        
        .testimonial-content:before {
            content: '"';
            font-size: 4rem;
            position: absolute;
            top: -20px;
            left: -15px;
            color: rgba(0, 85, 164, 0.1);
            font-family: Georgia, serif;
        }
        
        .testimonial-author {
            display: flex;
            align-items: center;
        }
        
        .author-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--primary-green) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            margin-right: 15px;
        }
        
        /* Footer */
        .footer {
            background: linear-gradient(180deg, var(--primary-blue) 0%, #003a75 100%);
            color: white;
            padding: 60px 0 20px;
        }
        
        .footer h5 {
            margin-bottom: 25px;
            position: relative;
            padding-bottom: 10px;
        }
        
        .footer h5:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 50px;
            height: 3px;
            background: var(--primary-green);
            border-radius: 3px;
        }
        
        .footer ul {
            list-style: none;
            padding: 0;
        }
        
        .footer ul li {
            margin-bottom: 10px;
        }
        
        .footer ul li a {
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .footer ul li a:hover {
            color: white;
            padding-left: 5px;
        }
        
        .footer-bottom {
            border-top: 1px solid rgba(255,255,white,0.1);
            padding-top: 20px;
            margin-top: 40px;
            text-align: center;
        }
        
        .social-icons a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255,255,255,0.1);
            color: white;
            margin-right: 10px;
            transition: all 0.3s;
        }
        
        .social-icons a:hover {
            background: var(--primary-green);
            transform: translateY(-3px);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .hero h1 {
                font-size: 2.5rem;
            }
            
            .hero p {
                font-size: 1.1rem;
            }
            
            .stat-number {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark sticky-top">
  <div class="container-fluid">
    <!-- Logo and text side by side with left padding -->
    <div class="d-flex flex-row align-items-center ps-5">
      <img src="images/logooo.png" alt="Company Logo" style="width: 120px; border-radius: 15px;">
      <span class="text-white fw-bold ms-3 fs-3">Solano Appointment System</span>
    </div>

    <!-- Mobile toggler -->
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>

    <!-- Collapsible navigation -->
    <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
      <ul class="navbar-nav align-items-lg-center">
        <li class="nav-item">
          <a class="nav-link active" href="#">Home</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="#services">Services</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="#how-it-works">How It Works</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="#contact">Contact</a>
        </li>
        <li class="nav-item ms-lg-2">
          <a href="login.php" class="btn btn-outline-light me-2">Login</a>
        </li>
        <li class="nav-item">
          <a href="register.php" class="btn btn-light">Register</a>
        </li>
      </ul>
    </div>
  </div>
</nav>





    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-overlay"></div>
        <div class="container hero-content">
            <h1>Solano Mayor's Office Appointment System</h1>
            <p>Schedule your visit to the Mayor's Office quickly and conveniently. Save time and avoid long queues.</p>
            <div class="d-flex justify-content-center gap-3 flex-wrap">
                <a href="login.php" class="btn btn-hero">
                    <i class="bi bi-box-arrow-in-right me-2"></i>Login to Schedule Appointment
                </a>
                <a href="register.php" class="btn btn-outline-light btn-hero" style="background: transparent; border: 2px solid white;">
                    <i class="bi bi-person-plus me-2"></i>Create Account
                </a>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
   

    <!-- Services Section -->
    <section class="services" id="services">
        <div class="container">
            <h2 class="section-title">Our Services</h2>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="service-card">
                        <div class="service-icon">
                            <i class="bi bi-file-earmark-text"></i>
                        </div>
                        <h3>Document Requests</h3>
                        <p>Request official documents, certificates, and permits from the Mayor's Office.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="service-card">
                        <div class="service-icon">
                            <i class="bi bi-building"></i>
                        </div>
                        <h3>Business Permits</h3>
                        <p>Apply for new business permits or renew existing ones with our streamlined process.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="service-card">
                        <div class="service-icon">
                            <i class="bi bi-house-door"></i>
                        </div>
                        <h3>Building Permits</h3>
                        <p>Submit applications for construction permits and building-related concerns.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="service-card">
                        <div class="service-icon">
                            <i class="bi bi-people"></i>
                        </div>
                        <h3>Public Consultations</h3>
                        <p>Schedule meetings with the Mayor or other officials for community concerns.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="service-card">
                        <div class="service-icon">
                            <i class="bi bi-cash-coin"></i>
                        </div>
                        <h3>Tax Payments</h3>
                        <p>Pay local taxes and fees conveniently at the Mayor's Office.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="service-card">
                        <div class="service-icon">
                            <i class="bi bi-chat-dots"></i>
                        </div>
                        <h3>Complaints & Suggestions</h3>
                        <p>Submit your concerns and suggestions directly to the Mayor's Office.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works -->
    <section class="how-it-works" id="how-it-works">
        <div class="container">
            <h2 class="section-title">How Our Appointment System Works</h2>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="step-card">
                        <div class="step-number">1</div>
                        <div class="service-icon">
                            <i class="bi bi-person-check"></i>
                        </div>
                        <h3>Login or Register</h3>
                        <p>Create an account or login to access the appointment system.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="step-card">
                        <div class="step-number">2</div>
                        <div class="service-icon">
                            <i class="bi bi-calendar-check"></i>
                        </div>
                        <h3>Pick Date & Time</h3>
                        <p>Select your preferred date and available time slot for your appointment.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="step-card">
                        <div class="step-number">3</div>
                        <div class="service-icon">
                            <i class="bi bi-envelope-check"></i>
                        </div>
                        <h3>Email Confirmation</h3>
                        <p>Wait for our staff to confirm your appointment via Gmail.</p>
                    </div>
                </div>
            </div>
            
            <!-- Confirmation Process -->
            <div class="confirmation-process">
                <br>
                <h3>Confirmation Process</h3>
                <p class="lead">After you schedule your appointment, our staff will review your request and send a confirmation to your Gmail account within 24 hours.</p>
                <div class="d-flex justify-content-center gap-3 mt-4 flex-wrap">
                    <div class="text-start">
                        <h4><i class="bi bi-envelope me-2"></i> What to expect:</h4>
                        <ul>
                            <li>Confirmation email within 24 hours</li>
                            <li>Appointment details with date/time</li>
                            <li>Required documents list</li>
                            <li>Office location and instructions</li>
                        </ul>
                    </div>
                    <div class="text-start">
                        <h4><i class="bi bi-clock me-2"></i> Processing time:</h4>
                        <ul>
                            <li>Weekdays: Within 24 hours</li>
                            <li>Weekends: Next business day</li>
                            <li>Holidays: Following business day</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

   

    <!-- Footer -->
    <footer class="footer" id="contact">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 mb-4 mb-lg-0">
                    <h5>Solano Mayor's Office</h5>
                    <p>Providing efficient services to the residents of Solano through our appointment system.</p>
                    <div class="social-icons mt-4">
                        <a href="#"><i class="bi bi-facebook"></i></a>
                        <a href="#"><i class="bi bi-twitter"></i></a>
                        <a href="#"><i class="bi bi-instagram"></i></a>
                        <a href="#"><i class="bi bi-envelope"></i></a>
                    </div>
                </div>
                <div class="col-lg-4 mb-4 mb-lg-0">
                    <h5>Quick Links</h5>
                    <ul>
                        <li><a href="#">Home</a></li>
                        <li><a href="#services">Services</a></li>
                        <li><a href="#how-it-works">How It Works</a></li>
                        <li><a href="#testimonials">Testimonials</a></li>
                        <li><a href="login.php">Login</a></li>
                    </ul>
                </div>
                <div class="col-lg-4">
                    <h5>Contact Us</h5>
                    <ul>
                        <li><i class="bi bi-geo-alt me-2"></i> Municipal Hall, Solano, Nueva Vizcaya</li>
                        <li><i class="bi bi-telephone me-2"></i> (078) 123-4567</li>
                        <li><i class="bi bi-envelope me-2"></i> appointments@solano.gov.ph</li>
                        <li><i class="bi bi-clock me-2"></i> Monday-Friday: 8:00 AM - 5:00 PM</li>
                    </ul>
                </div>
            </div>
            <!-- <div class="footer-bottom">
                <p class="mb-0">&copy; 2023 Solano Mayor's Office Appointment System. All Rights Reserved.</p>
            </div> -->
        </div>
    </footer>





    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });
    </script>
</body>
</html>