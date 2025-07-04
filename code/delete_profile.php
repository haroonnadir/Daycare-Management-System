<?php
include 'db_connect.php';
session_start();

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    // Optionally, you can first delete the old profile image from uploads/
    $query = "SELECT profile_image FROM users WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($row = mysqli_fetch_assoc($result)) {
        if ($row['profile_image'] !== 'default.png') {
            unlink('uploads/' . $row['profile_image']);
        }
    }
    mysqli_stmt_close($stmt);

    // Now delete user
    $delete = "DELETE FROM users WHERE id = ?";
    $stmt = mysqli_prepare($conn, $delete);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);

    mysqli_stmt_close($stmt);
}

mysqli_close($conn);
header("Location: manage_profiles.php");
exit();
?>
