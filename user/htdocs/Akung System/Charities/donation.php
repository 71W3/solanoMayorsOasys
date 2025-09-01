<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "connect.php"; ?>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donation System</title>
    <style>
        .butun
        {
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
        .butun:hover
        {
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
    <link rel="stylesheet" href="Style/design.css">
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
            <a href="donors.php">Registration</a>
        </div>
    </div> 
    <a href="charityList.php">Charities</a>
    <a href="history.php">Donation History</a>
    <form action="loginFunctions.php" method="post">
        <button class="butun" name="logout">Log out</button>
    </form>
</div>
</div>

    <h3>Select Donation Type</h3>
    <div class="container">
        <label>
            <input type="radio" name="donation_type" value="cash" onclick="toggleForm('cash')"> Cash Donation
        </label>
        <label>
            <input type="radio" name="donation_type" value="item" onclick="toggleForm('item')"> Item Donation
        </label>
    </div>

    <div id="cash-form" class="container form-section">
        <h3>Cash Donation</h3>
        <form action="functions.php" method="post" id="donation-form">
            <table>
                <tr>
                    <td>Name:</td>
                    <td>
                        <select name="donors">
                        <option value="" disabled selected>Select your name</option>
                            <?php 
                              $sql ="SELECT donor_id, name from donors order by name asc";
                              $res = mysqli_query($con, $sql) or die( mysqli_error($con));
                              while($row=mysqli_fetch_object($res)) {
                                echo "<option value='$row->donor_id'>$row->name</option>";
                              }
                            ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td>Charity:</td>
                    <td>
                        <select name="charities">
                        <option value="" disabled selected>Select a Charity</option>
                            <?php 
                              $sql = "SELECT charity_id, charity_name FROM charities ORDER BY charity_name ASC";
                              $res = mysqli_query($con, $sql) or die(mysqli_error($con));
                              while($row = mysqli_fetch_object($res)) {
                                echo "<option value='$row->charity_id'>$row->charity_name</option>";
                              }
                            ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td>Amount:</td>
                    <td><input type="text" name="amount" required></td>
                </tr>
                <tr>
                    <td>Date:</td>
                    <td><input type="date" name="date" required></td>
                </tr>
                <tr>
                    <td></td>
                    <td><input type="submit" name="submitCash" value="Submit"></td>
                </tr>
            </table>
        </form>
    </div>

    <div id="item-form" class="container form-section">
    <h3>Item Donation</h3>
    <form action="functions.php" method="post" id="donation-form">
        <table>
            <tr>
                <td>Name:</td>
                <td>
                    <select name="donors" required>
                        <option value="" disabled selected>Select your name</option>
                        <?php 
                          $sql ="SELECT donor_id, name from donors order by name asc";
                          $res = mysqli_query($con, $sql) or die(mysqli_error($con));
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
                    <select name="charities" required>
                        <option value="" disabled selected>Select a Charity</option>
                        <?php 
                          $sql = "SELECT charity_id, charity_name FROM charities ORDER BY charity_name ASC";
                          $res = mysqli_query($con, $sql) or die(mysqli_error($con));
                          while($row = mysqli_fetch_object($res)) {
                              echo "<option value='$row->charity_id'>$row->charity_name</option>";
                          }
                        ?>
                    </select>
                </td>
            </tr>
            <tr>
                <td>Date:</td>
                <td><input type="date" name="date" required></td>
            </tr>
        </table>

        <table id="item-table">
            <thead>
                <tr>
                    <th>Item Name</th>
                    <th>Category</th>
                    <th>Quantity</th>
                    <th>Expiration Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><input type="text" name="item_name[]" required></td>
                    <td>
                        <select name="category[]" onchange="handleCategoryChange(this)" required>
                            <option value="" disabled selected>Select a category</option>
                            <option value="Food">Food</option>
                            <option value="Clothing">Clothing</option>
                            <option value="Others">Others</option>
                        </select>
                    </td>
                    <td><input type="text" name="quantity[]" required></td>
                    <td><input type="date" name="expiration_date[]" class="expiration-date"></td>
                    <td><button type="button" onclick="removeRow(this)">Remove</button></td>
                </tr>
            </tbody>
        </table>
        <button type="button" onclick="addItemRow()">Add Another Item</button>
        <br><br>
        <input type="submit" name="submitItems" value="Submit">
    </form>
</div>

    <script>
        function addItemRow() 
        {
            const table = document.getElementById("item-table").getElementsByTagName("tbody")[0];
            const newRow = table.insertRow();
            newRow.innerHTML = `
                <td><input type="text" name="item_name[]" required></td>
                <td>
                    <select name="category[]" onchange="handleCategoryChange(this)" required>
                        <option value="" disabled selected>Select a category</option>
                        <option value="Food">Food</option>
                        <option value="Clothing">Clothing</option>
                        <option value="Others">Others</option>
                    </select>
                </td>
                <td><input type="text" name="quantity[]" required></td>
                <td><input type="date" name="expiration_date[]" class="expiration-date"></td>
                <td><button type="button" onclick="removeRow(this)">Remove</button></td>
            `;
        }

        function removeRow(button) 
        {
            const row = button.parentElement.parentElement;
            row.parentElement.removeChild(row);
        }

        function handleCategoryChange(select) 
        {
            const expirationDateInput = select.parentElement.nextElementSibling.nextElementSibling.querySelector(".expiration-date");

            if (select.value === "Clothing" || select.value === "Others") {
                expirationDateInput.disabled = true;
                expirationDateInput.value = ""; // Clear the value
            } else {
                expirationDateInput.disabled = false;
            }
        }

        function toggleForm(formType) 
        {
            console.log(formType);
            document.getElementById('cash-form').style.display = 'none';
            document.getElementById('item-form').style.display = 'none';

            if (formType === 'cash') 
            {
                document.getElementById('cash-form').style.display = 'block';
            } 
            else if (formType === 'item')
            {
                document.getElementById('item-form').style.display = 'block';
            }
        }
    </script>
</body>
</html>
