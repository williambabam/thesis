<?php
session_start();
require_once __DIR__ . '/config.php';
$user = $_SESSION['user'] ?? null;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Welcome to ResortEase</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root { --primary: #7c3aed; --bg: #f8fafc; }
    body { font-family: 'Inter', sans-serif; background-color: var(--bg); }
    
    .hero-section {
        /* Nice Resort Background Image */
        background: linear-gradient(rgba(0,0,0,0.4), rgba(0,0,0,0.4)), url('https://images.unsplash.com/photo-1571896349842-6e53ce41e8f2?q=80&w=2071&auto=format&fit=crop');
        background-size: cover;
        background-position: center;
        height: 85vh; /* Full screen height */
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        text-align: center;
        position: relative;
    }
    
    .hero-content h1 { font-weight: 800; font-size: 3.5rem; text-shadow: 0 4px 15px rgba(0,0,0,0.3); margin-bottom: 1rem; }
    .hero-content p { font-size: 1.25rem; opacity: 0.95; margin-bottom: 2rem; font-weight: 500; }
    
    .btn-hero {
        padding: 15px 40px;
        font-size: 1.1rem;
        font-weight: 700;
        border-radius: 50px;
        background-color: var(--primary);
        border: none;
        color: white;
        transition: transform 0.2s, background 0.2s;
        box-shadow: 0 4px 15px rgba(124, 58, 237, 0.4);
    }
    .btn-hero:hover { transform: scale(1.05); background-color: #6d28d9; color: white; }

    /* Partner CTA Section */
    .partner-cta {
        background: white;
        padding: 80px 0;
        text-align: center;
    }
    .partner-card {
        background: #f3e8ff;
        border-radius: 20px;
        padding: 50px;
        max-width: 900px;
        margin: 0 auto;
        border: 1px solid #d8b4fe;
    }
    
    /* Footer (Matches Dashboard) */
    .footer-dark { background-color: #191e1f; color: #efefef; padding: 40px 0; font-size: 14px; }
    .footer-brand { font-weight: 800; font-size: 24px; color: white; text-decoration: none; }
    .social-icons a { color: white; margin-right: 15px; font-size: 1.2rem; opacity: 0.8; }
  </style>
</head>
<body>

<?php include __DIR__ . '/navbar.php'; ?>

<div class="hero-section">
    <div class="container hero-content">
        <h1>Looking for a place to stay?</h1>
        <p>Discover the best resorts and hidden gems with ResortEase.</p>
        
        <a href="guest/browse.php" class="btn btn-hero">
            <i class="fas fa-search me-2"></i> Find Your Resort
        </a>
    </div>
</div>

<div class="partner-cta container">
    <div class="partner-card shadow-sm">
        <div class="row align-items-center">
            <div class="col-md-8 text-md-start mb-4 mb-md-0">
                <h2 class="fw-bold text-dark mb-2">Become a Resort Partner</h2>
                <p class="text-muted mb-0" style="font-size: 1.1rem;">
                    Do you own a resort? List your property on ResortEase today and reach thousands of travelers instantly.
                </p>
            </div>
            <div class="col-md-4 text-md-end">
                <a href="partner_register.php" class="btn btn-outline-primary btn-lg rounded-pill fw-bold px-4" style="border-color: #7c3aed; color: #7c3aed;">
                    List Your Property
                </a>
                <div class="mt-2">
                    <a href="partner_login.php" class="text-decoration-none small text-muted">Already a partner? Login here</a>
                </div>
            </div>
        </div>
    </div>
</div>

<footer class="footer-dark">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-6">
                <a href="#" class="footer-brand">ResortEase</a>
                <div class="mt-2">
                    <span class="me-3"><i class="fas fa-globe me-1"></i> Philippines</span>
                </div>
                <div class="social-icons mt-3">
                    <a href="#"><i class="fab fa-facebook"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                </div>
            </div>
            <div class="col-md-6 text-md-end mt-4 mt-md-0">
                <h6 class="text-white fw-bold mb-2">Legal</h6>
                <a href="https://privacy.gov.ph/data-privacy-act/" target="_blank" class="text-secondary text-decoration-none">Data Privacy Act of 2012 <i class="fas fa-external-link-alt small ms-1"></i></a>
            </div>
        </div>
        <div class="text-center text-muted small mt-5 pt-4 border-top border-secondary">
            &copy; <?php echo date('Y'); ?> ResortEase Philippines. All rights reserved.
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>