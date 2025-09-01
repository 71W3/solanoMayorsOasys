<?php
include "connect.php";

if (isset($_POST['register'])) 
{
    $name = $_POST['name'];
    $address = $_POST['address'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];

    $sql = "INSERT INTO donors (name, email, phone, address) 
            VALUES ('$name', '$email', '$phone', '$address')";
    $res = mysqli_query($con, $sql) or die(mysqli_error($con));
    if ($res) {
        header("location: donors.php");
        echo "<script>alert('Regitered Successfully');</script>";
    }
}

if (isset($_POST['deleteCharity'])) 
{
    $id = $_POST['charity_id'];

    $sql = "DELETE FROM donation_status WHERE id = $statusId";
    $res = mysqli_query($con, $sql) or die(mysqli_error($con));
    if ($res) {
        header("location: charityList.php");
        echo "<script>alert('Deleted Successfully');</script>";
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") 
{
    if (isset($_POST['submitCash'])) {
        // Handle cash donation
        $donor_id = $_POST['donors'];
        $charity_id = $_POST['charities'];
        $amount = $_POST['amount'];
        $date = $_POST['date'];

        // Insert cash donation into the database
        $sql = "INSERT INTO cash_donations (donor_id, charity_id, amount, date) VALUES ('$donor_id', '$charity_id', '$amount', '$date')";
        mysqli_query($con, $sql) or die(mysqli_error($con));

        // Get the last inserted donation ID
        $donation_id = mysqli_insert_id($con);

        // Insert into donation status
        $status_sql = "INSERT INTO donation_status (donor_id, donation_id, status, charity_id, amount, date) VALUES ('$donor_id', '$donation_id', 'pending', '$charity_id', '$amount', '$date')";
        mysqli_query($con, $status_sql) or die(mysqli_error($con));

        echo "<script>alert('Cash Donated Successfully');</script>";
        include "donation.php";
        
    } elseif (isset($_POST['submitItems'])) {
        // Handle item donation
        $donor_id = $_POST['donors'];
        $charity_id = $_POST['charities'];
        $item_name = $_POST['item_name'];
        $category = $_POST['category'];
        $quantity = $_POST['quantity'];
        $expiration_date = $_POST['expiration_date'];
        $date = $_POST['date'];

        // Insert item donation into the database
        $sql = "INSERT INTO item_donations (donor_id, charity_id, item_name, category, quantity, expiration_date, date) VALUES ('$donor_id', '$charity_id', '$item_name', '$category', '$quantity', '$expiration_date', '$date')";
        mysqli_query($con, $sql) or die(mysqli_error($con));

        // Get the last inserted donation ID
        $donate_id = mysqli_insert_id($con);

        // Insert into donation status
        $status_sql = "INSERT INTO donation_status (donor_id, status, donate_id, charity_id, date) VALUES ('$donor_id', 'pending', '$donate_id', '$charity_id', '$date')";
        mysqli_query($con, $status_sql) or die(mysqli_error($con));

        echo "<script>alert('Items Donated Successfully');</script>";
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
            include "donors.php";
        }
        
    }
}
catch(mysqli_sql_exception $e)
{
    include "donors.php";
    echo "<script>alert('Cannot delete, donors already donated and in the Donation History  ');</script>"; 
}


if (isset($_POST['update-charity'])) {
    $id = $_POST['charity_id'];
    $name = $_POST['charity_name'];
    $description = $_POST['description'];
    $contact = $_POST['contact_information'];
    $address = $_POST['address'];
    $website = $_POST['website'];
    $status = $_POST['status'];

    $sql = "UPDATE charities SET charity_name='$name', description='$description', 
            contact_information='$contact', address='$address', website='$website', 
            status='$status' WHERE charity_id='$id'";
    mysqli_query($con, $sql);
    header('Location: charity.php');
}



if (isset($_POST['update-donors'])) {
    $id = mysqli_real_escape_string($con, $_POST['donor_id']);
    $name = mysqli_real_escape_string($con, $_POST['name']);
    $email = mysqli_real_escape_string($con, $_POST['email']);
    $phone = mysqli_real_escape_string($con, $_POST['phone']);
    $address = mysqli_real_escape_string($con, $_POST['address']);
    $user = mysqli_real_escape_string($con, $_POST['username']);
    $pass = mysqli_real_escape_string($con, $_POST['password']);

    $sql = "UPDATE donors SET name='$name', email='$email', phone='$phone',
            address='$address', username='$user', password='$pass' WHERE donor_id='$id'";
    if (mysqli_query($con, $sql)) {
        header('Location: donors.php');
        exit;
    } else {
        echo "Error updating record: " . mysqli_error($con);
    }
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