<?php
include "connect.php";
if (!isset($_GET['id'])) {
    die("No appointment ID provided.");
}
$id = intval($_GET['id']);
$result = $con->query("SELECT attachments FROM appointments WHERE id = $id");
if (!$result || $result->num_rows === 0) {
    die("Attachment not found.");
}
$row = $result->fetch_assoc();
$attachment = $row['attachments'];
if (empty($attachment)) {
    die("No attachment available.");
}
// Try to detect file type (basic)
$finfo = new finfo(FILEINFO_MIME_TYPE);
$type = $finfo->buffer($attachment);
if (!$type) $type = 'application/octet-stream';
header("Content-Type: $type");
header("Content-Disposition: inline; filename=attachment_$id");
echo $attachment;
exit; 