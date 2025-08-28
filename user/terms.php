<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms of Service - Solano Mayor's Office Appointment System</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Space+Grotesk:wght@400;500;600;700&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="userStyles/register.css">
    <style>
        .terms-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 2rem;
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }
        .terms-header {
            text-align: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f1f5f9;
        }
        .terms-content h3 {
            color: #0055a4;
            margin-top: 2rem;
            margin-bottom: 1rem;
        }
        .terms-content p, .terms-content li {
            line-height: 1.6;
            color: #64748b;
            margin-bottom: 0.75rem;
        }
        .terms-content ul {
            padding-left: 1.5rem;
        }
        .last-updated {
            background: #f8fafc;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            font-size: 0.9rem;
            color: #64748b;
        }
    </style>
</head>
<body>
    <a href="register.php" class="back-home">
        <i class="bi bi-arrow-left"></i>
        <span>Back to Registration</span>
    </a>
    
    <div class="terms-container">
        <div class="terms-header">
            <div class="logo-container mb-3">
                <div class="logo-icon no-border">
                    <img src="images/logooo.png" alt="Solano Logo" onerror="this.style.display='none'; this.parentElement.innerHTML='<div style=\'width:50px;height:50px;background:#0055a4;border-radius:12px;display:flex;align-items:center;justify-content:center;color:white;font-weight:bold;font-size:20px;\'>S</div>';">
                </div>
            </div>
            <h2>Terms of Service</h2>
            <p class="text-muted">Solano Mayor's Office Appointment System</p>
        </div>
        
        <div class="last-updated">
            <i class="bi bi-calendar3 me-2"></i>
            <strong>Last Updated:</strong> <?php echo date('F j, Y'); ?>
        </div>
        
        <div class="terms-content">
            <h3>1. Acceptance of Terms</h3>
            <p>By accessing and using the Solano Mayor's Office Appointment System ("the System"), you accept and agree to be bound by the terms and provision of this agreement. If you do not agree to abide by the above, please do not use this service.</p>
            
            <h3>2. Use License</h3>
            <p>Permission is granted to temporarily use the System for personal, non-commercial transitory viewing and appointment scheduling only. This is the grant of a license, not a transfer of title, and under this license you may not:</p>
            <ul>
                <li>Modify or copy the materials</li>
                <li>Use the materials for any commercial purpose or for any public display (commercial or non-commercial)</li>
                <li>Attempt to decompile or reverse engineer any software contained in the System</li>
                <li>Remove any copyright or other proprietary notations from the materials</li>
            </ul>
            
            <h3>3. Account Registration</h3>
            <p>To use certain features of the System, you must register for an account. You agree to:</p>
            <ul>
                <li>Provide accurate, current, and complete information during registration</li>
                <li>Maintain the security of your password and identification</li>
                <li>Notify us immediately of any unauthorized use of your account</li>
                <li>Accept responsibility for all activities that occur under your account</li>
            </ul>
            
            <h3>4. Appointment Scheduling</h3>
            <p>The System allows you to schedule appointments with the Mayor's Office. You agree to:</p>
            <ul>
                <li>Provide accurate information when scheduling appointments</li>
                <li>Attend scheduled appointments or cancel with reasonable notice</li>
                <li>Respect the time and resources of the Mayor's Office staff</li>
                <li>Follow all applicable laws and regulations during your visit</li>
            </ul>
            
            <h3>5. Privacy and Data Protection</h3>
            <p>Your privacy is important to us. Please review our Privacy Policy, which also governs your use of the System, to understand our practices.</p>
            
            <h3>6. Prohibited Uses</h3>
            <p>You may not use the System:</p>
            <ul>
                <li>For any unlawful purpose or to solicit others to perform unlawful acts</li>
                <li>To violate any international, federal, provincial, or state regulations, rules, laws, or local ordinances</li>
                <li>To infringe upon or violate our intellectual property rights or the intellectual property rights of others</li>
                <li>To harass, abuse, insult, harm, defame, slander, disparage, intimidate, or discriminate</li>
                <li>To submit false or misleading information</li>
            </ul>
            
            <h3>7. Service Availability</h3>
            <p>We strive to maintain the System's availability but cannot guarantee uninterrupted service. The System may be temporarily unavailable due to maintenance, updates, or technical issues.</p>
            
            <h3>8. Limitation of Liability</h3>
            <p>In no event shall the Solano Mayor's Office or its suppliers be liable for any damages (including, without limitation, damages for loss of data or profit, or due to business interruption) arising out of the use or inability to use the System.</p>
            
            <h3>9. Modifications</h3>
            <p>The Solano Mayor's Office may revise these terms of service at any time without notice. By using this System, you are agreeing to be bound by the then current version of these terms of service.</p>
            
            <h3>10. Governing Law</h3>
            <p>These terms and conditions are governed by and construed in accordance with the laws of the Philippines and you irrevocably submit to the exclusive jurisdiction of the courts in that state or location.</p>
            
            <h3>11. Contact Information</h3>
            <p>If you have any questions about these Terms of Service, please contact the Solano Mayor's Office through the official channels provided in the System.</p>
        </div>
        
        <div class="text-center mt-4">
            <a href="register.php" class="btn btn-primary">
                <i class="bi bi-arrow-left me-2"></i>Back to Registration
            </a>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
