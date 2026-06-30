<?php
// Nếu không phải là admin hoặc chưa đăng nhập thì chuyển hướng về trang chủ
if (!$isAdmin || !$isLoggedIn) {
    if (!headers_sent()) {
        header('Location: ' . $baseUrl);
    }
    exit('<script>window.location.href="' . $baseUrl . '";</script>');
}
?>
<?php
// Sản phẩm thêm mới 
$pid = (int)($_GET['id'] ?? 0);
function hexToRgb($hex) {
    $hex = str_replace("#", "", $hex);
    if(strlen($hex) == 3) {
        $r = hexdec(substr($hex,0,1).substr($hex,0,1));
        $g = hexdec(substr($hex,1,1).substr($hex,1,1));
        $b = hexdec(substr($hex,2,1).substr($hex,2,1));
    } else {
        $r = hexdec(substr($hex,0,2));
        $g = hexdec(substr($hex,2,2));
        $b = hexdec(substr($hex,4,2));
    }
    return "$r, $g, $b";
}
?>

 <div class="sticky-actions-card shadow-lg" aria-label="Lưu sản phẩm">
    <a class="btn btn-light border shadow-sm" href="<?= h($baseUrl) ?>" title="Quay lại"><i class="bi bi-arrow-left"></i></a>
    <?php if ($pid > 0): ?>
        <a class="btn btn-outline-primary border shadow-sm" id="btnViewProductStorefront" href="<?= h($baseUrl) ?>" target="_blank" title="Xem sản phẩm">
            <i class="bi bi-eye"></i> <span class="sticky-btn-text">Xem</span>
        </a>
        <div class="vr mx-1 d-none d-md-block" style="height: 24px; opacity: 0.2;"></div>
    <?php endif; ?>
    <button class="btn btn-outline-primary border shadow-sm" type="button" id="p_status_quick_toggle" title="Trạng thái hiển thị" data-active="true">
        <i class="bi bi-toggle-on me-1" id="p_status_quick_icon"></i> <span class="sticky-btn-text" id="p_status_quick_label">BẬT</span>
    </button>
    <div class="vr mx-1 d-none d-md-block" style="height: 24px; opacity: 0.2;"></div>
    <button class="btn btn-primary shadow-sm px-4" id="btnSaveProduct" type="button">
        <i class="bi bi-save"></i> <span class="sticky-btn-text">Lưu</span>
    </button>
</div>

<div class="card border shadow-none rounded-1 mb-4">
    <div class="card-header bg-body-tertiary border-bottom py-3 d-none d-sm-block">
        <h5 class="card-title mb-1 fw-bold text-dark"><i class="bi bi-brush me-2"></i>Ảnh đại diện & Thông tin chung</h5>
        <div class="card-subtitle text-muted small">Ảnh đại diện cùng tên, danh mục, hãng sản xuất trong một card.</div>
    </div>  
    <div class="card-body">
        
        <div class="d-flex flex-column flex-md-row gap-4 align-items-start">
            <div class="p-3 text-center bg-white" style="min-width: 240px; width: 100%; max-width: 280px; margin: 0 auto;">
                <div id="mainImagePreviewWrapper" class="mb-3" style="aspect-ratio: 1/1; overflow: hidden;">
                    <div class="text-secondary small" id="mainImagePlaceholder">Chưa có ảnh</div>
                </div>
                <button class="btn btn-sm btn-primary w-100 mb-2 rounded-1" type="button" id="btnPickMainImage">
                    <i class="bi bi-cloud-upload me-1"></i><span>Chọn ảnh</span>
                </button>
                <div id="mainImageRatioGroup" class="d-none d-flex flex-column align-items-center">
                    <span class="form-label small mb-2 fw-semibold text-dark">Tỉ lệ ảnh:</span>
                    <div class="btn-group" role="group">
                        <input type="radio" class="btn-check" name="main_image_ratio" id="ratio_1_1" value="1:1" autocomplete="off" checked>
                        <label class="btn btn-outline-primary btn-sm rounded-start-1" for="ratio_1_1">1:1</label>
                        <input type="radio" class="btn-check" name="main_image_ratio" id="ratio_3_4" value="3:4" autocomplete="off">
                        <label class="btn btn-outline-primary btn-sm rounded-end-1" for="ratio_3_4">3:4</label>
                    </div>
                </div>
            </div>
            <!-- /./ -->
            <div class="flex-grow-1 w-100">
                <div class="row g-3 mb-3">
                    <div class="col-12 col-md-12">
                        <label class="form-label fw-semibold small mb-1 text-dark">Tên sản phẩm</label>
                        <input class="form-control form-control-sm rounded-1" id="p_name" placeholder="VD: Sơn nội thất cao cấp">
                    </div>
                    <div class="col-12 col-md-12">
                        <label class="form-label fw-semibold small mb-1 text-dark">Slug (Giữ nguyên hoặc tạo mới)</label>
                        <div class="input-group input-group-sm">
                            <input class="form-control rounded-start-1" id="p_slug" placeholder="vd: son-noi-that-cao-cap">
                            <button class="btn btn-outline-secondary rounded-end-1" type="button" onclick="if(window.pmSlugify) $('#p_slug').val(window.pmSlugify($('#p_name').val()))" title="Tạo lại slug từ tên"><i class="bi bi-arrow-clockwise"></i></button>
                        </div>
                        <div class="form-text small mt-1">Chỉ dùng chữ thường, số và dấu gạch ngang.</div>
                    </div>
                    <div class="col-12 col-sm-3">
                        <label class="form-label fw-semibold small mb-1 text-dark">SKU</label>
                        <input class="form-control form-control-sm rounded-1" id="p_sku" placeholder="VD: SP-ABC123">
                        <div class="form-text small mt-1 d-none">Mã SKU cấp sản phẩm (khác với SKU phân loại).</div>
                    </div>
                    <div class="col-12 col-sm-3">
                        <label class="form-label fw-semibold small mb-1 text-dark d-flex align-items-center gap-2">
                            VAT (%)  
                            <input class="form-check-input mt-0" type="checkbox" id="p_vat_enabled" checked>
                        </label>
                        <input class="form-control form-control-sm rounded-1" id="p_vat" type="number" min="0" max="100" step="0.01" value="8">
                    </div>
                    <div class="col-12 col-sm-3">
                        <label class="form-label fw-semibold small mb-1 text-dark">Danh mục</label>
                        <select class="form-select form-select-sm rounded-1" id="p_cat"></select>
                    </div>
                    <div class="col-12 col-sm-3">
                        <label class="form-label fw-semibold small mb-1 text-dark">Hãng sản xuất</label>
                        <select class="form-select form-select-sm rounded-1" id="p_brand">
                            <option value="">-- Chọn hãng sản xuất --</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold small mb-2 text-dark">Khu vực</label>
                        <div id="p_region_group" class="d-flex flex-wrap gap-3 p-3 bg-body-tertiary border rounded-1">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="p_region_all" value="ALL">
                                <label class="form-check-label small fw-bold text-dark" for="p_region_all">Tất cả</label>
                            </div>
                            <?php foreach ($regionOptions as $idx => $regionItem): ?>
                            <div class="form-check">
                                <input class="form-check-input p-region-item" type="checkbox" id="p_region_item_<?= (int)$idx ?>" value="<?= h($regionItem) ?>">
                                <label class="form-check-label small" for="p_region_item_<?= (int)$idx ?>">
                                    <?= h($regionItem) ?>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div> 

                <div class="row g-3 mb-3">
                    <div class="col-md-6"><label class="form-label fw-semibold small mb-1 text-dark">Loại nhựa</label><input id="p_resin_type" class="form-control form-control-sm rounded-1"></div>
                    <div class="col-md-6"><label class="form-label fw-semibold small mb-1 text-dark">VOC</label><input id="p_voc" class="form-control form-control-sm rounded-1"></div>
               </div> 
            </div>
        </div>
        <!-- Mô tả chung -->
        <div class="col-12 col-md-12 mb-3">
            <label class="form-label fw-semibold small mb-1 text-dark">Mô tả chung</label>
            <div class="border rounded-1">
            <textarea id="p_description" class="form-control border-0 rounded-1" rows="8" placeholder="Viết mô tả sản phẩm..."></textarea>
            </div>
        </div>
        <hr class="my-4 text-secondary-subtle">     
        <!-- Ảnh/Video -->
        <div class="d-flex gap-2">
            <button class="btn btn-sm btn-outline-dark rounded-1" type="button" id="btnPickMedia"><i class="bi bi-collection-play me-1"></i><span>Thêm Media</span></button>
        </div>
        <div class="form-text small mt-2">Video sản phẩm: tối đa <?= $max_UpfileSize ?>MB, định dạng MP4, thời lượng tối đa 10 phút.</div>
        <div class="table-responsive mt-3" id="mediaList"></div>
        <!-- Nhóm phân loại -->
        <div class="mb-4">
            <div class="d-flex justify-content-between align-items-center mt-3 mb-3">
                <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-collection me-2"></i>NHÓM PHÂN LOẠI</h6>
                <button type="button" class="btn btn-outline-primary btn-sm rounded-1" onclick="openVariantGroupModal()"><i class="bi bi-plus-lg me-1"></i>Thêm nhóm</button>
            </div>
            <div id="variantGroupsContainer" class="d-flex flex-wrap gap-3 p-3 bg-light rounded-2 border">
                <div class="text-muted small w-100 text-center py-2">Đang tải danh sách nhóm...</div>
            </div>
            <div class="text-muted small mt-2 italic"><i class="bi bi-info-circle me-1"></i>Tạo các nhóm như "Màu sắc", "Kích thước", "Khối lượng"... trước khi thêm biến thể.</div>
        </div>
        <hr class="my-4">
        <style>
        /* Bộ lọc nhóm biến thể (chip dạng card) */
        #variantGroupFilters .filter-card {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            background: #fff;
            cursor: pointer;
            user-select: none;
            transition: border-color .15s ease, background-color .15s ease, box-shadow .15s ease, color .15s ease;
        }
        #variantGroupFilters .filter-card:hover {
            border-color: var(--theme-primary, #0d6efd);
            background: #f8fafc;
        }
        #variantGroupFilters .filter-card.active {
            border-color: var(--theme-primary, #0d6efd);
            background: var(--theme-primary, #0d6efd);
            color: #fff;
            box-shadow: 0 2px 8px rgba(13, 110, 253, 0.20);
        }
        #variantGroupFilters .filter-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 8px;
            background: #f1f5f9;
            color: #64748b;
            font-size: 0.95rem;
            flex-shrink: 0;
        }
        #variantGroupFilters .filter-card.active .filter-icon {
            background: rgba(255, 255, 255, 0.22);
            color: #fff;
        }
        #variantGroupFilters .filter-label {
            display: inline-flex;
            align-items: center;
            font-size: 0.85rem;
            font-weight: 600;
            white-space: nowrap;
        }
        /* Badge số lượng đổi màu cho dễ đọc khi chip active */
        #variantGroupFilters .filter-card.active .badge {
            background: rgba(255, 255, 255, 0.25) !important;
            color: #fff !important;
        }
        </style>
        <h6 class="fw-bold mb-3 text-dark"><i class="bi bi-list-ul me-2"></i>DANH SÁCH BIẾN THỂ</h6>
        <div id="variantGroupFilters" class="d-flex flex-wrap gap-2 mb-4 pb-2 border-bottom"></div>
        <!-- /./ -->
        <!-- /./ -->
        <div class="d-flex gap-2 mb-3">
            <button class="btn btn-outline-primary btn-sm rounded-1 px-3 mb-3" type="button" id="btnQuickAddVariantModal"><i class="bi bi-file-earmark-text me-1"></i> Thêm nhanh</button>    
            <button class="btn btn-success btn-sm rounded-1 px-3 mb-3" type="button" onclick="openVariantAddModal()"><i class="bi bi-plus-lg me-1"></i> Biến thể</button>
        </div>

        <!-- Bộ lọc nâng cao -->
        <div class="row g-2 mb-3 align-items-end p-3 bg-light rounded-2 border">
            <div class="col-12 col-md-2">
                <label class="form-label small fw-bold text-dark mb-1 text-uppercase" style="font-size: 0.7rem;"><i class="bi bi-search me-1"></i>Tìm kiếm</label>
                <div class="input-group input-group-sm">
                    <input type="text" class="form-control border-secondary-subtle" id="variantSearch" placeholder="SKU, tên...">
                </div>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-bold text-dark mb-1 text-uppercase" style="font-size: 0.7rem;"><i class="bi bi-image me-1"></i>Ảnh</label>
                <select class="form-select form-select-sm border-secondary-subtle" id="filterImageStatus">
                    <option value="all">Tất cả</option>
                    <option value="no_image">Chưa có ảnh</option>
                    <option value="has_image">Đã có ảnh</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-bold text-dark mb-1 text-uppercase" style="font-size: 0.7rem;"><i class="bi bi-tag me-1"></i>Giá</label>
                <select class="form-select form-select-sm border-secondary-subtle" id="filterPriceStatus">
                    <option value="all">Tất cả</option>
                    <option value="no_price">Chưa có giá</option>
                    <option value="has_price">Đã có giá</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-bold text-dark mb-1 text-uppercase" style="font-size: 0.7rem;"><i class="bi bi-toggle-on me-1"></i>T.Thái</label>
                <select class="form-select form-select-sm border-secondary-subtle" id="filterActiveStatus">
                    <option value="all">Tất cả</option>
                    <option value="active">Bật</option>
                    <option value="inactive">Tắt</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-bold text-dark mb-1 text-uppercase" style="font-size: 0.7rem;"><i class="bi bi-box-seam me-1"></i>Kho</label>
                <select class="form-select form-select-sm border-secondary-subtle" id="filterStockStatus">
                    <option value="all">Tất cả</option>
                    <option value="in_stock">Còn hàng</option>
                    <option value="out_of_stock">Hết hàng</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-bold text-dark mb-1 text-uppercase" style="font-size: 0.7rem;"><i class="bi bi-sort-down me-1"></i>Sắp xếp</label>
                <select class="form-select form-select-sm border-secondary-subtle" id="variantSort">
                    <option value="id_desc">Mới nhất</option>
                    <option value="id_asc">Cũ nhất</option>
                    <option value="name_asc">Tên A-Z</option>
                    <option value="name_desc">Tên Z-A</option>
                    <option value="price_asc">Giá: Thấp -> Cao</option>
                    <option value="price_desc">Giá: Cao -> Thấp</option>
                    <option value="stock_desc">Tồn kho giảm dần</option>
                    <option value="stock_asc">Tồn kho tăng dần</option>
                </select>
            </div>
        </div>
        <div id="bulkEditControls" class="mb-3 d-none">
            <div class="d-flex align-items-center gap-2 p-2 bg-warning-subtle border border-warning-subtle rounded-1">
                <span class="small fw-bold text-warning-emphasis"><i class="bi bi-check2-all me-1"></i>Đã chọn <span id="bulkEditCount">0</span> phân loại:</span>
                <button type="button" class="btn btn-warning btn-sm fw-bold rounded-1" id="btnBulkEdit"><i class="bi bi-pencil-square me-1"></i>SỬA HÀNG LOẠT</button>
                <button type="button" class="btn btn-outline-secondary btn-sm rounded-1" id="btnClearBulkSelection">Bỏ chọn</button>
            </div>
        </div>
        <div class="table-responsive">
            <table id="variantTable" class="table table-bordered table-striped align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="text-center"><input type="checkbox" class="form-check-input border-primary" id="checkAllVariants"></th>
                        <th class="text-center">Ảnh</th>
                        <th>Nhóm</th>
                        <th>Tên biến thể</th>
                        <th class="text-end">Giá</th>
                        <th class="text-center">Kho</th>
                        <th>SKU</th>
                        <th class="text-center">Trạng thái</th>
                        <th class="text-end">Khối lượng</th>
                        <th class="text-center">Sửa</th>
                        <th class="text-center">Xóa</th>
                    </tr>
                </thead>
                <tbody id="variantTbody"></tbody>
            </table>
        </div>
        <!-- /./ -->
        <hr class="my-4 text-secondary-subtle">                     
        <div class="mb-4">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-diagram-3 me-2"></i>Hệ thống sơn</h6>
                <button type="button" class="btn btn-primary btn-sm rounded-1 px-3" id="btnAddCoatingSystem"><i class="bi bi-plus-lg me-1"></i> Thêm hệ thống</button>
            </div>
            <div class="table-responsive border rounded-1">
                <table class="table table-sm table-bordered table-striped align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="text-center" style="width:20px;">ID</th>
                            <th style="width:200px">Tên sản phẩm</th>
                            <th style="width:100px">Loại</th>
                            <th class="text-center" style="width:50px;">Số lớp</th>
                            <th class="text-center" style="width:50px;">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody id="coatingSystemTbody">
                        <tr>
                            <td colspan="5" class="text-center text-muted py-3 small">Lưu sản phẩm trước khi thêm hệ thống sơn.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mb-4">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-tools me-2"></i>Thi công</h6>
                <button type="button" class="btn btn-outline-dark btn-sm rounded-1 px-3" id="btnConstructionEdit">
                    <i class="bi bi-pencil-square me-1"></i> Thêm thông tin
                </button>
            </div>
            <div class="table-responsive border rounded-1">
                <table class="table table-sm table-bordered table-striped align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="text-center" style="width:20px;">ID</th>
                            <th style="width:350px;">Chuẩn bị bề mặt</th>
                            <th style="width:150px">Dụng cụ</th>
                            <th style="width:150px">Cách thi công</th>
                            <th class="text-center" style="width:50px;">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody id="constructionTbody">
                        <tr>
                            <td colspan="5" class="text-center text-muted py-3 small">Lưu sản phẩm trước khi cấu hình dữ liệu thi công.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <label class="form-label fw-semibold small mb-2 text-dark">Không gian</label>
                <select class="form-select form-select-sm rounded-1" id="p_paint_space">
                    <option value="">-- Không chọn --</option>
                    <option value="interior">Nội thất</option>
                    <option value="exterior">Ngoại thất</option>
                    <option value="all">Tất cả</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold small mb-2 text-dark">Vị trí phù hợp</label>
                <div id="p_paint_positions_group" class="d-flex flex-column gap-2 p-3 bg-body-tertiary border rounded-1 h-100" style="max-height: 250px; overflow-y: auto;">
                    <div class="form-check border-bottom pb-2 mb-1">
                        <input class="form-check-input" type="checkbox" id="p_paint_pos_all" value="all">
                        <label class="form-check-label small fw-bold text-dark" for="p_paint_pos_all">Tất cả</label>
                    </div>
                    <div class="form-check"><input class="form-check-input p-paint-pos-item" type="checkbox" id="p_paint_pos_wall" value="wall"><label class="form-check-label small" for="p_paint_pos_wall">Tường</label></div>
                    <div class="form-check"><input class="form-check-input p-paint-pos-item" type="checkbox" id="p_paint_pos_door" value="door"><label class="form-check-label small" for="p_paint_pos_door">Cửa</label></div>
                    <div class="form-check"><input class="form-check-input p-paint-pos-item" type="checkbox" id="p_paint_pos_ceiling" value="ceiling"><label class="form-check-label small" for="p_paint_pos_ceiling">Trần</label></div>
                    <div class="form-check"><input class="form-check-input p-paint-pos-item" type="checkbox" id="p_paint_pos_trim" value="trim"><label class="form-check-label small" for="p_paint_pos_trim">Viền</label></div>
                    <div class="form-check"><input class="form-check-input p-paint-pos-item" type="checkbox" id="p_paint_pos_window" value="window"><label class="form-check-label small" for="p_paint_pos_window">Cửa sổ</label></div>
                    <div class="form-check"><input class="form-check-input p-paint-pos-item" type="checkbox" id="p_paint_pos_floor" value="floor"><label class="form-check-label small" for="p_paint_pos_floor">Sàn</label></div>
                    <div class="form-check"><input class="form-check-input p-paint-pos-item" type="checkbox" id="p_paint_pos_roof" value="roof"><label class="form-check-label small" for="p_paint_pos_roof">Mái tôn</label></div>
                    <div class="form-check"><input class="form-check-input p-paint-pos-item" type="checkbox" id="p_paint_pos_other" value="other"><label class="form-check-label small" for="p_paint_pos_other">Khác</label></div>
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold small mb-2 text-dark">Nhu cầu / không gian</label>
                <div id="p_paint_needs_group" class="d-flex flex-column gap-2 p-3 bg-body-tertiary border rounded-1 h-100" style="max-height: 250px; overflow-y: auto;">
                    <div class="form-check border-bottom pb-2 mb-1">
                        <input class="form-check-input" type="checkbox" id="p_paint_need_all" value="all">
                        <label class="form-check-label small fw-bold text-dark" for="p_paint_need_all">Tất cả</label>
                    </div>
                    <div class="form-check"><input class="form-check-input p-paint-need-item" type="checkbox" id="p_paint_need_living_room" value="living_room"><label class="form-check-label small" for="p_paint_need_living_room">Phòng khách</label></div>
                    <div class="form-check"><input class="form-check-input p-paint-need-item" type="checkbox" id="p_paint_need_kids_room" value="kids_room"><label class="form-check-label small" for="p_paint_need_kids_room">Phòng trẻ em</label></div>
                    <div class="form-check"><input class="form-check-input p-paint-need-item" type="checkbox" id="p_paint_need_bathroom" value="bathroom"><label class="form-check-label small" for="p_paint_need_bathroom">Phòng tắm / WC</label></div>
                    <div class="form-check"><input class="form-check-input p-paint-need-item" type="checkbox" id="p_paint_need_bedroom" value="bedroom"><label class="form-check-label small" for="p_paint_need_bedroom">Phòng ngủ</label></div>
                    <div class="form-check"><input class="form-check-input p-paint-need-item" type="checkbox" id="p_paint_need_kitchen" value="kitchen"><label class="form-check-label small" for="p_paint_need_kitchen">Phòng bếp</label></div>
                    <div class="form-check"><input class="form-check-input p-paint-need-item" type="checkbox" id="p_paint_need_facade" value="facade"><label class="form-check-label small" for="p_paint_need_facade">Mặt tiền / ngoài trời</label></div>
                    <div class="form-check"><input class="form-check-input p-paint-need-item" type="checkbox" id="p_paint_need_waterproof" value="waterproof"><label class="form-check-label small" for="p_paint_need_waterproof">Chống thấm</label></div>
                    <div class="form-check"><input class="form-check-input p-paint-need-item" type="checkbox" id="p_paint_need_anti_mold" value="anti_mold"><label class="form-check-label small" for="p_paint_need_anti_mold">Chống ẩm mốc</label></div>
                    <div class="form-check"><input class="form-check-input p-paint-need-item" type="checkbox" id="p_paint_need_dust_resistant" value="dust_resistant"><label class="form-check-label small" for="p_paint_need_dust_resistant">Chống bám bụi</label></div>
                    <div class="form-check"><input class="form-check-input p-paint-need-item" type="checkbox" id="p_paint_need_alkali_resistant" value="alkali_resistant"><label class="form-check-label small" for="p_paint_need_alkali_resistant">Chống kiềm</label></div>
                    <div class="form-check"><input class="form-check-input p-paint-need-item" type="checkbox" id="p_paint_need_uv_resistant" value="uv_resistant"><label class="form-check-label small" for="p_paint_need_uv_resistant">Chống UV</label></div>
                    <div class="form-check"><input class="form-check-input p-paint-need-item" type="checkbox" id="p_paint_need_rust_resistant" value="rust_resistant"><label class="form-check-label small" for="p_paint_need_rust_resistant">Chống rỉ sét</label></div>
                    <div class="form-check"><input class="form-check-input p-paint-need-item" type="checkbox" id="p_paint_need_crack_resistant" value="crack_resistant"><label class="form-check-label small" for="p_paint_need_crack_resistant">Chống nứt</label></div>
                </div>
            </div>
            <div class="row g-3 mb-4">
            <div class="col-md-2 col-sm-6"><label class="form-label fw-semibold small mb-1 text-dark">% Tỷ lệ rắn</label><input id="p_solid_content" class="form-control form-control-sm rounded-1"></div>
            <div class="col-md-2 col-sm-6"><label class="form-label fw-semibold small mb-1 text-dark">Độ phủ</label><input id="p_coverage" class="form-control form-control-sm rounded-1"></div>
            <div class="col-md-2 col-sm-6"><label class="form-label fw-semibold small mb-1 text-dark">Độ bóng</label><input id="p_gloss_level" class="form-control form-control-sm rounded-1"></div>
            <div class="col-md-3 col-sm-6"><label class="form-label fw-semibold small mb-1 text-dark">Thời gian khô</label><input id="p_drying_time" class="form-control form-control-sm rounded-1"></div>
            <div class="col-md-3 col-sm-6"><label class="form-label fw-semibold small mb-1 text-dark">Khối lượng gốc</label>
                <div class="input-group input-group-sm">
                    <input id="p_stock_quantily" class="form-control form-control-sm rounded-start-1" type="number" min="0" step="0.01" placeholder="Ví dụ: 18">
                    <select id="p_stock_unit" class="form-select form-select-sm rounded-end-1" style="max-width: 110px;">
                        <option value="">Không xác định</option>
                        <option value="kg">kg</option>
                        <option value="g">g</option>
                        <option value="gr">gr</option>
                        <option value="L">L</option>
                        <option value="ml">ml</option>
                    </select>
                </div>
            </div>
            </div>
            <div class="row g-3 mb-4">
            <div class="col-12 col-md-4"><label class="form-label fw-semibold small mb-1 text-dark">Đặc tính & Thông số</label><textarea id="p_key_features" class="form-control form-control-sm rounded-1" rows="4"></textarea></div>
            <div class="col-12 col-md-4"><label class="form-label fw-semibold small mb-1 text-dark">Ứng dụng công trình</label><textarea id="p_applications" class="form-control form-control-sm rounded-1" rows="3" placeholder="Ví dụ: Nội thất, ngoại thất, mặt tiền, khu vực ẩm thấp..."></textarea></div>
            <div class="col-12 col-md-4"><label class="form-label fw-semibold small mb-1 text-dark">Bảo quản</label><textarea id="p_storage" class="form-control form-control-sm rounded-1" rows="2"></textarea></div>
            </div>
            <!--/./-->                    
        </div>
    </div> 
