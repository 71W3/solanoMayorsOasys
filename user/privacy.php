<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy - Solano Mayor's Office Appointment System</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Space+Grotesk:wght@400;500;600;700&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="userStyles/register.css">
    <style>
        .privacy-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 2rem;
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }
        .privacy-header {
            text-align: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f1f5f9;
        }
        .privacy-content h3 {
            color: #0055a4;
            margin-top: 2rem;
            margin-bottom: 1rem;
        }
        .privacy-content p, .privacy-content li {
            line-height: 1.6;
            color: #64748b;
            margin-bottom: 0.75rem;
        }
        .privacy-content ul {
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
        .highlight-box {
            background: #eff6ff;
            border-left: 4px solid #0055a4;
            padding: 1rem;
            margin: 1rem 0;
            border-radius: 0 8px 8px 0;
        }
    </style>
</head>
<body>
    <a href="register.php" class="back-home">
        <i class="bi bi-arrow-left"></i>
        <span>Back to Registration</span>
    </a>
    
    <div class="privacy-container">
        <div class="privacy-header">
            <div class="logo-container mb-3">
                <div class="logo-icon no-border">
                    <img src="images/logooo.png" alt="Solano Logo" onerror="this.style.display='none'; this.parentElement.innerHTML='<div style=\'width:50px;height:50px;background:#0055a4;border-radius:12px;display:flex;align-items:center;justify-content:center;color:white;font-weight:bold;font-size:20px;\'>S</div>';">
                </div>
            </div>
            <h2>Privacy Policy</h2>
            <p class="text-muted">Solano Mayor's Office Appointment System</p>
        </div>
        
        <div class="last-updated">
            <i class="bi bi-calendar3 me-2"></i>
            <strong>Last Updated:</strong> <?php echo date('F j, Y'); ?>
        </div>
        
        <div class="privacy-content">
            <div class="highlight-box">
                <p><strong>Your Privacy Matters:</strong> The Solano Mayor's Office is committed to protecting your personal information and your right to privacy. This Privacy Policy explains how we collect, use, and safeguard your information when you use our appointment system.</p>
            </div>
            
            <h3>1. Information We Collect</h3>
            <p>We collect information you provide directly to us when you:</p>
            <ul>
                <li><strong>Register for an account:</strong> Full name, username, email address, phone number, and address</li>
                <li><strong>Schedule appointments:</strong> Appointment details, preferred dates and times, purpose of visit</li>
                <li><strong>Contact us:</strong> Any information you provide when communicating with our office</li>
                <li><strong>Use our system:</strong> Login times, IP address, browser type, and usage patterns</li>
            </ul>
            
            <h3>2. How We Use Your Information</h3>
            <p>We use the information we collect to:</p>
            <ul>
                <li>Process and manage your appointment requests</li>
                <li>Send appointment confirmations, reminders, and updates</li>
                <li>Communicate with you about your appointments or account</li>
                <li>Improve our services and user experience</li>
                <li>Ensure the security and proper functioning of our system</li>
                <li>Comply with legal obligations and government requirements</li>
            </ul>
            
            <h3>3. Information Sharing and Disclosure</h3>
            <p>We do not sell, trade, or rent your personal information to third parties. We may share your information only in the following circumstances:</p>
            <ul>
                <li><strong>With Mayor's Office Staff:</strong> To facilitate your appointments and provide requested services</li>
                <li><strong>Legal Requirements:</strong> When required by law, court order, or government regulation</li>
                <li><strong>Security Purposes:</strong> To protect the rights, property, or safety of our office, users, or the public</li>
                <li><strong>Service Providers:</strong> With trusted third-party service providers who assist in operating our system (under strict confidentiality agreements)</li>
            </ul>
            
            <h3>4. Data Security</h3>
            <p>We implement appropriate technical and organizational security measures to protect your personal information against unauthorized access, alteration, disclosure, or destruction. These measures include:</p>
            <ul>
                <li>Secure server infrastructure and encrypted data transmission</li>
                <li>Regular security assessments and updates</li>
                <li>Access controls and authentication requirements</li>
                <li>Staff training on data protection and privacy practices</li>
            </ul>
            
            <h3>5. Data Retention</h3>
            <p>We retain your personal information only for as long as necessary to:</p>
            <ul>
                <li>Provide the services you requested</li>
                <li>Comply with legal obligations</li>
                <li>Resolve disputes and enforce our agreements</li>
                <li>Maintain accurate records for government operations</li>
            </ul>
            
            <h3>6. Your Rights and Choices</h3>
            <p>You have the right to:</p>
            <ul>
                <li><strong>Access:</strong> Request a copy of the personal information we hold about you</li>
                <li><strong>Correction:</strong> Request correction of inaccurate or incomplete information</li>
                <li><strong>Deletion:</strong> Request deletion of your personal information (subject to legal requirements)</li>
                <li><strong>Portability:</strong> Request transfer of your information to another service</li>
                <li><strong>Withdrawal:</strong> Withdraw consent for processing where applicable</li>
            </ul>
            
            <h3>7. Cookies and Tracking Technologies</h3>
            <p>Our system may use cookies and similar tracking technologies to:</p>
            <ul>
                <li>Remember your login credentials and preferences</li>
                <li>Analyze system usage and performance</li>
                <li>Enhance security and prevent fraud</li>
                <li>Improve user experience and functionality</li>
            </ul>
            <p>You can control cookie settings through your browser preferences.</p>
            
            <h3>8. Third-Party Links</h3>
            <p>Our system may contain links to third-party websites or services. We are not responsible for the privacy practices or content of these external sites. We encourage you to review the privacy policies of any third-party sites you visit.</p>
            
            <h3>9. Children's Privacy</h3>
            <p>Our services are not intended for children under 13 years of age. We do not knowingly collect personal information from children under 13. If we become aware that we have collected such information, we will take steps to delete it promptly.</p>
            
            <h3>10. Changes to This Privacy Policy</h3>
            <p>We may update this Privacy Policy from time to time to reflect changes in our practices or legal requirements. We will notify users of any material changes by:</p>
            <ul>
                <li>Posting the updated policy on our system</li>
                <li>Sending email notifications to registered users</li>
                <li>Displaying prominent notices within the system</li>
            </ul>
            
            <h3>11. Contact Information</h3>
            <p>If you have any questions, concerns, or requests regarding this Privacy Policy or our data practices, please contact us:</p>
            <div class="highlight-box">
                <p><strong>Solano Mayor's Office</strong><br>
                Email: [Contact through the appointment system]<br>
                Address: [Official Mayor's Office Address]<br>
                Phone: [Official Contact Number]</p>
            </div>
            
            <h3>12. Governing Law</h3>
            <p>This Privacy Policy is governed by the laws of the Philippines and the Data Privacy Act of 2012. Any disputes relating to this policy will be subject to the jurisdiction of Philippine courts.</p>
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
