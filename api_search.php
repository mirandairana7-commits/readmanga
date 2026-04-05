<?php
require_once 'config/database.php';
header('Content-Type: application/json');

$q = isset($_GET['q']) ? trim($_GET['q']) : '';

if (empty($q)) {
    echo json_encode([]);
    exit;
}

$search_term = "%" . $q . "%";

// [KEAMANAN DITINGKATKAN]: Prepared Statement untuk API
$stmt = mysqli_prepare($conn, "SELECT title, slug, cover_image FROM comics WHERE title LIKE ? OR alternative_titles LIKE ? LIMIT 5");
mysqli_stmt_bind_param($stmt, "ss", $search_term, $search_term);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$data = [];
while ($row = mysqli_fetch_assoc($result)) {
    // Pastikan link cover benar (lokal vs ImgBB)
    $cover = $row['cover_image'];
    if (strpos($cover, 'http') !== 0) {
        $cover = $base_url . '/uploads/covers/' . $cover;
    }
    
    $data[] = [
        'title' => $row['title'],
        'slug' => $row['slug'],
        'cover' => $cover
    ];
}

echo json_encode($data);
?>