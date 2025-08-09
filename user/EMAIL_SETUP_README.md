# Email Notification System Setup

This document explains how to set up and use the automated email notification system for the Solano Mayor's Office appointment system.

## Overview

The email notification system automatically sends confirmation emails to users when they submit appointment requests. The system includes:

1. **Basic Email Helper** (`email_helper.php`) - Uses PHP's built-in `mail()` function
2. **Advanced Email Helper** (`email_helper_advanced.php`) - Uses PHPMailer for more reliable delivery
3. **Test File** (`test_email.php`) - For testing email functionality

## Files Created

- `email_helper.php` - Basic email functionality using PHP mail()
- `email_helper_advanced.php` - Advanced email functionality with PHPMailer support
- `test_email.php` - Test file to verify email functionality
- Modified `userSide.php` - Now includes email notifications

## Email Features

### Appointment Confirmation Email
- Sent automatically when a user submits an appointment request
- Includes appointment details (date, time, purpose, attendees)
- Professional HTML formatting with Solano Mayor's Office branding
- Clear status indication (PENDING APPROVAL)
- Contact information and next steps

### Email Content Includes:
- Professional header with office branding
- Appointment details in a formatted table
- Status badge showing "PENDING APPROVAL"
- Next steps and what to expect
- Important notes for the appointment
- Contact information
- Professional footer

## Setup Instructions

### 1. Basic Setup (Using PHP mail())

The basic setup uses PHP's built-in `mail()` function. This should work on most servers but may have delivery issues.

**Requirements:**
- PHP with mail() function enabled
- Server configured to send emails

**Steps:**
1. The email functionality is already integrated into `userSide.php`
2. Test the functionality using `test_email.php`
3. Update the test email address in `test_email.php` to your email

### 2. Advanced Setup (Using PHPMailer)

For more reliable email delivery, use PHPMailer.

**Requirements:**
- PHPMailer library installed
- SMTP server credentials

**Steps:**
1. Install PHPMailer:
   ```bash
   composer require phpmailer/phpmailer
   ```
   Or download manually from https://github.com/PHPMailer/PHPMailer

2. Update `email_helper_advanced.php` with your SMTP settings:
   ```php
   $mail->Host = 'smtp.gmail.com'; // Your SMTP server
   $mail->Username = 'your-email@gmail.com'; // Your email
   $mail->Password = 'your-app-password'; // Your password
   ```

3. Replace the include in `userSide.php`:
   ```php
   require_once 'email_helper_advanced.php';
   ```

## Testing the Email System

1. **Run the test file:**
   ```
   http://your-domain/test_email.php
   ```

2. **Check the results:**
   - Green checkmark = Email sent successfully
   - Red X = Email failed to send

3. **Common issues and solutions:**
   - **Emails not sending:** Check server email configuration
   - **Emails going to spam:** Configure SPF/DKIM records
   - **PHPMailer errors:** Verify SMTP credentials

## Email Templates

### Appointment Confirmation Template
- **Subject:** "Appointment Request Received - Solano Mayor's Office"
- **Content:** Professional HTML email with appointment details
- **Status:** Shows "PENDING APPROVAL" badge
- **Next Steps:** Explains the approval process

### Status Update Template (Advanced)
- **Subject:** "Appointment Status Update - Solano Mayor's Office"
- **Content:** Status-specific formatting and messaging
- **Status Colors:** Green for approved, red for cancelled, yellow for rescheduled

## Database Requirements

The system expects the following database structure:

```sql
-- Users table should have email and full_name columns
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(255),
    email VARCHAR(255),
    full_name VARCHAR(255),
    -- other columns...
);
```

## Configuration Options

### Email Settings
- **From Email:** noreply@solanomayor.gov.ph
- **Reply-To:** info@solanomayor.gov.ph
- **Office Phone:** (078) 123-4567
- **Office Email:** info@solanomayor.gov.ph
- **Office Hours:** Monday - Friday, 8:00 AM - 5:00 PM

### Customization
To customize the email content:
1. Edit the `$message` variable in the email functions
2. Update contact information and branding
3. Modify the HTML/CSS styling as needed

## Troubleshooting

### Email Not Sending
1. Check if `mail()` function is available: `function_exists('mail')`
2. Check server error logs for email errors
3. Verify SMTP settings (if using PHPMailer)
4. Test with a different email address

### Emails Going to Spam
1. Configure SPF records for your domain
2. Set up DKIM authentication
3. Use a reputable SMTP service
4. Avoid spam trigger words in subject lines

### Database Connection Issues
1. Verify database connection in `kon.php`
2. Check if users table has email and full_name columns
3. Ensure user_id is properly set in session

## Security Considerations

1. **Email Validation:** Always validate email addresses before sending
2. **Rate Limiting:** Consider implementing rate limiting for email sending
3. **Error Logging:** Log email failures for debugging
4. **SPF/DKIM:** Configure email authentication to prevent spoofing

## Future Enhancements

1. **Email Queue System:** For handling large volumes of emails
2. **Template System:** Dynamic email templates based on appointment type
3. **SMS Notifications:** Add SMS notifications as an alternative
4. **Email Preferences:** Allow users to opt-out of certain notifications
5. **Admin Notifications:** Send notifications to admin when appointments are submitted

## Support

For issues with the email system:
1. Check the error logs in your server
2. Test with the provided test file
3. Verify server email configuration
4. Contact your hosting provider for email setup assistance 