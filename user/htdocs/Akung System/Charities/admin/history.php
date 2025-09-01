<?php
include "connect.php";

$searchName = '';
if (isset($_GET['searchStud'])) {
    $searchName = mysqli_real_escape_string($con, $_GET['search-studText']);
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
            WHEN dh.donate_id IS NULL THEN CONCAT('₱', dn.amount)
            ELSE id.item_name
        END AS donation_details,
        dh.donation_date
    FROM donationhistory dh
    LEFT JOIN donors d ON dh.donor_id = d.donor_id
    LEFT JOIN charities c ON dh.charity_id = c.charity_id
    LEFT JOIN cash_donations dn ON dh.donation_id = dn.donation_id
    LEFT JOIN item_donations id ON dh.donate_id = id.donate_id
    WHERE d.name LIKE '%$searchName%'  -- Filter by donor name
    ORDER BY dh.donation_date DESC";

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
    <link rel="stylesheet" href="adminStyle/design.css">
    <style>
        .butun {
        background-color: #555  ;
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
    .stick{
            position: sticky;
            z-index: 1035;
            top: 0;
        }
    </style>
</head>
<body>
    <div class="stick">
<div class="navbar">
    <div class="dropdown">
        <button class="dropbtn">Donation 
            <i class="arrow down"></i>
        </button>
        <div class="dropdown-content">
            <a href="donors.php">Donors</a>
            <a href="status.php">Donation Status</a>
        </div>
    </div> 
    <a href="charity.php">Charities</a>
    <a href="history.php">Donation History</a>
    <form action="../loginFunctions.php" method="post">
        <button class="butun" name="logout">Log out</button>
    </form>
</div>
</div>

<div class="container">
    <h1>Donation History</h1>
    <div class="search-container">
        <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="get">
            <input type="text" name="search-studText" value="<?php echo htmlspecialchars($searchName); ?>" placeholder="Search by Donor Name">
            <input type="submit" name="searchStud" value="Search">
        </form>
    </div>

    <table>
        <thead>
            <tr>
                <th>Donor Name</th>
                <th>Charity Name</th>
                <th>Donation Type</th>
                <th>Donation Details</th>
                <th>Donation Date</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if (mysqli_num_rows($result) > 0) 
            {
                while ($row = mysqli_fetch_assoc($result)) 
                {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($row['donor_name']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['charity_name']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['donation_type']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['donation_details']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['donation_date']) . "</td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='5'>No donations found for this donor. <a href='history.php'>back</a></td></tr>";
            }
            ?>
        </tbody>
    </table>
</div>

</body>
</html>
