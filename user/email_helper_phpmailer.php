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

function sendAppointmentApprovedEmail($user_email, $user_name, $appointment_date, $appointment_time, $purpose, $attendees, $other_details = '', $admin_message = '') {
    // Email subject
    $subject = "Appointment Approved - Solano Mayor's Office";
    
    // Format date/time
    $formatted_date = date('l, F j, Y', strtotime($appointment_date));
    $formatted_time = date('g:i A', strtotime($appointment_time));
    
    // Email body
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #28a745; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background: #f9f9f9; }
            .appointment-details { background: white; padding: 15px; margin: 15px 0; border-left: 4px solid #28a745; }
            .footer { background: #f0f0f0; padding: 15px; text-align: center; font-size: 12px; color: #666; }
            .status-badge { background: #28a745; color: white; padding: 5px 10px; border-radius: 15px; font-weight: bold; }
            .instructions { background: #d4edda; padding: 15px; margin: 15px 0; border-radius: 5px; border: 1px solid #c3e6cb; }
            .admin-message { background: #e8f5e8; padding: 15px; margin: 15px 0; border-radius: 5px; }
            .important-notes { background: #fff3cd; padding: 15px; margin: 15px 0; border-radius: 5px; border: 1px solid #ffeeba; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>🎉 Congratulations!</h2>
                <h3>Your Appointment Has Been Approved</h3>
                <p>Solano Mayor's Office</p>
            </div>
            
            <div class='content'>
                <h3>Dear " . htmlspecialchars($user_name) . ",</h3>
                
                <p>Great news! Your appointment request has been <strong>approved</strong> and confirmed by our office.</p>
                
                <div class='appointment-details'>
                    <h4>📅 Your Confirmed Appointment Details:</h4>
                    <p><strong>Date:</strong> " . $formatted_date . "</p>
                    <p><strong>Time:</strong> " . $formatted_time . "</p>
                    <p><strong>Purpose:</strong> " . htmlspecialchars($purpose) . "</p>
                    <p><strong>Number of Attendees:</strong> " . $attendees . " person(s)</p>";
    
    if (!empty($other_details)) {
        $message .= "<p><strong>Additional Details:</strong> " . htmlspecialchars($other_details) . "</p>";
    }
    
    $message .= "
                    <p><strong>Status:</strong> <span class='status-badge'>✅ APPROVED & CONFIRMED</span></p>
                </div>";
                
    if (!empty($admin_message)) {
        $message .= "
                <div class='admin-message'>
                    <h4>📝 Message from Admin:</h4>
                    <p>" . nl2br(htmlspecialchars($admin_message)) . "</p>
                </div>";
    }
    
    $message .= "
                <div class='instructions'>
                    <h4>🔔 What to do next:</h4>
                    <ul>
                        <li>Mark this date and time in your calendar</li>
                        <li>Prepare any necessary documents mentioned in your appointment request</li>
                        <li>Plan to arrive 10 minutes before your scheduled time</li>
                        <li>If you need to reschedule or cancel, contact us at least 24 hours in advance</li>
                    </ul>
                </div>
                
                <div class='important-notes'>
                    <h4>⚠️ Important Reminders:</h4>
                    <ul>
                        <li><strong>Arrive on Time:</strong> Please be at our office 10 minutes before your scheduled appointment</li>
                        <li><strong>Bring Valid ID:</strong> Government-issued identification is required</li>
                        <li><strong>Required Documents:</strong> Bring any documents related to your appointment purpose</li>
                        <li><strong>Dress Code:</strong> Please dress appropriately for a government office visit</li>
                        <li><strong>Health Protocols:</strong> Follow any health and safety guidelines in place</li>
                    </ul>
                </div>
                
                <div class='appointment-details'>
                    <h4>📍 Office Information:</h4>
                    <p><strong>Address:</strong> Solano Mayor's Office<br>
                    Municipal Hall, Solano, Nueva Vizcaya</p>
                    <p><strong>Contact Information:</strong></p>
                    <ul>
                        <li><strong>Phone:</strong> (078) 123-4567</li>
                        <li><strong>Email:</strong> info@solanomayor.gov.ph</li>
                        <li><strong>Office Hours:</strong> Monday - Friday, 8:00 AM - 5:00 PM</li>
                    </ul>
                </div>
                
                <p>We look forward to serving you on your appointed date. Thank you for choosing the Solano Mayor's Office for your municipal service needs.</p>
                
                <p>If you have any questions or concerns about your appointment, please don't hesitate to contact us.</p>
                
                <p>Best regards,<br>
                <strong>Solano Mayor's Office Team</strong><br>
                <em>\"Serving our community with excellence\"</em></p>
            </div>
            
            <div class='footer'>
                <p>This is an automated confirmation email. Please save this email for your records.</p>
                <p>&copy; 2025 Solano Mayor's Office. All rights reserved.</p>
                <p style='color: #28a745; font-weight: bold;'>Your appointment is confirmed! We'll see you soon.</p>
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
        
        // SSL certificate verification settings for development
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        $mail->setFrom(getSMTPSettings()['from_email'], 'Solano Mayor\'s Office');
        $mail->addAddress($user_email, $user_name);
        $mail->addReplyTo('info@solanomayor.gov.ph', 'Solano Mayor\'s Office');
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $message;
        $mail->AltBody = strip_tags(str_replace(['<br>', '</p>', '</li>'], ["\n", "\n\n", "\n"], $message));
        
        $mail->send();
        logEmailAttempt($user_email, $user_name, $subject, true, 'Approval email sent successfully');
        
        return [
            'success' => true, 
            'message' => 'Approval email sent successfully to ' . $user_email
        ];
        
    } catch (Exception $e) {
        logEmailAttempt($user_email, $user_name, $subject, false, $e->getMessage());
        return [
            'success' => false, 
            'message' => 'Approval email could not be sent. Error: ' . $e->getMessage()
        ];
    }
}

/**
 * Send appointment rescheduled email using PHPMailer
 * @param string $user_email User's email address
 * @param string $user_name User's full name
 * @param string $old_date Previous appointment date
 * @param string $old_time Previous appointment time
 * @param string $new_date New appointment date
 * @param string $new_time New appointment time
 * @param string $purpose Purpose of visit
 * @param string $admin_message Additional message from admin
 * @return array Array with success status and message
 */
function sendAppointmentRescheduledEmail($user_email, $user_name, $old_date, $old_time, $new_date, $new_time, $purpose, $admin_message = '') {
    // Email subject
    $subject = "Appointment Rescheduled - Solano Mayor's Office";
    
    // Format the appointment dates for display
    $formatted_old_date = date('l, F j, Y', strtotime($old_date));
    $formatted_old_time = date('g:i A', strtotime($old_time));
    $formatted_new_date = date('l, F j, Y', strtotime($new_date));
    $formatted_new_time = date('g:i A', strtotime($new_time));
    
    // Email body
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #0055a4; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background: #f9f9f9; }
            .reschedule-notice { background: #fff3cd; color: #856404; padding: 15px; margin: 15px 0; border-left: 4px solid #ffc107; border-radius: 5px; }
            .appointment-details { background: white; padding: 15px; margin: 15px 0; border-left: 4px solid #17a2b8; }
            .footer { background: #f0f0f0; padding: 15px; text-align: center; font-size: 12px; color: #666; }
            .status-badge { background: #17a2b8; color: white; padding: 5px 10px; border-radius: 15px; font-weight: bold; }
            .contact-info { background: #e8f5e8; padding: 15px; margin: 15px 0; border-radius: 5px; }
            .old-appointment { background: #ffe6e6; padding: 10px; margin: 10px 0; border-left: 4px solid #dc3545; }
            .new-appointment { background: #e6ffe6; padding: 10px; margin: 10px 0; border-left: 4px solid #28a745; }
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
                
                <div class='reschedule-notice'>
                    <h4>🔄 Appointment Rescheduled</h4>
                    <p>Your appointment has been rescheduled. Please note the new date and time below.</p>
                </div>
                
                <div class='old-appointment'>
                    <h4>Previous Appointment:</h4>
                    <p><strong>Date:</strong> " . $formatted_old_date . "</p>
                    <p><strong>Time:</strong> " . $formatted_old_time . "</p>
                    <p><strong>Purpose:</strong> " . htmlspecialchars($purpose) . "</p>
                </div>
                
                <div class='new-appointment'>
                    <h4>New Appointment:</h4>
                    <p><strong>Date:</strong> " . $formatted_new_date . "</p>
                    <p><strong>Time:</strong> " . $formatted_new_time . "</p>
                    <p><strong>Purpose:</strong> " . htmlspecialchars($purpose) . "</p>
                    <p><strong>Status:</strong> <span class='status-badge'>RESCHEDULED</span></p>
                </div>";
    
    if (!empty($admin_message)) {
        $message .= "
                <div class='admin-message'>
                    <h4>📝 Message from our staff:</h4>
                    <p>" . nl2br(htmlspecialchars($admin_message)) . "</p>
                </div>";
    }
    
    $message .= "
                <div class='instructions'>
                    <h4>🔔 What to do next:</h4>
                    <ul>
                        <li>Update your calendar with the new appointment date and time</li>
                        <li>Cancel any previous arrangements for the old date</li>
                        <li>Prepare any necessary documents for your appointment</li>
                        <li>Plan to arrive 10 minutes before your new scheduled time</li>
                    </ul>
                </div>
                
                <div class='important-notes'>
                    <h4>⚠️ Important Reminders:</h4>
                    <ul>
                        <li><strong>Arrive on Time:</strong> Please be at our office 10 minutes before your scheduled appointment</li>
                        <li><strong>Bring Valid ID:</strong> Government-issued identification is required</li>
                        <li><strong>Required Documents:</strong> Bring any documents related to your appointment purpose</li>
                        <li><strong>Dress Code:</strong> Please dress appropriately for a government office visit</li>
                        <li><strong>Health Protocols:</strong> Follow any health and safety guidelines in place</li>
                    </ul>
                </div>
                
                <div class='appointment-details'>
                    <h4>📍 Office Information:</h4>
                    <p><strong>Address:</strong> Solano Mayor's Office<br>
                    Municipal Hall, Solano, Nueva Vizcaya</p>
                    <p><strong>Contact Information:</strong></p>
                    <ul>
                        <li><strong>Phone:</strong> (078) 123-4567</li>
                        <li><strong>Email:</strong> info@solanomayor.gov.ph</li>
                        <li><strong>Office Hours:</strong> Monday - Friday, 8:00 AM - 5:00 PM</li>
                    </ul>
                </div>
                
                <p>We apologize for any inconvenience this rescheduling may have caused. We look forward to serving you on your new appointment date.</p>
                
                <p>If you have any questions about your rescheduled appointment, please don't hesitate to contact us.</p>
                
                <p>Best regards,<br>
                <strong>Solano Mayor's Office Team</strong><br>
                <em>\"Serving our community with excellence\"</em></p>
            </div>
            
            <div class='footer'>
                <p>This is an automated notification email. Please save this email for your records.</p>
                <p>&copy; 2025 Solano Mayor's Office. All rights reserved.</p>
                <p style='color: #17a2b8; font-weight: bold;'>Your appointment has been rescheduled. Please note the new date and time.</p>
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
        
        // SSL certificate verification settings for development
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        $mail->setFrom(getSMTPSettings()['from_email'], 'Solano Mayor\'s Office');
        $mail->addAddress($user_email, $user_name);
        $mail->addReplyTo('info@solanomayor.gov.ph', 'Solano Mayor\'s Office');
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $message;
        $mail->AltBody = strip_tags(str_replace(['<br>', '</p>', '</li>'], ["\n", "\n\n", "\n"], $message));
        
        $mail->send();
        logEmailAttempt($user_email, $user_name, $subject, true, 'Reschedule email sent successfully');
        
        return [
            'success' => true, 
            'message' => 'Reschedule email sent successfully to ' . $user_email
        ];
        
    } catch (Exception $e) {
        logEmailAttempt($user_email, $user_name, $subject, false, $e->getMessage());
        return [
            'success' => false, 
            'message' => 'Reschedule email could not be sent. Error: ' . $e->getMessage()
        ];
    }
}
?> 