</div>
<div class="card border shadow-none rounded-1 mb-4 d-none">
    <div class="card-header bg-body-tertiary border-bottom py-3">
        <h6 class="card-title mb-1 fw-bold text-dark"><i class="bi bi-cart-check me-2"></i>Mua nhiều giảm giá</h6>
        <div class="text-muted small">Thiết lập đơn giá theo số lượng sản phẩm.</div>
    </div>
    <div class="card-body">
        <div class="row g-2 align-items-end mb-3 p-3 bg-body-tertiary border rounded-1">
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-dark">Khoảng giá</label>
                <input class="form-control form-control-sm rounded-1" id="tier_name" placeholder="KHOẢNG GIÁ 1">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-dark">Từ (SP)</label>
                <input class="form-control form-control-sm rounded-1" id="tier_from" type="number" min="1" value="1">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-dark">Đến (SP)</label>
                <input class="form-control form-control-sm rounded-1" id="tier_to" type="number" min="1" value="5">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-dark">Đơn giá</label>
                <input class="form-control form-control-sm rounded-1" id="tier_price" onkeyup="fmtNum(this)" placeholder="100000">
            </div>
            <div class="col-md-2">
                <button class="btn btn-dark btn-sm rounded-1 w-100" type="button" id="btnAddBulkTier"><i class="bi bi-plus-lg me-1"></i> Thêm</button>
            </div>
        </div>
        <div class="table-responsive border rounded-1">
            <table class="table table-sm table-bordered table-striped mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Khoảng giá</th>
                        <th class="text-center">Từ (SP)</th>
                        <th class="text-center">Đến (SP)</th>
                        <th class="text-end">Đơn giá</th>
                        <th class="text-center">Thao tác</th>
                    </tr>
                </thead>
                <tbody id="bulkTierTbody"></tbody>
            </table>
        </div>
    </div>
</div>

<div class="card border shadow-none rounded-1 mb-4">
    <div class="card-header bg-body-tertiary border-bottom py-3">
        <h6 class="card-title mb-1 fw-bold text-dark"><i class="bi bi-truck me-2"></i>Vận chuyển</h6>
        <div class="text-muted small">Chọn dịch vụ và cấu hình gói hàng để tính phí tự động.</div>
    </div>
    <div class="card-body">
        <label class="form-label fw-semibold small mb-2 text-dark">Dịch vụ vận chuyển</label>
        <div id="shippingMethodsEditor" class="p-2 d-flex flex-wrap gap-2"></div>
        <div class="form-text small mt-2">Hệ thống sẽ map theo dịch vụ khả dụng (Nhanh, Tiết kiệm, Hỏa tốc...) tại địa chỉ giao hàng.</div>
    </div>
</div>

<div class="card border shadow-none rounded-1 mb-4">
    <div class="card-body py-3 d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div>
            <h6 class="card-title fw-bold mb-1 text-dark"><i class="bi bi-clock-history me-2"></i>Hàng đặt trước</h6>
            <div class="text-muted small">Cho phép nhận đơn đặt trước cho sản phẩm này.</div>
        </div>
        <div class="btn-group" role="group">
            <input type="radio" class="btn-check" name="preorder_mode" id="preorder_no" value="0" autocomplete="off" checked>
            <label class="btn btn-outline-secondary btn-sm rounded-start-1 px-4" for="preorder_no">Không</label>

            <input type="radio" class="btn-check" name="preorder_mode" id="preorder_yes" value="1" autocomplete="off">
            <label class="btn btn-outline-success btn-sm rounded-end-1 px-4" for="preorder_yes">Đồng ý</label>
        </div>
    </div>
</div>

<!-- Modal: Thêm hệ thống sơn -->
<div class="modal fade" id="coatingSystemModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-diagram-3 me-2"></i>Thêm hệ thống sơn</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3 note">1) Chọn danh mục & sản phẩm gợi ý. 2) Chọn loại sản phẩm (Lót/Phủ hoặc nhập thủ công). 3) Chọn số lớp (có thể bỏ qua). 4) Lưu để thêm vào hệ thống sơn.</div>
                <div class="coating-system-row mb-3">
                    <div>
                        <label class="form-label">1. Danh mục &amp; sản phẩm</label>
                        <div class="d-flex gap-2 flex-wrap mb-2">
                            <select class="form-select flex-fill" id="cs_category">
                                <option value="0">-- Chọn danh mục --</option>
                            </select>
                            <select class="form-select flex-fill" id="cs_product">
                                <option value="0">-- Chọn sản phẩm gợi ý --</option>
                            </select>
                        </div>
                        <div id="cs_product_preview" class="border rounded-3 p-2 bg-light small text-muted">
                            Chưa chọn sản phẩm gợi ý.
                        </div>
                    </div>
                    <div>
                        <label class="form-label">2. Loại sản phẩm</label>
                        <div class="d-flex flex-column gap-2">
                            <div class="d-flex flex-wrap gap-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="cs_layer_type_mode" id="cs_layer_type_lot" value="lot" checked>
                                    <label class="form-check-label" for="cs_layer_type_lot">Lót</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="cs_layer_type_mode" id="cs_layer_type_phu" value="phu">
                                    <label class="form-check-label" for="cs_layer_type_phu">Phủ</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="cs_layer_type_mode" id="cs_layer_type_custom_mode" value="custom">
                                    <label class="form-check-label" for="cs_layer_type_custom_mode">Nhập thủ công</label>
                                </div>
                            </div>
                            <input type="text" class="form-control form-control-sm" id="cs_layer_type_custom" placeholder="Nhập loại sản phẩm..." disabled>
                            <input type="hidden" id="cs_layer_type">
                        </div>
                    </div>
                    <div>
                        <label class="form-label">3. Số lớp</label>
                        <div class="d-flex flex-column gap-2">
                            <div class="d-flex flex-wrap gap-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="cs_layer_count_mode" id="cs_layer_count_none" value="none" checked>
                                    <label class="form-check-label" for="cs_layer_count_none">Không cần</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="cs_layer_count_mode" id="cs_layer_count_input_mode" value="input">
                                    <label class="form-check-label" for="cs_layer_count_input_mode">Nhập số lớp</label>
                                </div>
                            </div>
                            <input type="number" min="1" class="form-control form-control-sm" id="cs_layer_count" value="1" disabled>
                        </div>
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label">4. Xem trước dòng mô tả</label>
                    <input type="text" class="form-control" id="cs_preview" readonly>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                <button type="button" class="btn btn-primary" id="cs_apply"><i class="bi bi-check-lg me-1"></i>Chèn vào hệ thống sơn</button>
            </div>
        </div>
    </div>
</div>
<!-- Modal: Dữ liệu thi công -->
<div class="modal fade" id="constructionModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-tools me-2"></i>Dữ liệu thi công</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body">
                <div class="form-grid mb-2">
                    <div class="span-12">
                        <label class="form-label d-flex align-items-center justify-content-between">
                            <span>Dụng cụ</span>
                            <span class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" id="pc_tools_enabled" checked>
                                <span class="form-check-label">Bật</span>
                            </span>
                        </label>
                        <textarea id="pc_tools" class="form-control" rows="2" placeholder="Nhập danh sách dụng cụ, ví dụ: cọ, rulô, súng phun..."></textarea>
                    </div>
                    <div class="span-12">
                        <label class="form-label d-flex align-items-center justify-content-between">
                            <span>Chuẩn bị bề mặt</span>
                            <span class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" id="pc_surface_enabled">
                                <span class="form-check-label">Bật</span>
                            </span>
                        </label>
                        <div id="pc_surface_fields" class="mt-2 d-none">
                            <div class="mb-2">
                                <label class="form-label small mb-1">Bề mặt mới</label>
                                <textarea id="pc_surface_new" class="form-control" rows="2" placeholder="Hướng dẫn cho bề mặt mới..."></textarea>
                            </div>
                            <div>
                                <label class="form-label small mb-1">Bề mặt cũ</label>
                                <textarea id="pc_surface_old" class="form-control" rows="2" placeholder="Hướng dẫn cho bề mặt cũ..."></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="span-12">
                        <label class="form-label">Cách thi công</label>
                        <div class="mb-2">
                            <label class="form-label small mb-1">Tài liệu PDF (tùy chọn)</label>
                            <input type="file" id="pc_method_file" class="form-control" accept="application/pdf" multiple>
                            <div class="small text-muted mt-1" id="pc_method_file_info">Bạn có thể chọn 1 hoặc nhiều file PDF. File sẽ được tải lên khi bấm “Lưu”.</div>
                            <div class="mt-2" id="pc_method_file_list"></div>
                        </div>
                        <label class="form-label small mb-1">Mô tả cách thi công (nhập thủ công)</label>
                        <textarea id="pc_method_text" class="form-control" rows="3" placeholder="Mô tả chi tiết quy trình thi công..."></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light border" id="btnConstructionCancel">Đóng</button>
                <button type="button" class="btn btn-primary" id="btnSaveConstruction"><i class="bi bi-save"></i> Lưu lại thông tin</button>
            </div>
        </div>
    </div>
</div>
<!-- // -->
<div class="modal fade" id="quickAddVariantModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Thêm nhanh phân loại (Văn bản/Excel)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info py-2 small mb-3">
                    Copy danh sách phân loại từ Excel/Google Sheets hoặc gõ tay và dán vào ô bên dưới. Mỗi dòng là 1 phân loại.<br>
                    <strong>Cấu trúc chuẩn:</strong> <code>SKU PHÂN LOẠI | TÊN LOẠI | GIÁ BÁN | KHO HÀNG</code> (cột phân cách bằng dấu Tab hoặc gạch đứng <code>|</code>)
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Nhóm áp dụng cho các phân loại này</label>
                    <select class="form-select border-primary" id="quick_v_group_id">
                        <option value="0">-- Chọn nhóm --</option>
                    </select>
                </div>
                <textarea class="form-control font-monospace text-nowrap bg-body-tertiary" id="quickAddVariantText" rows="10" placeholder="RO249072|2X Satin|265.000|26"></textarea>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-primary" id="btnProcessQuickAddVariant"><i class="bi bi-play-circle me-1"></i> Xử lý dữ liệu</button>
            </div>
        </div>
    </div>
</div>
<!-- // -->
<div class="modal fade" id="variantAddModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Thêm phân loại</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row d-flex flex-wrap g-3">
                                <div class="col-12">
                                    <label class="form-label fw-bold">Nhóm phân loại</label>
                                    <select class="form-select border-primary" id="a_v_group_id">
                                        <option value="0">-- Chọn nhóm --</option>
                                    </select>
                                    <div class="small text-muted mt-1">Chọn nhóm (ví dụ: Màu sắc, Khối lượng...) trước khi thêm.</div>
                                </div>
                                <div class="col-12 col-md-12">
                                    <label class="form-label">Tên loại</label>
                                    <input class="form-control" id="a_v_name" placeholder="VD: Sơn nội thất">
                                </div>
                                <div class="col-4 col-md-4">
                                    <label class="form-label">Giá bán</label>
                                    <input class="form-control" id="a_v_price" placeholder="VNĐ" onkeyup="fmtNum(this)">
                                </div>
                                <div class="col-4 col-md-4">
                                    <label class="form-label">Kho hàng</label>
                                    <input class="form-control" id="a_v_stock" type="number" min="0" step="1" value="10">
                                </div>
                                <div class="col-4 col-md-4">
                                    <label class="form-label">SKU phân loại</label>
                                    <input class="form-control" id="a_v_sku" placeholder="ONE_COAT_18L">
                                </div>
                                <div class="col-4 col-md-4">
                                    <label class="form-label">Khối lượng</label>
                                    <input class="form-control" id="a_v_weight" type="number" min="0" step="0.01" value="1">
                                </div>
                                <div class="col-4 col-md-4">
                                    <label class="form-label">Đơn vị</label>
                                    <select class="form-select" id="a_v_weight_unit">
                                        <option value="kg" selected>Kg</option>
                                        <option value="gram">Gram</option>
                                        <option value="l">L</option>
                                        <option value="ml">ml</option>
                                    </select>
                                </div>
                                <div class="col-4 col-md-4">
                                    <label class="form-label">Dài (cm)</label>
                                    <input class="form-control" id="a_v_length_cm" type="number" min="1" step="1" value="20">
                                </div>
                                <div class="col-4 col-md-4">
                                    <label class="form-label">Rộng (cm)</label>
                                    <input class="form-control" id="a_v_width_cm" type="number" min="1" step="1" value="20">
                                </div>
                                <div class="col-4 col-md-4">
                                    <label class="form-label">Cao (cm)</label>
                                    <input class="form-control" id="a_v_height_cm" type="number" min="1" step="1" value="20">
                                </div>
                                <div class="col-12 col-md-12">
                                    <label class="form-label">Ảnh sản phẩm</label>
                                    <div class="d-flex align-items-center gap-2">
                                        <div id="a_v_image_preview" class="border rounded bg-light d-flex align-items-center justify-content-center" style="width: 100px; height: 100px; overflow: hidden;">
                                            <i class="bi bi-image text-muted fs-2"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <button type="button" class="btn btn-outline-primary btn-sm rounded-1 mb-2" id="btn_a_v_image">
                                                <i class="bi bi-cloud-upload me-1"></i>Chọn ảnh
                                            </button>
                                            <div class="small text-muted">Tải ảnh lên trực tiếp cho phân loại này.</div>
                                            <input type="hidden" id="a_v_image_url">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Đóng</button>
                            <button type="button" class="btn btn-primary" id="btnSaveVariantAdd">Thêm phân loại</button>
                        </div>
                    </div>
                </div>
            </div>
<!-- // -->
<div class="modal fade" id="variantQuickEditModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Sửa nhanh phân loại</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row d-flex flex-wrap g-3">
                                <div class="col-12">
                                    <label class="form-label fw-bold">Nhóm phân loại</label>
                                    <select class="form-select border-primary" id="q_v_group_id">
                                        <option value="0">-- Chọn nhóm --</option>
                                    </select>
                                </div>
                                <div class="col-12 col-md-12">
                                    <label class="form-label">Tên loại</label>
                                    <input class="form-control" id="q_v_name" placeholder="VD: Sơn nội thất">
                                </div>
                                <div class="col-4 col-md-4">
                                    <label class="form-label">Giá bán</label>
                                    <input class="form-control" id="q_v_price" placeholder="VNĐ" onkeyup="fmtNum(this)">
                                </div>
                                <div class="col-4 col-md-4">
                                    <label class="form-label">Kho hàng</label>
                                    <input class="form-control" id="q_v_stock" type="number" min="0" step="1">
                                </div>
                                <div class="col-4 col-md-4">
                                    <label class="form-label">SKU phân loại</label>
                                    <input class="form-control" id="q_v_sku" placeholder="ONE_COAT_18L">
                                </div>
                                <div class="col-4 col-md-4">
                                    <label class="form-label">Khối lượng</label>
                                    <input class="form-control" id="q_v_weight" type="number" min="0" step="0.01">
                                </div>
                                <div class="col-4 col-md-4">
                                    <label class="form-label">Đơn vị</label>
                                    <select class="form-select" id="q_v_weight_unit">
                                        <option value="ml">ml</option>
                                        <option value="l">Lít</option>
                                        <option value="gram">Gram</option>
                                        <option value="kg">kg</option>
                                        
                                    </select>
                                </div>
                                <div class="col-4 col-md-4">
                                    <label class="form-label">Dài (cm)</label>
                                    <input class="form-control" id="q_v_length_cm" type="number" min="1" step="1">
                                </div>
                                <div class="col-4 col-md-4">
                                    <label class="form-label">Rộng (cm)</label>
                                    <input class="form-control" id="q_v_width_cm" type="number" min="1" step="1">
                                </div>
                                <div class="col-4 col-md-4">
                                    <label class="form-label">Cao (cm)</label>
                                    <input class="form-control" id="q_v_height_cm" type="number" min="1" step="1">
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Đóng</button>
                            <button type="button" class="btn btn-primary" id="btnSaveVariantQuick">Lưu thay đổi</button>
                        </div>
                    </div>
                </div>
            </div>

