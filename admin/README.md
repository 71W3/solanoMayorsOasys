# Admin Registration System

This directory contains the admin registration system for the Solano Mayor's Office Appointment System.

## Files

### `adminRegister.php`
The main admin registration page that allows creation of administrative accounts with different roles.

### `adminRegister.css`
Styling for the admin registration page with modern design and responsive layout.

### `adminRegister.js`
JavaScript functionality for enhanced user experience including password validation, form handling, and animations.

### `connect.php`
Database connection file for the admin system.

## Features

### Role-Based Registration
The system supports the following administrative roles:
- **Admin** - General administrative access
- **Front Desk** - Front desk staff access
- **Mayor** - Mayor's office access
- **Super Admin** - Highest level administrative access

### Form Validation
- Real-time password strength checking
- Password confirmation matching
- Input validation for all fields
- Phone number format validation (10-15 digits)
- Email format validation

### User Experience
- Modern, responsive design
- Password visibility toggle
- Real-time form validation feedback
- Auto-save functionality (localStorage)
- Smooth animations and transitions
- Mobile-friendly interface

## Database Structure

The admin registration system uses the existing `users` table with the following structure:

```sql
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    username VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    role ENUM('admin', 'frontdesk', 'mayor', 'superadmin', 'user') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

## Usage

### Accessing the Registration Page
Navigate to: `/admin/adminRegister.php`

### Registration Process
1. Fill in all required fields:
   - Full Name
   - Username (must be unique)
   - Email (must be unique)
   - Phone Number (10-15 digits)
   - Address
   - Role selection
   - Password (minimum 8 characters)
   - Confirm Password

2. Click "Create Admin Account" to submit

3. Upon successful registration, you'll be redirected to the admin login page

### Security Features
- Password hashing using PHP's `password_hash()` function
- Input sanitization to prevent SQL injection
- Session-based security
- Unique username and email validation

## Customization

### Styling
Modify `adminRegister.css` to change the visual appearance:
- Color scheme (CSS variables in `:root`)
- Layout dimensions
- Animations and transitions
- Responsive breakpoints

### Functionality
Modify `adminRegister.js` to add or change features:
- Form validation rules
- Password strength requirements
- Animation timings
- Auto-save intervals

### Backend Logic
Modify `adminRegister.php` to change:
- Validation rules
- Database operations
- Error handling
- Success redirects

## Browser Support

- Chrome 60+
- Firefox 55+
- Safari 12+
- Edge 79+

## Dependencies

- PHP 7.4+
- MySQL 5.7+
- Bootstrap 5.3.0
- Bootstrap Icons 1.10.0
- Modern web browser with JavaScript enabled

## Security Notes

- All passwords are hashed using PHP's built-in password hashing
- Input is sanitized to prevent SQL injection
- Sessions are used for security
- HTTPS is recommended for production use
- Regular security audits are recommended

## Troubleshooting

### Common Issues

1. **Database Connection Error**
   - Check `connect.php` configuration
   - Verify database credentials
   - Ensure MySQL service is running

2. **Registration Fails**
   - Check for duplicate username/email
   - Verify all required fields are filled
   - Check password requirements (minimum 8 characters)

3. **Styling Issues**
   - Ensure CSS file is accessible
   - Check browser console for errors
   - Verify Bootstrap CSS is loading

4. **JavaScript Errors**
   - Check browser console for errors
   - Ensure JavaScript is enabled
   - Verify all required elements exist in DOM

### Debug Mode
Enable debug mode by setting:
```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

## Support

For technical support or questions about the admin registration system, please contact the development team.

## Version History

- **v1.0** - Initial release with basic admin registration
- **v1.1** - Added enhanced styling and animations
- **v1.2** - Improved form validation and user experience
- **v1.3** - Added auto-save functionality and mobile optimization
