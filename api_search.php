<?php
// api_search.php
require_once 'config/database.php';

header('Content-Type: application/json');

$keyword = isset($_GET['q']) ? mysqli_real_escape_string($conn, $_GET['q']) : '';

if (strlen($keyword) < 2) {
    echo json_encode([]); // Jangan cari kalau cuma 1 huruf
    exit;
}

// UPDATE: Menambahkan kolom 'genres' ke dalam SELECT
// Limit 6 hasil saja agar tidak kepanjangan
$query = "SELECT title, slug, cover_image, type, status, genres 
          FROM comics 
          WHERE title LIKE '%$keyword%' OR alternative_titles LIKE '%$keyword%' 
          LIMIT 6";

$result = mysqli_query($conn, $query);
$data = [];

while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode($data);
?>