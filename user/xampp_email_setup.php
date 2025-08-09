<?php
/**
 * XAMPP Email Configuration Helper
 * This file helps configure email functionality in XAMPP environment
 */

// Check current PHP mail settings
echo "<h2>XAMPP Email Configuration Helper</h2>";

echo "<h3>Current PHP Mail Settings:</h3>";
echo "<ul>";
echo "<li>SMTP: " . ini_get('SMTP') . "</li>";
echo "<li>smtp_port: " . ini_get('smtp_port') . "</li>";
echo "<li>sendmail_path: " . ini_get('sendmail_path') . "</li>";
echo "<li>mail.add_x_header: " . ini_get('mail.add_x_header') . "</li>";
echo "</ul>";

// Solution 1: Configure for Gmail SMTP
echo "<h3>Solution 1: Configure for Gmail SMTP (Recommended)</h3>";
echo "<p>To use Gmail SMTP, you need to:</p>";
echo "<ol>";
echo "<li>Enable 2-factor authentication on your Gmail account</li>";
echo "<li>Generate an App Password for your application</li>";
echo "<li>Use the following settings:</li>";
echo "</ol>";

echo "<div style='background: #f0f0f0; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
echo "<strong>Gmail SMTP Settings:</strong><br>";
echo "Host: smtp.gmail.com<br>";
echo "Port: 587<br>";
echo "Encryption: TLS<br>";
echo "Username: your-email@gmail.com<br>";
echo "Password: your-app-password (not your regular password)<br>";
echo "</div>";

// Solution 2: Use a free SMTP service
echo "<h3>Solution 2: Use Free SMTP Service</h3>";
echo "<p>You can use free SMTP services like:</p>";
echo "<ul>";
echo "<li><strong>SendGrid:</strong> Free tier with 100 emails/day</li>";
echo "<li><strong>Mailgun:</strong> Free tier with 5,000 emails/month</li>";
echo "<li><strong>Mailtrap:</strong> For testing emails safely</li>";
echo "</ul>";

// Solution 3: Configure local mail server
echo "<h3>Solution 3: Configure Local Mail Server</h3>";
echo "<p>For XAMPP, you can install and configure Mercury mail server:</p>";
echo "<ol>";
echo "<li>Download Mercury from the XAMPP Control Panel</li>";
echo "<li>Configure it as a local SMTP server</li>";
echo "<li>Update php.ini settings</li>";
echo "</ol>";

// PHP configuration code
echo "<h3>PHP Configuration Code:</h3>";
echo "<p>Add this code to your email helper or at the top of your script:</p>";
echo "<pre style='background: #f5f5f5; padding: 10px; border-radius: 3px;'>";
echo "// Configure PHP mail settings
ini_set('SMTP', 'smtp.gmail.com');
ini_set('smtp_port', '587');
ini_set('sendmail_from', 'your-email@gmail.com');

// For Gmail with authentication
ini_set('auth_username', 'your-email@gmail.com');
ini_set('auth_password', 'your-app-password');";
echo "</pre>";

// Test with different configurations
echo "<h3>Test Different Configurations:</h3>";
echo "<p><a href='test_email_gmail.php' class='btn btn-primary'>Test with Gmail SMTP</a></p>";
echo "<p><a href='test_email_sendgrid.php' class='btn btn-success'>Test with SendGrid</a></p>";
echo "<p><a href='test_email_mailtrap.php' class='btn btn-info'>Test with Mailtrap</a></p>";

// Quick fix for immediate testing
echo "<h3>Quick Fix for Testing:</h3>";
echo "<p>If you just want to test the email functionality without setting up SMTP, you can:</p>";
echo "<ol>";
echo "<li>Use a service like Mailtrap.io (free for testing)</li>";
echo "<li>Use Gmail SMTP with App Password</li>";
echo "<li>Use a hosting provider's SMTP server</li>";
echo "</ol>";

echo "<h3>Recommended Next Steps:</h3>";
echo "<ol>";
echo "<li>Set up a Gmail account with App Password</li>";
echo "<li>Update the email helper with SMTP settings</li>";
echo "<li>Test with the provided test files</li>";
echo "<li>For production, use a reliable SMTP service</li>";
echo "</ol>";
?> 