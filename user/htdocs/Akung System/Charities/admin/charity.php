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
    
<div class="container">
    <h3>Charity List</h3>
    <div class="search-container">
        <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="get">
            <input type="text" name="search-studText" value="<?php echo isset($_REQUEST['searchStud']) ? $_REQUEST['search-studText'] : ""; ?>" placeholder="Search by Name">
            <input type="submit" name="searchStud" value="Search">
        </form>
    </div>
    <table border="1">
        <tr>
            <th>No.</th>
            <th>Name</th>
            <th>Description</th>
            <th>Contact</th>
            <th>Address</th>
            <th>Website</th>
            <th>Status</th>
            <th>Action</th>
        </tr>

        <?php
        include "connect.php";
        $sql = "SELECT * FROM charities";
        if (isset($_GET['searchStud'])) 
        {
            $search = $_GET['search-studText'];
            $sql = "SELECT charity_id, charity_name, description, contact_information, address, website, status FROM charities WHERE charity_name LIKE '%$search%'";
        }
        $res = mysqli_query($con, $sql);
        $i = 1;
        while ($row = mysqli_fetch_object($res)) 
        {
            echo "<tr>";
            echo "<td>" . $i++ . "</td>";
            echo "<td>" . $row->charity_name . "</td>";
            echo "<td>" . $row->description . "</td>";
            echo "<td>" . $row->contact_information . "</td>";
            echo "<td>" . $row->address . "</td>";
            echo "<td>" . $row->website . "</td>";
            echo "<td>" . $row->status . "</td>";
            echo "<td><button class='edit-btn' data-id='$row->charity_id'>Edit</button></td>";
            echo "</tr>";
        }
        ?>
    </table>
</div>


<div id="editModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h3>Edit Charity</h3>
        <form action="functions.php" method="post">
        <table border="1" width="100%">
    <input type="hidden" name="charity_id" id="charity_id">

    <tr>
        <td><label for="charity_name">Name:</label></td>
        <td><input type="text" name="charity_name" id="charity_name" required></td>
    </tr>

    <tr>
        <td><label for="description">Description:</label></td>
        <td><input type="text" name="description" id="description" required></td>
    </tr>

    <tr>
        <td><label for="contact_information">Contact Information:</label></td>
        <td><input type="text" name="contact_information" id="contact_information" required></td>
    </tr>

    <tr>
        <td><label for="address">Address:</label></td>
        <td><input type="text" name="address" id="address" required></td>
    </tr>

    <tr>
        <td><label for="website">Website:</label></td>
        <td><input type="text" name="website" id="website" required></td>
    </tr>

    <tr>
        <td><label>Status:</label></td>
        <td>
            <input type="radio" name="status" value="Active" id="status_active"> Active
            <input type="radio" name="status" value="Inactive" id="status_inactive"> Inactive
        </td>
    </tr>

    <tr>
        <td colspan="2" style="text-align: center;">
            <input type="submit" name="update-charity" value="Update" class="submitBtn">
        </td>
    </tr>
</table>

        </form>
    </div>
</div>

<script>
    const modal = document.getElementById('editModal');
    const closeModal = document.getElementsByClassName('close')[0];

    const editButtons = document.querySelectorAll('.edit-btn');

    editButtons.forEach(button => 
    {
        button.addEventListener('click', function() 
        {
            const id = this.getAttribute('data-id');
            
            fetch(`get-charity.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('charity_id').value = data.charity_id;
                    document.getElementById('charity_name').value = data.charity_name;
                    document.getElementById('description').value = data.description;
                    document.getElementById('contact_information').value = data.contact_information;
                    document.getElementById('address').value = data.address;
                    document.getElementById('website').value = data.website;
                    document.getElementById('status_' + data.status.toLowerCase()).checked = true;
                });

            modal.style.display = 'block';
        });
    });

    closeModal.onclick = function() 
    {
        modal.style.display = 'none';
    }

    window.onclick = function(event) 
    {
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    }
</script>

</body>
</html>