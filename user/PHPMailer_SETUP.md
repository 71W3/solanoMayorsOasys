# PHPMailer Setup Instructions

This guide will help you set up PHPMailer with SMTP service for professional email delivery in your appointment system.

## 🚀 **Quick Setup Steps**

### **Step 1: Install PHPMailer**

1. **Install Composer** (if not already installed):
   ```bash
   # Download Composer installer
   php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
   php composer-setup.php
   php -r "unlink('composer-setup.php');"
   ```

2. **Install PHPMailer**:
   ```bash
   composer install
   ```

### **Step 2: Configure SMTP Settings**

Edit `email_helper_phpmailer.php` and update the `getSMTPSettings()` function:

```php
function getSMTPSettings() {
    return [
        'host' => 'smtp.gmail.com', // Your SMTP host
        'username' => 'your-email@gmail.com', // Your email
        'password' => 'your-app-password', // Your password
        'port' => 587,
        'from_email' => 'noreply@solanomayor.gov.ph',
        'from_name' => 'Solano Mayor\'s Office'
    ];
}
```

### **Step 3: Choose Your SMTP Service**

#### **Option A: Gmail SMTP (Free)**
- **Host:** smtp.gmail.com
- **Port:** 587
- **Encryption:** TLS
- **Username:** your-email@gmail.com
- **Password:** App Password (not regular password)

**Setup Gmail:**
1. Enable 2-factor authentication
2. Generate App Password: Google Account → Security → App passwords
3. Use the generated password in your settings

#### **Option B: SendGrid (Recommended for Production)**
- **Host:** smtp.sendgrid.net
- **Port:** 587
- **Encryption:** TLS
- **Username:** apikey
- **Password:** Your SendGrid API key

**Setup SendGrid:**
1. Create free account at sendgrid.com
2. Get API key from Settings → API Keys
3. Use "apikey" as username and your API key as password

#### **Option C: Mailgun**
- **Host:** smtp.mailgun.org
- **Port:** 587
- **Encryption:** TLS
- **Username:** Your Mailgun username
- **Password:** Your Mailgun password

### **Step 4: Test the Setup**

1. **Run the test file:**
   ```
   http://your-domain/test_email_phpmailer.php
   ```

2. **Check the results:**
   - Green checkmark = Email sent successfully
   - Red X = Configuration issue (check settings)

### **Step 5: Update Your Application**

Your `userSide.php` is already configured to use PHPMailer. The system will now:
- Send professional emails with high deliverability
- Log all email attempts for debugging
- Handle errors gracefully
- Provide detailed feedback

## 📧 **SMTP Service Comparison**

| Service | Cost | Reliability | Setup Difficulty | Best For |
|---------|------|-------------|------------------|----------|
| **Gmail SMTP** | Free | ⭐⭐⭐⭐ | ⭐⭐⭐ | Small scale |
| **SendGrid** | $15/month | ⭐⭐⭐⭐⭐ | ⭐⭐⭐ | Production |
| **Mailgun** | $35/month | ⭐⭐⭐⭐⭐ | ⭐⭐⭐ | High volume |
| **Hosting Provider** | Included | ⭐⭐⭐ | ⭐⭐⭐⭐ | Simple setup |

## 🔧 **Configuration Examples**

### **Gmail SMTP Configuration:**
```php
'host' => 'smtp.gmail.com',
'username' => 'solano.mayor.office@gmail.com',
'password' => 'abcd efgh ijkl mnop', // App Password
'port' => 587,
'from_email' => 'noreply@solanomayor.gov.ph',
```

### **SendGrid Configuration:**
```php
'host' => 'smtp.sendgrid.net',
'username' => 'apikey',
'password' => 'SG.your-api-key-here',
'port' => 587,
'from_email' => 'noreply@solanomayor.gov.ph',
```

## 🛠 **Troubleshooting**

### **Common Issues:**

1. **"Class 'PHPMailer' not found"**
   - Solution: Run `composer install`
   - Check that `vendor/autoload.php` exists

2. **"Authentication failed"**
   - Solution: Check username/password
   - For Gmail: Use App Password, not regular password

3. **"Connection timeout"**
   - Solution: Check firewall settings
   - Verify port 587 is open

4. **"Emails going to spam"**
   - Solution: Configure SPF/DKIM records
   - Use reputable SMTP service

### **Debug Mode:**

To enable debug output, change this line in `email_helper_phpmailer.php`:
```php
$mail->SMTPDebug = 2; // Set to 2 for detailed debug output
```

## 📊 **Monitoring and Logs**

### **Email Log File:**
All email attempts are logged to `email_log.txt`:
```
2025-07-30 13:45:22 | SUCCESS | To: user@example.com | User: John Doe | Subject: Appointment Request Received | Message: Email sent successfully
```

### **Error Logging:**
Failed emails are logged to PHP error log with detailed error messages.

## 🎯 **Production Checklist**

- [ ] PHPMailer installed via Composer
- [ ] SMTP credentials configured
- [ ] Test email sent successfully
- [ ] Error handling implemented
- [ ] Email logs being generated
- [ ] SPF/DKIM records configured (for custom domain)
- [ ] Backup SMTP service configured (optional)

## 🚀 **Benefits of This Setup**

✅ **Professional delivery** - 99%+ inbox rates  
✅ **Detailed logging** - Track all email attempts  
✅ **Error handling** - Graceful failure management  
✅ **Scalable** - Handle any volume of emails  
✅ **Reliable** - Industry-standard solution  
✅ **Flexible** - Works with any SMTP service  

## 📞 **Support**

If you encounter issues:
1. Check the email log file
2. Enable debug mode for detailed output
3. Verify SMTP credentials
4. Test with a different SMTP service
5. Check server firewall settings

Your appointment system now has professional email delivery capabilities! 