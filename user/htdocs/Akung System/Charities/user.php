<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Login</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <style>
    body {
      font-family: 'Arial', sans-serif;
      background-color: #f7f7f7;
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
      margin: 0;
    }

    .login-container {
      background-color: #fff;
      padding: 40px;
      border-radius: 10px;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
      width: 400px;
      text-align: center;
    }

    h3 {
      font-size: 24px;
      color: #333;
      margin-bottom: 20px;
    }

    .form-table {
      width: 100%;
      margin: 20px 0;
      border-collapse: collapse;
    }

    .form-table td {
      padding: 12px;
      font-size: 16px;
    }

    .form-table input[type="text"],
    .form-table input[type="password"] {
      width: 100%;
      padding: 10px;
      font-size: 16px;
      border-radius: 5px;
      border: 1px solid #ccc;
      margin-top: 5px;
    }

    .form-table input[type="submit"] {
      width: 100%;
      padding: 12px;
      background-color: #007bff;
      color: #fff;
      font-size: 18px;
      border: none;
      border-radius: 5px;
      cursor: pointer;
      margin-top: 20px;
      transition: background-color 0.3s ease;
    }

    .form-table input[type="submit"]:hover {
      background-color: #0056b3;
    }

    .error-message {
      color: red;
      margin-top: 20px;
    }

    .toggle-password {
      position: absolute;
      right: 25px;
      top: 145px;
      cursor: pointer;
    }

    .form-table td:last-child {
      text-align: center;
    }

    @media (max-width: 480px) {
      .login-container {
        width: 90%;
      }
    }
  </style>
</head>
<body>

<div class="login-container">
  <h3>Login</h3>
  <form action="loginFunctions.php" method="post" onsubmit="return confirm('Are you sure you want to login?')">
    <table class="form-table">
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
        <td><a href="donors.php">Create Account</a>
            <input type="submit" name="userlogin" value="Log in"></td>
      </tr>
    </table>
  </form>
  <div id="error-message" class="error-message"></div>
</div>

</body>
</html>
