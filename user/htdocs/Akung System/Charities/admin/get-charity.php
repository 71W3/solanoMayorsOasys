<?php
include 'connect.php';

$id = $_GET['id'];

$sql = "SELECT * FROM charities WHERE charity_id = $id";
$res = mysqli_query($con, $sql);
$data = mysqli_fetch_assoc($res);

header('Content-Type: application/json');
echo json_encode($data);
?>
