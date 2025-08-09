<?php
session_start();
include 'kon.php';

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    echo "Not logged in";
    exit();
}

$username = $_SESSION['username'];
echo "<h2>Checking User Email</h2>";
echo "<p>Username: $username</p>";

// Get user details
$query = "SELECT id, username, email, name FROM users WHERE username = '$username'";
$result = mysqli_query($con, $query);

if ($result && mysqli_num_rows($result) > 0) {
    $user = mysqli_fetch_assoc($result);
    echo "<h3>User Details:</h3>";
    echo "<ul>";
    echo "<li><strong>ID:</strong> " . $user['id'] . "</li>";
    echo "<li><strong>Username:</strong> " . $user['username'] . "</li>";
    echo "<li><strong>Email:</strong> " . ($user['email'] ? $user['email'] : '<span style="color: red;">NO EMAIL SET</span>') . "</li>";
    echo "<li><strong>Full Name:</strong> " . ($user['name'] ? $user['name'] : '<span style="color: red;">NO NAME SET</span>') . "</li>";
    echo "</ul>";
    
    if (!$user['email']) {
        echo "<div style='background: #ffe6e6; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
        echo "<h4>⚠️ Problem Found!</h4>";
        echo "<p>Your user account doesn't have an email address. This is why you're not receiving emails.</p>";
        echo "<p><strong>Solution:</strong> Add your email address to your user profile.</p>";
        echo "</div>";
    } else {
        echo "<div style='background: #e6ffe6; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
        echo "<h4>✅ Email Found!</h4>";
        echo "<p>Your email address is set to: <strong>" . $user['email'] . "</strong></p>";
        echo "</div>";
    }
    
} else {
    echo "<p style='color: red;'>User not found in database.</p>";
}

// Test email functions
echo "<h3>Testing Email Functions:</h3>";
require_once 'email_helper_phpmailer.php';

$user_id = $user['id'] ?? 0;
$test_email = getUserEmail($con, $user_id);
$test_name = getUserFullName($con, $user_id);

echo "<ul>";
echo "<li><strong>getUserEmail():</strong> " . ($test_email ? $test_email : 'NULL') . "</li>";
echo "<li><strong>getUserFullName():</strong> " . ($test_name ? $test_name : 'NULL') . "</li>";
echo "</ul>";

if ($test_email && $test_name) {
    echo "<div style='background: #e6ffe6; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<h4>✅ Email Functions Working!</h4>";
    echo "<p>The email functions are working correctly.</p>";
    echo "</div>";
} else {
    echo "<div style='background: #ffe6e6; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<h4>❌ Email Functions Not Working!</h4>";
    echo "<p>The email functions are not returning your email/name.</p>";
    echo "</div>";
}
?> 