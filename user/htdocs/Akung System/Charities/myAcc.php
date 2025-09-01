<?php
include "connect.php";
include "loginFunctions.php";
?>

<link rel="stylesheet" href="Style/design.css">

<div class="navbar">
    <a href="myAcc.php">My account</a>
    <div class="dropdown">
        <button class="dropbtn">Donation</button>
        <div class="dropdown-content">
            <a href="donation.php">Donation Area</a>
        </div>
    </div>
    <a href="charityList.php">Charities</a>
    <a href="history.php">Donation History</a>
    <form action="loginFunctions.php" method="post">
        <button class="butun" name="logout">Log out</button>
    </form>
</div>

<div class="welcome-message">
    <h1>Hello, <?php echo $_SESSION['name']; ?></h1>
</div>

<?php
$id = $_SESSION['donor_id'];

$sql = "SELECT donor_id, name, email, phone, address, username, password 
        FROM donors WHERE donor_id = '$id'";

$res = mysqli_query($con, $sql);

if ($res && mysqli_num_rows($res) > 0) {
    $row = mysqli_fetch_object($res);
?>
<div class="container">
    <div class="side-menu">
        <a href="userPending.php">Donations</a>
        <a href="userDonations.php">Pending Donations</a>
    </div>

    <div class="profile-info">  
        <img src="images/user-trust.png" alt="User Logo" width="200px" height="200px">
    </div>

    <div class="account-info">
        <h1>Account Information</h1>
        <p><span>Full Name:</span> <?php echo $row->name; ?></p>
        <p><span>Email:</span> <?php echo $row->email; ?></p>
        <p><span>Phone:</span> <?php echo $row->phone; ?></p>
        <p><span>Address:</span> <?php echo $row->address; ?></p>
        <p><span>Username:</span> <?php echo $row->username; ?></p>
        <p><span>Password:</span> <?php echo $row->password; ?></p>
    </div>
</div>
<?php
}
?>

<style>
    .butun {
        background-color: #395886;
        color: #F0F3FA;
        border: none;
        border-radius: 5px;
        padding: 10px 20px;
        font-size: 16px;
        cursor: pointer;
        transition: all 0.3s ease-in-out;
        box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.2);
        align-items: right;
        float: right;
    }

    .butun:hover {
        background-color: #638ECB;
        box-shadow: 0px 6px 8px rgba(0, 0, 0, 0.3);
        transform: scale(1.05);
    }

    .welcome-message {
        margin-top: 70px;
        text-align: center;
        font-size: 24px;
        color: #395886;
    }

    .container {
        display: flex;
        justify-content: space-between;
        padding: 20px;
        margin-top: 30px;
    }

    .side-menu {
        width: 25%;
        background-color: lightblue;
        padding: 15px;
        border-radius: 10px;
        box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.1);
    }

    .side-menu a {
        display: block;
        padding: 10px 15px;
        color: #395886;
        text-decoration: none;
        border-radius: 5px;
        margin-bottom: 10px;
    }

    .side-menu a:hover {
        background-color: #395886;
        color: #fff;
    }

    .profile-info {
        width: 30%;
        text-align: center;
    }

    .account-info {
        width: 40%;
    }

    .account-info h1 {
        color: #395886;
    }

    .account-info p {
        font-size: 18px;
    }

    .account-info span {
        font-weight: bold;
    }
</style>
