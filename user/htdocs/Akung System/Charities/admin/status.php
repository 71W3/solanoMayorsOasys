<?php
include "connect.php";

if (isset($_POST['updateStatus'])) 
{
    $statusId = intval($_POST['id']);
    $newStatus = mysqli_real_escape_string($con, $_POST['status']);

    if ($newStatus == "Cancelled") 
    {
        $deleteQuery = "DELETE FROM donation_status WHERE id = $statusId";
        mysqli_query($con, $deleteQuery);
    } 
    elseif ($newStatus == "Completed") 
    {
        $insertQuery = "
            INSERT INTO donationhistory (donor_id, charity_id, donation_id, amount, donation_date, donate_id)
            SELECT 
                ds.donor_id, 
                ds.charity_id, 
                ds.donation_id, 
                ds.amount, 
                COALESCE(cd.date, id.date),  -- Use the donation date from either cash_donations or item_donations
                ds.donate_id
            FROM 
                donation_status ds
            LEFT JOIN cash_donations cd ON ds.donation_id = cd.donation_id
            LEFT JOIN item_donations id ON ds.donate_id = id.donate_id
            WHERE ds.id = $statusId";
        mysqli_query($con, $insertQuery);

        $deleteQuery = "DELETE FROM donation_status WHERE id = $statusId";
        mysqli_query($con, $deleteQuery);
    } 
    else 
    {
        $updateQuery = "UPDATE donation_status SET status = '$newStatus' WHERE id = $statusId";
        mysqli_query($con, $updateQuery);
    }
}

$sql = "
    SELECT
        ds.id,
        d.name AS Donor,
        CASE
            WHEN id.donate_id IS NOT NULL THEN 'Item Donation'
            ELSE 'Cash Donation'
        END AS DonationType,
        CASE
            WHEN id.donate_id IS NOT NULL THEN id.item_name
            ELSE cd.amount
        END AS DonationDetails,
        CASE
            WHEN id.donate_id IS NOT NULL THEN id.date
            ELSE cd.date
        END AS DonationDate,
        ds.status AS Status
    FROM donors d
    JOIN donation_status ds ON d.donor_id = ds.donor_id
    LEFT JOIN item_donations id ON ds.donate_id = id.donate_id
    LEFT JOIN cash_donations cd ON ds.donation_id = cd.donation_id";

$result = mysqli_query($con, $sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
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
    </style>
</head>
<body>
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

    <h3>Donation Status</h3>
    <table>
        <tr>
            <th>No.</th>
            <th>Donor</th>
            <th>Donation Type</th>
            <th>Donation Details</th>
            <th>Donation Date</th>
            <th>Status</th>
            <th>Action</th>
        </tr>
        <?php
        $i = 1;
        while ($row = mysqli_fetch_assoc($result)) 
        {
            echo "<tr>";
            echo "<td>" . $i++ . "</td>";
            echo "<td>" . htmlspecialchars($row['Donor']) . "</td>";
            echo "<td>" . htmlspecialchars($row['DonationType']) . "</td>";
            echo "<td>" . htmlspecialchars($row['DonationDetails']) . "</td>";
            echo "<td>" . htmlspecialchars($row['DonationDate']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Status']) . "</td>";
            echo "<td>
                    <form action='' method='post'>
                        <input type='hidden' name='id' value='" . $row['id'] . "'>
                        <select name='status'>
                            <option value='Pending' " . ($row['Status'] == 'Pending' ? 'selected' : '') . ">Pending</option>
                            <option value='Cancelled'>Cancelled</option>
                            <option value='Completed'>Completed</option>
                        </select>
                        <input type='submit' name='updateStatus' value='Update'>
                    </form>
                  </td>";
            echo "</tr>";
        }
        ?>
    </table>
</body>
</html>
