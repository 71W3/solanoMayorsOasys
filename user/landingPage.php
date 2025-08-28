<?php
// Enable output buffering and compression for better performance
ob_start();
if (extension_loaded('zlib') && !ob_get_level()) {
    ob_start('ob_gzhandler');
}

// Set caching headers
header('Cache-Control: public, max-age=3600'); // Cache for 1 hour
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 3600) . ' GMT');
header('Vary: Accept-Encoding');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Solano Mayor's Office Appointment System - Schedule appointments efficiently and skip waiting lines">
    <meta name="keywords" content="Solano, Mayor, Appointment, Government, Services, Nueva Vizcaya">
    <title>Solano Mayor's Office Appointment System</title>
    
    <!-- Preload critical resources -->
    <link rel="preload" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" as="style">
    <link rel="preload" href="userStyles/landingPage.css" as="style">
    <link rel="preload" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Space+Grotesk:wght@400;500;600;700&display=swap" as="style">
    
    <!-- DNS prefetch for external resources -->
    <link rel="dns-prefetch" href="//cdn.jsdelivr.net">
    <link rel="dns-prefetch" href="//fonts.googleapis.com">
    <link rel="dns-prefetch" href="//fonts.gstatic.com">
    
    <!-- Critical CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="userStyles/landingPage.css">
