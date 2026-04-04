<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$chapter_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$comic_slug = '';

$query = "SELECT c.*, cm.slug as comic_slug 
          FROM chapters c 
          JOIN comics cm ON c.comic_id = cm.id 
          WHERE c.id = $chapter_id";
$result = mysqli_query($conn, $query);
$chapter = mysqli_fetch_assoc($result);

if ($chapter) {
    $comic_slug = $chapter['comic_slug'];
    $chapter_num = $chapter['chapter_number'];

    $target_dir = "../uploads/comics/" . $comic_slug . "/chapter_" . $chapter_num . "/";

    function deleteDirectory($dir) {
        if (!file_exists($dir)) {
            return true;
        }

        if (!is_dir($dir)) {
            return unlink($dir);
        }

        foreach (scandir($dir) as $item) {
            if ($item == '.' || $item == '..') {
                continue;
            }

            if (!deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) {
                return false;
            }
        }

        return rmdir($dir);
    }

    deleteDirectory($target_dir);

    $delete_query = "DELETE FROM chapters WHERE id = $chapter_id";
    mysqli_query($conn, $delete_query);

    header("Location: ../comic.php?slug=" . $comic_slug);
    exit();

} else {
    echo "Chapter tidak ditemukan.";
}
?>