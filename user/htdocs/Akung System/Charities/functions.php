<?php
include "connect.php";

if (isset($_POST['deleteCharity'])) 
{
    $id = $_POST['charity_id'];

    $sql = "DELETE FROM donation_status WHERE id = $statusId";
    $res = mysqli_query($con, $sql) or die(mysqli_error($con));
    if ($res) {
        include "charityList";
        echo "<script>alert('Deleted Successfully');</script>";
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['submitCash'])) {
        $donor_id = $_POST['donors'];
        $charity_id = $_POST['charities'];
        $amount = $_POST['amount'];
        $date = $_POST['date'];

        $sql = "INSERT INTO cash_donations (donor_id, charity_id, amount, date) VALUES ('$donor_id', '$charity_id', '$amount', '$date')";
        mysqli_query($con, $sql) or die(mysqli_error($con));

        $donation_id = mysqli_insert_id($con);

        $status_sql = "INSERT INTO donation_status (donor_id, donation_id, status, charity_id, amount, date) VALUES ('$donor_id', '$donation_id', 'pending', '$charity_id', '$amount', '$date')";
        mysqli_query($con, $status_sql) or die(mysqli_error($con));

        echo "<script>alert('Thank you! You can now go to the nearest office in your area to complete your donation. God Bless You!');</script>";
        include "donation.php";

    } elseif (isset($_POST['submitItems'])) {
        $donor_id = $_POST['donors'];
        $charity_id = $_POST['charities'];
        $date = $_POST['date'];
        $item_names = $_POST['item_name'];
        $categories = $_POST['category'];
        $quantities = $_POST['quantity'];
        $expiration_dates = isset($_POST['expiration_date']) ? $_POST['expiration_date'] : [];

        foreach ($item_names as $index => $item_name) {
            $category = $categories[$index];
            $quantity = $quantities[$index];
            $expiration_date = isset($expiration_dates[$index]) && !empty($expiration_dates[$index]) ? $expiration_dates[$index] : null;

            // Check if quantity is less than 0
            if ($quantity < 0) {
                echo "<script>alert('Quantity cannot be less than 0 for item: $item_name');</script>";
                exit;
            }

            $sql = "INSERT INTO item_donations (donor_id, charity_id, item_name, category, quantity, expiration_date, date) 
                    VALUES ('$donor_id', '$charity_id', '$item_name', '$category', '$quantity', " .
                   ($expiration_date ? "'$expiration_date'" : "NULL") . ", '$date')";
            mysqli_query($con, $sql) or die(mysqli_error($con));

            $donate_id = mysqli_insert_id($con);

            $status_sql = "INSERT INTO donation_status (donor_id, status, donate_id, charity_id, date) 
                           VALUES ('$donor_id', 'pending', '$donate_id', '$charity_id', '$date')";
            mysqli_query($con, $status_sql) or die(mysqli_error($con));
        }

        echo "<script>alert('Thank you! You can now go to the nearest office in your area to complete your donation. God Bless You!');</script>";
        include "donation.php";
    }
}

    
    


try
{
    if(isset($_REQUEST['delete-student'])){
        $id = $_REQUEST['item'];
        
        $sql ="DELETE FROM donors WHERE donor_id=$id";
        $res = mysqli_query($con, $sql);
        if($res){
            echo "<script>alert('Delted Successfully');</script>"; 
        header("location: donors.php");
        }
        
    }
}
catch(mysqli_sql_exception $e)
{
    include "donors.php";
    echo "<script>alert('Cannot delete, donors already donated and in the Donation History  ');</script>"; 
}
?>

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
