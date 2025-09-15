<?php
// index.php - Single-file working example (uses PDO to avoid "Class 'mysqli' not found" fatal error)

// ===== Configuration =====
$host   = 'localhost';
$dbname = 'blogdb';
$user   = 'root';
$pass   = '';       // set your MySQL password if any
$charset= 'utf8mb4';

// ===== Connect with PDO (more portable than mysqli) =====
$dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    // Friendly error output (no fatal crash)
    echo "<h2>Database connection error</h2>";
    echo "<p>Unable to connect to the database. Please check your DB credentials and that MySQL is running.</p>";
    // For debugging you may uncomment the next line (remove on production)
    // echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    exit;
}

// ===== Pagination & Search setup =====
$limit = 5; // posts per page
$page  = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);
if ($page === false || $page === null || $page < 1) { $page = 1; }
$search = trim((string)($_GET['search'] ?? ''));

// Build WHERE and params safely
$where = '';
$params = [];
if ($search !== '') {
    $where = "WHERE title LIKE :search OR content LIKE :search";
    $params[':search'] = "%{$search}%";
}

// ===== Count total matching posts =====
$countSql = "SELECT COUNT(*) FROM posts $where";
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();
$pages = $total > 0 ? (int)ceil($total / $limit) : 1;

// Ensure page is within bounds
if ($page > $pages) { $page = $pages; }
$start = ($page - 1) * $limit;

// ===== Fetch posts for current page =====
$listSql = "SELECT id, title, content, created_at FROM posts $where ORDER BY created_at DESC LIMIT :start, :limit";
$stmt = $pdo->prepare($listSql);
// bind search param(s) first
if (isset($params[':search'])) {
    $stmt->bindValue(':search', $params[':search'], PDO::PARAM_STR);
}
$stmt->bindValue(':start', $start, PDO::PARAM_INT);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->execute();
$posts = $stmt->fetchAll();

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Blog — Search & Pagination</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background:#f8f9fa; }
    .post-preview { white-space:pre-wrap; }
  </style>
</head>
<body>
<div class="container py-4">
  <h1 class="text-center mb-4">Blog Posts</h1>

  <!-- Search Form -->
  <form class="row g-2 mb-4" method="get" action="">
    <div class="col-md-10">
      <input type="text" name="search" class="form-control" placeholder="Search by title or content" value="<?php echo htmlspecialchars($search); ?>">
    </div>
    <div class="col-md-2 d-grid">
      <button class="btn btn-primary" type="submit">Search</button>
    </div>
  </form>

  <!-- Posts -->
  <?php if (count($posts) === 0): ?>
    <div class="alert alert-warning">No posts found.</div>
  <?php else: ?>
    <div class="list-group mb-4">
      <?php foreach ($posts as $post): ?>
        <div class="list-group-item shadow-sm mb-2 rounded">
          <div class="d-flex justify-content-between align-items-start">
            <h5 class="mb-1"><?php echo htmlspecialchars($post['title']); ?></h5>
            <small class="text-muted"><?php echo htmlspecialchars($post['created_at']); ?></small>
          </div>
          <p class="mb-0 post-preview"><?php
              $preview = mb_substr($post['content'], 0, 200);
              if (mb_strlen($post['content']) > 200) $preview .= '...';
              echo nl2br(htmlspecialchars($preview));
          ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <!-- Pagination -->
  <?php if ($total > 0 && $pages > 1): ?>
    <nav aria-label="Posts pagination">
      <ul class="pagination justify-content-center">
        <?php
          // Build base query string (retain search)
          $baseQuery = [];
          if ($search !== '') $baseQuery['search'] = $search;

          // Previous button
          $prevPage = $page - 1;
          $prevClass = $prevPage < 1 ? 'disabled' : '';
          $q = $baseQuery; $q['page'] = max(1, $prevPage);
          $prevHref = '?' . http_build_query($q);
        ?>
        <li class="page-item <?php echo $prevClass; ?>">
          <a class="page-link" href="<?php echo $prevHref; ?>" aria-label="Previous">Previous</a>
        </li>

        <?php
        // Show a limited range of page links for readability
        $startPage = max(1, $page - 3);
        $endPage = min($pages, $page + 3);
        if ($startPage > 1) {
            $q = $baseQuery; $q['page'] = 1;
            echo '<li class="page-item"><a class="page-link" href="?'.http_build_query($q).'">1</a></li>';
            if ($startPage > 2) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
        }
        for ($i = $startPage; $i <= $endPage; $i++):
            $q = $baseQuery; $q['page'] = $i;
            $active = $i === $page ? 'active' : '';
        ?>
          <li class="page-item <?php echo $active; ?>"><a class="page-link" href="?<?php echo http_build_query($q); ?>"><?php echo $i; ?></a></li>
        <?php endfor;
        if ($endPage < $pages) {
            if ($endPage < $pages - 1) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
            $q = $baseQuery; $q['page'] = $pages;
            echo '<li class="page-item"><a class="page-link" href="?'.http_build_query($q).'">'. $pages .'</a></li>';
        }
        // Next button
        $nextPage = $page + 1;
        $nextClass = $nextPage > $pages ? 'disabled' : '';
        $q = $baseQuery; $q['page'] = min($pages, $nextPage);
        $nextHref = '?' . http_build_query($q);
        ?>
        <li class="page-item <?php echo $nextClass; ?>">
          <a class="page-link" href="<?php echo $nextHref; ?>" aria-label="Next">Next</a>
        </li>
      </ul>
    </nav>
  <?php endif; ?>

  <div class="text-center text-muted small mt-3">
    Showing page <?php echo $page; ?> of <?php echo $pages; ?> (<?php echo $total; ?> total posts)
  </div>
</div>
</body>
</html>
