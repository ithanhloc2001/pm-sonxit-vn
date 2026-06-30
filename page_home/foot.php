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
                <li><a href="#" class="text-white/60 text-sm hover:text-white transition-colors">Sơn nội thất</a></li>
                <li><a href="#" class="text-white/60 text-sm hover:text-white transition-colors">Sơn ngoại thất</a></li>
                <li><a href="#" class="text-white/60 text-sm hover:text-white transition-colors">Chống thấm</a></li>
                <li><a href="#" class="text-white/60 text-sm hover:text-white transition-colors">Bột bả - Chống kiềm</a></li>
            </ul>
        </div>
        
        <div class="md:col-span-2">
            <h4 class="font-bold text-accent uppercase text-xs tracking-widest mb-6">Hỗ trợ</h4>
            <ul class="space-y-3">
                <li><a href="#" class="text-white/60 text-sm hover:text-white transition-colors">Tính toán lượng sơn</a></li>
                <li><a href="#" class="text-white/60 text-sm hover:text-white transition-colors">Hướng dẫn thi công</a></li>
                <li><a href="#" class="text-white/60 text-sm hover:text-white transition-colors">Chính sách bảo hành</a></li>
                <li><a href="#" class="text-white/60 text-sm hover:text-white transition-colors">Tìm đại lý</a></li>
            </ul>
        </div>
        
        <div class="md:col-span-3">
            <h4 class="font-bold text-accent uppercase text-xs tracking-widest mb-6">Liên hệ</h4>
            <ul class="space-y-3">
                <li class="flex items-start gap-3 text-white/60 text-sm">
                    <span class="material-symbols-outlined text-accent text-sm mt-0.5">mail</span>
                    <?php echo $company_email ?: 'info@paintmore.vn'; ?>
                </li>
                <li class="flex items-start gap-3 text-white/60 text-sm">
                    <span class="material-symbols-outlined text-accent text-sm mt-0.5">call</span>
                    <?php echo $hotline; ?>
                </li>
                <li class="flex items-start gap-3 text-white/60 text-sm">
                    <span class="material-symbols-outlined text-accent text-sm mt-0.5">location_on</span>
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