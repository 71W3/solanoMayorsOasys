<style>
      /* General Styles */
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

        /* Form and Table Styles */
        .container {
            padding: 20px;
            max-width: 800px;
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
            text-align: center;
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
</style>

<div class="navbar">
    <a href="front.php">Home</a>
    <div class="dropdown">
        <button class="dropbtn">Donation 
            <i class="arrow down"></i>
        </button>
        <div class="dropdown-content">
            <a href="donors.php">Registration</a>
            <a href="donors.php">Donation Area</a>
            <a href="list.php">Donors</a>
        </div>
    </div> 
    <a href="charityList.php">Charities</a>
    <a href="history.php">Donation History</a>
</div>

<h3></h3>
<div class="container">
    <div class="search-container">
        <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="get">
            <input type="text" name="search-studText" value="<?php echo isset($_REQUEST['searchStud']) ? $_REQUEST['search-studText'] : ""; ?>" placeholder="Search by Name">
            <input type="submit" name="searchStud" value="Search">
        </form>
    </div>
    <table>
        <tr>
            <th>No.</th>
            <th>Name</th>
            <th>Email</th>
            <th>Phone</th>
            <th>Address</th>
        </tr>
        <?php
        include "connect.php";
        $sql = "SELECT donor_id, name, email, phone, address FROM donors";
        if (isset($_GET['searchStud'])) {
            $search = $_GET['search-studText'];
            $sql = "SELECT donor_id, name, email, phone, address FROM donors WHERE name LIKE '%$search%'";
        }
        $res = mysqli_query($con, $sql) or die("error" . mysqli_error($con));
        $i = 1;
        while ($row = mysqli_fetch_object($res)) {
            echo "<tr>";
            echo "<td>" . $i++ . "</td>";
            echo "<td>" . $row->name . "</td>";
            echo "<td>" . $row->email . "</td>";
            echo "<td>" . $row->phone . "</td>";
            echo "<td>" . $row->address . "</td>";
            echo "</tr>";
        }
        ?>
    </table>
</div>