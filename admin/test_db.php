<?php
// Test database connection and basic operations
header('Content-Type: text/plain');

try {
    // Include database connection
    require_once 'connect.php';
    
    echo "Database connection test:\n";
    echo "Connection status: " . ($con ? "SUCCESS" : "FAILED") . "\n";
    
    if ($con) {
        // Test basic query
        $test_query = mysqli_query($con, "SELECT COUNT(*) as count FROM users");
        if ($test_query) {
            $user_count = mysqli_fetch_assoc($test_query)['count'];
            echo "Users table accessible. Current count: $user_count\n";
            
            // Test table structure
            $columns_query = mysqli_query($con, "DESCRIBE users");
            if ($columns_query) {
                echo "Users table structure:\n";
                while ($column = mysqli_fetch_assoc($columns_query)) {
                    echo "- {$column['Field']}: {$column['Type']} {$column['Null']} {$column['Key']} {$column['Default']}\n";
                }
            }
            
            // Test insert permissions
            $test_insert = mysqli_query($con, "INSERT INTO users (name, email, username, password, phone, address, role) VALUES ('TEST_USER', 'test@test.com', 'testuser123', 'testpass', '123', 'test', 'user')");
            if ($test_insert) {
                echo "INSERT test: SUCCESS\n";
                // Clean up
                mysqli_query($con, "DELETE FROM users WHERE username = 'testuser123'");
                echo "Test record cleaned up\n";
            } else {
                echo "INSERT test: FAILED - " . mysqli_error($con) . "\n";
            }
            
        } else {
            echo "Users table query failed: " . mysqli_error($con) . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
