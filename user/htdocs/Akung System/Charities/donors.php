<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blue Palette Design</title>
    <link rel="stylesheet" href="Style/design.css">
</head>
<body>
<div class="navbar">
    <a href="front.php">Go Back</a>
</div>

<div class="container">
    <h3>Donors Information</h3>
    <form name="donorForm" action="functions.php" method="post" onsubmit="return validatePassword()">
        <table class="form-table">
            <tr>
                <td>Name:</td>
                <td><input type="text" name="name" required></td>
            </tr>
            <tr>
                <td>Email:</td>
                <td><input type="text" name="email" required></td>
            </tr>
            <tr>
                <td>Phone:</td>
                <td><input type="text" name="phone" required></td>
            </tr>
            <tr>
                <td>Address:</td>
                <td><input type="text" name="address" required></td>
            </tr>
            <tr>
                <td>Username:</td>
                <td><input type="text" name="username" required></td>
            </tr>
            <tr>
                <td>Password:</td>
                <td><input type="password" name="password" required></td>
            </tr>           
            <tr>
                <td></td>
                <td><input type="submit" name="register" value="Register" onclick="return confirm('Are you sure you want to add?')"></td>
            </tr>
        </table>
    </form>
</div>
<script>
    function validatePassword() {
        var password = document.forms["donorForm"]["password"].value;
        var regex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*])[A-Za-z\d!@#$%^&*]{8,}$/;
        
        if (!regex.test(password)) {
            alert("Password must be at least 8 characters long, contain an uppercase letter, a lowercase letter, a number, and a special character.");
            return false;
        }
        return true;
    }
</script>


</body>
</html>