<!-- Modal Nhóm phân loại -->
<div class="modal fade" id="variantGroupModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary__ py-3">
                <h5 class="modal-title fw-bold text-white__" id="variantGroupModalTitle">Quản lý nhóm phân loại</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" id="vg_id" value="0">
                <div class="mb-3">
                    <label class="form-label fw-bold small text-uppercase">Tên nhóm</label>
                    <input type="text" class="form-control" id="vg_name" placeholder="VD: Màu sắc, Kích thước...">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold small text-uppercase">Slug</label>
                    <input type="text" class="form-control" id="vg_slug" placeholder="VD: mau-sac">
                </div>
                <div class="row g-3">
                    <div class="col-6">
                        <label class="form-label fw-bold small text-uppercase">Thứ tự</label>
                        <input type="number" class="form-control" id="vg_sort" value="0">
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-bold small text-uppercase">Trạng thái</label>
                        <select class="form-select" id="vg_status">
                            <option value="1">Bật (Công khai)</option>
                            <option value="0">Tắt (Ẩn)</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light border-top-0">
                <button type="button" class="btn btn-light border px-4" data-bs-dismiss="modal">Đóng</button>
                <button type="button" class="btn btn-primary px-4 fw-bold" onclick="saveVariantGroup()">Lưu thay đổi</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Sửa hàng loạt phân loại -->
<div class="modal fade" id="variantBulkEditModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-warning py-3">
                <h5 class="modal-title fw-bold text-dark"><i class="bi bi-pencil-square me-2"></i>Sửa hàng loạt phân loại</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="alert alert-warning border-0 bg-warning-subtle small mb-4">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>Lưu ý: Chỉ những trường được tích chọn bên dưới mới được cập nhật cho các phân loại đã chọn (<span class="bulk-count-text">0</span> mục).
                </div>
                
                <div class="row g-4">
                    <!-- Nhóm chung -->
                    <div class="col-12">
                        <div class="form-check mb-2">
                            <input class="form-check-input bulk-toggle" type="checkbox" id="toggle_b_group_id" data-target="b_group_id">
                            <label class="form-check-label fw-bold small text-uppercase" for="toggle_b_group_id">1. Chọn nhóm chung</label>
                        </div>
                        <select class="form-select border-secondary-subtle" id="b_group_id" disabled>
                            <option value="0">-- Giữ nguyên --</option>
                        </select>
                    </div>

                    <!-- Ảnh chung -->
                    <div class="col-12">
                        <div class="form-check mb-2">
                            <input class="form-check-input bulk-toggle" type="checkbox" id="toggle_b_image" data-target="btn_b_image">
                            <label class="form-check-label fw-bold small text-uppercase" for="toggle_b_image">2. Chọn ảnh chung</label>
                        </div>
                        <div class="d-flex align-items-center gap-3">
                            <div id="b_image_preview" class="border rounded bg-light d-flex align-items-center justify-content-center" style="width: 60px; height: 60px; overflow: hidden;">
                                <i class="bi bi-image text-muted fs-4"></i>
                            </div>
                            <button type="button" class="btn btn-outline-primary btn-sm rounded-1" id="btn_b_image" disabled><i class="bi bi-cloud-upload me-1"></i>Chọn ảnh</button>
                            <input type="hidden" id="b_image_url">
                        </div>
                    </div>

                    <!-- Khối lượng chung -->
                    <div class="col-md-6">
                        <div class="form-check mb-2">
                            <input class="form-check-input bulk-toggle" type="checkbox" id="toggle_b_weight" data-target="b_weight,b_weight_unit">
                            <label class="form-check-label fw-bold small text-uppercase" for="toggle_b_weight">3. Chọn khối lượng chung</label>
                        </div>
                        <div class="input-group">
                            <input type="number" class="form-control" id="b_weight" placeholder="0.00" step="0.01" min="0" disabled>
                            <select class="form-select" id="b_weight_unit" style="max-width: 100px;" disabled>
                                <option value="kg">kg</option>
                                <option value="l">Lít</option>
                                <option value="ml">ml</option>
                                <option value="gram">g</option>
                            </select>
                        </div>
                    </div>

                    <!-- Giá chung -->
                    <div class="col-md-6">
                        <div class="form-check mb-2">
                            <input class="form-check-input bulk-toggle" type="checkbox" id="toggle_b_price" data-target="b_price">
                            <label class="form-check-label fw-bold small text-uppercase" for="toggle_b_price">4. Chọn giá chung</label>
                        </div>
                        <div class="input-group">
                            <input type="text" class="form-control" id="b_price" placeholder="VNĐ" onkeyup="fmtNum(this)" disabled>
                            <span class="input-group-text">₫</span>
                        </div>
                    </div>

                    <!-- SKU chung -->
                    <div class="col-md-6">
                        <div class="form-check mb-2">
                            <input class="form-check-input bulk-toggle" type="checkbox" id="toggle_b_sku" data-target="b_sku">
                            <label class="form-check-label fw-bold small text-uppercase" for="toggle_b_sku">5. Chọn SKU chung</label>
                        </div>
                        <input type="text" class="form-control" id="b_sku" placeholder="Nhập SKU dùng chung..." disabled>
                    </div>

                    <!-- Kích thước chung -->
                    <div class="col-md-6">
                        <div class="form-check mb-2">
                            <input class="form-check-input bulk-toggle" type="checkbox" id="toggle_b_dims" data-target="b_length,b_width,b_height">
                            <label class="form-check-label fw-bold small text-uppercase" for="toggle_b_dims">6. Chọn kích thước chung</label>
                        </div>
                        <div class="input-group">
                            <input type="number" class="form-control" id="b_length" placeholder="D" title="Dài" disabled>
                            <input type="number" class="form-control" id="b_width" placeholder="R" title="Rộng" disabled>
                            <input type="number" class="form-control" id="b_height" placeholder="C" title="Cao" disabled>
                            <span class="input-group-text">cm</span>
                        </div>
                    </div>

                    <!-- Trạng thái chung -->
                    <div class="col-md-6">
                        <div class="form-check mb-2">
                            <input class="form-check-input bulk-toggle" type="checkbox" id="toggle_b_status" data-target="b_status">
                            <label class="form-check-label fw-bold small text-uppercase" for="toggle_b_status">7. Chọn trạng thái chung</label>
                        </div>
                        <select class="form-select" id="b_status" disabled>
                            <option value="1">Bật (Mở bán)</option>
                            <option value="0">Tắt (Tạm ẩn)</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light border-top-0">
                <button type="button" class="btn btn-light border px-4" data-bs-dismiss="modal">Đóng</button>
                <button type="button" class="btn btn-warning fw-bold px-4" id="btnSaveBulkEdit">LƯU THAY ĐỔI</button>
            </div>
        </div>
    </div>
</div>

