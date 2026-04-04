<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$query = "SELECT * FROM comics WHERE id = $id";
$result = mysqli_query($conn, $query);
$comic = mysqli_fetch_assoc($result);

if ($comic) {
    if ($comic['cover_image'] != 'default.jpg' && file_exists('../uploads/covers/' . $comic['cover_image'])) {
        unlink('../uploads/covers/' . $comic['cover_image']);
    }

    $comic_dir = "../uploads/comics/" . $comic['slug'];

    function deleteDirectory($dir) {
        if (!file_exists($dir)) return true;
        if (!is_dir($dir)) return unlink($dir);
        foreach (scandir($dir) as $item) {
            if ($item == '.' || $item == '..') continue;
            if (!deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) return false;
        }
        return rmdir($dir);
    }

    deleteDirectory($comic_dir);

    mysqli_query($conn, "DELETE FROM comics WHERE id = $id");
}

header("Location: index.php");
exit();
?>