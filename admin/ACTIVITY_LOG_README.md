# Activity Logging System for Admin Panel

## Overview
The Activity Logging System provides comprehensive tracking of all admin and frontdesk activities for superadmin monitoring. It tracks login/logout, appointment management, walk-in registrations, and other administrative actions.

## Features

### 1. Comprehensive Activity Tracking
- **Login/Logout Activities**: Tracks when admins and frontdesk staff log in and out
- **Appointment Management**: Logs approvals, declines, reschedules, and completions
- **Walk-in Registrations**: Tracks walk-in appointments added by staff
- **Queue Management**: Logs queue-related actions
- **Schedule Changes**: Tracks schedule modifications
- **Announcement Actions**: Logs creation, updates, and deletions
- **User Management**: Tracks user account actions

### 2. Detailed Information
Each activity log entry includes:
- **User Information**: Name and role of the person performing the action
- **Action Details**: Description of what was done
- **Timestamp**: Exact time of the action
- **IP Address**: For security tracking
- **Target Information**: What was affected (appointment ID, user, etc.)

### 3. Real-time Monitoring
- **Live Updates**: Activity log updates in real-time
- **Role-based Filtering**: Filter activities by user role (Admin, Front Desk, Mayor)
- **Statistics Dashboard**: Overview of total activities and recent actions
- **Search and Filter**: Easy navigation through activity history

## Files Added/Modified

### New Files
- `activity_logger.php` - Core activity logging functions
- Activity logging integrated directly into appointment management
- `ACTIVITY_LOG_README.md` - This documentation file

### Modified Files
- `adminPanel.php` - Updated to show comprehensive activity log
- `adminPanel_functions.php` - Added activity data retrieval functions
- `adminPanel.css` - Added styling for activity log components
- `appointment.php` - Added activity logging for approvals, declines, reschedules, and completions
- `reschedule.php` - Added activity logging for reschedules
- `walk_in.php` - Added activity logging for walk-ins
- `login.php` - Added activity logging for logins
- `logoutAdmin.php` - Added activity logging for logouts

## Database Schema

The system automatically creates an `activity_log` table with the following structure:

```sql
CREATE TABLE `activity_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `user_name` varchar(255) NOT NULL,
  `user_role` varchar(50) NOT NULL,
  `action_type` varchar(100) NOT NULL,
  `action_description` text NOT NULL,
  `target_type` varchar(50) DEFAULT NULL,
  `target_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_action_type` (`action_type`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_user_role` (`user_role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## Usage

### 1. Automatic Logging
Most activities are logged automatically when you:
- Approve/decline appointments
- Reschedule appointments
- Register walk-ins
- Log in/log out
- Manage users or announcements

### 2. Manual Logging
You can manually log custom activities using:

```php
logActivity($con, $user_id, $user_name, $user_role, $action_type, $description, $target_type, $target_id);
```

### 3. Viewing Activities
- **Admin Panel**: View recent activities in the sidebar
- **Comprehensive Log**: Full activity log with filtering options
- **Statistics**: Overview of total activities and role-based counts

## Activity Types

### System Activities
- `login` - User logged in
- `logout` - User logged out

### Appointment Activities
- `appointment_approved` - Appointment was approved
- `appointment_declined` - Appointment was declined
- `appointment_rescheduled` - Appointment was rescheduled
- `appointment_completed` - Appointment was marked as completed

### Other Activities
- `walk_in_registered` - Walk-in appointment registered
- `queue_management` - Queue-related actions
- `schedule_change` - Schedule modifications
- `announcement_created` - New announcement created
- `user_created` - New user account created

## Security Features

### 1. IP Address Tracking
- Records IP address of each action
- Helps identify suspicious activities
- Useful for security audits

### 2. User Agent Logging
- Tracks browser/device information
- Helps identify unauthorized access
- Useful for troubleshooting

### 3. Role-based Access
- Only logs activities for authorized users
- Prevents unauthorized logging
- Maintains data integrity

## Testing

To test the activity logging system:

1. **Test Activity Logging**: Perform actions in the admin panel to see activity logging in action
2. **Check Database**: Verify `activity_log` table exists
3. **Perform Actions**: Try approving/declining appointments
4. **View Logs**: Check admin panel for new activities

## Troubleshooting

### Common Issues

1. **Table Not Created**
   - Check database permissions
   - Verify `activity_logger.php` is included
   - Check for PHP errors

2. **Activities Not Logging**
   - Verify session variables are set
   - Check database connection
   - Ensure functions are called correctly

3. **Performance Issues**
   - Check database indexes
   - Monitor query performance
   - Consider archiving old logs

### Error Logging
All errors are logged to PHP error log. Check your server's error log for debugging information.

## Future Enhancements

### Planned Features
- **Export Functionality**: Export activity logs to CSV/PDF
- **Advanced Filtering**: Date range, action type, user filtering
- **Real-time Notifications**: Alert superadmins of important activities
- **Activity Analytics**: Charts and graphs of activity patterns
- **Automated Cleanup**: Archive old logs automatically

### Customization
The system is designed to be easily extensible. You can:
- Add new activity types
- Customize logging fields
- Integrate with external monitoring systems
- Add custom reporting features

## Support

For technical support or questions about the activity logging system:
1. Check this documentation
2. Review the test script output
3. Check PHP error logs
4. Verify database connectivity

## Conclusion

The Activity Logging System provides comprehensive monitoring capabilities for superadmins to track all administrative activities. It enhances security, provides audit trails, and enables better oversight of the appointment system operations.

By implementing this system, you now have full visibility into:
- Who performed what actions
- When actions were performed
- What was affected by each action
- Security-related information (IP addresses, user agents)

This creates a robust foundation for administrative oversight and security monitoring.
