<?php
include "connect.php";
session_start(); 

if (isset($_POST['register'])) {
    $name = $_POST['name'];
    $address = $_POST['address'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $user = $_POST['username'];
    $pass = $_POST['password'];

    // Server-side password validation
    if (!preg_match("/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*])[A-Za-z\d!@#$%^&*]{8,}$/", $pass)) {
        echo "<script>alert('Password must be at least 8 characters long, contain an uppercase letter, a lowercase letter, a number, and a special character.');</script>";
        exit;
    }

    // If the password is valid, proceed with the insertion
    $sql = "INSERT INTO donors (name, email, phone, address, username, password) 
            VALUES ('$name', '$email', '$phone', '$address', '$user', '$pass')";
    $res = mysqli_query($con, $sql) or die(mysqli_error($con));

    if ($res) {
        echo "<script>alert('Registered Successfully');</script>";
        include "donors.php";
    }
}

if (isset($_POST["userlogin"])) {
    $user = mysqli_real_escape_string($con, $_POST["username"]);
    $psw = mysqli_real_escape_string($con, $_POST["password"]);

    $sql = "SELECT donor_id, name, email, phone, address, username, password 
            FROM donors WHERE username = ? AND password = ?";

    $stmt = mysqli_prepare($con, $sql);
    mysqli_stmt_bind_param($stmt, 'ss', $user, $psw);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($res) > 0) {
        $row = mysqli_fetch_object($res);
        $_SESSION['donor_id'] = $row->donor_id;
        $_SESSION['name'] = $row->name;
        $_SESSION['email'] = $row->email;
        $_SESSION['phone'] = $row->phone;
        $_SESSION['address'] = $row->address;
        header("Location: myAcc.php");
        exit();
    } else {
        $sql = "SELECT admin_id, name, username, password 
                FROM admin WHERE username = ? AND password = ?";
        
        $stmt = mysqli_prepare($con, $sql);
        mysqli_stmt_bind_param($stmt, 'ss', $user, $psw);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);

        if (mysqli_num_rows($res) > 0) {
            header("Location: admin/donors.php");
        } else {
            echo "<script>alert('Invalid username or password');</script>";
            include "front.php";
        }
    }
}

if (isset($_POST["logout"])) {
    session_unset();
    session_destroy();
    header("Location: front.php");
    exit();
}
?>
