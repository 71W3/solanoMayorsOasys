<?php
include "connect.php";
include "loginFunctions.php";
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
    <h1><?php echo $_SESSION['name']; ?> Pending Donation.</h1>
</div>

<?php
$id = $_SESSION['donor_id'];

$sql = "SELECT
    ds.id,
    d.name AS Donor,
    c.charity_name AS Charity,
    CASE
        WHEN id.donate_id IS NOT NULL THEN 'Item Donation'
        ELSE 'Cash Donation'
    END AS DonationType,
    CASE
        WHEN id.donate_id IS NOT NULL THEN id.item_name
        ELSE CONCAT('₱', FORMAT(cd.amount, 2))  -- Formatting cash donation amount as currency
    END AS DonationDetails,
    CASE
        WHEN id.donate_id IS NOT NULL THEN id.date
        ELSE cd.date
    END AS DonationDate,
    ds.status AS Status
FROM donors d
JOIN donation_status ds ON d.donor_id = ds.donor_id
LEFT JOIN item_donations id ON ds.donate_id = id.donate_id
LEFT JOIN cash_donations cd ON ds.donation_id = cd.donation_id
LEFT JOIN charities c ON ds.charity_id = c.charity_id
WHERE d.donor_id = '$id' AND ds.status = 'Pending'";

$res = mysqli_query($con, $sql);

if ($res && mysqli_num_rows($res) > 0) {
?>

<div class="donation-container">
    <?php
    $i = 1;
    while ($row = mysqli_fetch_assoc($res)) {
        echo "<div class='donation-card'>";
        echo "<div class='card-header'>";
        echo "<h3>Donation #" . $i++ . "</h3>";
        echo "<p>Status: <span class='status'>" . htmlspecialchars($row['Status']) . "</span></p>";
        echo "</div>";
        echo "<div class='card-body'>";
        echo "<p><strong>Donor:</strong> " . htmlspecialchars($row['Donor']) . "</p>";
        echo "<p><strong>Charity:</strong> " . htmlspecialchars($row['Charity']) . "</p>";
        echo "<p><strong>Donation Type:</strong> " . htmlspecialchars($row['DonationType']) . "</p>";
        echo "<p><strong>Donation Details:</strong> " . htmlspecialchars($row['DonationDetails']) . "</p>";
        echo "<p><strong>Donation Date:</strong> " . htmlspecialchars($row['DonationDate']) . "</p>";
        echo "</div>";
        echo "</div>";
    }
    ?>
</div>

<?php
} else {
    echo "<p>No pending donations found.</p>";
}
?>

<style>
    .butun {
        background-color: #2980b9;
        color: #F0F3FA;
        border: none;
        border-radius: 5px;
        padding: 10px 20px;
        font-size: 16px;
        cursor: pointer;
        transition: all 0.3s ease-in-out;
        align-items: right;
        float: right;
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

    .card-header p {
        margin: 5px 0;
    }

    .status {
        color: #e74c3c;
    }

    .card-body p {
        margin: 10px 0;
        font-size: 16px;
    }

    .card-body p strong {
        color: #ffffff;
    }

    /* Adjusting for mobile */
    @media (max-width: 600px) {
        .donation-container {
            grid-template-columns: 1fr;
        }
    }
</style>