</head>
<body class="loading">
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <div class="d-flex align-items-center">
                <div class="logo-container d-flex align-items-center me-3">
                    <div class="logo-icon">
                        <img src="images/logooo.png" alt="Solano Logo" class="responsive" loading="lazy" width="50" height="50">
                    </div>
                </div>
                <a class="navbar-brand" href="#">
                    <span>Solano Appointment System</span>
                </a>
            </div>

            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" style="box-shadow: none;">
                <i class="bi bi-list"></i>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-lg-center">
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
                    <li class="nav-item ms-lg-3">
                        <a href="login.php" class="btn-outline-modern me-2">
                            <i class="bi bi-box-arrow-in-right"></i>
                            Login
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="register.php" class="btn-modern">
                            <i class="bi bi-person-plus"></i>
                            Register
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-10 hero-content text-center">
                    <h1>Streamlined Appointment System for Solano Mayor's Office</h1>
                    <p>Book your appointment efficiently and skip the waiting lines. Experience seamless government service scheduling.</p>
                    <div class="hero-buttons d-flex justify-content-center gap-3 flex-wrap">
                        <a href="login.php" class="btn-modern">
                            <i class="bi bi-calendar-check"></i>
                            Schedule Appointment
                        </a>
                        <a href="register.php" class="btn-outline-modern">
                            <i class="bi bi-person-plus"></i>
                            Create Account
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <div class="stat-item">
                        <div class="stat-number" data-count="2500">0</div>
                        <div class="stat-label">Appointments Scheduled</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-item">
                        <div class="stat-number" data-count="98">0</div>
                        <div class="stat-label">Satisfaction Rate (%)</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-item">
                        <div class="stat-number" data-count="45">0</div>
                        <div class="stat-label">Average Wait Time Reduced (min)</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Services Section -->
    <section class="services" id="services">
        <div class="container">
            <div class="section-title">
                <h2>Available Services</h2>
                <p>Comprehensive government services available through our appointment system</p>
            </div>
            <div class="row g-4">
                <div class="col-lg-4 col-md-6">
                    <div class="service-card">
                        <div class="service-icon">
                            <i class="bi bi-file-earmark-text"></i>
                        </div>
                        <h3>Document Services</h3>
                        <p>Request certificates, permits, and official documents with streamlined processing.</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="service-card">
                        <div class="service-icon">
                            <i class="bi bi-building"></i>
                        </div>
                        <h3>Business Permits</h3>
                        <p>Fast-track business permit applications and renewals for local entrepreneurs.</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="service-card">
                        <div class="service-icon">
                            <i class="bi bi-house-door"></i>
                        </div>
                        <h3>Construction Permits</h3>
                        <p>Efficient processing of building permits and construction-related applications.</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="service-card">
                        <div class="service-icon">
                            <i class="bi bi-people"></i>
                        </div>
                        <h3>Public Consultations</h3>
                        <p>Direct meetings with officials for community concerns and public matters.</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="service-card">
                        <div class="service-icon">
                            <i class="bi bi-cash-coin"></i>
                        </div>
                        <h3>Tax Services</h3>
                        <p>Convenient scheduling for tax payments and related financial transactions.</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="service-card">
                        <div class="service-icon">
                            <i class="bi bi-headset"></i>
                        </div>
                        <h3>Support Services</h3>
                        <p>General inquiries, complaints, and assistance with government procedures.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works -->
    <section class="how-it-works" id="how-it-works">
        <div class="container">
            <div class="section-title">
                <h2>How It Works</h2>
                <p>Simple steps to schedule your appointment</p>
            </div>
            <div class="row g-4 mb-4">
                <div class="col-lg-4">
                    <div class="step-card">
                        <div class="step-number">1</div>
                        <div class="service-icon">
                            <i class="bi bi-person-check"></i>
                        </div>
                        <h3>Create Account</h3>
                        <p>Register with your basic information or login to your existing account.</p>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="step-card">
                        <div class="step-number">2</div>
                        <div class="service-icon">
                            <i class="bi bi-calendar-event"></i>
                        </div>
                        <h3>Select Service & Time</h3>
                        <p>Choose your required service and pick from available appointment slots.</p>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="step-card">
                        <div class="step-number">3</div>
                        <div class="service-icon">
                            <i class="bi bi-check-circle"></i>
                        </div>
                        <h3>Confirmation</h3>
                        <p>Receive email confirmation with appointment details and required documents.</p>
                    </div>
                </div>
            </div>
            
            <div class="confirmation-process">
                <div class="row align-items-center">
                    <div class="col-lg-6">
                        <h3>Appointment Confirmation Process</h3>
                        <p class="mb-4">Our staff reviews and confirms appointments within 24 hours during business days.</p>
                        <div class="row">
                            <div class="col-sm-6 mb-3 mb-sm-0">
                                <h5><i class="bi bi-envelope me-2"></i>Email Notification</h5>
                                <ul class="list-unstyled">
                                    <li>• Confirmation within 24 hours</li>
                                    <li>• Appointment details</li>
                                    <li>• Required documents</li>
                                    <li>• Office directions</li>
                                </ul>
                            </div>
                            <div class="col-sm-6">
                                <h5><i class="bi bi-clock me-2"></i>Processing Time</h5>
                                <ul class="list-unstyled">
                                    <li>• Weekdays: Same day</li>
                                    <li>• Weekends: Next business day</li>
                                    <li>• Holidays: Following workday</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6 text-center">
                        <div class="service-icon mx-auto" style="width: 120px; height: 120px; font-size: 3rem;">
                            <i class="bi bi-envelope-check"></i>
                        </div>
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
                    <p class="mb-4" style="color: rgba(255, 255, 255, 0.7);">Modernizing government services through efficient appointment scheduling and digital solutions.</p>
                    <div class="social-icons">
                        <a href="#" aria-label="Facebook"><i class="bi bi-facebook"></i></a>
                        <a href="#" aria-label="Twitter"><i class="bi bi-twitter"></i></a>
                        <a href="#" aria-label="Instagram"><i class="bi bi-instagram"></i></a>
                        <a href="#" aria-label="Email"><i class="bi bi-envelope"></i></a>
                    </div>
                </div>
                <div class="col-lg-4 mb-4 mb-lg-0">
                    <h5>Quick Links</h5>
                    <ul>
                        <li><a href="#">Home</a></li>
                        <li><a href="#services">Services</a></li>
                        <li><a href="#how-it-works">How It Works</a></li>
                        <li><a href="login.php">Login</a></li>
                        <li><a href="register.php">Register</a></li>
                    </ul>
                </div>
                <div class="col-lg-4">
                    <h5>Contact Information</h5>
                    <ul>
                        <li><i class="bi bi-geo-alt me-2"></i>Municipal Hall, Solano, Nueva Vizcaya</li>
                        <li><i class="bi bi-telephone me-2"></i>(078) 123-4567</li>
                        <li><i class="bi bi-envelope me-2"></i>appointments@solano.gov.ph</li>
                        <li><i class="bi bi-clock me-2"></i>Mon-Fri: 8:00 AM - 5:00 PM</li>
                    </ul>
                </div>
            </div>
            <hr style="border-color: rgba(255, 255, 255, 0.1); margin: 2rem 0 1rem;">
            <div class="text-center">
                <p class="mb-0" style="color: rgba(255, 255, 255, 0.6);">&copy; 2023 Solano Mayor's Office. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Optimized JavaScript loading -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" defer></script>
    <script src="javascripts/landingPage.js" defer></script>

</body>
</html>