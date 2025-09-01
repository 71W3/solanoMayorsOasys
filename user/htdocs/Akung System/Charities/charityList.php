<link rel="stylesheet" href="Style/design.css">
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

<?php
include "connect.php"; 
$sql = "SELECT charity_id, charity_name, description, contact_information, address, website, status FROM charities";
$res = mysqli_query($con, $sql) or die("Error: " . mysqli_error($con));

$images = [
    "images/gawad.jpg",
    "images/red.jpg",
    "images/caritas.jpg",
    "images/unicef.png",
    "images/habitat.jpg",
    "images/children.jpg",
    "images/habitat.jpg",
    "images/world.jpg",
    "images/paws.png",
    "images/hour.jpg"
];

?>

<div class="card-container">
    <div class="card-grid">
        <?php
        $index = 0;
        while ($row = mysqli_fetch_object($res)) 
        {
            $image = $images[$index % count($images)];

            echo '<div class="card">';
            echo '<img src="' . $image . '" alt="' . htmlspecialchars($row->charity_name) . '">';
            echo '<div class="card-content">';
            echo '<h4>' . htmlspecialchars($row->charity_name) . '</h4>';
            echo '<p><strong>Type:</strong> ' . htmlspecialchars($row->description) . '</p>';
            echo '<p><strong>Location:</strong> ' . htmlspecialchars($row->address) . '</p>';
            echo '<p><strong>Status:</strong> ' . htmlspecialchars($row->status) . '</p>';
            echo '<p>' . htmlspecialchars($row->contact_information) . '</p>';
            echo '</div>';
            echo '<div class="card-actions">';
            
            if (!empty($row->website)) 
            {
                echo '<a href="' . htmlspecialchars($row->website) . '" target="_blank">Visit Website</a>';
            } else 
            {
                echo '<span>No Website Available</span>';
            }

            echo '</div>';
            echo '</div>';
            $index++;
        }
        ?>
    </div>
</div>