<script src="<?= $_TinyMceUrl ?>" referrerpolicy="origin"></script>
<?php $mceToolbarVer = @filemtime(__DIR__ . '/../../assets/js/mce-toolbar.js') ?: time(); ?>
<script src="<?= h($baseUrl) ?>/assets/js/mce-toolbar.js?v=<?= (int)$mceToolbarVer ?>"></script>
<script>
const fmtNum = e => { let v=e.value.replace(/\D/g,''); e.value=v?new Intl.NumberFormat('vi-VN').format(v):''; }
const BASE_URL = '<?= h($baseUrl) ?>';
const API_URL = `${BASE_URL}/core_admin/ecommerce/product.php`;
const PARTNER_API_URL = `${BASE_URL}/core_admin/ajax/partner.php`;
const PRODUCT_ID = <?= (int)$pid ?>;
const REGION_OPTIONS = <?= json_encode(array_values($regionOptions), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?> || [];
let catsData = [];
let partnerOptions = [];
let variantGroups = [];
let mediaGallery = [];
let bulkPriceTiers = [];
let mainImageUrl = '';


let mainImageRatio = '1:1';
let preorderEnabled = false;
let shippingMethods = [];
let variantsData = [];
let activeVariantFilter = 'all';
let variantSearchTerm = '';
let priceFilter = 'all';
let statusFilter = 'all';
let stockFilter = 'all';
let imageFilter = 'all';
let variantSortMode = 'id_desc';
let editingVariantId = 0;
let variantQuickEditInstance = null;
let variantBulkEditInstance = null;
let variantAddModalInstance = null;
let variantGroupModalInstance = null;

let descEditorReady = false;
let descEditorInitializing = false;
let pendingDescHtml = null;
let descEditorInitAttempts = 0;
let coatingRows = [];
let editingCoatingId = 0;
let pendingCoatingProductId = 0;
let constructionData = null;
let constructionEditorReady = false;
let isFormDirty = false;
let constructionEditorInitializing = false;
let pendingConstructionHtml = null;

const GHN_SERVICE_OPTIONS = [
    { key: 'ghn_nhanh', label: 'Nhanh', note: 'Ưu tiên giao nhanh nếu tuyến có hỗ trợ.' },
    { key: 'ghn_tiet_kiem', label: 'Tiết kiệm', note: 'Tối ưu chi phí vận chuyển cho tuyến thường.' },
    { key: 'ghn_hoa_toc', label: 'Hỏa tốc', note: 'Giao gấp dịch vụ.' },
    { key: 'tieu_chuan', label: 'Tiêu chuẩn', note: 'Phí cố định toàn quốc.' }
];

const normalizeShippingMethods = (rawList) => {
    const source = Array.isArray(rawList) ? rawList : [];
    const map = new Map();
    const legacyMap = {
        nhanh: 'ghn_nhanh',
        cong_kenh: 'ghn_tiet_kiem',
        hoa_toc: 'ghn_hoa_toc',
        tieu_chuan: 'tieu_chuan'
    };
    source.forEach(item => {
        let key = '';
        let active = true;
        if (typeof item === 'string') {
            key = String(item || '').trim().toLowerCase();
        } else if (item && typeof item === 'object') {
            key = String(item.key || '').trim().toLowerCase();
            if (Object.prototype.hasOwnProperty.call(item, 'active')) {
                active = !!item.active;
            }
        }
        if (legacyMap[key]) key = legacyMap[key];
        if (!GHN_SERVICE_OPTIONS.find(opt => opt.key === key)) return;
        map.set(key, {
            key,
            label: String((item && item.label) || GHN_SERVICE_OPTIONS.find(opt => opt.key === key)?.label || key),
            active
        });
    });

    const hasExplicitSelection = map.size > 0;

    return GHN_SERVICE_OPTIONS.map(opt => {
        if (map.has(opt.key)) return map.get(opt.key);
        return {
            key: opt.key,
            label: opt.label,
            active: !hasExplicitSelection
        };
    });
};

const renderShippingMethodsEditor = () => {
    const $wrap = $('#shippingMethodsEditor');
    if (!$wrap.length) return;
    if (!Array.isArray(shippingMethods) || !shippingMethods.length) {
        shippingMethods = normalizeShippingMethods([]);
    }
    const html = shippingMethods.map((method, idx) => {
        const activeClass = method.active ? 'active' : '';
        const option = GHN_SERVICE_OPTIONS.find(opt => opt.key === method.key);
        const note = String(option?.note || method.note || 'Dùng để map dịch vụ GHN khi tính phí.');
        return `
            <label class="me-2 ghn-service-card ${activeClass}" data-idx="${idx}" for="ship_active_${idx}">
                <input class="form-check-input ship-method-active" type="checkbox" id="ship_active_${idx}" ${method.active ? 'checked' : ''}>
                <div class="ghn-service-meta">
                    <div class="ghn-service-name">${escapeHtml(method.label || method.key)}</div>
                    <div class="ghn-service-note">${escapeHtml(note)}</div>
                </div>
            </label>
        `;
    }).join('');
    $wrap.html(html);
};

const collectShippingMethodsFromEditor = () => {
    const result = [];
    $('#shippingMethodsEditor .ghn-service-card').each(function(){
        const $card = $(this);
        const idx = Number($card.data('idx'));
        const base = shippingMethods[idx] || {};
        const key = String(base.key || '').trim().toLowerCase();
        if (!key || !GHN_SERVICE_OPTIONS.find(opt => opt.key === key)) return;
        const active = $card.find('.ship-method-active').is(':checked');
        const option = GHN_SERVICE_OPTIONS.find(opt => opt.key === key);

        result.push({
            key,
            label: String(option?.label || base.label || key),
            active
        });
    });
    return normalizeShippingMethods(result);
};

const clampNumber = (value, fallback, min = 0) => {
    const num = Number(value);
    if (!Number.isFinite(num) || num < min) return fallback;
    return num;
};

const normalizeRegionSelection = (raw) => {
    let input = raw;
    if (typeof input === 'string') {
        const txt = input.trim();
        if (txt.startsWith('[')) {
            try {
                const parsed = JSON.parse(txt);
                input = Array.isArray(parsed) ? parsed : txt;
            } catch (err) {
                input = txt;
            }
        } else {
            input = txt;
        }
    }

    let values = [];
    if (Array.isArray(input)) {
        values = input.map(x => String(x || '').trim()).filter(Boolean);
    } else {
        const txt = String(input || '').trim();
        values = txt ? txt.split(',').map(x => String(x || '').trim()).filter(Boolean) : [];
    }

    const upperValues = values.map(v => v.toUpperCase());
    if (!values.length || upperValues.includes('ALL') || upperValues.includes('TOÀN QUỐC') || upperValues.includes('TẤT CẢ')) {
        return ['ALL'];
    }

    const picked = [];
    REGION_OPTIONS.forEach(region => {
        if (values.includes(region)) picked.push(region);
    });

    if (!picked.length || picked.length >= REGION_OPTIONS.length) {
        return ['ALL'];
    }
    return picked;
};

const applyRegionSelection = (raw) => {
    const selected = normalizeRegionSelection(raw);
    const allSelected = selected.includes('ALL');
    $('#p_region_all').prop('checked', allSelected);
    $('.p-region-item').each(function(){
        const val = String($(this).val() || '');
        $(this).prop('checked', allSelected || selected.includes(val));
    });
};

const collectRegionSelection = () => {
    const $allCb = $('#p_region_all')[0];
    const allChecked = $allCb && $allCb.checked && !$allCb.indeterminate;
    if (allChecked) return ['ALL'];
    const picked = $('.p-region-item:checked').map(function(){
        return String($(this).val() || '').trim();
    }).get().filter(Boolean);
    if (picked.length >= REGION_OPTIONS.length) return ['ALL'];
    return picked;
};

const PAINT_POSITION_CODES = ['wall','door','ceiling','trim','window','floor','roof','other','all'];
const PAINT_NEED_CODES = ['living_room','kids_room','bathroom','bedroom','kitchen','facade','waterproof','anti_mold','dust_resistant','alkali_resistant','uv_resistant','rust_resistant','crack_resistant','all'];

const parseSelectionArray = (raw) => {
    if (raw === null || raw === undefined || raw === '') return ['all'];
    if (Array.isArray(raw)) {
        return raw.map(v => String(v || '').trim().toLowerCase()).filter(Boolean);
    }
    const txt = String(raw).trim();
    if (!txt) return ['all'];
    if (txt.startsWith('[')) {
        try {
            const parsed = JSON.parse(txt);
            if (Array.isArray(parsed)) {
                return parsed.map(v => String(v || '').trim().toLowerCase()).filter(Boolean);
            }
        } catch (e) {}
    }
    return txt.split(',').map(v => v.trim().toLowerCase()).filter(Boolean);
};

const applySelection = (raw, allId, itemClass, codes) => {
    const values = parseSelectionArray(raw);
    const hasAll = values.includes('all') || values.length >= (codes.length - 1);
    $(allId).prop('checked', hasAll);
    $(itemClass).each(function(){
        const val = String($(this).val() || '').toLowerCase();
        $(this).prop('checked', hasAll || values.includes(val));
    });
};

const collectSelection = (allId, itemClass, codes) => {
    const allChecked = $(allId).is(':checked');
    if (allChecked) return ['all'];
    const picked = $(`${itemClass}:checked`).map(function(){
        return String($(this).val() || '').toLowerCase();
    }).get().filter(Boolean);
    if (picked.length >= (codes.length - 1)) return ['all'];
    return picked;
};

// Khối lượng gốc: lưu dạng "<số> <đơn vị>" (vd "18 kg"), đơn vị có thể trống = không xác định
const STOCK_UNITS = ['kg', 'g', 'gr', 'L', 'ml'];
const applyStockQuantily = (raw) => {
    const txt = String(raw ?? '').trim();
    const m = txt.match(/^([\d.,]+)\s*(.*)$/);
    let num = '', unit = '';
    if (m) {
        num = m[1].replace(',', '.');
        const u = (m[2] || '').trim();
        // Khớp đơn vị không phân biệt hoa thường; "l"/"lit"/"lít" -> "L"
        const found = STOCK_UNITS.find(x => x.toLowerCase() === u.toLowerCase());
        if (found) unit = found;
        else if (/^l(it|ít)?$/i.test(u)) unit = 'L';
        else if (/^gram$/i.test(u)) unit = 'g';
    } else if (txt) {
        num = txt; // chuỗi không parse được: đổ vào ô số để không mất dữ liệu
    }
    $('#p_stock_quantily').val(num);
    $('#p_stock_unit').val(unit);
};
const collectStockQuantily = () => {
    const num = String($('#p_stock_quantily').val() || '').trim();
    const unit = String($('#p_stock_unit').val() || '').trim();
    if (!num) return '';
    return unit ? (num + ' ' + unit) : num;
};

const applyPaintPositionsSelection = (raw) => applySelection(raw, '#p_paint_pos_all', '.p-paint-pos-item', PAINT_POSITION_CODES);
const applyPaintNeedsSelection = (raw) => applySelection(raw, '#p_paint_need_all', '.p-paint-need-item', PAINT_NEED_CODES);
const collectPaintPositions = () => collectSelection('#p_paint_pos_all', '.p-paint-pos-item', PAINT_POSITION_CODES);
const collectPaintNeeds = () => collectSelection('#p_paint_need_all', '.p-paint-need-item', PAINT_NEED_CODES);

const safeParseArray = (value) => {
    if (Array.isArray(value)) return value;
    const parsed = tryParseJson(value);
    return Array.isArray(parsed) ? parsed : [];
};

const normalizeHex = (value) => {
    if(!value) return '';
    let hex = value.trim();
    if(!hex) return '';
    if(hex[0] !== '#') hex = '#' + hex;
    if(hex.length === 4) {
        hex = '#' + hex[1] + hex[1] + hex[2] + hex[2] + hex[3] + hex[3];
    }
    return /^#([0-9A-Fa-f]{6})$/.test(hex) ? hex.toUpperCase() : '';
};

const ESCAPE_MAP = {"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#39;"};
const escapeHtml = (str = '') => str.replace(/[&<>"']/g, ch => ESCAPE_MAP[ch] || ch);

const tryParseJson = (raw) => {
    if (raw === null || raw === undefined) return null;
    const txt = String(raw).trim();
    if (!txt) return null;
    if (!(txt.startsWith('{') || txt.startsWith('['))) return null;
    try {
        return JSON.parse(txt);
    } catch (e) {
        return null;
    }
};

const setMceContentHelper = (editorId, html, getPending, setPending) => {
    const content = String(html || '');
    setPending(content);
    $(`#${editorId}`).val(content);
    
    const trySet = () => {
        if (getPending() === null) return true;
        const editor = window.tinymce && typeof window.tinymce.get === 'function'
            ? window.tinymce.get(editorId)
            : null;
        if (editor && editor.initialized && typeof editor.setContent === 'function') {
            editor.setContent(content);
            setPending(null);
            return true;
        }
        return false;
    };

    if (!trySet()) {
        let attempts = 0;
        const interval = setInterval(() => {
            attempts++;
            if (trySet() || attempts > 10) clearInterval(interval);
        }, 300);
    }
};

const syncMceContentHelper = (editorId) => {
    const editor = window.tinymce && typeof window.tinymce.get === 'function'
        ? window.tinymce.get(editorId)
        : null;
    if (editor && editor.initialized) {
        $(`#${editorId}`).val(editor.getContent() || '');
    }
};

const syncDescEditorToTextarea = () => syncMceContentHelper('p_description');

const setDescEditorHtml = (html = '') => setMceContentHelper('p_description', html, () => pendingDescHtml, (v) => pendingDescHtml = v);







const sanitizeMediaGallery = (list) => {
    const sanitized = [];
    list.forEach(item => {
        if(!item || !item.url) return;
        const url = String(item.url).trim();
        if(!url) return;
        sanitized.push({
            type: item.type === 'video' ? 'video' : 'image',
            url,
            caption: item.caption ? String(item.caption).trim() : ''
        });
    });
    return sanitized;
};

const sanitizeBulkPriceTiers = (list) => {
    if (!Array.isArray(list)) return [];
    const clean = [];
    list.forEach((item, idx) => {
        if (!item || typeof item !== 'object') return;
        const fromQty = Math.max(1, Number(item.from_qty || item.from || 0));
        const toRaw = Number(item.to_qty || item.to || 0);
        const toQty = toRaw >= fromQty ? toRaw : fromQty;
        const unitPrice = Math.max(0, Number(String(item.unit_price ?? item.price ?? '0').replace(/\D/g, '')));
        if (!unitPrice) return;
        clean.push({
            name: String(item.name || `KHOẢNG GIÁ ${idx + 1}`),
            from_qty: fromQty,
            to_qty: toQty,
            unit_price: unitPrice
        });
    });
    return clean;
};

const renderBulkPriceTiers = () => {
    if (!bulkPriceTiers.length) {
        $('#bulkTierTbody').html('<tr><td colspan="5" class="text-center text-muted">Chưa có khoảng giá.</td></tr>');
        return;
    }
    let html = '';
    bulkPriceTiers.forEach((tier, idx) => {
        html += `<tr>
            <td>${escapeHtml(String(tier.name || `KHOẢNG GIÁ ${idx + 1}`))}</td>
            <td class="text-center">${Number(tier.from_qty || 0)}</td>
            <td class="text-center">${Number(tier.to_qty || 0)}</td>
            <td class="text-end fw-bold text-success">${new Intl.NumberFormat('vi-VN').format(Number(tier.unit_price || 0))} đ</td>
            <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeBulkTier(${idx})"><i class="bi bi-trash"></i></button></td>
        </tr>`;
    });
    $('#bulkTierTbody').html(html);
};

const resolveUrl = (url = '') => {
    const u = String(url || '').trim();
    if(!u) return '';
    if(/^https?:\/\//i.test(u)) return u;
    // Ưu tiên media domain (toMediaUrl từ head.php) cho file trong thư mục upload.
    if (typeof window.toMediaUrl === 'function') return window.toMediaUrl(u);
    const prefix = BASE_URL || '';
    if(u.startsWith('/')) return prefix + u;
    return `${prefix}/${u}`;
};



const renderMainImage = () => {
    const wrap = document.getElementById('mainImagePreviewWrapper');
    const placeholder = document.getElementById('mainImagePlaceholder');
    if(!wrap) return;
    const ratio = (String($('input[name="main_image_ratio"]:checked').val() || mainImageRatio || '1:1') === '3:4') ? '3 / 4' : '1 / 1';
    if(mainImageUrl){
        wrap.innerHTML = `<img src="${escapeHtml(resolveUrl(mainImageUrl))}" alt="Ảnh đại diện" class="avatar-img" style="aspect-ratio:${ratio};">`;
    } else if(placeholder) {
        const node = document.createElement('div');
        node.className = 'avatar-placeholder';
        node.id = 'mainImagePlaceholder';
        node.style.aspectRatio = ratio;
        node.textContent = 'Chưa có ảnh';
        wrap.innerHTML = '';
        wrap.appendChild(node);
    }
};

const renderMediaGallery = () => {
    if(!mediaGallery.length) {
        document.getElementById('mediaList').innerHTML = '<div class="color-empty">Chưa thêm ảnh/video mô tả.</div>';
        return;
    }
    let cards = '';
    mediaGallery.forEach((item, idx) => {
        const isVideo = item.type === 'video';
        const fullUrl = resolveUrl(item.url);
        const safeUrl = escapeHtml(fullUrl);
        const thumbInner = isVideo
            ? `<video src="${safeUrl}" muted preload="metadata"></video>`
            : `<img src="${safeUrl}" alt="media">`;
        const typeLabel = isVideo ? 'Video' : 'Ảnh';
        const typeIcon = isVideo ? 'bi-play-circle' : 'bi-image';
        cards += `
            <div class="media-card" draggable="true" data-idx="${idx}">
                <div class="media-thumb">
                    <a href="${safeUrl}" target="_blank" rel="noopener">${thumbInner}</a>
                    <span class="media-type-pill"><i class="bi ${typeIcon}"></i>${typeLabel}</span>
                    <button type="button" class="media-delete-btn" onclick="removeMediaItem(${idx})" title="Xóa"><i class="bi bi-x"></i></button>
                </div>
            </div>`;
    });

    document.getElementById('mediaList').innerHTML = `
        <div class="media-strip-wrapper">
            <button type="button" class="btn btn-light border media-scroll-btn" id="mediaPrevBtn" aria-label="Ảnh trước"><i class="bi bi-chevron-left"></i></button>
            <div class="media-strip" id="mediaStrip">${cards}</div>
            <button type="button" class="btn btn-light border media-scroll-btn" id="mediaNextBtn" aria-label="Ảnh tiếp theo"><i class="bi bi-chevron-right"></i></button>
        </div>`;

    initMediaStripControls();
    initMediaDragAndDrop();
};

let mediaDragSrcIdx = null;
const initMediaDragAndDrop = () => {
    const strip = document.getElementById('mediaStrip');
    if (!strip) return;

    Array.from(strip.querySelectorAll('.media-card')).forEach(card => {
        card.addEventListener('dragstart', e => {
            mediaDragSrcIdx = Number(card.getAttribute('data-idx'));
            card.classList.add('dragging');
            if (e.dataTransfer) {
                e.dataTransfer.effectAllowed = 'move';
                try { e.dataTransfer.setData('text/plain', String(mediaDragSrcIdx)); } catch (err) {}
            }
        });
        card.addEventListener('dragover', e => {
            e.preventDefault();
            card.classList.add('table-warning');
        });
        card.addEventListener('dragleave', () => {
            card.classList.remove('table-warning');
        });
        card.addEventListener('drop', e => {
            e.preventDefault();
            card.classList.remove('table-warning');
            const targetIdx = Number(card.getAttribute('data-idx'));
            const from = mediaDragSrcIdx;
            const to = targetIdx;
            mediaDragSrcIdx = null;
            if (!Number.isFinite(from) || !Number.isFinite(to) || from === to) return;
            if (from < 0 || from >= mediaGallery.length || to < 0 || to >= mediaGallery.length) return;
            const moved = mediaGallery.splice(from, 1)[0];
            mediaGallery.splice(to, 0, moved);
            renderMediaGallery();
        });
        card.addEventListener('dragend', () => {
            card.classList.remove('dragging');
        });
    });
};

const initMediaStripControls = () => {
    const strip = document.getElementById('mediaStrip');
    const prevBtn = document.getElementById('mediaPrevBtn');
    const nextBtn = document.getElementById('mediaNextBtn');
    if (!strip || !prevBtn || !nextBtn) return;

    const getStep = () => Math.max(strip.clientWidth * 0.8, 200);

    prevBtn.onclick = () => {
        strip.scrollBy({ left: -getStep(), behavior: 'smooth' });
    };
    nextBtn.onclick = () => {
        strip.scrollBy({ left: getStep(), behavior: 'smooth' });
    };
};

const renderCoatingSystemTable = () => {
    const $tbody = $('#coatingSystemTbody');
    if (!$tbody.length) return;
    if (!PRODUCT_ID && (!Array.isArray(coatingRows) || !coatingRows.length)) {
        $tbody.html('<tr><td colspan="5" class="text-center text-muted">Chưa có hệ thống sơn nào được thêm.</td></tr>');
        return;
    }
    if (PRODUCT_ID && (!Array.isArray(coatingRows) || !coatingRows.length)) {
        $tbody.html('<tr><td colspan="5" class="text-center text-muted">Chưa có hệ thống sơn nào cho sản phẩm này.</td></tr>');
        return;
    }
    let html = '';
    coatingRows.forEach((row, idx) => {
        const id = Number(row.id || 0);
        const name = String(row.suggest_product_name || row.category_name || '').trim() || '—';
        const type = String(row.layer_type || '').trim() || '—';
        const count = Number(row.layer_count || 0) || 0;
        html += `<tr>
            <td class="text-center">${id || (idx + 1)}</td>
            <td>${escapeHtml(name)}</td>
            <td>${escapeHtml(type)}</td>
            <td class="text-center">${count > 0 ? count : ''}</td>
            <td class="text-center">
                <button type="button" class="btn btn-xs btn-outline-primary me-1" onclick="editCoatingRow(${id})"><i class="bi bi-pencil"></i></button>
                <button type="button" class="btn btn-xs btn-outline-danger" onclick="deleteCoatingRow(${id})"><i class="bi bi-trash"></i></button>
            </td>
        </tr>`;
    });
    $tbody.html(html);
};

const loadCoatingSystem = () => {
    if (!PRODUCT_ID) {
        renderCoatingSystemTable();
        return;
    }
    $.get(API_URL + '?ajax=product_coating&pid=' + PRODUCT_ID, res => {
        if (res && res.ok) {
            coatingRows = Array.isArray(res.rows) ? res.rows : [];
        } else {
            coatingRows = [];
        }
        renderCoatingSystemTable();
    }, 'json').fail(() => {
        coatingRows = [];
        renderCoatingSystemTable();
    });
};

const loadCats = () => {
    return $.get(API_URL + '?ajax=categories', res => {
        catsData = Array.isArray(res.data) ? res.data : [];
        // Chỉ cho phép chọn danh mục đang bật (status truthy) ở màn hình chỉnh sửa sản phẩm
        const activeCats = catsData.filter(c => {
            const s = String(c.status ?? '').toLowerCase().trim();
            return s === '1' || s === 'true' || s === 'on' || s === 'yes' || s === 'active' || s === 'enabled';
        });
        let select = '<option value="0">-- Chọn danh mục --</option>';
        activeCats.forEach(c => { select += `<option value="${c.id}">${c.name}</option>`; });
        $('#p_cat').html(select);
        // đồng bộ danh mục cho modal hệ thống sơn nếu có
        $('#cs_category').html(select);
    }, 'json');
};

const renderPartnerOptions = (selected = '') => {
    const picked = String(selected || '').trim();
    const exists = partnerOptions.some(name => name === picked);
    let options = '<option value="">-- Chọn hãng sản xuất --</option>';
    partnerOptions.forEach(name => {
        options += `<option value="${escapeHtml(name)}">${escapeHtml(name)}</option>`;
    });
    if (picked && !exists) {
        options += `<option value="${escapeHtml(picked)}">${escapeHtml(picked)}</option>`;
    }
    $('#p_brand').html(options).val(picked);
};

const loadPartners = () => {
    return $.get(`${PARTNER_API_URL}?action=list`, res => {
        const rows = Array.isArray(res?.rows) ? res.rows : [];
        const names = rows
            .filter(item => Number(item?.is_active || 0) === 1)
            .map(item => String(item?.partner_name || '').trim())
            .filter(Boolean);
        partnerOptions = [...new Set(names)];
        renderPartnerOptions($('#p_brand').val() || '');
    }, 'json').fail(() => {
        partnerOptions = [];
        renderPartnerOptions($('#p_brand').val() || '');
    });
};

const syncVatEnabledUi = () => {
    const enabled = $('#p_vat_enabled').is(':checked');
    $('#p_vat').prop('disabled', !enabled);
};

const updateQuickStatusLabel = (isActive) => {
    const $btn = $('#p_status_quick_toggle');
    const $label = $('#p_status_quick_label');
    const $icon = $('#p_status_quick_icon');
    
    $btn.attr('data-active', isActive ? 'true' : 'false');
    
    if (isActive) {
        $btn.removeClass('btn-outline-secondary btn-light').addClass('btn-primary text-white');
        $label.text('BẬT');
        $icon.attr('class', 'bi bi-toggle-on me-1');
    } else {
        $btn.removeClass('btn-primary text-white btn-light').addClass('btn-outline-secondary');
        $label.text('TẮT');
        $icon.attr('class', 'bi bi-toggle-off me-1');
    }
};

const loadProduct = () => {
    if(!PRODUCT_ID) return;
    $.get(API_URL + '?ajax=product_detail&pid=' + PRODUCT_ID, res => {
        if(!res || !res.ok) { toastr.error(res?.msg || 'Không tải được sản phẩm'); return; }
        const product = res.product;
        $('#p_name').val(product.product_name || '');
        if (window.pmBuildProductUrl) {
            $('#btnViewProductStorefront').attr('href', window.pmBuildProductUrl(PRODUCT_ID, product.product_name || ''));
        }
        const slugFromApi = String(product.slug || '').trim();
        $('#p_slug').val(slugFromApi || (typeof window.pmSlugify === 'function' ? window.pmSlugify(product.product_name || '') : ''));
        $('#p_sku').val(product.sku || '');
        $('#p_cat').val(product.category_id || 0);
        renderPartnerOptions(product.manufacturer || '');
        const vatEnabled = Number(product.vat_enabled ?? 1) === 1;
        $('#p_vat_enabled').prop('checked', vatEnabled);
        $('#p_vat').val(Math.min(100, clampNumber(product.vat, 8, 0)));
        syncVatEnabledUi();
        applyRegionSelection(product.region_scope || product.khu_vuc_giao_hang || product.khu_vuc_ap_dung || 'ALL');
        ['resin_type', 'voc', 'solid_content', 'coverage', 'gloss_level', 'drying_time', 'applications', 'key_features', 'storage'].forEach(f => {
            $(`#p_${f}`).val(product[f] || '');
        });
        applyStockQuantily(product.stock_quantily);
        $('#p_paint_space').val(String(product.paint_space || '').trim());
        applyPaintPositionsSelection(product.paint_positions);
        applyPaintNeedsSelection(product.paint_needs);
        mainImageRatio = (String(product.image_ratio || '1:1').trim() === '3:4') ? '3:4' : '1:1';
        preorderEnabled = Number(product.preorder_enabled || 0) === 1;
        $('input[name="main_image_ratio"][value="' + mainImageRatio + '"]').prop('checked', true);
        $('input[name="preorder_mode"][value="' + (preorderEnabled ? '1' : '0') + '"]').prop('checked', true);

        // Quick status toggle initialization
        const isProductActive = !(String(product.status || 'true').toLowerCase() === 'false' || String(product.status) === '0');
        updateQuickStatusLabel(isProductActive);


        mainImageUrl = resolveUrl(product.img || product.image || product.avatar || product.image_url || product.anh_daidien || product.anh || '');

        try {
            setDescEditorHtml(product.description || '');
        } catch (error) {
            //console.error('Set TinyMCE content failed:', error);
            $('#p_description').val(String(product.description || ''));
            pendingDescHtml = String(product.description || '');
        }


        mediaGallery = sanitizeMediaGallery(safeParseArray(product.media_gallery || []));
        bulkPriceTiers = sanitizeBulkPriceTiers(safeParseArray(product.bulk_price_tiers || []));
        shippingMethods = normalizeShippingMethods(safeParseArray(product.shipping_methods || []));
        // Không còn cấu hình bảng màu chung trong UI nên không cần bật chế độ màu hay render danh sách
        renderMediaGallery();
        // Bảng giá và phương thức vận chuyển vẫn giữ được hiển thị chung nên cứ render bình thường
        renderBulkPriceTiers();
        // Phương thức vận chuyển có thể ảnh hưởng đến một số logic khác nên cứ render dù UI mới đã tách riêng, sau này nếu có chỉnh sửa gì liên quan đến vận chuyển thì sẽ dễ dàng hơn
        renderShippingMethodsEditor();

        loadCoatingSystem();
        loadConstruction();

        renderMainImage();
        variantGroups = Array.isArray(res.groups) ? res.groups : [];
        renderVariantGroups();
        updateGroupSelects();
        loadVariants();
    }, 'json');
};

const loadVariantGroups = () => {
    if(!PRODUCT_ID) return;
    $.get(API_URL + '?ajax=get_variant_groups&pid=' + PRODUCT_ID, res => {
        variantGroups = Array.isArray(res?.data) ? res.data : [];
        renderVariantGroups();
        updateGroupSelects();
    }, 'json');
};

const renderVariantGroups = () => {
    let h = '';
    if(!variantGroups.length) {
        h = '<div class="text-muted small w-100 text-center py-2">Chưa có nhóm phân loại nào. Hãy thêm nhóm như "Màu sắc", "Kích thước"...</div>';
    } else {
        const counts = {};
        (variantsData || []).forEach(v => {
            const gid = String(v.group_id || 0);
            counts[gid] = (counts[gid] || 0) + 1;
        });

        variantGroups.forEach((g, idx) => {
            const statusBadge = g.status == 1 ? '<span class="badge bg-success-subtle text-success ms-1">Bật</span>' : '<span class="badge bg-secondary-subtle text-secondary ms-1">Tắt</span>';
            const count = counts[String(g.id)] || 0;
            h += `
                <div class="d-flex align-items-center bg-white border rounded-2 px-3 py-2 shadow-sm group-item-card" 
                     style="min-width: 200px; cursor: grab;" 
                     draggable="true"
                     data-idx="${idx}"
                     data-id="${g.id}">
                    <i class="bi bi-grip-vertical text-muted me-2 fs-5"></i>
                    <div class="me-auto">
                        <div class="fw-bold text-dark small text-uppercase">${escapeHtml(g.name)} ${statusBadge} <span class="badge bg-light text-dark border ms-1">${count}</span></div>
                    </div>
                    <div class="ms-3 d-flex gap-1">
                        <button type="button" class="btn btn-xs btn-outline-primary" onclick="openVariantGroupModal(${g.id})" title="Sửa nhóm"><i class="bi bi-pencil"></i></button>
                        <button type="button" class="btn btn-xs btn-outline-danger" onclick="delVariantGroup(${g.id})" title="Xóa nhóm"><i class="bi bi-trash"></i></button>
                    </div>
                </div>`;
        });
    }
    $('#variantGroupsContainer').html(h);
    initVariantGroupDragAndDrop();
    renderGroupFilters();
};

let groupDragSrcIdx = null;
const initVariantGroupDragAndDrop = () => {
    const container = document.getElementById('variantGroupsContainer');
    if (!container) return;

    const cards = container.querySelectorAll('.group-item-card');
    cards.forEach(card => {
        card.addEventListener('dragstart', e => {
            groupDragSrcIdx = Number(card.getAttribute('data-idx'));
            card.classList.add('dragging');
            if (e.dataTransfer) {
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', String(groupDragSrcIdx));
            }
        });
        card.addEventListener('dragover', e => {
            e.preventDefault();
            card.classList.add('drag-over');
        });
        card.addEventListener('dragleave', () => {
            card.classList.remove('drag-over');
        });
        card.addEventListener('drop', e => {
            e.preventDefault();
            card.classList.remove('drag-over');
            const targetIdx = Number(card.getAttribute('data-idx'));
            const from = groupDragSrcIdx;
            const to = targetIdx;
            groupDragSrcIdx = null;
            
            if (from === null || from === to) return;
            
            const moved = variantGroups.splice(from, 1)[0];
            variantGroups.splice(to, 0, moved);
            
            renderVariantGroups();
            saveVariantGroupsOrder();
        });
        card.addEventListener('dragend', () => {
            card.classList.remove('dragging');
        });
    });
};

const saveVariantGroupsOrder = () => {
    if (!variantGroups.length) return;
    
    variantGroups.forEach((g, index) => {
        g.sort_order = (index + 1) * 10;
    });

    if (!PRODUCT_ID) {
        updateGroupSelects();
        toastr.success('Đã cập nhật thứ tự nhóm tạm thời');
        return;
    }
    
    const orders = variantGroups.map((g, index) => ({
        id: g.id,
        sort_order: g.sort_order
    }));

    $.post(API_URL, {
        action: 'sort_variant_groups',
        product_id: PRODUCT_ID,
        orders: orders
    }, res => {
        if (res.ok) {
            toastr.success('Đã cập nhật thứ tự nhóm');
            updateGroupSelects(); // Cập nhật lại dropdowns
        } else {
            toastr.error('Lỗi cập nhật thứ tự');
        }
    }, 'json');
};

const renderGroupFilters = () => {
    const counts = { all: (variantsData || []).length };
    (variantsData || []).forEach(v => {
        const gid = String(v.group_id || 0);
        counts[gid] = (counts[gid] || 0) + 1;
    });

    let h = `
        <div class="filter-card ${activeVariantFilter === 'all' ? 'active' : ''}" onclick="setVariantFilter('all')">
            <div class="filter-icon"><i class="bi bi-grid-fill"></i></div>
            <div class="filter-label">Tất cả <span class="badge bg-secondary-subtle text-secondary ms-1">${counts.all}</span></div>
        </div>
    `;
    variantGroups.forEach(g => {
        const c = counts[String(g.id)] || 0;
        h += `
            <div class="filter-card ${activeVariantFilter == g.id ? 'active' : ''}" onclick="setVariantFilter(${g.id})">
                <div class="filter-icon"><i class="bi bi-folder2-fill"></i></div>
                <div class="filter-label">${escapeHtml(g.name)} <span class="badge bg-secondary-subtle text-secondary ms-1">${c}</span></div>
            </div>
        `;
    });
    $('#variantGroupFilters').html(h);
};

window.setVariantFilter = (id) => {
    activeVariantFilter = id;
    renderGroupFilters();
    renderVariants();
};

const updateGroupSelects = () => {
    let opts = '<option value="0">-- Chọn nhóm --</option>';
    variantGroups.forEach(g => {
        opts += `<option value="${g.id}">${escapeHtml(g.name)}</option>`;
    });
    $('#a_v_group_id, #q_v_group_id, #quick_v_group_id, #bulk_group_id').html(opts);
};


const openVariantGroupModal = (id = 0) => {
    const g = variantGroups.find(x => x.id == id) || { id: 0, name: '', slug: '', status: 1, sort_order: 0 };
    ['id', 'name', 'slug', 'status', 'sort'].forEach(f => $(`#vg_${f}`).val(g[f === 'sort' ? 'sort_order' : f]));
    $('#variantGroupModalTitle').text(id ? 'Sửa nhóm phân loại' : 'Thêm nhóm phân loại');
    variantGroupModalInstance ??= new bootstrap.Modal(document.getElementById('variantGroupModal'));
    variantGroupModalInstance.show();
};

const saveVariantGroup = () => {
    const name = $('#vg_name').val();
    const slug = $('#vg_slug').val();
    const status = $('#vg_status').val();
    const sort_order = $('#vg_sort').val();
    if(!name) { toastr.warning('Vui lòng nhập tên nhóm'); return; }

    if (!PRODUCT_ID) {
        const id = $('#vg_id').val() ? Number($('#vg_id').val()) : -1 - variantGroups.length;
        const existing = variantGroups.find(g => g.id == id);
        if (existing) {
            existing.name = name;
            existing.slug = slug;
            existing.status = status;
            existing.sort_order = sort_order;
        } else {
            variantGroups.push({ id, name, slug, status, sort_order });
        }
        toastr.success('Đã lưu nhóm phân loại tạm thời');
        if(variantGroupModalInstance) variantGroupModalInstance.hide();
        renderVariantGroups();
        updateGroupSelects();
        return;
    }

    const data = {
        action: 'save_variant_group',
        product_id: PRODUCT_ID,
        id: $('#vg_id').val(),
        name: name,
        slug: slug,
        status: status,
        sort_order: sort_order
    };
    $.post(API_URL, data, res => {
        if(res.ok) {
            toastr.success('Đã lưu nhóm phân loại');
            if(variantGroupModalInstance) variantGroupModalInstance.hide();
            loadVariantGroups();
        } else {
            toastr.error(res.msg || 'Lỗi khi lưu');
        }
    }, 'json');
};

const delVariantGroup = (id) => {
    const group = variantGroups.find(g => g.id == id);
    const gName = group ? String(group.name || 'nhóm này').trim() : 'nhóm này';
    const memberCount = variantsData.filter(v => v.group_id == id).length;
    const memberNote = memberCount > 0 ? `\n(${memberCount} phân loại trong nhóm này sẽ bị gỡ khỏi nhóm.)` : '';
    if (!confirm(`Bạn có chắc chắn muốn xóa nhóm "${gName}"?${memberNote}\nHành động này không thể hoàn tác.`)) return;
    if (!PRODUCT_ID) {
        variantGroups = variantGroups.filter(g => g.id != id);
        variantsData.forEach(v => {
            if (v.group_id == id) v.group_id = 0;
        });
        toastr.success('Đã xóa nhóm tạm thời.');
        renderVariantGroups();
        updateGroupSelects();
        renderVariants();
        return;
    }
    $.post(API_URL, { action: 'del_variant_group', id, product_id: PRODUCT_ID }, res => {
        if (res && res.ok) {
            toastr.success('Đã xóa nhóm.');
            loadVariantGroups();
            loadVariants();
        } else {
            toastr.error(res?.msg || 'Không xóa được nhóm.');
        }
    }, 'json').fail(() => {
        toastr.error('Lỗi kết nối khi xóa nhóm.');
    });
};

const loadVariants = () => {
    if(!PRODUCT_ID) {
        renderVariants();
        return;
    }
    $.get(API_URL + '?ajax=get_variants&pid=' + PRODUCT_ID, res => {
        variantsData = Array.isArray(res?.data) ? res.data : [];
        renderVariants();
    }, 'json');
};

const renderVariants = () => {
    if ($.fn.DataTable.isDataTable('#variantTable')) {
        $('#variantTable').DataTable().clear().destroy();
    }
    let filteredData = [...variantsData];

    // Lọc theo nhóm
    if (activeVariantFilter !== 'all') {
        filteredData = filteredData.filter(v => String(v.group_id) === String(activeVariantFilter));
    }

    // Lọc theo từ khóa tìm kiếm (SKU, Tên, Nhóm)
    if (variantSearchTerm) {
        const s = variantSearchTerm.toLowerCase();
        filteredData = filteredData.filter(v => 
            (v.variant_name || '').toLowerCase().includes(s) || 
            (v.sku_variant || '').toLowerCase().includes(s) || 
            (v.group_name || '').toLowerCase().includes(s)
        );
    }

    // Lọc theo trạng thái giá
    if (priceFilter === 'no_price') {
        filteredData = filteredData.filter(v => !Number(v.price));
    } else if (priceFilter === 'has_price') {
        filteredData = filteredData.filter(v => Number(v.price) > 0);
    }

    // Lọc theo trạng thái bật/tắt
    if (statusFilter === 'active') {
        filteredData = filteredData.filter(v => String(v.status ?? '1') !== '0');
    } else if (statusFilter === 'inactive') {
        filteredData = filteredData.filter(v => String(v.status ?? '1') === '0');
    }

    // Lọc theo tồn kho
    if (stockFilter === 'in_stock') {
        filteredData = filteredData.filter(v => Number(v.stock_quantity || 0) > 0);
    } else if (stockFilter === 'out_of_stock') {
        filteredData = filteredData.filter(v => Number(v.stock_quantity || 0) <= 0);
    }

    // Lọc theo trạng thái ảnh
    if (imageFilter === 'no_image') {
        filteredData = filteredData.filter(v => !(v.image_url || v.variant_image || v.image || v.img));
    } else if (imageFilter === 'has_image') {
        filteredData = filteredData.filter(v => (v.image_url || v.variant_image || v.image || v.img));
    }

    // Sắp xếp dữ liệu
    filteredData.sort((a, b) => {
        switch(variantSortMode) {
            case 'name_asc': return (a.variant_name || '').localeCompare(b.variant_name || '');
            case 'name_desc': return (b.variant_name || '').localeCompare(a.variant_name || '');
            case 'price_asc': return Number(a.price || 0) - Number(b.price || 0);
            case 'price_desc': return Number(b.price || 0) - Number(a.price || 0);
            case 'stock_desc': return Number(b.stock_quantity || 0) - Number(a.stock_quantity || 0);
            case 'stock_asc': return Number(a.stock_quantity || 0) - Number(b.stock_quantity || 0);
            case 'id_asc': return Number(a.id || 0) - Number(b.id || 0);
            case 'id_desc': 
            default: return Number(b.id || 0) - Number(a.id || 0);
        }
    });

    const emptyText = activeVariantFilter === 'all'
        ? 'Chưa có dữ liệu phân loại.'
        : 'Chưa có dữ liệu phân loại cho nhóm này.';
    const emptyHtml = `<div class="text-center text-muted py-4">
        <i class="bi bi-inbox fs-2 d-block mb-2 text-secondary"></i>
        ${emptyText}
    </div>`;

    let h = '';
    if(!filteredData.length) {
        // Leave tbody empty; DataTables will render its own empty-row.
        h = '';
    } else {
        filteredData.forEach(v => {
            const variantCustomUrl = resolveUrl(v.image_url || v.variant_image || v.image || v.img || '');
            const variantShownUrl = variantCustomUrl || resolveUrl(mainImageUrl);
            const variantImage = variantShownUrl
                ? `<img src="${escapeHtml(variantShownUrl)}" alt="Ảnh" style="width:38px;height:38px;border-radius:8px;object-fit:cover;border:1px solid #e2e8f0;">`
                : '<span class="text-muted small">Chưa có</span>';
            const variantImageCell = `<div class="d-flex align-items-center justify-content-center gap-2">
                ${variantImage}
                <button type="button" class="btn btn-xs btn-outline-primary" onclick="pickVariantImage(${Number(v.id || 0)})" title="Tải ảnh riêng cho phân loại">
                    <i class="bi bi-cloud-upload"></i>
                </button>
            </div>`;
            const variantWeight = Number(v.shipping_weight_value || 0);
            const variantUnitRaw = String(v.shipping_weight_unit || 'kg').trim().toLowerCase();
            const variantUnit = ['gram','gr','g','kg','ml','l'].includes(variantUnitRaw) ? variantUnitRaw : 'kg';
            const unitLabel = variantUnit === 'gram' || variantUnit === 'gr' || variantUnit === 'g' ? 'g' : (variantUnit === 'l' ? 'L' : (variantUnit === 'ml' ? 'mL' : 'kg'));
            const variantLength = Math.max(1, Number(v.shipping_length_cm || 20));
            const variantWidth = Math.max(1, Number(v.shipping_width_cm || 20));
            const variantHeight = Math.max(1, Number(v.shipping_height_cm || 20));
            const unitPrice = Number(v.price || 0);
            const unitPriceText = unitPrice > 0 ? unitPrice.toLocaleString('vi-VN') : '';
            const rawStatus = String(v.status ?? '1').toLowerCase();
            const isActiveVariant = !(rawStatus === '0' || rawStatus === 'false');

            h += `<tr>
                <td class="text-center">
                    <input type="checkbox" class="form-check-input variant-checkbox" value="${v.id}">
                </td>
                <td class="text-center">${variantImageCell}</td>

                <td><span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 small text-uppercase">${escapeHtml(v.group_name || 'Chưa chọn')}</span></td>
                <td><input type="text" class="form-control form-control-sm border-0 bg-transparent" value="${escapeHtml(v.variant_name || '')}" onchange="updateVariantField(${v.id}, 'variant_name', this.value)" style="min-width: 120px;"></td>
                <td class="text-end"><input type="text" class="form-control form-control-sm border-0 bg-transparent text-end fw-bold text-success" value="${unitPriceText}" onkeyup="fmtNum(this)" onchange="updateVariantField(${v.id}, 'price', this.value.replace(/\\D/g,''))" style="width: 100px; margin-left: auto;"></td>
                <td class="text-center"><input type="number" class="form-control form-control-sm border-0 bg-transparent text-center" value="${v.stock_quantity || 0}" onchange="updateVariantField(${v.id}, 'stock_quantity', this.value)" style="width: 70px; margin: 0 auto;"></td>
                <td><input type="text" class="form-control form-control-sm border-0 bg-transparent" value="${escapeHtml(v.sku_variant || '')}" onchange="updateVariantField(${v.id}, 'sku_variant', this.value)" style="width: 100px;"></td>
                <td class="text-center">
                    <div class="form-check form-switch d-inline-flex align-items-center justify-content-center p-0 m-0" style="min-height: auto;">
                        <input class="form-check-input m-0" type="checkbox" role="switch" style="cursor: pointer; width: 2.4em; height: 1.2em;" ${isActiveVariant ? 'checked' : ''} onchange="toggleVariantStatus(${Number(v.id || 0)}, ${isActiveVariant ? 0 : 1})">
                    </div>
                    <div class="x-small mt-1 ${isActiveVariant ? 'text-success' : 'text-muted'}" style="font-size: 10px; font-weight: 600;">${isActiveVariant ? 'BẬT' : 'TẮT'}</div>
                </td>
                <td class="text-end">
                    <div class="input-group input-group-sm" style="width: 85px; margin-left: auto;">
                        <input type="number" class="form-control text-end px-1 border-primary-subtle" value="${variantWeight}" step="0.01" min="0" onchange="updateVariantField(${v.id}, 'shipping_weight_value', this.value)">
                        <span class="input-group-text px-1 x-small bg-light text-muted" style="font-size: 10px;">${unitLabel}</span>
                    </div>
                </td>

              
                <td class="text-center"><button type="button" class="btn btn-sm btn-outline-primary" onclick="editVariantQuick(${v.id})"><i class="bi bi-pencil-square"></i></button></td>
                <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger" onclick="delVariant(${v.id})"><i class="bi bi-trash"></i></button></td>
            </tr>`;
        });
    }
    $('#variantTbody').html(h);
    // Initialize DataTable
    $('#variantTable').DataTable({
        paging: true,
        lengthMenu: [5, 10, 25, 50, 100, 200, 300, 500, 1000],
        pageLength: 100,
        //searching: true,
        //ordering: true,
        //info: true,
        order: [],
        autoWidth: false,
        dom: '<"d-flex justify-content-between align-items-center mb-3"l>t<"d-flex justify-content-between align-items-center mt-3"ip>',
        language: {
            info: "Hiển thị _START_ - _END_ / _TOTAL_",
            infoEmpty: "Không có dữ liệu",
            infoFiltered: "(lọc từ _MAX_ mục)",
            lengthMenu: "Hiển thị _MENU_ mục",
            zeroRecords: emptyHtml,
            search: "Tìm kiếm:",
            paginate: {
                first: "Đầu",
                last: "Cuối",
                next: ">",
                previous: "<"
            },
            processing: "Đang xử lý...",
            loadingRecords: "Đang tải...",
            emptyTable: emptyHtml
        },
        columnDefs: [
            { orderable: false, targets: [0, 1, 7, 9, 10] }
        ],
        drawCallback: function() {
            // Re-sync checkboxes and bulk control on draw
            syncBulkEditVisibility();
        }
    });

    $('#checkAllVariants').prop('checked', false).off('change').on('change', function() {
        const isChecked = $(this).is(':checked');
        $('.variant-checkbox').prop('checked', isChecked);
        syncBulkEditVisibility();
    });

    syncBulkEditVisibility();
    renderGroupFilters();
    renderVariantGroups();
};

const syncBulkEditVisibility = () => {
    const selectedIds = getSelectedVariantIds();
    const count = selectedIds.length;
    if (count > 0) {
        $('#bulkEditCount').text(count);
        $('.bulk-count-text').text(count);
        $('#bulkEditControls').removeClass('d-none');
    } else {
        $('#bulkEditControls').addClass('d-none');
    }
};

const getSelectedVariantIds = () => {
    const ids = [];
    $('.variant-checkbox:checked').each(function() {
        ids.push($(this).val());
    });
    return ids;
};

// Khởi tạo Modal Sửa hàng loạt
const initBulkEditModal = () => {
    const modalEl = document.getElementById('variantBulkEditModal');
    const modal = new bootstrap.Modal(modalEl);
    
    $('#btnBulkEdit').off('click').on('click', () => {
        // Reset form
        $('.bulk-toggle').prop('checked', false);
        $('.bulk-toggle').each(function() {
            const targets = $(this).data('target').split(',');
            targets.forEach(t => $(`#${t}`).prop('disabled', true));
        });
        
        // Populate group options
        const $groupSelect = $('#b_group_id');
        $groupSelect.html('<option value="0">-- Giữ nguyên --</option>');
        variantGroups.forEach(g => {
            $groupSelect.append(`<option value="${g.id}">${escapeHtml(g.name)}</option>`);
        });
        
        // Reset image
        $('#b_image_url').val('');
        $('#b_image_preview').html('<i class="bi bi-image text-muted fs-4"></i>');
        
        modal.show();
    });

    $('.bulk-toggle').on('change', function() {
        const isChecked = $(this).is(':checked');
        const targets = $(this).data('target').split(',');
        targets.forEach(t => $(`#${t}`).prop('disabled', !isChecked));
    });

    $('#btn_b_image').off('click').on('click', () => {
        MediaLibrary.open({
            type: 'image',
            onSelect: (items) => {
                if (items.length > 0) {
                    $('#b_image_url').val(items[0].url);
                    $('#b_image_preview').html(`<img src="${resolveUrl(items[0].url)}" style="width:100%;height:100%;object-fit:cover;border-radius:4px;">`);
                }
            }
        });
    });

    $('#btnSaveBulkEdit').off('click').on('click', () => {
        const ids = getSelectedVariantIds();
        if (ids.length < 1) return;

        const data = {
            action: 'bulk_update_variants',
            product_id: PRODUCT_ID,
            ids: ids
        };

        let hasChange = false;
        if ($('#toggle_b_group_id').is(':checked')) {
            data.group_id = $('#b_group_id').val();
            hasChange = true;
        }
        if ($('#toggle_b_image').is(':checked')) {
            data.image_url = $('#b_image_url').val();
            hasChange = true;
        }
        if ($('#toggle_b_weight').is(':checked')) {
            data.shipping_weight_value = $('#b_weight').val();
            data.shipping_weight_unit = $('#b_weight_unit').val();
            hasChange = true;
        }
        if ($('#toggle_b_price').is(':checked')) {
            data.price = $('#b_price').val().replace(/\D/g, '');
            hasChange = true;
        }
        if ($('#toggle_b_sku').is(':checked')) {
            data.sku_variant = $('#b_sku').val();
            hasChange = true;
        }
        if ($('#toggle_b_dims').is(':checked')) {
            data.shipping_length_cm = $('#b_length').val();
            data.shipping_width_cm = $('#b_width').val();
            data.shipping_height_cm = $('#b_height').val();
            hasChange = true;
        }
        if ($('#toggle_b_status').is(':checked')) {
            data.status = $('#b_status').val();
            hasChange = true;
        }

        if (!hasChange) {
            toastr.warning('Vui lòng chọn ít nhất một trường để cập nhật');
            return;
        }

        $.post(API_URL, data, (res) => {
            if (res && res.ok) {
                toastr.success(res.msg || 'Đã cập nhật hàng loạt');
                modal.hide();
                loadVariants(); // Refresh data
            } else {
                toastr.error(res?.msg || 'Cập nhật thất bại');
            }
        }).fail(() => toastr.error('Lỗi kết nối server'));
    });

    $('#btnClearBulkSelection').on('click', () => {
        $('.variant-checkbox').prop('checked', false);
        $('#checkAllVariants').prop('checked', false);
        syncBulkEditVisibility();
    });
};

// Event listeners cho các bộ lọc nâng cao
$(document).on('input', '#variantSearch', function() {
    variantSearchTerm = $(this).val();
    renderVariants();
});

$(document).on('change', '#filterPriceStatus', function() {
    priceFilter = $(this).val();
    renderVariants();
});

$(document).on('change', '#filterActiveStatus', function() {
    statusFilter = $(this).val();
    renderVariants();
});

$(document).on('change', '#filterStockStatus', function() {
    stockFilter = $(this).val();
    renderVariants();
});

$(document).on('change', '#filterImageStatus', function() {
    imageFilter = $(this).val();
    renderVariants();
});

$(document).on('change', '#variantSort', function() {
    variantSortMode = $(this).val();
    renderVariants();
});

$(document).on('change', '#checkAllVariants', function() {
    const checked = $(this).is(':checked');
    $('.variant-checkbox').prop('checked', checked);
    syncBulkEditVisibility();
});
$(document).on('change', '.variant-checkbox', function() {
    const total = $('.variant-checkbox').length;
    const checked = $('.variant-checkbox:checked').length;
    $('#checkAllVariants').prop('checked', total > 0 && total === checked);
    syncBulkEditVisibility();
});


window.bulkAssignVariantGroup = () => {
    const groupId = $('#bulk_group_id').val();
    const ids = [];
    $('.variant-checkbox:checked').each(function() {
        ids.push($(this).val());
    });

    if (!ids.length) { toastr.warning('Chưa chọn biến thể'); return; }
    if (groupId === "0") { toastr.warning('Chưa chọn nhóm phân loại'); return; }

    if (!confirm(`Gán nhóm phân loại cho ${ids.length} biến thể đã chọn?`)) return;

    let count = 0;
    const total = ids.length;
    ids.forEach(id => {
        const item = variantsData.find(v => v.id == id);
        if(!item) return;

        const payload = {
            action: 'save_variant',
            v_id: id,
            product_id: PRODUCT_ID,
            group_id: groupId,
            variant_name: item.variant_name,
            price: item.price,
            stock_quantity: item.stock_quantity,
            sku_variant: item.sku_variant,
            shipping_weight_value: item.shipping_weight_value,
            shipping_weight_unit: item.shipping_weight_unit,
            shipping_length_cm: item.shipping_length_cm,
            shipping_width_cm: item.shipping_width_cm,
            shipping_height_cm: item.shipping_height_cm,
            status: item.status
        };

        $.post(API_URL, payload, res => {
            count++;
            if (count === total) {
                toastr.success(`Đã cập nhật ${total} biến thể`);
                loadVariants();
            }
        });
    });
};

// Xóa ảnh/video khỏi thư viện media, nếu không giữ phím Shift sẽ hỏi xác nhận để tránh xóa nhầm
window.removeMediaItem = (idx) => {
    if(typeof idx === 'undefined' || idx < 0 || idx >= mediaGallery.length) return;
    if(!confirm('Xóa media này?')) return;
    mediaGallery.splice(idx,1);
    renderMediaGallery();
};

window.updateVariantField = (vid, field, newVal) => {
    const item = variantsData.find(v => v.id == vid);
    if (!item) return;

    // Chuẩn hoá giá trị trước khi lưu
    let val = newVal;
    if (field === 'price') {
        val = parseInt(String(newVal).replace(/\D/g, ''), 10) || 0;
    } else if (['stock_quantity', 'shipping_weight_value', 'shipping_length_cm', 'shipping_width_cm', 'shipping_height_cm'].includes(field)) {
        val = parseFloat(String(newVal).replace(/[^0-9.]/g, '')) || 0;
    } else {
        val = String(newVal).trim();
    }

    if (!PRODUCT_ID) {
        item[field] = val;
        toastr.success('Đã cập nhật tạm thời');
        loadVariants();
        return;
    }

    const payload = {
        action: 'save_variant',
        v_id: vid,
        product_id: PRODUCT_ID,
        group_id: item.group_id || 0,
        variant_name: field === 'variant_name' ? val : String(item.variant_name || '').trim(),
        price: field === 'price' ? val : String(item.price || '0'),
        stock_quantity: field === 'stock_quantity' ? val : String(item.stock_quantity || '0'),
        sku_variant: field === 'sku_variant' ? val : String(item.sku_variant || '').trim(),
        shipping_weight_value: field === 'shipping_weight_value' ? val : (item.shipping_weight_value || 0),
        shipping_weight_unit: String(item.shipping_weight_unit || 'kg').trim().toLowerCase(),
        shipping_length_cm: field === 'shipping_length_cm' ? val : (item.shipping_length_cm || 20),
        shipping_width_cm: field === 'shipping_width_cm' ? val : (item.shipping_width_cm || 20),
        shipping_height_cm: field === 'shipping_height_cm' ? val : (item.shipping_height_cm || 20),
        status: String(item.status || '1')
    };

    $.post(API_URL, payload, res => {
        if (res && res.ok) {
            item[field] = val;
            toastr.success('Đã cập nhật');
        } else {
            toastr.error(res?.msg || 'Không cập nhật được');
            loadVariants();
        }
    }, 'json');
};

window.delVariant = (vid) => {
    const variant = variantsData.find(v => v.id == vid);
    const vName = variant ? String(variant.variant_name || variant.name || 'phân loại này').trim() : 'phân loại này';
    if (!confirm(`Bạn có chắc chắn muốn xóa "${vName}"?\nHành động này không thể hoàn tác và có thể ảnh hưởng đến các đơn hàng cũ đang tham chiếu phân loại này.`)) return;
    if (!PRODUCT_ID) {
        variantsData = variantsData.filter(v => v.id != vid);
        toastr.success('Đã xóa phân loại tạm thời.');
        loadVariants();
        return;
    }
    $.post(API_URL, {action:'del_variant', id:vid}, (res) => {
        if (res && res.ok) {
            toastr.success('Đã xóa phân loại.');
            loadVariants();
        } else {
            toastr.error(res?.msg || 'Không xóa được phân loại.');
        }
    }, 'json').fail(() => {
        toastr.error('Lỗi kết nối khi xóa phân loại.');
    });
};
// Bật/tắt trạng thái bán của phân loại
window.toggleVariantStatus = (vid, nextStatus) => {
    const variantId = Number(vid || 0);
    if (!variantId) {
        toastr.warning('Không xác định được phân loại');
        return;
    }
    const item = variantsData.find(v => Number(v.id || 0) === variantId);
    if (!item) {
        toastr.warning('Không tìm thấy phân loại');
        return;
    }

    if (!PRODUCT_ID) {
        item.status = nextStatus ? 1 : 0;
        toastr.success('Đã cập nhật trạng thái phân loại tạm thời');
        loadVariants();
        return;
    }

    const payload = {
        action: 'save_variant',
        v_id: variantId,
        product_id: PRODUCT_ID,
        group_id: item.group_id || 0,
        variant_name: String(item.variant_name || '').trim(),
        color: String(item.color || ''),
        price: String(item.price || ''),
        stock_quantity: String(item.stock_quantity || ''),
        sku_variant: String(item.sku_variant || '').trim(),
        shipping_weight_value: String(item.shipping_weight_value || '').trim(),
        shipping_weight_unit: String(item.shipping_weight_unit || 'kg').trim().toLowerCase(),
        shipping_length_cm: String(item.shipping_length_cm || '').trim(),
        shipping_width_cm: String(item.shipping_width_cm || '').trim(),
        shipping_height_cm: String(item.shipping_height_cm || '').trim(),
        status: String(nextStatus ? '1' : '0')
    };


    $.post(API_URL, payload, res => {
        if (res && res.ok) {
            toastr.success('Đã cập nhật trạng thái phân loại');
            loadVariants();
        } else {
            toastr.error(res?.msg || 'Không cập nhật được trạng thái phân loại');
        }
    }, 'json');
};
// Mở form sửa nhanh phân loại, cho phép chỉnh tên, giá, tồn kho, SKU, trọng lượng và kích thước
window.editVariantQuick = (vid) => {
    const item = variantsData.find(v => Number(v.id) === Number(vid));
    if(!item) return toastr.warning('Không tìm thấy phân loại');

    editingVariantId = item.id;
    $('#q_v_group_id').val(item.group_id || 0);
    $('#q_v_name').val(String(item.variant_name || '').trim());
    $('#q_v_price').val(Number(item.price) > 0 ? new Intl.NumberFormat('vi-VN').format(item.price) : '');
    $('#q_v_stock').val(Math.max(0, Number(item.stock_quantity || 0)));
    $('#q_v_sku').val(String(item.sku_variant || '').trim());
    $('#q_v_weight').val(Number(item.shipping_weight_value || 1));
    $('#q_v_weight_unit').val(String(item.shipping_weight_unit || 'kg').trim().toLowerCase());
    $('#q_v_length_cm').val(Math.max(1, Number(item.shipping_length_cm || 20)));
    $('#q_v_width_cm').val(Math.max(1, Number(item.shipping_width_cm || 20)));
    $('#q_v_height_cm').val(Math.max(1, Number(item.shipping_height_cm || 20)));

    variantQuickEditInstance ??= new bootstrap.Modal(document.getElementById('variantQuickEditModal'));
    variantQuickEditInstance.show();
};


const renderAddVariantImagePreview = () => {
    if(mainImageUrl){
        $('#a_v_image_preview').html(`<img src="${escapeHtml(resolveUrl(mainImageUrl))}" alt="Ảnh sản phẩm" style="width:52px;height:52px;border-radius:10px;object-fit:cover;border:1px solid #e2e8f0;">`);
    } else {
        $('#a_v_image_preview').html('<span class="note">Dùng ảnh bìa hiện tại</span>');
    }
};

$('#btnSaveVariantQuick').on('click', () => {
    const variantId = Number(editingVariantId || 0);
    if(!variantId) {
        toastr.warning('Không xác định được phân loại cần sửa');
        return;
    }

    const khoiLuongUnit = String($('#q_v_weight_unit').val() || 'kg').trim().toLowerCase();
    if(!['kg', 'gram', 'l', 'ml'].includes(khoiLuongUnit)) {
        toastr.warning('Đơn vị chỉ chấp nhận: kg, gram, l, ml');
        return;
    }

    const name = String($('#q_v_name').val() || '').trim();
    const price = String($('#q_v_price').val() || '').replace(/\D/g, '');
    const stock = String($('#q_v_stock').val() || '').replace(/\D/g, '');
    const sku = String($('#q_v_sku').val() || '').trim();
    const weight = String($('#q_v_weight').val() || '').replace(/[^0-9.]/g, '');
    const length = String($('#q_v_length_cm').val() || '').replace(/[^0-9.]/g, '');
    const width = String($('#q_v_width_cm').val() || '').replace(/[^0-9.]/g, '');
    const height = String($('#q_v_height_cm').val() || '').replace(/[^0-9.]/g, '');

    if(!name || !price) {
        toastr.warning('Tên loại và giá bán là bắt buộc');
        return;
    }

    if (!PRODUCT_ID) {
        const v = variantsData.find(x => x.id == variantId);
        if (v) {
            const groupId = $('#q_v_group_id').val();
            const g = variantGroups.find(x => x.id == groupId) || { name: '' };
            v.group_id = groupId;
            v.group_name = g.name;
            v.variant_name = name;
            v.price = price;
            v.stock_quantity = stock;
            v.sku_variant = sku;
            v.shipping_weight_value = weight;
            v.shipping_weight_unit = khoiLuongUnit;
            v.shipping_length_cm = length;
            v.shipping_width_cm = width;
            v.shipping_height_cm = height;
        }
        toastr.success('Đã cập nhật phân loại tạm thời');
        if (variantQuickEditInstance) variantQuickEditInstance.hide();
        loadVariants();
        return;
    }

    const payload = {
        action: 'save_variant',
        v_id: variantId,
        product_id: PRODUCT_ID,
        group_id: $('#q_v_group_id').val(),
        variant_name: name,
        color: '',
        price: price,
        stock_quantity: stock,
        sku_variant: sku,
        shipping_weight_value: weight,
        shipping_weight_unit: khoiLuongUnit,
        shipping_length_cm: length,
        shipping_width_cm: width,
        shipping_height_cm: height
    };

    $.post(API_URL, payload, res => {
        if(res && res.ok) {
            toastr.success('Đã cập nhật phân loại');
            if (variantQuickEditInstance) variantQuickEditInstance.hide();
            loadVariants();
        } else {
            toastr.error(res?.msg || 'Không cập nhật được phân loại');
        }
    }, 'json');
});

// Thêm sản phẩm.
let quickAddVariantModalInstance = null;
$('#btnQuickAddVariantModal').on('click', () => {
    $('#quickAddVariantText').val('');
    if (!quickAddVariantModalInstance) {
        const modalElement = document.getElementById('quickAddVariantModal');
        if (modalElement) quickAddVariantModalInstance = new bootstrap.Modal(modalElement);
    }
    if (quickAddVariantModalInstance) quickAddVariantModalInstance.show();
});

$('#btnProcessQuickAddVariant').on('click', async function() {
    const text = $('#quickAddVariantText').val().trim();
    if (!text) {
        toastr.warning('Vui lòng nhập dữ liệu phân loại');
        return;
    }

    const lines = text.split('\n');
    let queue = [];

    lines.forEach(line => {
        line = line.trim();
        if (!line) return;
        
        let parts = line.split('\t');
        if (parts.length < 2) {
            parts = line.split('|');
        }
        
        if (parts.length >= 2) {
            const sku = parts[0].trim();
            const name = parts[1].trim();
            let priceStr = (parts[2] || '0').trim();
            let stockStr = (parts[3] || '0').trim();
            
            const price = parseFloat(priceStr.replace(/[,.]/g, '')) || 0;
            const stock = parseFloat(stockStr.replace(/[,.]/g, '')) || 0;

            queue.push({
                action: 'save_variant',
                product_id: PRODUCT_ID,
                group_id: $('#quick_v_group_id').val(),
                variant_name: name,
                color: '',
                price: price,
                stock_quantity: stock,
                sku_variant: sku,
                shipping_weight_value: 1,
                shipping_weight_unit: 'kg',
                shipping_length_cm: 20,
                shipping_width_cm: 20,
                shipping_height_cm: 20
            });

        }
    });

    if (queue.length === 0) {
        toastr.warning('Không tìm thấy dòng dữ liệu nào hợp lệ');
        return;
    }

    const $btn = $(this);
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span> Đang xử lý...');

    if (!PRODUCT_ID) {
        const groupId = $('#quick_v_group_id').val();
        const g = variantGroups.find(x => x.id == groupId) || { name: '' };
        queue.forEach(item => {
            const tempId = -1 - variantsData.length;
            variantsData.push({
                id: tempId,
                group_id: groupId,
                group_name: g.name,
                variant_name: item.variant_name,
                color: '',
                price: item.price,
                stock_quantity: item.stock_quantity,
                sku_variant: item.sku_variant,
                shipping_weight_value: 1,
                shipping_weight_unit: 'kg',
                shipping_length_cm: 20,
                shipping_width_cm: 20,
                shipping_height_cm: 20,
                status: 1
            });
        });
        toastr.success(`Đã thêm thành công ${queue.length} phân loại tạm thời`);
        loadVariants();
        if (quickAddVariantModalInstance) quickAddVariantModalInstance.hide();
        $btn.prop('disabled', false).html('<i class="bi bi-play-circle me-1"></i> Xử lý dữ liệu');
        return;
    }

    let successCount = 0;
    let failCount = 0;

    for (const item of queue) {
        try {
            const res = await new Promise((resolve, reject) => {
                $.post(API_URL, item, resolve, 'json').fail(reject);
            });
            if (res && res.ok) {
                successCount++;
            } else {
                failCount++;
            }
        } catch (e) {
            failCount++;
        }
    }

    if (successCount > 0) {
        toastr.success(`Đã thêm thành công ${successCount} phân loại. Lỗi: ${failCount}`);
        loadVariants();
        if (quickAddVariantModalInstance) quickAddVariantModalInstance.hide();
    } else {
        toastr.error(`Không thể thêm phân loại nào. Lỗi: ${failCount}`);
    }

    $btn.prop('disabled', false).html('<i class="bi bi-play-circle me-1"></i> Xử lý dữ liệu');
});


const openVariantAddModal = () => {
    $('#a_v_name,#a_v_price,#a_v_sku').val('');
    $('#a_v_group_id').val('0');
    $('#a_v_stock').val('10');
    $('#a_v_weight').val('1');
    $('#a_v_weight_unit').val('kg');
    $('#a_v_length_cm,#a_v_width_cm,#a_v_height_cm').val('20');
    renderAddVariantImagePreview();

    if (!variantAddModalInstance) {
        const modalElement = document.getElementById('variantAddModal');
        if (!modalElement) {
            toastr.error('Không mở được form thêm phân loại');
            return;
        }
        variantAddModalInstance = new bootstrap.Modal(modalElement);
    }
    variantAddModalInstance.show();
};


    $('#btnPickMainImage').off('click').on('click', () => {
        MediaLibrary.open({
            type: 'image',
            onSelect: (items) => {
                if (items.length > 0) {
                    mainImageUrl = items[0].url;
                    renderMainImage();
                }
            }
        });
    });

    $('#btnPickMedia').off('click').on('click', () => {
        MediaLibrary.open({
            type: '', // Cả ảnh và video
            multiple: true,
            onSelect: (items) => {
                items.forEach(item => {
                    const type = item.file_type.startsWith('video/') ? 'video' : 'image';
                    pushMedia(type, item.url, item.title || '');
                });
            }
        });
    });

    window.pickVariantImage = (vid) => {
        MediaLibrary.open({
            type: 'image',
            onSelect: (items) => {
                if (items.length > 0) {
                    updateVariantField(vid, 'image_url', items[0].url);
                }
            }
        });
    };

$('#btnSaveVariantAdd').on('click', () => {
    const groupId = $('#a_v_group_id').val();
    const name = $('#a_v_name').val();
    const price = String($('#a_v_price').val() || '').replace(/\D/g, '');
    const stock = String($('#a_v_stock').val() || '').replace(/\D/g, '');
    const sku = ($('#a_v_sku').val() || '').trim();
    const weight = String($('#a_v_weight').val() || '').replace(/[^0-9.]/g, '');
    const unit = ($('#a_v_weight_unit').val() || 'kg');
    const length = String($('#a_v_length_cm').val() || '').replace(/[^0-9.]/g, '');
    const width = String($('#a_v_width_cm').val() || '').replace(/[^0-9.]/g, '');
    const height = String($('#a_v_height_cm').val() || '').replace(/[^0-9.]/g, '');
    
    if(!name || !price) { toastr.warning('Nhập tên loại và giá'); return; }

    if (!PRODUCT_ID) {
        const tempId = -1 - variantsData.length;
        const g = variantGroups.find(x => x.id == groupId) || { name: '' };
        variantsData.push({
            id: tempId,
            group_id: groupId,
            group_name: g.name,
            variant_name: name,
            color: '',
            price: price,
            stock_quantity: stock,
            sku_variant: sku,
            shipping_weight_value: weight,
            shipping_weight_unit: unit,
            shipping_length_cm: length,
            shipping_width_cm: width,
            shipping_height_cm: height,
            status: 1
        });
        toastr.success('Đã thêm phân loại tạm thời');
        if (variantAddModalInstance) variantAddModalInstance.hide();
        loadVariants();
        return;
    }

    const data = {
        action: 'save_variant',
        product_id: PRODUCT_ID,
        group_id: groupId,
        variant_name: name,
        color: '',
        price: price,
        stock_quantity: stock,
        sku_variant: sku,
        shipping_weight_value: weight,
        shipping_weight_unit: unit,
        shipping_length_cm: length,
        shipping_width_cm: width,
        shipping_height_cm: height
    };

    $.post(API_URL, data, res => {
        if(res && res.ok){
            toastr.success('Đã thêm phân loại');
            if (variantAddModalInstance) variantAddModalInstance.hide();
            loadVariants();
        } else {
            toastr.error(res?.msg || 'Không thêm được phân loại');
        }
    }, 'json');
});

$('#btn_a_v_image').off('click').on('click', () => {
    MediaLibrary.open({
        type: 'image',
        onSelect: (items) => {
            if (items.length > 0) {
                $('#a_v_image_url').val(items[0].url);
                $('#a_v_image_preview').html(`<img src="${resolveUrl(items[0].url)}" style="width:100%;height:100%;object-fit:cover;border-radius:4px;">`);
                window.a_v_image_url = items[0].url;
            }
        }
    });
});


const pushMedia = (type, url, caption) => {
    if(type === 'video') {
        mediaGallery = mediaGallery.filter(item => item.type !== 'video');
    }
    mediaGallery.push({ type, url, caption });
    renderMediaGallery();
};

const uploadMediaFile = () => {
    const fileInput = document.getElementById('media_file_image');
    if(!fileInput || !fileInput.files || !fileInput.files.length) { toastr.warning('Chọn ảnh trước'); return; }

    const files = Array.from(fileInput.files);
    // Tối đa <?= $max_UpfileSize  ?>MB mỗi ảnh để tránh quá nặng
    const maxPerFile = '<?= $total_UpfileSize  ?>';

    const uploadNext = () => {
        const file = files.shift();
        if (!file) {
            fileInput.value = '';
            return;
        }
        if (file.size > maxPerFile) {
            toastr.warning(`Ảnh "${file.name}" vượt quá <?= $max_UpfileSize ?>MB, bỏ qua.`);
            uploadNext();
            return;
        }

        const fd = new FormData();
        fd.append('action','upload_media');
        fd.append('media_kind','image');
        fd.append('file', file);
        $.ajax({
            url: API_URL,
            type: 'POST',
            data: fd,
            contentType: false,
            processData: false,
            dataType: 'json',
            success: res => {
                if(res && res.ok){
                    const mediaUrl = res.url || '';
                    if(!mediaUrl){
                        toastr.error('Không nhận được đường dẫn media');
                    } else {
                        pushMedia('image', mediaUrl, 'Ảnh sản phẩm');
                    }
                } else {
                    toastr.error(res?.msg || `Không upload được ảnh "${file.name}"`);
                }
                uploadNext();
            },
            error: () => {
                toastr.error(`Lỗi kết nối khi upload ảnh "${file.name}"`);
                uploadNext();
            }
        });
    };

    uploadNext();
};

const uploadVideoFile = (file) => {
    const fd = new FormData();
    fd.append('action', 'upload_media');
    fd.append('media_kind', 'video');
    fd.append('file', file);
    $.ajax({
        url: API_URL,
        type: 'POST',
        data: fd,
        contentType: false,
        processData: false,
        dataType: 'json',
        success: res => {
            if(res && res.ok){
                pushMedia('video', res.url || '', 'Video sản phẩm');
                toastr.success('Đã thêm video sản phẩm');
            } else {
                toastr.error(res?.msg || 'Không upload được video');
            }
        },
        error: () => toastr.error('Lỗi kết nối')
    });
};

const validateAndUploadVideo = (file) => {
    if(!file) return;
    const maxSize = '<?= $total_UpfileSize ?>';
    if(file.size > maxSize){
        toastr.warning('Video tối đa <?= $max_UpfileSize ?>MB');
        return;
    }
    const isMp4 = /\.mp4$/i.test(file.name || '') || String(file.type || '').toLowerCase().includes('mp4');
    if(!isMp4){
        toastr.warning('Chỉ hỗ trợ định dạng MP4');
        return;
    }
    const video = document.createElement('video');
    video.preload = 'metadata';
    video.onloadedmetadata = () => {
        URL.revokeObjectURL(video.src);
        if((video.duration || 0) > (<?= $time_UpfileVideo ?> * 10)){
            toastr.warning('Video tối đa <?= $time_UpfileVideo ?> phút');
            return;
        }
        uploadVideoFile(file);
    };
    video.onerror = () => {
        URL.revokeObjectURL(video.src);
        toastr.warning('Không đọc được thời lượng video');
    };
    video.src = URL.createObjectURL(file);
};

// Hỗ trợ panel Hệ thống sơn
const getCoatingLayerCountFromUi = () => {
    const mode = $("input[name='cs_layer_count_mode']:checked").val();
    return mode === 'input' ? Math.max(1, parseInt($('#cs_layer_count').val(), 10) || 0) : 0;
};

const resolveCoatingLayerType = () => {
    const mode = $("input[name='cs_layer_type_mode']:checked").val() || 'lot';
    const val = mode === 'lot' ? 'Lót' : (mode === 'phu' ? 'Phủ' : $('#cs_layer_type_custom').val().trim());
    $('#cs_layer_type').val(val);
    return val;
};

const setCoatingLayerTypeUiFromValue = (raw) => {
    const v = String(raw || '').trim();
    const mode = ['lót', 'lot'].includes(v.toLowerCase()) || !v ? 'lot' : (['phủ', 'phu'].includes(v.toLowerCase()) ? 'phu' : 'custom');
    $(`input[name="cs_layer_type_mode"][value="${mode}"]`).prop('checked', true);
    $('#cs_layer_type_custom').val(mode === 'custom' ? v : '').prop('disabled', mode !== 'custom');
    resolveCoatingLayerType();
};

const setCoatingLayerCountUiFromValue = (raw) => {
    const n = parseInt(raw, 10) || 0;
    const isInput = n > 0;
    $(`input[name="cs_layer_count_mode"][value="${isInput ? 'input' : 'none'}"]`).prop('checked', true);
    $('#cs_layer_count').val(isInput ? n : '1').prop('disabled', !isInput);
};

const renderCoatingProductPreview = () => {
    const wrap = $('#cs_product_preview');
    if (!wrap.length) return;
    const prodId = Number($('#cs_product').val() || 0);
    if (!Array.isArray(window.csProductOptions) || !prodId) {
        wrap.html('Chưa chọn sản phẩm gợi ý.');
        return;
    }
    const it = window.csProductOptions.find(p => Number(p.id) === prodId);
    if (!it) {
        wrap.html('Chưa chọn sản phẩm gợi ý.');
        return;
    }
    const name = String(it.product_name || it.name || '').trim() || 'Sản phẩm';
    const sku = String(it.sku || '').trim();
    const img = String(it.image_url || '').trim();
    const url = img ? resolveUrl(img) : '';
    const imgHtml = url
        ? `<div style="width:56px;height:56px;border-radius:10px;overflow:hidden;border:1px solid #e2e8f0;background:#fff;flex-shrink:0;"><img src="${escapeHtml(url)}" alt="${escapeHtml(name)}" style="width:100%;height:100%;object-fit:cover;"></div>`
        : '<div style="width:56px;height:56px;border-radius:10px;border:1px dashed #cbd5e1;display:flex;align-items:center;justify-content:center;flex-shrink:0;" class="text-muted small">Ảnh</div>';
    const skuHtml = sku ? `<div class="text-muted small">SKU: ${escapeHtml(sku)}</div>` : '';
    wrap.html(`
        <div class="d-flex align-items-center gap-2">
            ${imgHtml}
            <div class="flex-grow-1">
                <div class="fw-semibold">${escapeHtml(name)}</div>
                ${skuHtml}
            </div>
        </div>
    `);
};

const buildCoatingSystemLine = () => {
    const catId = Number($('#cs_category').val() || 0);
    const prodId = Number($('#cs_product').val() || 0);
    const layerType = resolveCoatingLayerType();
    const layerCount = getCoatingLayerCountFromUi();
    const cat = catsData.find(c => Number(c.id) === catId);
    let productName = '';
    if (Array.isArray(window.csProductOptions)) {
        const p = window.csProductOptions.find(x => Number(x.id) === prodId);
        if (p) productName = String(p.product_name || p.name || '').trim();
    }
    const parts = [];
    if (layerType) parts.push(layerType);
    if (productName) parts.push(productName);
	else if (cat && cat.name) parts.push(String(cat.name));
    const main = parts.join(' - ');
    if (!main) return '';
    const prefix = layerCount > 0 ? `${layerCount} lớp: ` : '';
    return (prefix + main).trim();
};

const refreshCoatingPreview = () => {
    $('#cs_preview').val(buildCoatingSystemLine());
};

// =========================
// DỮ LIỆU THI CÔNG
// =========================
const parseConstructionTools = (raw) => {
    const fallbackText = String(raw || '').trim();
    const parsed = tryParseJson(raw);
    if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
        const enabled = Number(parsed.enabled || 0) === 1;
        const text = String(parsed.text || '').trim();
        return { enabled, text };
    }
    return { enabled: fallbackText !== '', text: fallbackText };
};

const packConstructionTools = (enabled, text) => {
    return JSON.stringify({
        enabled: enabled ? 1 : 0,
        text: String(text || '')
    });
};

const parseMethodFiles = (raw) => {
    const txt = String(raw || '').trim();
    if (!txt) return [];
    const parsed = tryParseJson(txt);
    if (Array.isArray(parsed)) {
        return parsed
            .map(x => String(x || '').trim())
            .filter(Boolean);
    }
    return [txt];
};

const renderMethodFileList = (filePaths) => {
    const $wrap = $('#pc_method_file_list');
    if (!$wrap.length) return;
    const list = Array.isArray(filePaths) ? filePaths : [];
    if (!list.length) {
        $wrap.html('<div class="small text-muted">Chưa có PDF.</div>');
        return;
    }
    const itemsHtml = list.map(p => {
        const path = String(p || '').trim();
        const name = path.split('/').pop() || 'PDF';
        const url = resolveUrl(path);
        return `
            <div class="list-group-item d-flex align-items-center justify-content-between gap-2">
                <a class="text-decoration-none" href="${escapeHtml(url)}" target="_blank" rel="noopener">${escapeHtml(name)}</a>
                <button type="button" class="btn btn-sm btn-outline-danger pc-method-file-remove" data-path="${escapeHtml(path)}" title="Xóa PDF">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        `;
    }).join('');
    $wrap.html(`<div class="list-group">${itemsHtml}</div>`);
};

const setConstructionMethodEditorHtml = (html = '') => setMceContentHelper('pc_method_text', html, () => pendingConstructionHtml, (v) => pendingConstructionHtml = v);

const syncConstructionMethodEditorToTextarea = () => syncMceContentHelper('pc_method_text');

const initConstructionMethodEditor = () => {
    if (constructionEditorReady || constructionEditorInitializing) return;
    if (!window.tinymce || typeof window.tinymce.init !== 'function') return;
    if (typeof window.initMceToolbar !== 'function') return;

    constructionEditorInitializing = true;
    try {
        window.initMceToolbar({
            selector: '#pc_method_text',
            uploadUrl: API_URL,
            baseUrl: BASE_URL,
            onChange: (html) => {
                $('#pc_method_text').val(String(html || ''));
            },
            onReady: (editor) => {
                constructionEditorReady = true;
                constructionEditorInitializing = false;
                
                const readyContent = pendingConstructionHtml !== null
                    ? String(pendingConstructionHtml || '')
                    : String($('#pc_method_text').val() || '');
                
                editor.setContent(readyContent);
                pendingConstructionHtml = null;
            }
        });
    } catch (e) {
        constructionEditorInitializing = false;
        if (window.console) console.error(e);
    }
};

const syncConstructionToolsUi = () => {
    const enabled = $('#pc_tools_enabled').is(':checked');
    $('#pc_tools').prop('disabled', !enabled);
};

const syncConstructionSurfaceUi = () => {
    const enabled = $('#pc_surface_enabled').is(':checked');
    $('#pc_surface_fields').toggleClass('d-none', !enabled);
};

const renderConstructionSummary = () => {
    const $el = $('#constructionSummary');
    const data = constructionData;
    const $tbody = $('#constructionTbody');
    const $btnEdit = $('#btnConstructionEdit');

    if (!$tbody.length) {
        // Fallback: chỉ cập nhật dòng mô tả
        if ($el.length) {
            $el.text('Chưa có thông tin thi công. Nhấn "Thêm thi công" để bắt đầu.');
        }
        return;
    }

    // Chưa lưu sản phẩm
    if (!PRODUCT_ID) {
        if ($el.length) {
            $el.text('Lưu sản phẩm trước khi cấu hình dữ liệu thi công.');
        }
        $tbody.html('<tr><td colspan="5" class="text-center text-muted">Lưu sản phẩm trước khi cấu hình dữ liệu thi công.</td></tr>');
        if ($btnEdit.length) {
            $btnEdit.prop('disabled', true);
        }
        return;
    }

    if (!data) {
        if ($el.length) {
            $el.text('Chưa có thông tin thi công. Nhấn "Thêm thi công" để bắt đầu.');
        }
        $tbody.html('<tr><td colspan="5" class="text-center text-muted">Chưa có dữ liệu thi công cho sản phẩm này.</td></tr>');
        if ($btnEdit.length) {
            $btnEdit.prop('disabled', false)
                .html('<i class="bi bi-plus-circle"></i> Thêm thi công');
        }
        return;
    }

    const toolsObj = parseConstructionTools(data.tools || '');
    const tools = toolsObj.enabled ? String(toolsObj.text || '').trim() : '';
    const surfaceEnabled = Number(data.surface_prep_enabled || 0) === 1;
    const surfaceNew = String(data.surface_prep_new || '').trim();
    const surfaceOld = String(data.surface_prep_old || '').trim();
    const methodText = String(data.method_text || '').trim();
    const methodFiles = parseMethodFiles(data.method_file || '');

    // Tóm tắt ngắn cho dòng mô tả trên cùng
    const parts = [];
    parts.push('Dụng cụ: ' + (toolsObj.enabled ? (tools ? tools : 'Bật') : 'Tắt'));
    if (surfaceEnabled) {
        const segs = [];
        if (surfaceNew) segs.push('Mới');
        if (surfaceOld) segs.push('Cũ');
        const label = segs.length ? ('Chuẩn bị bề mặt: ' + segs.join(' / ')) : 'Chuẩn bị bề mặt: Bật';
        parts.push(label);
    } else {
        parts.push('Chuẩn bị bề mặt: Không yêu cầu');
    }
    if (methodFiles.length && methodText) {
        parts.push('Cách thi công: PDF (' + methodFiles.length + ') + mô tả');
    } else if (methodFiles.length) {
        parts.push('Cách thi công: PDF (' + methodFiles.length + ')');
    } else if (methodText) {
        parts.push('Cách thi công: mô tả chi tiết');
    }
    if ($el.length) {
        $el.text(parts.length ? parts.join(' • ') : 'Chưa có thông tin thi công. Nhấn "Thêm thi công" để bắt đầu.');
    }

    // Cập nhật nút trên header
    if ($btnEdit.length) {
        $btnEdit.prop('disabled', false).html('<i class="bi bi-pencil-square"></i> Sửa thi công');
    }

    // Đổ dữ liệu vào bảng dạng 1 dòng với thao tác sửa/xóa
    let surfaceHtml = '';
    if (!surfaceEnabled) {
        surfaceHtml = 'Không';
    } else {
        if (surfaceNew) surfaceHtml += '<div><strong>Mới:</strong> ' + escapeHtml(surfaceNew) + '</div>';
        if (surfaceOld) surfaceHtml += '<div><strong>Cũ:</strong> ' + escapeHtml(surfaceOld) + '</div>';
        if (!surfaceHtml) surfaceHtml = 'Đã bật chuẩn bị bề mặt.';
    }

    let methodHtml = '';
    if (!methodFiles.length && !methodText) {
        methodHtml = 'Không';
    } else {
        const partsHtml = [];
        if (methodFiles.length) {
            const btns = methodFiles.map((p, idx) => {
                const url = resolveUrl(p);
                const label = methodFiles.length === 1 ? 'Tải PDF' : ('PDF ' + (idx + 1));
                return '<a href="' + escapeHtml(url) + '" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary me-2 mb-1">' + label + '</a>';
            }).join('');
            partsHtml.push(btns);
        }
        if (methodText) {
            partsHtml.push('<span class="text-muted small">(Có mô tả)</span>');
        }
        methodHtml = partsHtml.join(' ');
    }

    const idLabel = data.id ? String(data.id) : '—';
    const toolsHtml = toolsObj.enabled ? (tools ? escapeHtml(tools) : 'Bật') : 'Không';

    const rowHtml = `
        <tr>
            <td class="text-center">${idLabel}</td>
            <td>${surfaceHtml}</td>
            <td>${toolsHtml}</td>
            <td>${methodHtml}</td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-outline-primary me-1" onclick="window.editConstructionRow && window.editConstructionRow();">
                    <i class="bi bi-pencil"></i>
                </button>
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="window.deleteConstructionRow && window.deleteConstructionRow();">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        </tr>`;

    $tbody.html(rowHtml);
};
// Điền dữ liệu vào form thi công khi chỉnh sửa, hoặc reset form khi không có dữ liệu   
const fillConstructionForm = (data) => {
    const row = data || {};
    const toolsObj = parseConstructionTools(row.tools || '');
    $('#pc_tools_enabled').prop('checked', !!toolsObj.enabled);
    $('#pc_tools').val(toolsObj.text || '');
    syncConstructionToolsUi();
    const enabled = Number(row.surface_prep_enabled || 0) === 1;
    $('#pc_surface_enabled').prop('checked', enabled);
    $('#pc_surface_new').val(row.surface_prep_new || '');
    $('#pc_surface_old').val(row.surface_prep_old || '');
    setConstructionMethodEditorHtml(row.method_text || '');

    const fileInfoEl = $('#pc_method_file_info');
    const filePaths = parseMethodFiles(row.method_file || '');
    if (fileInfoEl.length) {
        fileInfoEl.text('Bạn có thể chọn 1 hoặc nhiều file PDF. File sẽ được tải lên khi bấm “Lưu”.');
    }
    renderMethodFileList(filePaths);
    $('#pc_method_file').val('');
    syncConstructionSurfaceUi();
};

const loadConstruction = () => {
    if (!PRODUCT_ID) {
        constructionData = null;
        renderConstructionSummary();
        return;
    }
    $.get(API_URL + '?ajax=product_construction&pid=' + PRODUCT_ID, res => {
        if (res && res.ok) {
            constructionData = res.row || null;
        } else {
            constructionData = null;
        }
        renderConstructionSummary();
        if (constructionData) {
            fillConstructionForm(constructionData);
        } else {
            fillConstructionForm(null);
        }
    }, 'json').fail(() => {
        constructionData = null;
        renderConstructionSummary();
    });
};

$('#btnAddCoatingSystem').on('click', () => {
    if (!PRODUCT_ID) {
        toastr.warning('Vui lòng lưu sản phẩm trước khi cấu hình hệ thống sơn');
        return;
    }
    editingCoatingId = 0;
    pendingCoatingProductId = 0;
    $('#cs_category').val('0');
    $('#cs_product').html('<option value="0">-- Chọn sản phẩm gợi ý --</option>');
    setCoatingLayerTypeUiFromValue('Lót');
    setCoatingLayerCountUiFromValue(0);
    renderCoatingProductPreview();
    refreshCoatingPreview();
    try {
        const el = document.getElementById('coatingSystemModal');
        if (el && window.bootstrap && typeof window.bootstrap.Modal === 'function') {
            const inst = window.bootstrap.Modal.getInstance(el) || new window.bootstrap.Modal(el);
            inst.show();
        } else if (typeof $ === 'function' && $.fn && typeof $.fn.modal === 'function') {
            $('#coatingSystemModal').modal('show');
        }
    } catch (e) {
        if (window.console) console.error(e);
    }
});

$('#cs_category').on('change', function(){
    const catId = Number($(this).val() || 0);
    $('#cs_product').html('<option value="0">Đang tải sản phẩm...</option>');
    window.csProductOptions = [];
    if (!catId) {
        $('#cs_product').html('<option value="0">-- Chọn sản phẩm gợi ý --</option>');
        refreshCoatingPreview();
        return;
    }
    $.get(API_URL + '?ajax=products_by_category&category_id=' + catId, res => {
        const rows = Array.isArray(res?.products) ? res.products : [];
        window.csProductOptions = rows;
        let html = '<option value="0">-- Chọn sản phẩm gợi ý --</option>';
        rows.forEach(p => {
            html += `<option value="${p.id}">${escapeHtml(p.product_name || p.name || '')}</option>`;
        });
        $('#cs_product').html(html);
        if (pendingCoatingProductId) {
            $('#cs_product').val(String(pendingCoatingProductId));
            pendingCoatingProductId = 0;
        }
        renderCoatingProductPreview();
        refreshCoatingPreview();
    }, 'json').fail(() => {
        $('#cs_product').html('<option value="0">-- Chọn sản phẩm gợi ý --</option>');
        renderCoatingProductPreview();
        refreshCoatingPreview();
    });
});

$('#cs_product').on('change', () => {
    renderCoatingProductPreview();
    refreshCoatingPreview();
});

$("input[name='cs_layer_type_mode']").on('change', () => {
    const mode = String($("input[name='cs_layer_type_mode']:checked").val() || 'lot');
    if (mode === 'custom') {
        $('#cs_layer_type_custom').prop('disabled', false).focus();
    } else {
        $('#cs_layer_type_custom').prop('disabled', true);
    }
    resolveCoatingLayerType();
    refreshCoatingPreview();
});

$('#cs_layer_type_custom').on('input', () => {
    if (String($("input[name='cs_layer_type_mode']:checked").val() || '') === 'custom') {
        resolveCoatingLayerType();
        refreshCoatingPreview();
    }
});

$("input[name='cs_layer_count_mode']").on('change', () => {
    const mode = String($("input[name='cs_layer_count_mode']:checked").val() || 'none');
    $('#cs_layer_count').prop('disabled', mode !== 'input');
    refreshCoatingPreview();
});

$('#cs_layer_count').on('change keyup', () => {
    refreshCoatingPreview();
});

$('#cs_apply').on('click', () => {
    const catId = Number($('#cs_category').val() || 0);
    const prodId = Number($('#cs_product').val() || 0);
    const layerType = resolveCoatingLayerType();
    let layerCount = getCoatingLayerCountFromUi();
    if (!layerType && !prodId && !catId) {
        toastr.warning('Vui lòng nhập loại lớp hoặc chọn sản phẩm/danh mục');
        return;
    }

    if (!PRODUCT_ID) {
        const catName = catId ? $('#cs_category option:selected').text().trim() : '';
        const prodName = prodId ? $('#cs_product option:selected').text().trim() : '';
        
        if (editingCoatingId) {
            const row = coatingRows.find(r => r.id == editingCoatingId);
            if (row) {
                row.category_id = catId;
                row.category_name = catName;
                row.suggest_product_id = prodId;
                row.suggest_product_name = prodName;
                row.layer_type = layerType;
                row.layer_count = layerCount;
            }
        } else {
            const newId = -1 - coatingRows.length;
            coatingRows.push({
                id: newId,
                category_id: catId,
                category_name: catName,
                suggest_product_id: prodId,
                suggest_product_name: prodName,
                layer_type: layerType,
                layer_count: layerCount
            });
        }
        
        toastr.success('Đã lưu hệ thống sơn tạm thời');
        renderCoatingSystemTable();
        
        try {
            const el = document.getElementById('coatingSystemModal');
            if (el && window.bootstrap && typeof window.bootstrap.Modal === 'function') {
                const inst = window.bootstrap.Modal.getInstance(el);
                if (inst) inst.hide();
            }
        } catch(e){}
        return;
    }

    const payload = {
        action: 'save_coating_row',
        id: editingCoatingId || 0,
        product_id: PRODUCT_ID,
        category_id: catId,
        suggest_product_id: prodId,
        layer_type: layerType,
        layer_count: layerCount
    };
    $.post(API_URL, payload, res => {
        if (res && res.ok) {
            toastr.success('Đã lưu hệ thống sơn');
            loadCoatingSystem();
            try {
                const el = document.getElementById('coatingSystemModal');
                if (el && window.bootstrap && typeof window.bootstrap.Modal === 'function') {
                    const inst = window.bootstrap.Modal.getInstance(el);
                    if (inst) inst.hide();
                } else if (typeof $ === 'function' && $.fn && typeof $.fn.modal === 'function') {
                    $('#coatingSystemModal').modal('hide');
                }
            } catch (e) {}
        } else {
            toastr.error(res?.msg || 'Không lưu được hệ thống sơn');
        }
    }, 'json').fail(() => {
        toastr.error('Lỗi kết nối khi lưu hệ thống sơn');
    });
});

window.editCoatingRow = (id) => {
    const rowId = Number(id || 0);
    if (!rowId) return;
    const row = Array.isArray(coatingRows) ? coatingRows.find(r => Number(r.id || 0) === rowId) : null;
    if (!row) {
        toastr.warning('Không tìm thấy dòng hệ thống sơn');
        return;
    }
    editingCoatingId = rowId;
    pendingCoatingProductId = Number(row.suggest_product_id || 0);
    setCoatingLayerTypeUiFromValue(row.layer_type || '');
    setCoatingLayerCountUiFromValue(row.layer_count || 0);
    $('#cs_category').val(String(row.category_id || 0)).trigger('change');
    refreshCoatingPreview();
    try {
        const el = document.getElementById('coatingSystemModal');
        if (el && window.bootstrap && typeof window.bootstrap.Modal === 'function') {
            const inst = window.bootstrap.Modal.getInstance(el) || new window.bootstrap.Modal(el);
            inst.show();
        } else if (typeof $ === 'function' && $.fn && typeof $.fn.modal === 'function') {
            $('#coatingSystemModal').modal('show');
        }
    } catch (e) {
        if (window.console) console.error(e);
    }
};

window.deleteCoatingRow = (id) => {
    const rowId = Number(id || 0);
    if (!rowId) return;
    if (!confirm('Xóa dòng hệ thống sơn này?')) return;

    if (!PRODUCT_ID) {
        coatingRows = coatingRows.filter(r => r.id != rowId);
        toastr.success('Đã xóa hệ thống sơn tạm thời');
        renderCoatingSystemTable();
        return;
    }

    $.post(API_URL, { action: 'del_coating_row', id: rowId }, res => {
        if (res && res.ok) {
            toastr.success('Đã xóa hệ thống sơn');
            loadCoatingSystem();
        } else {
            toastr.error(res?.msg || 'Không xóa được hệ thống sơn');
        }
    }, 'json').fail(() => {
        toastr.error('Lỗi kết nối khi xóa hệ thống sơn');
    });
};

$('#btnPickMainImage').on('click', () => {
    MediaLibrary.open({
        type: 'image',
        multiple: false,
        onSelect: (items) => {
            if (items && items.length > 0) {
                const item = items[0];
                const url = item.url;
                if (!PRODUCT_ID) {
                    mainImageUrl = resolveUrl(url);
                    renderMainImage();
                    toastr.success('Đã cập nhật ảnh đại diện tạm thời');
                    return;
                }
                $.post(API_URL, {
                    action: 'up_img_url',
                    id: PRODUCT_ID,
                    url: url
                }, res => {
                    if (res && res.ok) {
                        mainImageUrl = resolveUrl(url);
                        renderMainImage();
                        toastr.success('Đã cập nhật ảnh đại diện');
                    } else {
                        toastr.error(res?.msg || 'Không lưu được ảnh');
                    }
                }, 'json');
            }
        }
    });
});



$('#media_file_image').on('change', uploadMediaFile);
$('#media_file_video').on('change', function(){
    const file = this.files && this.files[0] ? this.files[0] : null;
    validateAndUploadVideo(file);
    this.value = '';
});

$(document).on('change', '.ship-method-active', function(){
    const $card = $(this).closest('.ghn-service-card');
    const idx = Number($card.data('idx'));
    if (Number.isNaN(idx) || !shippingMethods[idx]) return;
    shippingMethods[idx].active = $(this).is(':checked');
    $card.toggleClass('active', shippingMethods[idx].active);
    renderShippingMethodsEditor();
});

// Sự kiện cho Dữ liệu thi công
$('#btnConstructionEdit').on('click', () => {
    if (constructionData) {
        fillConstructionForm(constructionData);
    } else {
        fillConstructionForm(null);
    }
    try {
        const el = document.getElementById('constructionModal');
        if (el && window.bootstrap && typeof window.bootstrap.Modal === 'function') {
            const inst = window.bootstrap.Modal.getInstance(el) || new window.bootstrap.Modal(el);
            inst.show();
        } else if (typeof $ === 'function' && $.fn && typeof $.fn.modal === 'function') {
            $('#constructionModal').modal('show');
        }
    } catch (e) {
        if (window.console) console.error(e);
    }
});

// Init editor khi modal hiển thị để tránh lỗi khi textarea bị ẩn
(() => {
    const modalEl = document.getElementById('constructionModal');
    if (!modalEl) return;
    modalEl.addEventListener('shown.bs.modal', () => {
        initConstructionMethodEditor();
        syncConstructionToolsUi();
    });
})();

$('#pc_tools_enabled').on('change', () => {
    syncConstructionToolsUi();
});

$(document).on('click', '.pc-method-file-remove', function(){
    const path = String($(this).data('path') || '').trim();
    if (!path || !PRODUCT_ID) return;
    if (!confirm('Xóa file PDF này?')) return;
    $.post(API_URL, { action: 'del_construction_pdf', product_id: PRODUCT_ID, file: path }, res => {
        if (res && res.ok) {
            constructionData = res.data || constructionData;
            renderConstructionSummary();
            if (constructionData) fillConstructionForm(constructionData);
            toastr.success('Đã xóa PDF');
        } else {
            toastr.error(res?.msg || 'Không xóa được PDF');
        }
    }, 'json').fail(() => {
        toastr.error('Lỗi kết nối khi xóa PDF');
    });
});

$('#btnConstructionCancel').on('click', () => {
    try {
        const el = document.getElementById('constructionModal');
        if (el && window.bootstrap && typeof window.bootstrap.Modal === 'function') {
            const inst = window.bootstrap.Modal.getInstance(el);
            if (inst) inst.hide();
        } else if (typeof $ === 'function' && $.fn && typeof $.fn.modal === 'function') {
            $('#constructionModal').modal('hide');
        }
    } catch (e) {}
});

$('#pc_surface_enabled').on('change', () => {
    syncConstructionSurfaceUi();
});

$('#btnSaveConstruction').on('click', () => {
    syncConstructionMethodEditorToTextarea();
    const toolsEnabled = $('#pc_tools_enabled').is(':checked');
    const surfaceEnabled = $('#pc_surface_enabled').is(':checked');
    const surfaceNew = $('#pc_surface_new').val() || '';
    const surfaceOld = $('#pc_surface_old').val() || '';
    const methodText = $('#pc_method_text').val() || '';

    if (!PRODUCT_ID) {
        const fileInput = document.getElementById('pc_method_file');
        if (fileInput && fileInput.files && fileInput.files.length) {
            toastr.warning('Không thể tải file PDF khi chưa lưu sản phẩm. Vui lòng lưu sản phẩm trước.');
            return;
        }

        constructionData = {
            id: 0,
            product_id: 0,
            tools: packConstructionTools(toolsEnabled, $('#pc_tools').val() || ''),
            surface_prep_enabled: surfaceEnabled ? 1 : 0,
            surface_prep_new: surfaceNew,
            surface_prep_old: surfaceOld,
            method_text: methodText,
            method_file: ''
        };

        renderConstructionSummary();
        toastr.success('Đã lưu dữ liệu thi công tạm thời');
        
        try {
            const el = document.getElementById('constructionModal');
            if (el && window.bootstrap && typeof window.bootstrap.Modal === 'function') {
                const inst = window.bootstrap.Modal.getInstance(el);
                if (inst) inst.hide();
            }
        } catch(e){}
        return;
    }

    const fd = new FormData();
    fd.append('action', 'save_construction');
    fd.append('product_id', PRODUCT_ID);
    fd.append('tools', packConstructionTools(toolsEnabled, $('#pc_tools').val() || ''));
    fd.append('surface_enabled', surfaceEnabled ? 1 : 0);
    fd.append('surface_new', surfaceNew);
    fd.append('surface_old', surfaceOld);
    fd.append('method_text', methodText);
    const fileInput = document.getElementById('pc_method_file');
    if (fileInput && fileInput.files && fileInput.files.length) {
        Array.from(fileInput.files).forEach(f => {
            if (f) fd.append('method_files[]', f);
        });
    }
    $.ajax({
        url: API_URL,
        type: 'POST',
        data: fd,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: res => {
            if (res && res.ok) {
                constructionData = res.data || constructionData;
                renderConstructionSummary();
                if (constructionData) fillConstructionForm(constructionData);
                toastr.success('Đã lưu dữ liệu thi công');
                try {
                    const el = document.getElementById('constructionModal');
                    if (el && window.bootstrap && typeof window.bootstrap.Modal === 'function') {
                        const inst = window.bootstrap.Modal.getInstance(el);
                        if (inst) inst.hide();
                    }
                } catch(e){}
            } else {
                toastr.error(res?.msg || 'Không lưu được dữ liệu thi công');
            }
        },
        error: () => {
            toastr.error('Lỗi kết nối khi lưu dữ liệu thi công');
        }
    });
});

// Sửa / xóa dữ liệu thi công thông qua các nút trong bảng
window.editConstructionRow = () => {
    // Tái sử dụng form hiện có
    $('#btnConstructionEdit').trigger('click');
};

window.deleteConstructionRow = () => {
    if (!confirm('Xóa toàn bộ dữ liệu thi công của sản phẩm này?')) return;

    if (!PRODUCT_ID) {
        constructionData = null;
        renderConstructionSummary();
        fillConstructionForm(null);
        toastr.success('Đã xóa dữ liệu thi công tạm thời');
        return;
    }

    $.post(API_URL, { action: 'del_construction', product_id: PRODUCT_ID }, res => {
        if (res && res.ok) {
            constructionData = null;
            renderConstructionSummary();
            fillConstructionForm(null);
            toastr.success('Đã xóa dữ liệu thi công');
        } else {
            toastr.error(res?.msg || 'Không xóa được dữ liệu thi công');
        }
    }, 'json').fail(() => {
        toastr.error('Lỗi kết nối khi xóa dữ liệu thi công');
    });
};

$('#btnSaveProduct').on('click', () => {
    syncDescEditorToTextarea();
    shippingMethods = collectShippingMethodsFromEditor();

    // ── V5: Client-side validation ─────────────────────────────────────────
    const _name = String($('#p_name').val() || '').trim();
    const _sku  = String($('#p_sku').val() || '').trim();
    const _cat  = Number($('#p_cat').val() || 0);
    const _vat  = Number($('#p_vat').val() ?? 0);
    const _vatEnabled = $('#p_vat_enabled').is(':checked');

    if (!_name) {
        toastr.warning('Vui lòng nhập tên sản phẩm.');
        $('#p_name').focus();
        return;
    }
    if (_name.length < 3) {
        toastr.warning('Tên sản phẩm phải có ít nhất 3 ký tự.');
        $('#p_name').focus();
        return;
    }
    if (_sku && !/^[A-Za-z0-9\-_]+$/.test(_sku)) {
        toastr.warning('SKU chỉ được chứa chữ cái, số, dấu gạch ngang (-) hoặc gạch dưới (_).');
        $('#p_sku').focus();
        return;
    }
    if (_cat <= 0) {
        toastr.warning('Vui lòng chọn danh mục sản phẩm.');
        $('#p_cat').focus();
        return;
    }
    if (_vatEnabled && (_vat < 0 || _vat > 100)) {
        toastr.warning('Thuế VAT phải nằm trong khoảng 0 – 100%.');
        $('#p_vat').focus();
        return;
    }
    // ── End V5 ─────────────────────────────────────────────────────────────

    const shippingMethodsToSave = (Array.isArray(shippingMethods) ? shippingMethods : [])
        .filter(m => m && m.active)
        .map(m => ({ key: m.key, label: m.label, active: true }));

    if (!shippingMethodsToSave.length) {
        toastr.warning('Chọn ít nhất 1 dịch vụ vận chuyển.');
        return;
    }

    const $btnSave = $('#btnSaveProduct');
    const originalBtnHtml = $btnSave.html();
    $btnSave.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span> Đang lưu...');

    const statusVal = $('#p_status_quick_toggle').attr('data-active') === 'true' ? 'true' : 'false';

    const payload = {
        action: 'save_product',
        id: PRODUCT_ID,
        category_id: $('#p_cat').val(),
        product_name: $('#p_name').val(),
        slug: $('#p_slug').val(),
        sku: (String($('#p_sku').val() || '').trim()).toUpperCase(),
        manufacturer: $('#p_brand').val(),
        vat_enabled: $('#p_vat_enabled').is(':checked') ? 1 : 0,
        vat: $('#p_vat').val(),
        region_scope: JSON.stringify(collectRegionSelection()),
        resin_type: $('#p_resin_type').val(),
        voc: $('#p_voc').val(),
        solid_content: $('#p_solid_content').val(),
        coverage: $('#p_coverage').val(),
        gloss_level: $('#p_gloss_level').val(),
        drying_time: $('#p_drying_time').val(),
        stock_quantily: collectStockQuantily(),
        description: $('#p_description').val(),
        key_features: $('#p_key_features').val(),
        applications: $('#p_applications').val(),
        coating_system: '',
        storage: $('#p_storage').val(),
        paint_space: $('#p_paint_space').val(),
        paint_positions: JSON.stringify(collectPaintPositions()),
        paint_needs: JSON.stringify(collectPaintNeeds()),
        image_ratio: $('input[name="main_image_ratio"]:checked').val() || '1:1',
        preorder_enabled: $('input[name="preorder_mode"]:checked').val() || '0',

        shipping_methods: JSON.stringify(shippingMethodsToSave),
        media_gallery: JSON.stringify(mediaGallery),
        bulk_price_tiers: JSON.stringify(bulkPriceTiers),
        
        // Single-transaction staging details
        status: statusVal,
        image_url: mainImageUrl
    };

    if (!PRODUCT_ID) {
        payload.coating_system = JSON.stringify(coatingRows);
        payload.construction_data = JSON.stringify(constructionData);
        payload.variant_groups = JSON.stringify(variantGroups);
        payload.variants = JSON.stringify(variantsData);
    }

    $.post(API_URL, payload, res => {
        if(res && res.ok){
            isFormDirty = false;
            toastr.success('Đã lưu thông tin sản phẩm');
            if(res.new_id){
                window.location.href = '<?= h($baseUrl) ?>/admin/product-change?id=' + res.new_id;
            } else {
                window.location.reload();
            }
        } else {
            toastr.error(res?.msg || 'Không lưu được');
            $btnSave.prop('disabled', false).html(originalBtnHtml);
        }
    }, 'json').fail(() => {
        toastr.error('Lỗi kết nối khi lưu sản phẩm');
        $btnSave.prop('disabled', false).html(originalBtnHtml);
    });
});

$('#p_status_quick_toggle').on('click', function() {
    const $btn = $(this);
    const isCurrentlyActive = $btn.attr('data-active') === 'true';
    const isTargetActive = !isCurrentlyActive;
    
    if (!PRODUCT_ID) {
        updateQuickStatusLabel(isTargetActive);
        toastr.success('Đã thay đổi trạng thái tạm thời');
        return;
    }
    
    $btn.prop('disabled', true);
    const $label = $('#p_status_quick_label');
    const $icon = $('#p_status_quick_icon');
    $label.text('ĐANG XỬ LÝ...');
    $icon.attr('class', 'spinner-border spinner-border-sm me-1');

    const statusToSend = isTargetActive ? 'false' : 'true'; 

    $.post(API_URL, { action: 'toggle', id: PRODUCT_ID, s: statusToSend }, res => {
        if (res && res.ok) {
            toastr.success('Đã cập nhật trạng thái hiển thị');
            updateQuickStatusLabel(isTargetActive);
        } else {
            toastr.error(res?.msg || 'Không thể cập nhật trạng thái');
            updateQuickStatusLabel(isCurrentlyActive);
        }
    }, 'json').fail(() => {
        toastr.error('Lỗi kết nối khi cập nhật trạng thái');
        updateQuickStatusLabel(isCurrentlyActive);
    }).always(() => {
        $btn.prop('disabled', false);
    });
});

$('#mainImageFile').on('change', function(){
    if(!PRODUCT_ID) { toastr.warning('Lưu sản phẩm trước'); this.value = ''; return; }
    if(!this.files.length) return;
    const fd = new FormData();
    fd.append('action', 'up_img');
    fd.append('id', PRODUCT_ID);
    fd.append('img', this.files[0]);
    $.ajax({
        url: API_URL,
        type: 'POST',
        data: fd,
        contentType: false,
        processData: false,
        dataType: 'json',
        success: res => {
            if(res && res.ok){
                if(res.url) { mainImageUrl = resolveUrl(res.url); renderMainImage(); }
                toastr.success('Đã cập nhật ảnh đại diện');
            }
            else toastr.error('Không upload được');
        }
    });
    this.value = '';
});

let uploadingVariantId = 0;

window.pickVariantImage = (vid) => {
    const variantId = Number(vid || 0);
    if(!variantId) { toastr.warning('Thiếu phân loại'); return; }
    
    MediaLibrary.open({
        type: 'image',
        multiple: false,
        onSelect: (items) => {
            if (items && items.length > 0) {
                const item = items[0];
                const url = item.url;
                
                if (!PRODUCT_ID) {
                    const v = variantsData.find(v => Number(v.id || 0) === variantId);
                    if(v) v.image_url = url;
                    toastr.success('Đã cập nhật ảnh phân loại tạm thời');
                    loadVariants();
                    return;
                }

                $.post(API_URL, {
                    action: 'up_variant_img_url',
                    pid: PRODUCT_ID,
                    variant_id: variantId,
                    url: url
                }, res => {
                    if (res && res.ok) {
                        const v = variantsData.find(v => Number(v.id || 0) === variantId);
                        if(v) v.image_url = url;
                        toastr.success('Đã cập nhật ảnh phân loại');
                        loadVariants();
                    } else {
                        toastr.error(res?.msg || 'Không lưu được ảnh');
                    }
                }, 'json');
            }
        }
    });
};

$('#variantImageFile').on('change', function(){
    if(!PRODUCT_ID) { toastr.warning('Vui lòng dùng nút Chọn từ thư viện để thiết lập ảnh cho phân loại tạm thời.'); this.value = ''; return; }
    if(!uploadingVariantId) { toastr.warning('Thiếu phân loại'); this.value = ''; return; }
    if(!this.files || !this.files.length) return;

    const fd = new FormData();
    fd.append('action', 'up_variant_img');
    fd.append('pid', PRODUCT_ID);
    fd.append('variant_id', uploadingVariantId);
    fd.append('img', this.files[0]);

    $.ajax({
        url: API_URL,
        type: 'POST',
        data: fd,
        contentType: false,
        processData: false,
        dataType: 'json',
        success: res => {
            if(res && res.ok){
                const url = resolveUrl(res.url || '');
                const item = variantsData.find(v => Number(v.id || 0) === Number(uploadingVariantId));
                if(item) item.image_url = url || res.url || '';
                toastr.success('Đã cập nhật ảnh phân loại');
                loadVariants();
            } else {
                toastr.error(res?.msg || 'Không upload được');
            }
        },
        error: () => {
            toastr.error('Không upload được');
        },
        complete: () => {
            uploadingVariantId = 0;
            $('#variantImageFile').val('');
        }
    });
});

$(function(){
    const initDescriptionEditor = () => {
        if (descEditorReady || descEditorInitializing) return;
        
        if (!window.tinymce || typeof window.tinymce.init !== 'function') {
            descEditorInitAttempts += 1;
            if (descEditorInitAttempts <= 20) {
                setTimeout(initDescriptionEditor, 250);
            }
            return;
        }
        
        if (typeof window.initMceToolbar !== 'function') {
            toastr.error('Không tải được mce-toolbar.js');
            return;
        }

        descEditorInitializing = true;
        try {
            window.initMceToolbar({
                selector: '#p_description',
                uploadUrl: API_URL,
                baseUrl: BASE_URL,
                onChange: (html) => {
                    $('#p_description').val(String(html || ''));
                },
                onReady: (editor) => {
                    descEditorReady = true;
                    descEditorInitializing = false;
                    
                    // Ưu tiên content từ pendingDescHtml (thường là dữ liệu vừa load từ AJAX)
                    // Nếu không có thì lấy từ textarea (có thể là content mặc định hoặc do người dùng nhập trước khi editor load)
                    const readyContent = pendingDescHtml !== null
                        ? String(pendingDescHtml || '')
                        : String($('#p_description').val() || '');
                    
                    editor.setContent(readyContent);
                    pendingDescHtml = null;
                }
            });
        } catch (error) {
            descEditorInitializing = false;
            console.error('Init mce-toolbar failed:', error);
            toastr.error('MCE toolbar khởi tạo lỗi, vẫn tiếp tục tải dữ liệu sản phẩm');
        }
    };

    const bootstrapEditor = () => {
        if (PRODUCT_ID) {
            loadProduct();
            initDescriptionEditor();
            return;
        }
        initDescriptionEditor();
        applyRegionSelection('ALL');
        $('#p_paint_space').val('all');
        applyPaintPositionsSelection(['all']);
        applyPaintNeedsSelection(['all']);

        renderBulkPriceTiers();
        renderMediaGallery();
        shippingMethods = normalizeShippingMethods([]);
        renderShippingMethodsEditor();
        renderMainImage();
        loadVariants();

    };

    $.when(loadCats(), loadPartners()).always(bootstrapEditor);

    $('#p_vat_enabled').on('change', syncVatEnabledUi);
    syncVatEnabledUi();

    // Tự động sinh slug đã được gỡ bỏ theo yêu cầu. 
    // Người dùng có thể sử dụng nút làm mới cạnh ô nhập slug nếu muốn tạo lại.



    const setupGroupToggle = (allId, itemClass) => {
        let _syncing = false;

        // "Tất cả" thay đổi → áp lên tất cả items
        $(allId).on('change', function() {
            if (_syncing) return;
            _syncing = true;
            const isChecked = this.checked;
            $(itemClass).each(function() { this.checked = isChecked; });
            _syncing = false;
            // KHÔNG gọi syncAllState ở đây — user vừa tự click, không cần re-sync ngược lại
        });

        // Từng item thay đổi → cập nhật trạng thái "Tất cả"
        $(itemClass).on('change', function() {
            if (_syncing) return;
            _syncing = true;
            const total = $(itemClass).length;
            const checked = $(itemClass + ':checked').length;
            const $all = $(allId);
            $all.prop('indeterminate', checked > 0 && checked < total);
            if (!$all.prop('indeterminate')) {
                $all.prop('checked', checked === total);
            }
            _syncing = false;
        });
    };

    setupGroupToggle('#p_region_all', '.p-region-item');
    setupGroupToggle('#p_paint_pos_all', '.p-paint-pos-item');
    setupGroupToggle('#p_paint_need_all', '.p-paint-need-item');

$('input[name="main_image_ratio"]').on('change', renderMainImage);



window.removeBulkTier = (idx) => {
    if(typeof idx === 'undefined' || idx < 0 || idx >= bulkPriceTiers.length) return;
    if(!confirm('Xóa khoảng giá này?')) return;
    bulkPriceTiers.splice(idx, 1);
    renderBulkPriceTiers();
};

$('#btnAddBulkTier').on('click', () => {
    const name = ($('#tier_name').val() || '').trim() || `KHOẢNG GIÁ ${bulkPriceTiers.length + 1}`;
    const fromQty = Math.max(1, Number($('#tier_from').val() || 0));
    const toQty = Math.max(fromQty, Number($('#tier_to').val() || 0));
    const unitPrice = Number(String($('#tier_price').val() || '').replace(/\D/g, ''));
    if(!unitPrice){
        toastr.warning('Nhập đơn giá hợp lệ');
        return;
    }
    bulkPriceTiers.push({ name, from_qty: fromQty, to_qty: toQty, unit_price: unitPrice });
    $('#tier_name,#tier_price').val('');
    $('#tier_from').val('1');
    $('#tier_to').val('5');
    renderBulkPriceTiers();
});

    $(document).on('change input', 'input, select, textarea', () => {
        isFormDirty = true;
    });

    window.addEventListener('beforeunload', (e) => {
        if (isFormDirty) {
            e.preventDefault();
            e.returnValue = 'Bạn có thay đổi chưa lưu. Bạn có chắc chắn muốn rời đi?';
            return e.returnValue;
        }
    });

initBulkEditModal();
});
</script>
