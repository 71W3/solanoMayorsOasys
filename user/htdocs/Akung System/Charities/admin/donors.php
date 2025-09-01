<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donor Management</title>
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
        <button class="dropbtn">Donation</button>
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

<div class="container">
    <h3>Donors</h3>
    <div class="search-container">
        <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="get">
            <input type="text" name="search-studText" value="<?php echo isset($_REQUEST['searchStud']) ? $_REQUEST['search-studText'] : ""; ?>" placeholder="Search by Name">
            <input type="submit" name="searchStud" value="Search">
        </form>
    <table>
        <tr>
            <th>No.</th>
            <th>Name</th>
            <th>Email</th>
            <th>Phone</th>
            <th>Address</th>
            <th>Username</th>
            <th>Password</th>
            <th colspan="2">Actions</th>
        </tr>
        <?php
        include "connect.php";
        if (isset($_GET['searchStud'])) 
        {
            $search = $_GET['search-studText'];
            $sql = "SELECT donor_id, name, email, phone, address, username, password FROM donors WHERE name LIKE '%$search%'";
        }
        $sql = "SELECT donor_id, name, email, phone, address, username, password FROM donors";
        $res = mysqli_query($con, $sql);
        $i = 1;

        while ($row = mysqli_fetch_assoc($res)) 
        {
            echo "<tr>";
            echo "<td>" . $i++ . "</td>";
            echo "<td>" . htmlspecialchars($row['name']) . "</td>";
            echo "<td>" . htmlspecialchars($row['email']) . "</td>";
            echo "<td>" . htmlspecialchars($row['phone']) . "</td>";
            echo "<td>" . htmlspecialchars($row['address']) . "</td>";
            echo "<td>" . htmlspecialchars($row['username']) . "</td>";
            echo "<td>" . htmlspecialchars($row['password']) . "</td>";
            echo "<td><button class='edit-btn' data-id='{$row['donor_id']}'>Edit</button></td>";
            echo "<td><form action='functions.php' method='post' onsubmit=\"return confirm('Are you sure you want to delete this donor?')\">
                    <input type='hidden' name='item' value='{$row['donor_id']}'>
                    <input type='submit' name='delete-student' value='Delete'>
                  </form></td>";
            echo "</tr>";
        }
        ?>
    </table>
</div>

<div id="editModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h3>Edit Donor</h3>
        <form action="functions.php" method="post">
        <table border="1" width="100%">
    <input type="hidden" name="donor_id" id="donor_id">

    <tr>
        <td><label for="name">Name:</label></td>
        <td><input type="text" name="name" id="name" required></td>
    </tr>

    <tr>
        <td><label for="email">Email:</label></td>
        <td><input type="text" name="email" id="email" required></td>
    </tr>

    <tr>
        <td><label for="phone">Phone Number:</label></td>
        <td><input type="text" name="phone" id="phone" required></td>
    </tr>

    <tr>
        <td><label for="address">Address:</label></td>
        <td><input type="text" name="address" id="address" required></td>
    </tr>

    <tr>
        <td><label for="phone">Username:</label></td>
        <td><input type="text" name="username" id="username" required></td>
    </tr>

    <tr>
        <td><label for="address">Password:</label></td>
        <td><input type="text" name="password" id="password" required></td>
    </tr>

    <tr>
        <td colspan="2" style="text-align: center;">
            <input type="submit" name="update-donors" value="Update">
        </td>
    </tr>
</table>

        </form>
    </div>
</div>

<script>
    const modal = document.getElementById('editModal');
    const closeModal = document.querySelector('.close');
    const editButtons = document.querySelectorAll('.edit-btn');

    editButtons.forEach(button => {
        button.addEventListener('click', () => {
            const id = button.getAttribute('data-id');
            fetch(`get-donors.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('donor_id').value = data.donor_id;
                    document.getElementById('name').value = data.name;
                    document.getElementById('email').value = data.email;
                    document.getElementById('phone').value = data.phone;
                    document.getElementById('address').value = data.address;
                    document.getElementById('username').value = data.username;
                    document.getElementById('password').value = data.password;
                    modal.style.display = 'block';
                });
        });
    });

    closeModal.addEventListener('click', () => modal.style.display = 'none');
    window.addEventListener('click', (e) => e.target == modal ? modal.style.display = 'none' : false);
</script>

</body>
</html>
