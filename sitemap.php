<?php
header("Content-Type: application/xml; charset=utf-8");

// Tắt hoàn toàn việc hiển thị lỗi ra ngoài XML để tránh làm hỏng cấu trúc sitemap
ini_set('display_errors', 0);
error_reporting(0);

require_once __DIR__ . '/config.php';

// Xác định baseUrl động từ config hoặc tự động nhận diện
$baseUrlAbs = rtrim((string)($baseUrl ?? $_SiteUrl ?? ''), '/');
if ($baseUrlAbs === '') {
    $baseUrlAbs = 'https://sonxit.vn';
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
?>
<urlset
  xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
  xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
  xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9
    http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">

  <!-- ===================================================================== -->
  <!-- 1. Trang tĩnh chính và các trang Chính sách                           -->
  <!-- ===================================================================== -->
  <url>
    <loc><?= htmlspecialchars($baseUrlAbs) ?>/</loc>
    <changefreq>daily</changefreq>
    <priority>1.0</priority>
  </url>
  <url>
    <loc><?= htmlspecialchars($baseUrlAbs) ?>/shopping</loc>
    <changefreq>daily</changefreq>
    <priority>0.9</priority>
  </url>
  <url>
    <loc><?= htmlspecialchars($baseUrlAbs) ?>/blog</loc>
    <changefreq>daily</changefreq>
    <priority>0.9</priority>
  </url>
  <url>
    <loc><?= htmlspecialchars($baseUrlAbs) ?>/login</loc>
    <changefreq>weekly</changefreq>
    <priority>0.6</priority>
  </url>
  <url>
    <loc><?= htmlspecialchars($baseUrlAbs) ?>/payment.html</loc>
    <changefreq>monthly</changefreq>
    <priority>0.5</priority>
  </url>
  <url>
    <loc><?= htmlspecialchars($baseUrlAbs) ?>/privacy.html</loc>
    <changefreq>monthly</changefreq>
    <priority>0.5</priority>
  </url>
  <url>
    <loc><?= htmlspecialchars($baseUrlAbs) ?>/return.html</loc>
    <changefreq>monthly</changefreq>
    <priority>0.5</priority>
  </url>
  <url>
    <loc><?= htmlspecialchars($baseUrlAbs) ?>/shipping.html</loc>
    <changefreq>monthly</changefreq>
    <priority>0.5</priority>
  </url>
  <url>
    <loc><?= htmlspecialchars($baseUrlAbs) ?>/terms.html</loc>
    <changefreq>monthly</changefreq>
    <priority>0.5</priority>
  </url>

  <?php
  if (isset($ithanhloc) && $ithanhloc instanceof mysqli):
      // =====================================================================
      // 2. Danh mục sản phẩm hoạt động
      // =====================================================================
      $resCats = $ithanhloc->query("SELECT slug FROM ecommerce_category WHERE status = 1");
      if ($resCats):
          while ($row = $resCats->fetch_assoc()):
              $slug = trim((string)$row['slug']);
              if ($slug === '') continue;
              $loc = $baseUrlAbs . '/shopping/' . rawurlencode($slug);
              ?>
  <url>
    <loc><?= htmlspecialchars($loc) ?></loc>
    <changefreq>weekly</changefreq>
    <priority>0.8</priority>
  </url>
              <?php
          endwhile;
          $resCats->close();
      endif;

      // =====================================================================
      // 3. Sản phẩm hoạt động
      // =====================================================================
      $productTable = '';
      if (function_exists('first_existing_table')) {
          $productTable = first_existing_table($ithanhloc, array('ecommerce_product'));
      } else {
          $productTable = 'ecommerce_product';
      }

      if ($productTable !== ''):
          $resProds = $ithanhloc->query("SELECT id, product_name, created_at FROM `{$productTable}` WHERE (status = 'true' OR status = '1') AND shipping_methods IS NOT NULL AND shipping_methods != ''");
          if ($resProds):
              while ($row = $resProds->fetch_assoc()):
                  $id = (int)$row['id'];
                  $name = (string)$row['product_name'];
                  $createdAt = !empty($row['created_at']) ? date('Y-m-d', strtotime($row['created_at'])) : '';
                  if ($id <= 0) continue;
                  $loc = pm_product_url($id, $name, $baseUrlAbs);
                  ?>
  <url>
    <loc><?= htmlspecialchars($loc) ?></loc>
    <?php if ($createdAt !== ''): ?>
    <lastmod><?= $createdAt ?></lastmod>
    <?php endif; ?>
    <changefreq>daily</changefreq>
    <priority>0.8</priority>
  </url>
                  <?php
              endwhile;
              $resProds->close();
          endif;
      endif;

      // =====================================================================
      // 4. Danh mục blog hoạt động
      // =====================================================================
      $resBlogCats = $ithanhloc->query("SELECT slug FROM ecommerce_blog_category WHERE (is_active = 1 OR is_active IS NULL)");
      if ($resBlogCats):
          while ($row = $resBlogCats->fetch_assoc()):
              $slug = trim((string)$row['slug']);
              if ($slug === '') continue;
              $loc = $baseUrlAbs . '/blog/category/' . rawurlencode($slug);
              ?>
  <url>
    <loc><?= htmlspecialchars($loc) ?></loc>
    <changefreq>weekly</changefreq>
    <priority>0.7</priority>
  </url>
              <?php
          endwhile;
          $resBlogCats->close();
      endif;

      // =====================================================================
      // 5. Bài viết blog hoạt động
      // =====================================================================
      $resBlogs = $ithanhloc->query("SELECT slug, published_at FROM ecommerce_blog WHERE is_active = 1");
      if ($resBlogs):
          while ($row = $resBlogs->fetch_assoc()):
              $slug = trim((string)$row['slug']);
              if ($slug === '') continue;
              $loc = $baseUrlAbs . '/blog/' . rawurlencode($slug);
              $pubAt = !empty($row['published_at']) ? date('Y-m-d', strtotime($row['published_at'])) : '';
              ?>
  <url>
    <loc><?= htmlspecialchars($loc) ?></loc>
    <?php if ($pubAt !== ''): ?>
    <lastmod><?= $pubAt ?></lastmod>
    <?php endif; ?>
    <changefreq>weekly</changefreq>
    <priority>0.7</priority>
  </url>
              <?php
          endwhile;
          $resBlogs->close();
      endif;

  endif;
  ?>

</urlset>
