<?php
include "connect.php";

// Handle search logic
$searchName = '';
if (isset($_GET['searchStud'])) {
    $searchName = mysqli_real_escape_string($con, $_GET['search-studText']);
}

// Construct the query with a filter if the search is applied
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
<style>
     body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }

        /* Navigation Menu */
        .navbar {
            background-color: #333;
            overflow: hidden;
            font-size: 16px;
        }
        .navbar a {
            float: left;
            display: block;
            color: white;
            text-align: center;
            padding: 14px 20px;
            text-decoration: none;
        }
        .navbar a:hover, .dropdown:hover .dropbtn {
            background-color: #555;
        }
        .dropdown {
            float: left;
            overflow: hidden;
        }
        .dropdown .dropbtn {
            font-size: 16px;  
            border: none;
            outline: none;
            color: white;
            padding: 14px 20px;
            background-color: inherit;
            font-family: inherit;
            margin: 0;
        }
        .dropdown-content {
            display: none;
            position: absolute;
            background-color: #555;
            min-width: 160px;
            box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
            z-index: 1;
        }
        .dropdown-content a {
            float: none;
            color: white;
            padding: 12px 16px;
            text-decoration: none;
            display: block;
            text-align: left;
        }
        .dropdown-content a:hover {
            background-color: #777;
        }
        .dropdown:hover .dropdown-content {
            display: block;
        }

        .container {
            padding: 20px;
            max-width: 1100px;
            margin: auto;
            background-color: white;
            box-shadow: 0px 4px 8px rgba(0,0,0,0.1);
            margin-top: 20px;
            border-radius: 8px;
        }

        h3 {
            text-align: center;
            color: #333;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        table, th, td {
            border: 1px solid #ddd;
        }
        th, td {
            padding: 12px;
            text-align: left;
        }
        th {
            background-color: #333;
            color: white;
        }
        tr:nth-child(even) {
            background-color: #f2f2f2;
        }

        .search-container {
            text-align: left;
            margin-bottom: 20px;
        }
        .search-container input[type="text"] {
            padding: 8px;
            width: 200px;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        .search-container input[type="submit"] {
            padding: 8px 12px;
            border-radius: 4px;
            border: none;
            background-color: #333;
            color: white;
            cursor: pointer;
        }
        .search-container input[type="submit"]:hover {
            background-color: #555;
        }
        .edit-btn {
            padding: 8px 12px;
            border-radius: 4px;
            border: none;
            background-color: #555;
            color: white;
            cursor: pointer;
        }
        .edit-btn {
            background-color: #333;
        }
        .form-table input[type="text"],
        .form-table input[type="submit"] {
            width: 100%;
            padding: 8px;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        .form-table input[type="submit"] {
            background-color: #333;
            color: white;
            cursor: pointer;
        }
        .form-table input[type="submit"]:hover {
            background-color: #555;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
        }

        .modal-content {
            background-color: #fefefe;
            margin: 15% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 50%;
            border-radius: 8px;
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
        }

        .close:hover,
        .close:focus {
            color: black;
            text-decoration: none;
            cursor: pointer;
        }

     
</style>
<div class="navbar">
    <a href="../front.php">Home</a>
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
</div>
    
<div class="container">
    <h3>History</h3>
    <div class="search-container">
        <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="get">
            <input type="text" name="search-studText" value="<?php echo isset($_REQUEST['searchStud']) ? $_REQUEST['search-studText'] : ""; ?>" placeholder="Search by Name">
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
            if (mysqli_num_rows($result) > 0) {
                while ($row = mysqli_fetch_assoc($result)) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($row['donor_name']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['charity_name']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['donation_type']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['donation_details']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['donation_date']) . "</td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='5'>No donations found for this donor.</td></tr>";
            }
            ?>
        </tbody>
    </table>
</div>


<div id="editModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h3>Edit Charity</h3>
        <!-- In your modal, ensure the correct fields are used for donation history -->
<form action="functions.php" method="post">
    <input type="hidden" name="donation_id" id="donation_id"> <!-- Changed from charity_id -->

    <table border="1" width="100%">
        <tr>
            <td>Donor:</td>
            <td>
                <select name="donor_id">
                    <option value="" disabled selected>Select Donor</option>
                    <?php 
                    $sql = "SELECT donor_id, name from donors ORDER BY name ASC";
                    $res = mysqli_query($con, $sql);
                    while($row = mysqli_fetch_object($res)) {
                        echo "<option value='$row->donor_id'>$row->name</option>";
                    }
                    ?>
                </select>
            </td>
        </tr>

        <tr>
            <td>Charity:</td>
            <td>
                <select name="charity_id">
                    <?php 
                    $sql = "SELECT charity_id, charity_name FROM charities ORDER BY charity_name ASC";
                    $res = mysqli_query($con, $sql);
                    while($row = mysqli_fetch_object($res)) {
                        echo "<option value='$row->charity_id'>$row->charity_name</option>";
                    }
                    ?>
                </select>
            </td>
        </tr>

        <tr>
            <td>Donation Type:</td>
            <td>
                <select name="donation_type">
                    <option value="Cash Donation">Cash Donation</option>
                    <option value="Item Donation">Item Donation</option>
                </select>
            </td>
        </tr>

        <tr>
            <td>Donation Details:</td>
            <td>
                <input type="text" name="donation_details" id="donation_details" required>
            </td>
        </tr>

        <tr>
            <td>Donation Date:</td>
            <td>
                <input type="date" name="donation_date" id="donation_date" required>
            </td>
        </tr>

        <tr>
            <td colspan="2" style="text-align: center;">
                <input type="submit" name="update-donation-history" value="Update" class="submitBtn">
            </td>
        </tr>
    </table>
</form>

    </div>
</div>

<script>
   const editButtons = document.querySelectorAll('.edit-btn');

editButtons.forEach(button => {
    button.addEventListener('click', function() {
        const id = this.getAttribute('data-id');
        
        // Fetch donation history data using the ID
        fetch(`get-history.php?id=${id}`)
            .then(response => response.json())
            .then(data => {
                // Populate form fields with the data
                document.getElementById('donation_id').value = data.donation_id;
                document.getElementById('donor_id').value = data.donor_id;
                document.getElementById('charity_id').value = data.charity_id;
                document.getElementById('donation_type').value = data.donation_type;
                document.getElementById('donation_details').value = data.donation_details;
                document.getElementById('donation_date').value = data.donation_date;
            });

        // Show the modal
        modal.style.display = 'block';
    });
});

</script>

</body>
</html>