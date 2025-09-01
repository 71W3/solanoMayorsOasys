<?php
include "connect.php";
include "loginFunctions.php";

$selectedYear = isset($_POST['year']) ? $_POST['year'] : date("Y");

$id = $_SESSION['donor_id'];

$totalSql = "SELECT SUM(dn.amount) AS total_cash_donations
             FROM donationhistory dh
             LEFT JOIN cash_donations dn ON dh.donation_id = dn.donation_id
             WHERE dh.donor_id = '$id' AND YEAR(dh.donation_date) = '$selectedYear'";

$totalRes = mysqli_query($con, $totalSql);
$totalRow = mysqli_fetch_assoc($totalRes);
$totalCashDonations = $totalRow['total_cash_donations'] ? '₱' . number_format($totalRow['total_cash_donations'], 2) : '₱0.00';

$sql = "SELECT 
    d.name AS donor_name,
    c.charity_name,
    CASE 
        WHEN dh.donate_id IS NULL THEN 'Cash Donation'
        ELSE 'Item Donation'
    END AS donation_type,
    CASE 
        WHEN dh.donate_id IS NULL THEN CONCAT('₱', FORMAT(dn.amount, 2))
        ELSE id.item_name
    END AS donation_details,
    dh.donation_date
FROM donationhistory dh
LEFT JOIN donors d ON dh.donor_id = d.donor_id
LEFT JOIN charities c ON dh.charity_id = c.charity_id
LEFT JOIN cash_donations dn ON dh.donation_id = dn.donation_id
LEFT JOIN item_donations id ON dh.donate_id = id.donate_id
WHERE dh.donor_id = '$id' AND YEAR(dh.donation_date) = '$selectedYear'
ORDER BY dh.donation_date DESC
";

$res = mysqli_query($con, $sql);

?>

<link rel="stylesheet" href="Style/design.css">
<div class="navbar">
    <a href="myAcc.php">My Account</a>
    <div class="dropdown">
        <button class="dropbtn">Donation</button>
        <div class="dropdown-content">
            <a href="donors.php">Registration</a>
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
    <h1><?php echo htmlspecialchars($_SESSION['name']); ?> Donations</h1>
</div>

<div class="total">
    <div class="pilter">
<form action="userPending.php" method="POST" style="text-align: center; margin: 20px 0;">
    <label for="year">Select Year: </label>
    <input type="number" name="year" id="year" value="<?php echo $selectedYear; ?>" max="<?php echo date("Y"); ?>" min="2000" />
    <button type="submit" class="butun1">Filter</button>
</form>
</div>

<div class="total-donations">
    <h3>Total Cash Donations for <?php echo $selectedYear; ?>: <?php echo $totalCashDonations; ?></h3>
</div>
</div>

<?php
if ($res && mysqli_num_rows($res) > 0) {
?>

<div class="donation-container">
    <?php
    $i = 1;
    while ($row = mysqli_fetch_assoc($res)) {
        echo "<div class='donation-card'>";
        echo "<div class='card-header'>";
        echo "<h3>Donation #" . $i++ . "</h3>";
        echo "</div>";
        echo "<div class='card-body'>";
        echo "<p><strong>Donor:</strong> " . htmlspecialchars($row['donor_name']) . "</p>";
        echo "<p><strong>Charity:</strong> " . htmlspecialchars($row['charity_name']) . "</p>";
        echo "<p><strong>Donation Type:</strong> " . htmlspecialchars($row['donation_type']) . "</p>";
        echo "<p><strong>Donation Details:</strong> " . htmlspecialchars($row['donation_details']) . "</p>";
        echo "<p><strong>Donation Date:</strong> " . htmlspecialchars($row['donation_date']) . "</p>";
        echo "</div>";
        echo "</div>";
    }
    ?>
</div>

<?php
} else {
    echo "<p>No donations found.</p>";
}
?>

<style>
    .pilter
    {
        margin-top: 10px;
    }
    .total
    {
        margin-left: 457px;
        background-color: rgb(250, 249, 246);
        height: 100px;
        max-width: 40%;
        box-shadow: 2px 2px 5px rgba(0, 0, 0, 0.1);
        border-radius: 15px;
    }
    .butun {
        background-color: #2980b9;
        color: #F0F3FA;
        border: none;
        border-radius: 5px;
        padding: 10px 20px;
        font-size: 16px;
        cursor: pointer;
        transition: all 0.3s ease-in-out;
        float: right;
    }
    .butun1 {
        background-color: #2980b9;
        color: #F0F3FA;
        border: none;
        border-radius: 5px;
        padding: 10px 20px;
        font-size: 12px;
        cursor: pointer;
        transition: all 0.3s ease-in-out;
    }

    .butun:hover {
        background-color: #3498db;
        transform: scale(1.05);
    }

    .welcome-message {
        margin-top: 70px;
        text-align: center;
        font-size: 24px;
        color: #2c3e50;
    }

    .donation-container {
        width: 80%;
        margin: 30px auto;
        padding: 20px;
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 20px;
        background-color: #ecf0f1;
        border-radius: 10px;
    }

    .donation-card {
        background-color: #628ECB;
        color: #f0f3fa;
        border-radius: 8px;
        box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.1);
        padding: 20px;
        transition: transform 0.3s ease-in-out;
        text-align: left;
    }

    .donation-card:hover {
        transform: translateY(-5px);
        box-shadow: 0px 6px 8px rgba(0, 0, 0, 0.2);
    }

    .card-header {
        border-bottom: 2px solid #2980b9;
        margin-bottom: 10px;
    }

    .card-header h3 {
        margin: 0;
        font-size: 20px;
        color: #ffffff;
    }

    .card-body p {
        margin: 10px 0;
        font-size: 16px;
    }

    .card-body p strong {
        color: #ffffff;
    }

    .total-donations {
        text-align: center;
        margin-top: 20px;
        font-size: 18px;
        color: #2980b9;
    }

    @media (max-width: 600px) {
        .donation-container {
            grid-template-columns: 1fr;
        }
    }
</style>

