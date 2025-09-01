<?php
include "connect.php";

// Default values for the search and date filters
$searchName = '';
$startDate = '';
$endDate = '';

if (isset($_GET['searchStud'])) {
    $searchName = mysqli_real_escape_string($con, $_GET['search-studText']);
}

if (isset($_GET['start_date'])) {
    $startDate = mysqli_real_escape_string($con, $_GET['start_date']);
}

if (isset($_GET['end_date'])) {
    $endDate = mysqli_real_escape_string($con, $_GET['end_date']);
}

$sql = "
    SELECT 
    d.name AS donor_name,
    c.charity_name,
    CASE 
        WHEN dh.donate_id IS NULL THEN 'Cash Donation'
        ELSE 'Item Donation'
    END AS donation_type,
    CASE 
        WHEN dh.donate_id IS NULL THEN CONCAT('₱', FORMAT(dn.amount, 2))  -- Formatting amount as currency
        ELSE id.item_name
    END AS donation_details,
    dh.donation_date
FROM donationhistory dh
LEFT JOIN donors d ON dh.donor_id = d.donor_id
LEFT JOIN charities c ON dh.charity_id = c.charity_id
LEFT JOIN cash_donations dn ON dh.donation_id = dn.donation_id
LEFT JOIN item_donations id ON dh.donate_id = id.donate_id
WHERE d.name LIKE '%$searchName%'";  // Keep filtering by donor name

if ($startDate && $endDate) {
    $sql .= " AND dh.donation_date BETWEEN '$startDate' AND '$endDate'"; // Filter by date range
}

$sql .= " ORDER BY dh.donation_date DESC";

$result = mysqli_query($con, $sql);

if (!$result) {
    die("Error in query: " . mysqli_error($con));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donation History</title>
    <link rel="stylesheet" href="Style/design.css">
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
        .cards-containerHistory {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            justify-content: space-between;
        }
        .donation-section {
            width: 48%;
        }
        .donation-card {
            background-color: #fff;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        .donation-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        .donation-header h3 {
            margin: 0;
            font-size: 18px;
            font-weight: bold;
        }
        .donation-type {
            font-size: 14px;
            color: #777;
        }
        .donation-details p {
            margin: 5px 0;
        }
        .donation-footer {
            margin-top: 10px;
            font-size: 14px;
            color: #888;
        }
        .search-container {
            margin-bottom: 20px;
        }
        input[type="date"], input[type="text"] {
            padding: 8px;
            margin: 5px 0;
        }
        .stick
        {
            position: sticky;
            z-index: 1035;
            top: 0;
        }
    </style>
</head>
<body>
    <div class="stick">
<div class="navbar">
    <a href="myAcc.php">My account</a>
    <div class="dropdown">
        <button class="dropbtn">Donation 
            <i class="arrow down"></i>
        </button>
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
</div>

<div class="container">
    <h1>Donation History</h1>
    
    <!-- Search and Date Filter -->
    <div class="search-container">
        <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="get">
            <input type="text" name="search-studText" value="<?php echo htmlspecialchars($searchName); ?>" placeholder="Search by Donor Name">
            <input type="submit" name="searchStud" value="Search">
        </form>
    </div>

    <div class="cards-containerHistory">
        <!-- Cash Donations Section -->
        <div class="donation-section">
            <h2>Cash Donations</h2>
            <?php
            $result->data_seek(0); // Reset the result pointer
            while ($row = mysqli_fetch_assoc($result)) {
                if ($row['donation_type'] == 'Cash Donation') {
                    echo '<div class="donation-card">';
                    echo '<div class="donation-header">';
                    echo '<h3>' . htmlspecialchars($row['donor_name']) . '</h3>';
                    echo '<span class="donation-type">' . htmlspecialchars($row['donation_type']) . '</span>';
                    echo '</div>';
                    echo '<div class="donation-details">';
                    echo '<p><strong>Charity:</strong> ' . htmlspecialchars($row['charity_name']) . '</p>';
                    echo '<p><strong>Details:</strong> ' . htmlspecialchars($row['donation_details']) . '</p>';
                    echo '</div>';
                    echo '<div class="donation-footer">' . htmlspecialchars($row['donation_date']) . '</div>';
                    echo '</div>';
                }
            }
            ?>
        </div>

        <!-- Item Donations Section -->
        <div class="donation-section">
            <h2>Item Donations</h2>
            <?php
            $result->data_seek(0); // Reset the result pointer
            while ($row = mysqli_fetch_assoc($result)) {
                if ($row['donation_type'] == 'Item Donation') {
                    echo '<div class="donation-card">';
                    echo '<div class="donation-header">';
                    echo '<h3>' . htmlspecialchars($row['donor_name']) . '</h3>';
                    echo '<span class="donation-type">' . htmlspecialchars($row['donation_type']) . '</span>';
                    echo '</div>';
                    echo '<div class="donation-details">';
                    echo '<p><strong>Charity:</strong> ' . htmlspecialchars($row['charity_name']) . '</p>';
                    echo '<p><strong>Item:</strong> ' . htmlspecialchars($row['donation_details']) . '</p>';
                    echo '</div>';
                    echo '<div class="donation-footer">' . htmlspecialchars($row['donation_date']) . '</div>';
                    echo '</div>';
                }
            }
            ?>
        </div>
    </div>
</div>

</body>
</html>
