<!-- Footer Component -->
<footer class="bg-primary text-white py-20 px-margin-mobile md:px-margin-desktop mt-auto">
    <div class="max-w-7xl mx-auto grid grid-cols-1 md:grid-cols-12 gap-12">
        <div class="md:col-span-5">
            <h3 class="font-display text-2xl font-extrabold uppercase italic mb-6 tracking-tighter">
                <?php echo strtoupper($_SiteTitle); ?>
            </h3>
            <p class="text-white/70 text-sm leading-relaxed mb-8 max-w-sm">
                Giải pháp sơn trang trí cao cấp cho mọi công trình. Mang lại vẻ đẹp bền vững và bảo vệ tối ưu cho không gian sống của bạn.
            </p>
            <div class="flex gap-4">
                <?php if($social_facebook): ?>
                <a href="<?php echo $social_facebook; ?>" class="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center hover:bg-accent hover:text-primary transition-all">
                    <i class="fa-brands fa-facebook-f"></i>
                </a>
                <?php endif; ?>
                <?php if($social_tiktok): ?>
                <a href="<?php echo $social_tiktok; ?>" class="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center hover:bg-accent hover:text-primary transition-all">
                    <i class="fa-brands fa-tiktok"></i>
                </a>
                <?php endif; ?>
                <?php if($social_instagram): ?>
                <a href="<?php echo $social_instagram; ?>" class="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center hover:bg-accent hover:text-primary transition-all">
                    <i class="fa-brands fa-instagram"></i>
                </a>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="md:col-span-2">
            <h4 class="font-bold text-accent uppercase text-xs tracking-widest mb-6">Sản phẩm</h4>
            <ul class="space-y-3">
                <?php
                $footSuggestedProducts = [];
                if (isset($ithanhloc) && $ithanhloc instanceof mysqli) {
                    $productTable = function_exists('first_existing_table') ? first_existing_table($ithanhloc, ['ecommerce_product']) : 'ecommerce_product';
                    if ($productTable !== '') {
                        $variantTable = function_exists('first_existing_table') ? first_existing_table($ithanhloc, ['ecommerce_product_variants']) : 'ecommerce_product_variants';
                        
                        $pCols = function_exists('list_table_columns') ? list_table_columns($ithanhloc, $productTable) : [];
                        $productActiveExprSql = "status = 'true'";
                        if (!empty($pCols)) {
                            if (in_array('status', $pCols, true)) {
                                $productActiveExprSql = "LOWER(TRIM(CAST(status AS CHAR))) IN ('true','1','on','yes','active','enabled')";
                            } elseif (in_array('is_active', $pCols, true)) {
                                $productActiveExprSql = "LOWER(TRIM(CAST(is_active AS CHAR))) IN ('1','true','on','yes')";
                            }
                        }
                        
                        $vCols = ($variantTable !== '') ? (function_exists('list_table_columns') ? list_table_columns($ithanhloc, $variantTable) : []) : [];
                        $variantActiveWhere = "";
                        if (!empty($vCols)) {
                            if (in_array('status', $vCols, true)) {
                                $variantActiveWhere = " AND (status = 1 OR status = '1' OR LOWER(status) = 'true')";
                            } elseif (in_array('is_active', $vCols, true)) {
                                $variantActiveWhere = " AND is_active = 1";
                            }
                        }
                        
                        $stockCheckSql = "";
                        if ($variantTable !== '') {
                            $stockCheckSql = " AND EXISTS (SELECT 1 FROM `{$variantTable}` v WHERE v.product_id = p.id AND v.stock_quantity > 0{$variantActiveWhere})";
                        }
                        
                        $sql = "SELECT id, product_name, slug 
                                FROM `{$productTable}` p 
                                WHERE TRIM(product_name) <> '' AND {$productActiveExprSql}{$stockCheckSql}
                                ORDER BY RAND()
                                LIMIT 5";
                                
                        $productRes = $ithanhloc->query($sql);
                        if ($productRes instanceof mysqli_result) {
                            while ($row = $productRes->fetch_assoc()) {
                                $footSuggestedProducts[] = [
                                    'id' => (int)($row['id'] ?? 0),
                                    'name' => trim((string)($row['product_name'] ?? '')),
                                    'slug' => trim((string)($row['slug'] ?? '')),
                                ];
                            }
                            $productRes->close();
                        }
                    }
                }
                
                if (!empty($footSuggestedProducts)):
                    foreach ($footSuggestedProducts as $sp):
                        $spUrl = function_exists('pm_product_url') ? pm_product_url($sp['id'], $sp['name'], (string)($baseUrl ?? '')) : (string)($baseUrl ?? '') . '/product/' . pm_slugify($sp['name']) . '-' . $sp['id'];
                ?>
                        <li><a href="<?php echo htmlspecialchars($spUrl, ENT_QUOTES, 'UTF-8'); ?>" class="text-white/60 text-sm hover:text-white transition-colors"><?php echo htmlspecialchars($sp['name'], ENT_QUOTES, 'UTF-8'); ?></a></li>
                <?php 
                    endforeach;
                else: 
                ?>
                    <li><a href="#" class="text-white/60 text-sm hover:text-white transition-colors">Sơn nội thất</a></li>
                    <li><a href="#" class="text-white/60 text-sm hover:text-white transition-colors">Sơn ngoại thất</a></li>
                    <li><a href="#" class="text-white/60 text-sm hover:text-white transition-colors">Chống thấm</a></li>
                    <li><a href="#" class="text-white/60 text-sm hover:text-white transition-colors">Bột bả - Chống kiềm</a></li>
                <?php endif; ?>
            </ul>
        </div>
        
        <div class="md:col-span-2">
            <h4 class="font-bold text-accent uppercase text-xs tracking-widest mb-6">Hỗ trợ</h4>
            <ul class="space-y-3">
                <?php
                $footSuggestedBlogs = [];
                if (isset($ithanhloc) && $ithanhloc instanceof mysqli) {
                    $blogTable = function_exists('first_existing_table') ? first_existing_table($ithanhloc, ['ecommerce_blog']) : 'ecommerce_blog';
                    if ($blogTable !== '') {
                        $sqlBlog = "SELECT id, title, slug 
                                    FROM `{$blogTable}` 
                                    WHERE is_active = 1 
                                    ORDER BY published_at DESC, id DESC 
                                    LIMIT 5";
                        $blogRes = $ithanhloc->query($sqlBlog);
                        if ($blogRes instanceof mysqli_result) {
                            while ($row = $blogRes->fetch_assoc()) {
                                $footSuggestedBlogs[] = [
                                    'id' => (int)($row['id'] ?? 0),
                                    'title' => trim((string)($row['title'] ?? '')),
                                    'slug' => trim((string)($row['slug'] ?? '')),
                                ];
                            }
                            $blogRes->close();
                        }
                    }
                }
                
                if (!empty($footSuggestedBlogs)):
                    foreach ($footSuggestedBlogs as $post):
                        $postSlug = $post['slug'] !== '' ? $post['slug'] : 'bai-viet-' . $post['id'];
                        $postUrl = rtrim((string)($baseUrl ?? ''), '/') . '/blog/' . rawurlencode($postSlug);
                ?>
                        <li><a href="<?php echo htmlspecialchars($postUrl, ENT_QUOTES, 'UTF-8'); ?>" class="text-white/60 text-sm hover:text-white transition-colors"><?php echo htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8'); ?></a></li>
                <?php 
                    endforeach;
                else: 
                ?>
                    <li><a href="#" class="text-white/60 text-sm hover:text-white transition-colors">Tính toán lượng sơn</a></li>
                    <li><a href="#" class="text-white/60 text-sm hover:text-white transition-colors">Hướng dẫn thi công</a></li>
                    <li><a href="#" class="text-white/60 text-sm hover:text-white transition-colors">Chính sách bảo hành</a></li>
                    <li><a href="#" class="text-white/60 text-sm hover:text-white transition-colors">Tìm đại lý</a></li>
                <?php endif; ?>
            </ul>
        </div>
        
        <div class="md:col-span-3">
            <h4 class="font-bold text-accent uppercase text-xs tracking-widest mb-6">Liên hệ</h4>
            <ul class="space-y-3">
                <li class="flex items-start gap-3 text-white/60 text-sm">
                    <span class="material-symbols-outlined text-accent text-sm mt-0.5"><i class="fa-solid fa-envelope"></i></span>
                    <?php echo $company_email ?: 'info@paintmore.vn'; ?>
                </li>
                <li class="flex items-start gap-3 text-white/60 text-sm">
                    <span class="material-symbols-outlined text-accent text-sm mt-0.5"><i class="fa-solid fa-phone"></i></span>
                    <?php echo $hotline; ?>
                </li>
                <li class="flex items-start gap-3 text-white/60 text-sm">
                    <span class="material-symbols-outlined text-accent text-sm mt-0.5"><i class="fa-solid fa-location-dot"></i></span>
                    <?php echo $company_address ?: 'Hà Nội, Việt Nam'; ?>
                </li>
            </ul>
        </div>
    </div>
    
    <div class="max-w-7xl mx-auto border-t border-white/10 mt-16 pt-8 flex flex-col md:flex-row justify-between items-center gap-4">
        <p class="text-white/40 text-xs uppercase tracking-widest">
            © <?php echo date('Y'); ?> <?php echo strtoupper($_SiteTitle); ?> - POWERED BY ANTIGRAVITY
        </p>
    </div>
</footer>