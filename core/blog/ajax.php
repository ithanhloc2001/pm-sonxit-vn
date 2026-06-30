<?php
require_once __DIR__ . '/../../config.php';

$categorySlug = isset($_GET['category']) ? trim((string)$_GET['category']) : '';
$searchRaw    = isset($_GET['q']) ? (string)$_GET['q'] : '';
$search       = trim($searchRaw);
$categoryInfo = null;

if ($categorySlug !== '') {
    $stmtCat = $ithanhloc->prepare('SELECT id, name, slug FROM ecommerce_blog_category WHERE slug = ? AND (is_active = 1 OR is_active IS NULL) LIMIT 1');
    if ($stmtCat) {
        $stmtCat->bind_param('s', $categorySlug);
        $stmtCat->execute();
        $resCat = $stmtCat->get_result();
        $categoryInfo = $resCat ? $resCat->fetch_assoc() : null;
        if ($resCat) {
            $resCat->close();
        }
        $stmtCat->close();
    }
}

$blogCategories = [];
$catListRes = $ithanhloc->query("SELECT id, name, slug FROM ecommerce_blog_category WHERE (is_active = 1 OR is_active IS NULL) ORDER BY name ASC");
if ($catListRes) {
    while ($catRow = $catListRes->fetch_assoc()) {
        $blogCategories[] = $catRow;
    }
    $catListRes->close();
}

// Lấy danh sách bài viết với filter theo chuyên mục và/hoặc từ khóa tìm kiếm
$res         = null;
$hasCategory = (bool)$categoryInfo;
$hasSearch   = ($search !== '');

if ($hasCategory || $hasSearch) {
    $sql = 'SELECT b.id, b.title, b.slug, b.excerpt, b.thumbnail_url, b.author_name, b.published_at,
                   c.id AS category_id, c.name AS category_name, c.slug AS category_slug
            FROM ecommerce_blog b
            LEFT JOIN ecommerce_blog_category c ON c.id = b.category_id
            WHERE b.is_active = 1';

    $types  = '';
    $params = [];

    if ($hasCategory) {
        $sql .= ' AND b.category_id = ?';
        $types .= 'i';
        $params[] = (int)($categoryInfo['id'] ?? 0);
    }

    if ($hasSearch) {
        // Tìm theo tiêu đề, đoạn trích và tags
        $sql .= ' AND (b.title LIKE ? OR b.excerpt LIKE ? OR b.tags LIKE ?)';
        $types .= 'sss';
        $like = '%' . $search . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    if ($hasCategory) {
        $sql .= ' ORDER BY b.published_at DESC, b.id DESC';
    } else {
        $sql .= ' ORDER BY c.name ASC, b.published_at DESC, b.id DESC';
    }

    $stmt = $ithanhloc->prepare($sql);
    if ($stmt) {
        if ($types !== '' && !empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        $stmt->close();
    }
} else {
    $sql = 'SELECT b.id, b.title, b.slug, b.excerpt, b.thumbnail_url, b.author_name, b.published_at,
                   c.id AS category_id, c.name AS category_name, c.slug AS category_slug
            FROM ecommerce_blog b
            LEFT JOIN ecommerce_blog_category c ON c.id = b.category_id
            WHERE b.is_active = 1
            ORDER BY c.name ASC, b.published_at DESC, b.id DESC';
    $res = $ithanhloc->query($sql);
}

$groups = [];
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $catId   = (int)($row['category_id'] ?? 0);
        $catName = trim((string)($row['category_name'] ?? ''));
        $catSlug = trim((string)($row['category_slug'] ?? ''));
        if (!empty($row['published_at'])) {
            $row['published_at_fmt'] = date('d/m/Y', strtotime((string)$row['published_at']));
        } else {
            $row['published_at_fmt'] = '';
        }
        if ($catId <= 0 || $catName === '') {
            $catId   = 0;
            $catName = 'Khác';
            $catSlug = '';
        }
        if (!isset($groups[$catId])) {
            $groups[$catId] = [
                'name'  => $catName,
                'slug'  => $catSlug,
                'posts' => [],
            ];
        }
        $groups[$catId]['posts'][] = $row;
    }
    $res->close();
}

RespondJSON([
    'ok'           => true,
    'categorySlug' => $categorySlug,
    'category'     => $categoryInfo,
    'categories'   => $blogCategories,
    'search'       => $search,
    'groups'       => $groups,
]);
