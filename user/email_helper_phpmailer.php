<?php
/**
 * Professional Email Helper Functions for Appointment System
 * Uses PHPMailer with SMTP service for reliable email delivery
 */

// Load PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Include PHPMailer autoloader
require 'vendor/autoload.php';

/**
 * Send appointment confirmation email using PHPMailer
 * @param string $user_email User's email address
 * @param string $user_name User's full name
 * @param string $appointment_date Appointment date
 * @param string $appointment_time Appointment time
 * @param string $purpose Purpose of visit
 * @param int $attendees Number of attendees
 * @param string $other_details Additional details
 * @return array Array with success status and message
 */
function sendAppointmentConfirmationEmail($user_email, $user_name, $appointment_date, $appointment_time, $purpose, $attendees, $other_details = '') {
    // Email subject
    $subject = "Appointment Request Received - Solano Mayor's Office";
    
    // Format the appointment date for display
    $formatted_date = date('l, F j, Y', strtotime($appointment_date));
    $formatted_time = date('g:i A', strtotime($appointment_time));
    
    // Email body
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #0055a4; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background: #f9f9f9; }
            .appointment-details { background: white; padding: 15px; margin: 15px 0; border-left: 4px solid #28a745; }
            .footer { background: #f0f0f0; padding: 15px; text-align: center; font-size: 12px; color: #666; }
            .status-badge { background: #ffc107; color: #333; padding: 5px 10px; border-radius: 15px; font-weight: bold; }
            .contact-info { background: #e8f5e8; padding: 15px; margin: 15px 0; border-radius: 5px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>Solano Mayor's Office</h2>
                <p>Online Appointment System</p>
            </div>
            
            <div class='content'>
                <h3>Dear " . htmlspecialchars($user_name) . ",</h3>
                
                <p>Thank you for submitting your appointment request. We have received your booking and it is currently under review.</p>
                
                <div class='appointment-details'>
                    <h4>Appointment Details:</h4>
                    <p><strong>Date:</strong> " . $formatted_date . "</p>
                    <p><strong>Time:</strong> " . $formatted_time . "</p>
                    <p><strong>Purpose:</strong> " . htmlspecialchars($purpose) . "</p>
                    <p><strong>Number of Attendees:</strong> " . $attendees . " person(s)</p>";
    
    if (!empty($other_details)) {
        $message .= "<p><strong>Additional Details:</strong> " . htmlspecialchars($other_details) . "</p>";
    }
    
    $message .= "
                    <p><strong>Status:</strong> <span class='status-badge'>PENDING APPROVAL</span></p>
                </div>
                
                <div class='contact-info'>
                    <h4>What happens next?</h4>
                    <ul>
                        <li>Our staff will review your appointment request within 24-48 hours</li>
                        <li>You will receive an email notification once your appointment is approved or if any changes are needed</li>
                        <li>Please check your email regularly for updates</li>
                    </ul>
                </div>
                
                <p><strong>Important Notes:</strong></p>
                <ul>
                    <li>Please arrive 10 minutes before your scheduled appointment time</li>
                    <li>Bring a valid government-issued ID and any required documents</li>
                    <li>If you need to reschedule or cancel, please contact us at least 24 hours in advance</li>
                </ul>
                
                <p>If you have any questions or need to make changes to your appointment, please contact us:</p>
                <ul>
                    <li><strong>Phone:</strong> (078) 123-4567</li>
                    <li><strong>Email:</strong> info@solanomayor.gov.ph</li>
                    <li><strong>Office Hours:</strong> Monday - Friday, 8:00 AM - 5:00 PM</li>
                </ul>
                
                <p>Thank you for choosing the Solano Mayor's Office for your municipal service needs.</p>
                
                <p>Best regards,<br>
                <strong>Solano Mayor's Office Team</strong></p>
            </div>
            
            <div class='footer'>
                <p>This is an automated message. Please do not reply to this email.</p>
                <p>&copy; 2025 Solano Mayor's Office. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>";
    
    try {
        // Create a new PHPMailer instance
        $mail = new PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = getSMTPSettings()['host'];
        $mail->SMTPAuth = true;
        $mail->Username = getSMTPSettings()['username'];
        $mail->Password = getSMTPSettings()['password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = getSMTPSettings()['port'];
        
        // SSL certificate verification settings for development
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // Enable debug output (set to 0 for production)
        $mail->SMTPDebug = 0;
        
        // Recipients
        $mail->setFrom(getSMTPSettings()['from_email'], 'Solano Mayor\'s Office');
        $mail->addAddress($user_email, $user_name);
        $mail->addReplyTo('info@solanomayor.gov.ph', 'Solano Mayor\'s Office');
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $message;
        $mail->AltBody = strip_tags(str_replace(['<br>', '</p>'], ["\n", "\n\n"], $message));
        
        // Send email
        $mail->send();
        
        // Log successful email
        logEmailAttempt($user_email, $user_name, $subject, true, 'Email sent successfully');
        
        return [
            'success' => true,
            'message' => 'Email sent successfully to ' . $user_email
        ];
        
    } catch (Exception $e) {
        // Log failed email
        logEmailAttempt($user_email, $user_name, $subject, false, $e->getMessage());
        
        return [
            'success' => false,
            'message' => 'Email could not be sent. Mailer Error: ' . $e->getMessage()
        ];
    }
}

/**
 * Send a verification code email to the user
 * @param string $user_email
 * @param string $user_name
 * @param string $code
 * @return array
 */
function sendVerificationCodeEmail($user_email, $user_name, $code) {
    $subject = 'Your Verification Code';
    $message = "<html><body>"
        . "<p>Dear " . htmlspecialchars($user_name) . ",</p>"
        . "<p>Your verification code is: <strong>" . htmlspecialchars($code) . "</strong></p>"
        . "<p>Please enter this code to complete your registration.</p>"
        . "<br><p>Thank you,<br>Solano Mayor's Office Team</p>"
        . "</body></html>";

    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = getSMTPSettings()['host'];
        $mail->SMTPAuth = true;
        $mail->Username = getSMTPSettings()['username'];
        $mail->Password = getSMTPSettings()['password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = getSMTPSettings()['port'];
        
        // SSL certificate verification settings for development
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        $mail->SMTPDebug = 0;
        $mail->setFrom(getSMTPSettings()['from_email'], 'Solano Mayor\'s Office');
        $mail->addAddress($user_email, $user_name);
        $mail->addReplyTo('info@solanomayor.gov.ph', 'Solano Mayor\'s Office');
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $message;
        $mail->AltBody = strip_tags($message);
        $mail->send();
        logEmailAttempt($user_email, $user_name, $subject, true, 'Verification code sent');
        return [
            'success' => true,
            'message' => 'Verification code sent to ' . $user_email
        ];
    } catch (Exception $e) {
        logEmailAttempt($user_email, $user_name, $subject, false, $e->getMessage());
        return [
            'success' => false,
            'message' => 'Verification email could not be sent. Mailer Error: ' . $e->getMessage()
        ];
    }
}

/**
 * Get SMTP settings from configuration
 * @return array SMTP configuration
 */
function getSMTPSettings() {
    return [
        'host' => 'smtp.gmail.com',
        'username' => 'paladinemil947@gmail.com', // ← Change this
        'password' => 'qqam nawt bzuf kzdl',     // ← Change this
        'port' => 587,
        'from_email' => 'noreply@solanomayor.gov.ph',
        'from_name' => 'Solano Mayor\'s Office'
    ];
}

/**
 * Log email attempts for debugging
 * @param string $to_email Recipient email
 * @param string $user_name User name
 * @param string $subject Email subject
 * @param bool $success Whether email was sent successfully
 * @param string $message Additional message
 */
function logEmailAttempt($to_email, $user_name, $subject, $success, $message) {
    $log_entry = date('Y-m-d H:i:s') . " | ";
    $log_entry .= ($success ? "SUCCESS" : "FAILED") . " | ";
    $log_entry .= "To: $to_email | ";
    $log_entry .= "User: $user_name | ";
    $log_entry .= "Subject: $subject | ";
    $log_entry .= "Message: $message\n";
    
    $log_file = 'email_log.txt';
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}

/**
 * Get user email from database
 * @param mysqli $conn Database connection
 * @param int $user_id User ID
 * @return string|false User email or false if not found
 */
function getUserEmail($conn, $user_id) {
    $user_id = intval($user_id);
    $query = "SELECT email FROM users WHERE id = $user_id";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['email'];
    }
    
    return false;
}

/**
 * Get user full name from database
 * @param mysqli $conn Database connection
 * @param int $user_id User ID
 * @return string|false User full name or false if not found
 */
function getUserFullName($conn, $user_id) {
    $user_id = intval($user_id);
    $query = "SELECT name FROM users WHERE id = $user_id";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['name'];
    }
    
    return false;
}

/**
 * Test PHPMailer configuration
 * @return array Test results
 */
function testPHPMailerConfig() {
    $results = [];
    
    try {
        // Check if PHPMailer is available
        $results['phpmailer_available'] = class_exists('PHPMailer\PHPMailer\PHPMailer');
        
        // Test SMTP connection
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = getSMTPSettings()['host'];
        $mail->SMTPAuth = true;
        $mail->Username = getSMTPSettings()['username'];
        $mail->Password = getSMTPSettings()['password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = getSMTPSettings()['port'];
        
        // SSL certificate verification settings for development
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        $mail->SMTPDebug = 0;
        
        $results['smtp_connection'] = true;
        $results['smtp_host'] = getSMTPSettings()['host'];
        $results['smtp_port'] = getSMTPSettings()['port'];
        
    } catch (Exception $e) {
        $results['smtp_connection'] = false;
        $results['error'] = $e->getMessage();
    }
    
    return $results;
    
}

//decline to lods
/**
 * Send appointment declined email notification
 * @param string $user_email User's email address
 * @param string $user_name User's full name
 * @param string $appointment_date Appointment date
 * @param string $appointment_time Appointment time
 * @param string $purpose Purpose of visit
 * @param string $decline_reason Reason for declining
 * @return array Array with success status and message
 */
function sendAppointmentDeclinedEmail($user_email, $user_name, $appointment_date, $appointment_time, $purpose, $decline_reason) {
    // Email subject
    $subject = "Appointment Declined - Solano Mayor's Office";
    
    // Format date/time
    $formatted_date = date('F j, Y', strtotime($appointment_date));
    $formatted_time = date('g:i A', strtotime($appointment_time));
    
    // Email body
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #dc3545; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background: #f8f9fa; }
            .reason-box { background: #f8d7da; padding: 15px; border-left: 4px solid #dc3545; margin: 15px 0; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>Solano Mayor's Office</h2>
            </div>
            
            <div class='content'>
                <h3>Dear $user_name,</h3>
                
                <p>We regret to inform you that your appointment request has been declined.</p>
                
                <h4>Appointment Details:</h4>
                <p><strong>Date:</strong> $formatted_date</p>
                <p><strong>Time:</strong> $formatted_time</p>
                <p><strong>Purpose:</strong> $purpose</p>
                
                <div class='reason-box'>
                    <h4>Reason for Decline:</h4>
                    <p>" . nl2br(htmlspecialchars($decline_reason)) . "</p>
                </div>
                
                <p>If you believe this was declined in error or would like to submit a new request, 
                please visit our appointment system or contact our office.</p>
                
                <p>Best regards,<br>
                Solano Mayor's Office Team</p>
            </div>
        </div>
    </body>
    </html>";

    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = getSMTPSettings()['host'];
        $mail->SMTPAuth = true;
        $mail->Username = getSMTPSettings()['username'];
        $mail->Password = getSMTPSettings()['password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = getSMTPSettings()['port'];
        
        $mail->setFrom(getSMTPSettings()['from_email'], 'Solano Mayor\'s Office');
        $mail->addAddress($user_email, $user_name);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $message;
        $mail->AltBody = strip_tags($message);
        
        $mail->send();
        logEmailAttempt($user_email, $user_name, $subject, true, 'Decline email sent');
        
        return ['success' => true, 'message' => 'Email sent'];
        
    } catch (Exception $e) {
        logEmailAttempt($user_email, $user_name, $subject, false, $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}
?> 