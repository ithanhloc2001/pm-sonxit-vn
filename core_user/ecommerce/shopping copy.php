<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../config.php';
$ithanhloc->set_charset('utf8mb4');  
?>
<style>
/* CSS Grid Control via JS Variables */
.shopping.product-list-container {
    display: grid !important;
    grid-template-columns: repeat(var(--pc-cols, 6), minmax(0, 1fr)) !important;
    gap: 16px;
}

.filter-panel-col {
    /* align-self: flex-start để cột co lại theo nội dung của filter panel */
    align-self: flex-start !important;
}

/* Desktop: JS sẽ tự toggle class .is-pinned để simulate sticky */
.filter-panel {
    max-height: calc(100vh - 100px);
    overflow-y: auto;
    transition: none;
    -ms-overflow-style: none;
    scrollbar-width: none;
    will-change: transform;
}

.filter-panel.is-pinned {
    position: fixed !important;
    top: 80px !important;
    z-index: 100;
    /* width sẽ được JS set động để khớp với cột */
}

.filter-panel::-webkit-scrollbar {
    display: none !important;
}

@media (max-width: 768px) {
    .shopping.product-list-container {
        grid-template-columns: repeat(var(--mobile-cols, 2), minmax(0, 1fr)) !important;
    }
    .filter-panel {
        position: fixed !important;
        bottom: -100% !important;
        left: 0 !important;
        width: 100% !important;
        height: auto !important;
        max-height: 80vh !important;
        background: #fff !important;
        z-index: 2500 !important;
        border-radius: 24px 24px 0 0 !important;
        box-shadow: 0 -10px 30px rgba(0,0,0,0.15) !important;
        padding: 24px 20px !important;
        transition: bottom 0.3s cubic-bezier(0.16, 1, 0.3, 1) !important;
        margin-bottom: 0 !important;
        display: block !important;
        overflow-y: auto !important;
    }
    .filter-panel.show {
        bottom: 0 !important;
    }
    .filter-overlay {
        position: fixed;
        top: 0; left: 0;
        width: 100%; height: 100%;
        background: rgba(0,0,0,0.5);
        z-index: 2400;
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
    }
    .filter-overlay.show {
        opacity: 1;
        visibility: visible;
    }
}

/* Horizontal Filter Styles */
.filter-h-wrapper {
    background: #fff;
    padding: 10px 0;
}
.filter-h-section {
    margin-bottom: 10px;
}
.filter-h-title {
    font-weight: 700;
    font-size: 1.15rem;
    margin-bottom: 16px;
    color: #1f2937;
}
.filter-h-list {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
}
.filter-h-item {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 4px 10px;
    height: 38px;
    background: #f3f4f6;
    border: 1px solid transparent;
    border-radius: 50px;
    font-size: 0.8rem;
    font-weight: 500;
    color: #4b5563;
    cursor: pointer;
    transition: all 0.2s;
    white-space: nowrap;
    user-select: none;
    line-height: 1;
}
.filter-h-item i {
    font-size: 1.1rem;
    line-height: 1;
    display: flex;
    align-items: center;
}
.filter-h-item:hover {
    background: #e5e7eb;
}
.filter-h-item.active {
    background: #fff;
    border-color: var(--theme-primary);
    color: #1a1a1a;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}
.filter-h-item.highlight {
    border-color: var(--theme-primary);
    color: var(--theme-primary);
    background: #fff;
    border-width: 2px;
    padding: 9px 19px; /* Compensate for thicker border */
}
.filter-h-item.highlight i {
    color: var(--theme-primary);
}
.filter-h-item.dropdown::after {
    content: "";
    width: 12px;
    height: 12px;
    background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='currentColor'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E") no-repeat center;
    margin-left: 8px;
    opacity: 0.5;
    display: inline-block;
    transition: transform 0.2s;
}
.filter-h-item:hover.dropdown::after {
    transform: translateY(1px);
}

/* Search Box Styles */
.search-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}

.search-icon {
    position: absolute;
    left: 16px;
    color: #94a3b8;
    font-size: 1.1rem;
}

.search-input {
    width: 100%;
    padding: 10px 16px 10px 44px;
    border: 1px solid #e2e8f0;
    border-radius: 50px;
    font-size: 0.95rem;
    transition: all 0.2s;
    background: #fff;
    color: #1e293b;
}

.search-input:focus {
    outline: none;
    border-color: var(--theme-primary);
    box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
}

@media (max-width: 768px) {
    .filter-h-list {
        overflow-x: auto;
        flex-wrap: wrap;
        padding-bottom: 8px;
        margin: 0 -15px;
        padding: 0 15px 8px;
        -ms-overflow-style: none;
        scrollbar-width: none;
    }
    .filter-h-list::-webkit-scrollbar {
        display: none;
    }
    .filter-h-item {
        padding: 0 12px;
        height: 34px;
        font-size: 0.8rem;
        gap: 6px;
    }
    .filter-h-item i {
        font-size: 0.95rem;
    }
    .search-input {
        padding: 8px 12px 8px 40px;
        font-size: 0.9rem;
    }
    .search-icon {
        left: 14px;
        font-size: 1rem;
    }

    /* Mobile Filter Bar Styles - matching CellphoneS / modern e-commerce mobile design in image */
    .mobile-filter-bar {
        padding: 10px 4px;
        background: #fff;
    }
    .mobile-criteria-section {
        margin-bottom: 12px;
    }
    .mobile-filter-title {
        font-size: 15px;
        font-weight: 700;
        color: #1f2937;
        margin-bottom: 10px;
        padding-left: 4px;
    }
    .mobile-criteria-list {
        display: flex;
        overflow-x: auto;
        gap: 10px;
        padding: 2px 4px 6px;
        -ms-overflow-style: none;
        scrollbar-width: none;
    }
    .mobile-criteria-list::-webkit-scrollbar {
        display: none;
    }
    .mobile-criteria-item {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 6px 12px;
        height: 38px;
        background: #f3f4f6;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        font-size: 13.5px;
        font-weight: 500;
        color: #4b5563;
        cursor: pointer;
        white-space: nowrap;
        transition: all 0.2s ease;
        user-select: none;
    }
    .mobile-criteria-item i {
        font-size: 16px;
        display: flex;
        align-items: center;
    }
    .mobile-criteria-item .criteria-icon {
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .mobile-criteria-item.active {
        background: #fff;
        border-color: var(--theme-primary);
        color: var(--theme-primary);
        box-shadow: 0 2px 6px rgba(12, 76, 41, 0.08);
    }
    /* Style specifically for price filter pill matching image */
    .mobile-criteria-item.price-trigger {
        border-color: var(--theme-primary);
        color: var(--theme-primary);
        background: #fff;
    }
    .mobile-criteria-item.price-trigger .criteria-icon {
        background: var(--theme-primary);
        color: #fff;
        border-radius: 50%;
        width: 20px;
        height: 20px;
        font-size: 11px;
        font-weight: bold;
    }

    /* Tab Bar */
    .mobile-tab-bar {
        display: flex;
        border-bottom: 2px solid #e5e7eb;
        margin-bottom: 12px;
    }
    .mobile-tab-item {
        flex: 1;
        text-align: center;
        padding: 10px 4px;
        font-size: 14px;
        font-weight: 600;
        color: #4b5563;
        cursor: pointer;
        position: relative;
        white-space: nowrap;
        transition: all 0.2s ease;
    }
    .mobile-tab-item.active {
        color: var(--theme-primary);
    }
    .mobile-tab-item.active::after {
        content: "";
        position: absolute;
        bottom: -2px;
        left: 15%;
        width: 70%;
        height: 3px;
        background: var(--theme-primary);
        border-radius: 4px;
    }
}

/* Grand Popup Filter styles matching user image */
.main-filter-popup-wrapper {
    position: relative;
    display: inline-block;
}

.main-filter-popup {
    display: none;
    position: absolute;
    top: 100%;
    left: 0;
    z-index: 1200;
    width: 900px;
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 15px 45px rgba(0,0,0,0.12);
    padding: 28px;
    margin-top: 12px;
    border: 1px solid #edf2f7;
}

.main-filter-popup.show {
    display: block;
    animation: dropdownFadeIn 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}

.main-filter-grid {
    display: grid;
    grid-template-columns: 1.2fr 1.5fr 2.3fr;
    gap: 30px;
    margin-bottom: 24px;
}

.main-filter-col {
    display: flex;
    flex-direction: column;
}

.main-filter-col-title {
    font-weight: 700;
    font-size: 0.95rem;
    color: #1e293b;
    margin-bottom: 16px;
    border-bottom: 1px solid #f1f5f9;
    padding-bottom: 8px;
}

.main-filter-pills {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.main-filter-pills .filter-pill {
    display: inline-flex;
    align-items: center;
    padding: 6px 12px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 50px;
    font-size: 0.8rem;
    font-weight: 500;
    color: #475569;
    cursor: pointer;
    transition: all 0.2s;
    user-select: none;
}

.main-filter-pills .filter-pill:hover {
    background: #f1f5f9;
    border-color: #cbd5e1;
}

.main-filter-pills .filter-pill.selected {
    background: #fff;
    border-color: var(--theme-primary);
    color: var(--theme-primary);
    box-shadow: 0 2px 6px rgba(12, 76, 41, 0.08);
    font-weight: 600;
}

.main-filter-footer {
    display: flex;
    align-items: center;
    gap: 16px;
    border-top: 1px solid #edf2f7;
    padding-top: 20px;
}

.btn-popup-close {
    flex: 1;
    padding: 12px;
    background: #fff;
    border: 1px solid #cbd5e1;
    border-radius: 12px;
    font-weight: 600;
    color: #475569;
    cursor: pointer;
    transition: all 0.2s;
    text-align: center;
}

.btn-popup-close:hover {
    background: #f8fafc;
}

.btn-popup-apply {
    flex: 1.5;
    padding: 12px;
    background: var(--theme-primary);
    border: none;
    border-radius: 12px;
    font-weight: 600;
    color: #fff;
    cursor: pointer;
    transition: opacity 0.2s;
    text-align: center;
}

.btn-popup-apply:hover {
    opacity: 0.9;
}

@media (max-width: 992px) {
    .main-filter-popup {
        width: calc(100vw - 32px);
        position: fixed;
        top: auto;
        bottom: 0;
        left: 16px;
        right: 16px;
        border-radius: 24px 24px 0 0;
        box-shadow: 0 -10px 30px rgba(0,0,0,0.1);
        z-index: 2600;
    }
    .main-filter-grid {
        grid-template-columns: 1fr;
        gap: 20px;
        max-height: 50vh;
        overflow-y: auto;
    }
}

/* Brand Dropdown Menu */
.brand-dropdown-wrapper {
    position: relative;
    display: inline-block;
}

.brand-dropdown-menu {
    display: none;
    position: absolute;
    top: 100%;
    left: 0;
    z-index: 1100;
    width: 480px;
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 15px 40px rgba(0,0,0,0.12);
    padding: 24px;
    margin-top: 12px;
    border: 1px solid #edf2f7;
}

.brand-dropdown-menu.show {
    display: block;
    animation: dropdownFadeIn 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes dropdownFadeIn {
    from { opacity: 0; transform: translateY(-12px) scale(0.98); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}

.brand-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 24px;
    max-height: 300px;
    overflow-y: auto;
    padding-right: 4px;
}

/* Custom Scrollbar for Brand Grid */
.brand-grid::-webkit-scrollbar { width: 4px; }
.brand-grid::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 10px; }
.brand-grid::-webkit-scrollbar-thumb { background: #cbd5e0; border-radius: 10px; }

.brand-item-pill {
    padding: 10px 22px;
    border: 1px solid #e2e8f0;
    border-radius: 50px;
    font-size: 0.95rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    background: #fff;
    color: #4a5568;
    user-select: none;
}

.brand-item-pill:hover {
    background: #f7fafc;
    border-color: #cbd5e0;
}

.brand-item-pill.selected {
    border-color: #3182ce;
    color: #2b6cb0;
    background: #ebf8ff;
    box-shadow: 0 0 0 1px #3182ce;
}

.dropdown-footer {
    display: flex;
    gap: 12px;
    border-top: 1px solid #edf2f7;
    padding-top: 20px;
}

.btn-dropdown-close {
    flex: 1;
    padding: 12px;
    border: 1px solid #e2e8f0;
    background: #fff;
    border-radius: 12px;
    font-weight: 600;
    color: #4a5568;
    transition: background 0.2s;
}

.btn-dropdown-close:hover {
    background: #f7fafc;
}

.btn-dropdown-apply {
    flex: 1;
    padding: 12px;
    background: var(--theme-primary);
    color: #fff;
    border-radius: 12px;
    font-weight: 600;
    border: none;
    transition: opacity 0.2s;
}

.btn-dropdown-apply:hover {
    opacity: 0.9;
}

@media (max-width: 768px) {
    .brand-dropdown-menu {
        position: fixed;
        top: auto;
        bottom: 0;
        left: 0;
        width: 100%;
        border-radius: 24px 24px 0 0;
        margin-top: 0;
        padding: 24px 20px;
        box-shadow: 0 -10px 30px rgba(0,0,0,0.1);
    }
}

/* Price Dropdown Menu */
.price-dropdown-wrapper {
    position: relative;
    display: inline-block;
}

.price-dropdown-menu {
    display: none;
    position: absolute;
    top: 100%;
    left: 0;
    z-index: 1100;
    width: 400px;
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 15px 40px rgba(0,0,0,0.12);
    padding: 24px;
    margin-top: 12px;
    border: 1px solid #edf2f7;
}

.price-dropdown-menu.show {
    display: block;
    animation: dropdownFadeIn 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}

.price-dropdown-title {
    font-weight: 600;
    font-size: 1rem;
    margin-bottom: 20px;
    color: #4a5568;
}

.price-input-container {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 30px;
}

.price-field {
    flex: 1;
    position: relative;
}

.price-field input {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    font-size: 1rem;
    text-align: right;
    padding-right: 35px;
    color: #2d3748;
}

.price-field::after {
    content: "đ";
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #718096;
    font-size: 0.9rem;
}

/* Dual Range Slider */
.price-slider-group {
    position: relative;
    height: 40px;
    margin: 0 10px 24px;
}

.price-slider-base {
    position: absolute;
    top: 50%;
    width: 100%;
    height: 6px;
    background: #edf2f7;
    border-radius: 10px;
    transform: translateY(-50%);
}

.price-slider-fill {
    position: absolute;
    top: 50%;
    height: 6px;
    background: var(--theme-primary);
    border-radius: 10px;
    transform: translateY(-50%);
}

.price-slider-range {
    position: absolute;
    width: 100%;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    pointer-events: none;
    -webkit-appearance: none;
    margin: 0;
}

.price-slider-range::-webkit-slider-thumb {
    height: 24px;
    width: 24px;
    border-radius: 50%;
    background: #fff;
    border: 2px solid var(--theme-primary);
    pointer-events: auto;
    -webkit-appearance: none;
    box-shadow: 0 4px 10px rgba(12, 76, 41, 0.2);
    cursor: pointer;
}

.price-slider-range::-moz-range-thumb {
    height: 24px;
    width: 24px;
    border-radius: 50%;
    background: #fff;
    border: 2px solid var(--theme-primary);
    pointer-events: auto;
    box-shadow: 0 4px 10px rgba(12, 76, 41, 0.2);
    cursor: pointer;
}

/* Common Dropdown Footer adjustments */
.dropdown-footer .btn-apply-red {
    background: var(--theme-primary) !important;
    color: #fff !important;
    border: none !important;
}

@media (max-width: 768px) {
    .price-dropdown-menu {
        position: fixed;
        top: auto;
        bottom: 0;
        left: 0;
        width: 100%;
        border-radius: 24px 24px 0 0;
        margin-top: 0;
        padding: 24px 20px;
        box-shadow: 0 -10px 30px rgba(0,0,0,0.1);
    }
}

/* Space Dropdown Menu */
.space-dropdown-wrapper {
    position: relative;
    display: inline-block;
}

.space-dropdown-menu {
    display: none;
    position: absolute;
    top: 100%;
    left: 0;
    z-index: 1100;
    width: 320px;
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 15px 40px rgba(0,0,0,0.12);
    padding: 24px;
    margin-top: 12px;
    border: 1px solid #edf2f7;
}

.space-dropdown-menu.show {
    display: block;
    animation: dropdownFadeIn 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}

.space-option-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-bottom: 20px;
}

.space-option-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.2s;
    font-weight: 500;
    color: #4a5568;
}

.space-option-item:hover {
    background: #fff;
    border-color: #cbd5e0;
}

.space-option-item.selected {
    border-color: var(--theme-primary);
    background: #fff;
    color: var(--theme-primary);
}

.space-option-item i {
    font-size: 1.2rem;
}

@media (max-width: 768px) {
    .space-dropdown-menu {
        position: fixed;
        top: auto;
        bottom: 0;
        left: 0;
        width: 100%;
        border-radius: 24px 24px 0 0;
        margin-top: 0;
        padding: 24px 20px;
        box-shadow: 0 -10px 30px rgba(0,0,0,0.1);
    }
}

/* Positions Dropdown Menu */
.positions-dropdown-wrapper {
    position: relative;
    display: inline-block;
}

.positions-dropdown-menu {
    display: none;
    position: absolute;
    top: 100%;
    left: 0;
    z-index: 1100;
    width: 320px;
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 15px 40px rgba(0,0,0,0.12);
    padding: 24px;
    margin-top: 12px;
    border: 1px solid #edf2f7;
}

.positions-dropdown-menu.show {
    display: block;
    animation: dropdownFadeIn 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}

.positions-option-list {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    margin-bottom: 20px;
}

.positions-option-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 0.9rem;
    font-weight: 500;
    color: #4a5568;
}

.positions-option-item:hover {
    background: #fff;
    border-color: #cbd5e0;
}

.positions-option-item.selected {
    border-color: var(--theme-primary);
    background: #fff;
    color: var(--theme-primary);
}

.positions-option-item[data-value="all"] {
    grid-column: span 2;
    justify-content: center;
}

@media (max-width: 768px) {
    .positions-dropdown-menu {
        position: fixed;
        top: auto;
        bottom: 0;
        left: 0;
        width: 100%;
        border-radius: 24px 24px 0 0;
        margin-top: 0;
        padding: 24px 20px;
        box-shadow: 0 -10px 30px rgba(0,0,0,0.1);
        z-index: 2100;
    }
}

/* Category Dropdown Menu */
.category-dropdown-wrapper {
    position: relative;
    display: inline-block;
}

.category-dropdown-menu {
    display: none;
    position: absolute;
    top: 100%;
    left: 0;
    z-index: 1100;
    width: 320px;
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 15px 40px rgba(0,0,0,0.12);
    padding: 24px;
    margin-top: 12px;
    border: 1px solid #edf2f7;
}

.category-dropdown-menu.show {
    display: block;
    animation: dropdownFadeIn 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}

.category-grid {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-bottom: 24px;
    max-height: 300px;
    overflow-y: auto;
}

.category-item-pill {
    padding: 12px 16px;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    font-size: 0.95rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    background: #fff;
    color: #4a5568;
}

.category-item-pill:hover {
    background: #f7fafc;
}

.category-item-pill.selected {
    border-color: var(--theme-primary);
    color: var(--theme-primary);
    background: #ebf8ff;
}

@media (max-width: 768px) {
    .category-dropdown-menu {
        position: fixed;
        top: auto;
        bottom: 0;
        left: 0;
        width: 100%;
        border-radius: 24px 24px 0 0;
        margin-top: 0;
        padding: 24px 20px;
        box-shadow: 0 -10px 30px rgba(0,0,0,0.1);
        z-index: 2100;
    }
}
/* Needs Dropdown Menu */
.needs-dropdown-wrapper {
    position: relative;
    display: inline-block;
}

.needs-dropdown-menu {
    display: none;
    position: absolute;
    top: 100%;
    left: 0;
    z-index: 1100;
    width: 450px;
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 15px 40px rgba(0,0,0,0.12);
    padding: 24px;
    margin-top: 12px;
    border: 1px solid #edf2f7;
}

.needs-dropdown-menu.show {
    display: block;
    animation: dropdownFadeIn 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}

.needs-option-list {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    margin-bottom: 20px;
}

.needs-option-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 0.85rem;
    font-weight: 500;
    color: #4a5568;
}

.needs-option-item:hover {
    background: #fff;
    border-color: #cbd5e0;
}

.needs-option-item.selected {
    border-color: var(--theme-primary);
    background: #fff;
    color: var(--theme-primary);
}

.needs-option-item[data-value="all"] {
    grid-column: span 2;
    justify-content: center;
}

@media (max-width: 768px) {
    .needs-dropdown-menu {
        position: fixed;
        top: auto;
        bottom: 0;
        left: 0;
        width: 100%;
        border-radius: 24px 24px 0 0;
        margin-top: 0;
        padding: 24px 20px;
        box-shadow: 0 -10px 30px rgba(0,0,0,0.1);
        z-index: 2100;
    }
}

/* Active Filters Section */
.active-filters-section {
    display: none;
    margin-bottom: 20px;
    padding: 0 8px;
}

.active-filters-section.show {
    display: block;
}

.active-filters-label {
    font-size: 1.1rem;
    font-weight: 700;
    margin-bottom: 12px;
    color: #1a202c;
}

.active-filters-list {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 12px;
}

.filter-tag {
    display: inline-flex;
    align-items: center;
    background: #f7fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 8px 16px;
    font-size: 0.9rem;
    color: #4a5568;
    transition: all 0.2s;
}

.filter-tag .tag-label {
    color: #718096;
    margin-right: 4px;
}

.filter-tag .tag-value {
    color: var(--theme-primary);
    font-weight: 600;
    margin-right: 8px;
}

.filter-tag .tag-remove {
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 20px;
    height: 20px;
    background: #edf2f7;
    border-radius: 50%;
    color: #4a5568;
    font-size: 14px;
    transition: all 0.2s;
}

.filter-tag .tag-remove:hover {
    background: #e2e8f0;
    color: #ef4444;
}

.clear-all-filters {
    font-size: 0.9rem;
    color: var(--theme-primary);
    text-decoration: none;
    font-weight: 500;
    margin-left: 8px;
    cursor: pointer;
}

.clear-all-filters:hover {
    text-decoration: underline;
}

@media (max-width: 768px) {
    .active-filters-list {
        gap: 8px;
    }
    .filter-tag {
        padding: 6px 12px;
        font-size: 0.8rem;
    }
}
</style>
<div class="row">
    <div class="col-12 col-md-2 filter-panel-col">
        <aside class="filter-panel">
            <div class="filter-head"><i class="bi bi-funnel"></i> Bộ lọc tìm kiếm</div>
            <div class="filter-tabs" role="tablist" aria-label="Bộ lọc">
                <button type="button" class="filter-tab is-active" data-section="cat">Danh mục</button>
                <button type="button" class="filter-tab" data-section="brand">Hãng</button>
                <button type="button" class="filter-tab" data-section="price">Giá</button>
                <button type="button" class="filter-tab" data-section="sort">Sắp xếp</button>
                <button type="button" class="filter-tab" data-section="rating">Đánh giá</button>
            </div>
            <div class="filter-section is-active" data-section="cat">
                <div class="filter-group">
                    <div class="filter-title">Danh mục</div>
                    <div id="filterCategories"></div>
                </div>
            </div>
            <div class="filter-section" data-section="brand">
                <div class="filter-group">
                    <div class="filter-title">Hãng đối tác</div>
                    <div id="filterBrands"></div>
                </div>
            </div>
            <div class="filter-section" data-section="price">
                <div class="filter-group">
                    <div class="filter-title">Khoảng giá</div>
                    <label class="filter-option"><input type="radio" name="priceFilter" data-min="0" data-max="1000000"> Dưới 1 triệu</label>
                    <label class="filter-option"><input type="radio" name="priceFilter" data-min="1000000" data-max="3000000"> 1 - 3 triệu</label>
                    <label class="filter-option"><input type="radio" name="priceFilter" data-min="3000000" data-max=""> Trên 3 triệu</label>
                    <div class="mt-3">
                        <div class="filter-title mb-2" style="font-size: 0.6rem; opacity: 0.8;">Hoặc nhập khoảng giá</div>
                        <div class="d-flex align-items-center gap-2">
                            <input type="number" id="priceMin" class="form-control form-control-sm" placeholder="Từ" style="border-radius: 4px;">
                            <span class="text-muted">-</span>
                            <input type="number" id="priceMax" class="form-control form-control-sm" placeholder="Đến" style="border-radius: 4px;">
                        </div>
                    </div>
                </div>
            </div>
            <div class="filter-section" data-section="sort">
                <div class="filter-group mt-3 d-none">
                    <div class="filter-title">Trạng thái</div>
                    <label class="filter-option"><input type="checkbox" id="stockOnly" checked> Còn hàng</label>
                    <label class="filter-option"><input type="checkbox" id="promoOnly"> Đang khuyến mãi</label>
                </div>
                <div class="filter-group mt-3">
                    <div class="filter-title">Sắp xếp</div>
                    <label class="filter-option"><input type="radio" name="sortFilter" data-sort="newest" checked> Mới nhất</label>
                    <label class="filter-option"><input type="radio" name="sortFilter" data-sort="price_asc"> Giá thấp</label>
                    <label class="filter-option"><input type="radio" name="sortFilter" data-sort="price_desc"> Giá cao</label>
                    <label class="filter-option"><input type="radio" name="sortFilter" data-sort="name_asc"> Tên A-Z</label>
                    <label class="filter-option"><input type="radio" name="sortFilter" data-sort="name_desc"> Tên Z-A</label>
                </div>
            </div>
            <div class="filter-section" data-section="rating">
                <div class="filter-group">
                    <div class="filter-title">Theo đánh giá</div>
                    <label class="filter-option">
                        <input type="radio" name="ratingFilter" data-rating="5">
                        <span class="filter-stars"><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i></span>
                    </label>
                    <label class="filter-option">
                        <input type="radio" name="ratingFilter" data-rating="4">
                        <span class="filter-stars"><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i></span>
                    </label>
                    <label class="filter-option">
                        <input type="radio" name="ratingFilter" data-rating="3">
                        <span class="filter-stars"><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i></span>
                    </label>
                    <label class="filter-option">
                        <input type="radio" name="ratingFilter" data-rating="2">
                        <span class="filter-stars"><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i></span>
                    </label>
                    <label class="filter-option">
                        <input type="radio" name="ratingFilter" data-rating="1">
                        <span class="filter-stars"><i class="bi bi-star-fill"></i></span>
                    </label>
                </div>
            </div>
            <div class="filter-actions">
                <button class="filter-btn primary" type="button" id="filterApplyBtn">Áp dụng</button>
                <button class="filter-btn" type="button" id="filterClearBtn">Xóa tất cả</button>
            </div>
        </aside>
    </div>
    <div class="col-12 col-md-10">
        <section class="_card _border-0 _shadow-sm product-panel">
        <div class="_card-body">
            <!-- Bộ lọc -->
            <div class="filter-h-wrapper p-2">
                <!-- Desktop Filter Bar -->
                <div class="desktop-filter-bar d-none d-md-block">
                    <div class="filter-h-section p-2">
                        <div class="filter-h-title">Bộ lọc nâng cao</div>
                        <div class="search-wrapper mb-3 ">
                            <i class="bi bi-search search-icon"></i>
                            <input id="searchBox" class="search-input" placeholder="Tìm kiếm sản phẩm...">
                        </div>
                        <div class="filter-h-list" id="h-filter-criteria">
                            <!-- Main Multi-Column Filter Popup matching reference image -->
                            <div class="main-filter-popup-wrapper">
                                <div class="filter-h-item dropdown highlight" id="h-main-filter-btn"><i class="bi bi-funnel-fill"></i> Bộ lọc</div>
                                <div class="main-filter-popup" id="mainFilterPopup">
                                    <div class="main-filter-grid">
                                        <!-- Column 1: Không gian sử dụng -->
                                        <div class="main-filter-col">
                                            <div class="main-filter-col-title">Không gian sử dụng</div>
                                            <div class="main-filter-pills" id="popup-space-list">
                                                <span class="filter-pill selected" data-value="all">Tất cả</span>
                                                <span class="filter-pill" data-value="Nội thất">Nội thất</span>
                                                <span class="filter-pill" data-value="Ngoại thất">Ngoại thất</span>
                                            </div>
                                        </div>
                                        
                                        <!-- Column 2: Vị trí thi công -->
                                        <div class="main-filter-col">
                                            <div class="main-filter-col-title">Vị trí thi công</div>
                                            <div class="main-filter-pills" id="popup-positions-list">
                                                <span class="filter-pill selected" data-value="all">Tất cả</span>
                                                <span class="filter-pill" data-value="Tường">Tường</span>
                                                <span class="filter-pill" data-value="Cửa">Cửa</span>
                                                <span class="filter-pill" data-value="Trần">Trần</span>
                                                <span class="filter-pill" data-value="Viền">Viền</span>
                                                <span class="filter-pill" data-value="Cửa sổ">Cửa sổ</span>
                                                <span class="filter-pill" data-value="Sàn">Sàn</span>
                                                <span class="filter-pill" data-value="Mái tôn">Mái tôn</span>
                                                <span class="filter-pill" data-value="Khác">Khác</span>
                                            </div>
                                        </div>
                                        
                                        <!-- Column 3: Nhu cầu sử dụng -->
                                        <div class="main-filter-col">
                                            <div class="main-filter-col-title">Nhu cầu sử dụng</div>
                                            <div class="main-filter-pills" id="popup-needs-list">
                                                <span class="filter-pill selected" data-value="all">Tất cả</span>
                                                <span class="filter-pill" data-value="Phòng khách">Phòng khách</span>
                                                <span class="filter-pill" data-value="Phòng trẻ em">Phòng trẻ em</span>
                                                <span class="filter-pill" data-value="Phòng tắm / WC">Phòng tắm / WC</span>
                                                <span class="filter-pill" data-value="Phòng ngủ">Phòng ngủ</span>
                                                <span class="filter-pill" data-value="Phòng bếp">Phòng bếp</span>
                                                <span class="filter-pill" data-value="Mặt tiền / ngoài trời">Mặt tiền / ngoài trời</span>
                                                <span class="filter-pill" data-value="Chống thấm">Chống thấm</span>
                                                <span class="filter-pill" data-value="Chống ẩm mốc">Chống ẩm mốc</span>
                                                <span class="filter-pill" data-value="Chống bám bụi">Chống bám bụi</span>
                                                <span class="filter-pill" data-value="Chống kiềm">Chống kiềm</span>
                                                <span class="filter-pill" data-value="Chống UV">Chống UV</span>
                                                <span class="filter-pill" data-value="Chống rỉ sét">Chống rỉ sét</span>
                                                <span class="filter-pill" data-value="Chống nứt">Chống nứt</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="main-filter-footer">
                                        <button class="btn-popup-close" id="btn-popup-close" type="button">Đóng</button>
                                        <button class="btn-popup-apply" id="btn-popup-apply" type="button">Xem kết quả</button>
                                    </div>
                                </div>
                            </div>
                            <div class="filter-h-item" data-filter="default"><i class="bi bi-arrow-up-down"></i> Mặc định</div>
                            <div class="filter-h-item" data-filter="stock"><i class="bi bi-truck"></i> Sẵn hàng</div>
                            <div class="filter-h-item" data-filter="new"><i class="bi bi-cart-plus"></i> Hàng mới về</div>
                            <div class="category-dropdown-wrapper">
                                <div class="filter-h-item dropdown" id="h-category-filter" data-filter="category"><i class="bi bi-grid"></i> Danh mục</div>
                                <div class="category-dropdown-menu" id="categoryMenu">
                                    <div class="price-dropdown-title mb-3 fw-bold">Chọn danh mục sản phẩm</div>
                                    <div class="category-grid" id="h-category-grid">
                                        <!-- Categories will be injected here -->
                                    </div>
                                    <div class="dropdown-footer d-grid d-grid gap-2 d-md-flex justify-content-md-end">
                                        <button class="btn btn-sm btn-secondary" type="button" id="btn-category-close">Đóng</button>
                                        <button class="btn btn-sm btn-primary" type="button" id="btn-category-apply">Xem kết quả</button>
                                    </div>
                                </div>
                            </div>
                            <div class="price-dropdown-wrapper">
                                <div class="filter-h-item dropdown" id="h-price-filter"><i class="bi bi-coin"></i> Xem theo giá</div>
                                <div class="price-dropdown-menu" id="priceMenu">
                                    <div class="price-dropdown-title">Hãy chọn mức giá phù hợp với bạn</div>
                                    <div class="price-input-container">
                                        <div class="price-field">
                                            <input type="text" id="h-price-min" placeholder="0">
                                        </div>
                                        <span class="text-muted">-</span>
                                        <div class="price-field">
                                            <input type="text" id="h-price-max" placeholder="50.000.000">
                                        </div>
                                    </div>
                                    <div class="price-slider-group">
                                        <div class="price-slider-base"></div>
                                        <div class="price-slider-fill" id="h-price-fill"></div>
                                        <input type="range" class="price-slider-range" id="h-slider-min" min="0" max="50000000" step="100000" value="0">
                                        <input type="range" class="price-slider-range" id="h-slider-max" min="0" max="50000000" step="100000" value="50000000">
                                    </div>
                                    <div class="dropdown-footer d-grid d-grid gap-2 d-md-flex justify-content-md-end">
                                        <button class="btn btn-secondary" type="button" id="btn-price-close">Đóng</button>
                                        <button class="btn btn-primary" type="button" id="btn-price-apply">Xem kết quả</button>
                                    </div>
                                </div>
                            </div>
                            <div class="brand-dropdown-wrapper">
                                <div class="filter-h-item dropdown" id="h-brand-filter">Hãng sản xuất</div>
                                <div class="brand-dropdown-menu" id="brandMenu">
                                    <div class="brand-grid" id="h-brand-grid">
                                        <!-- Brands will be injected here -->
                                    </div>
                                    <div class="dropdown-footer d-grid d-grid gap-2 d-md-flex justify-content-md-end">
                                        <button class="btn btn-sm btn-secondary" type="button" id="btn-brand-close">Đóng</button>
                                        <button class="btn btn-sm btn-primary" type="button" id="btn-brand-apply">Xem kết quả</button>
                                    </div>
                                </div>
                            </div>
                            <div class="space-dropdown-wrapper">
                                <div class="filter-h-item dropdown" id="h-space-filter" data-filter="paint_space">Không gian</div>
                                <div class="space-dropdown-menu" id="spaceMenu">
                                    <div class="space-dropdown-title mb-3 fw-bold">Chọn không gian sử dụng</div>
                                    <div class="space-option-list" id="h-space-list">
                                        <div class="space-option-item selected" data-value="all">
                                            Tất cả
                                        </div>
                                        <div class="space-option-item" data-value="Nội thất">
                                            Nội thất
                                        </div>
                                        <div class="space-option-item" data-value="Ngoại thất">
                                            Ngoại thất
                                        </div>
                                    </div>
                                    <div class="dropdown-footer d-grid d-grid gap-2 d-md-flex justify-content-md-end">
                                        <button class="btn btn-sm btn-secondary" type="button" id="btn-space-close">Đóng</button>
                                        <button class="btn btn-sm btn-primary" type="button" id="btn-space-apply">Xem kết quả</button>
                                    </div>
                                </div>
                            </div>
                            <div class="positions-dropdown-wrapper">
                                <div class="filter-h-item dropdown" id="h-positions-filter" data-filter="paint_positions">Vị trí</div>
                                <div class="positions-dropdown-menu" id="positionsMenu">
                                    <div class="positions-dropdown-title mb-3 fw-bold">Chọn vị trí thi công</div>
                                    <div class="positions-option-list" id="h-positions-list">
                                        <div class="positions-option-item selected" data-value="all">Tất cả</div>
                                        <div class="positions-option-item" data-value="Tường">Tường</div>
                                        <div class="positions-option-item" data-value="Cửa">Cửa</div>
                                        <div class="positions-option-item" data-value="Trần">Trần</div>
                                        <div class="positions-option-item" data-value="Viền">Viền</div>
                                        <div class="positions-option-item" data-value="Cửa sổ">Cửa sổ</div>
                                        <div class="positions-option-item" data-value="Sàn">Sàn</div>
                                        <div class="positions-option-item" data-value="Mái tôn">Mái tôn</div>
                                        <div class="positions-option-item" data-value="Khác">Khác</div>
                                    </div>
                                    <div class="dropdown-footer d-grid d-grid gap-2 d-md-flex justify-content-md-end">
                                        <button class="btn btn-sm btn-secondary" type="button" id="btn-positions-close">Đóng</button>
                                        <button class="btn btn-sm btn-primary" type="button" id="btn-positions-apply">Xem kết quả</button>
                                    </div>
                                </div>
                            </div>
                            <div class="needs-dropdown-wrapper">
                                <div class="filter-h-item dropdown" id="h-needs-filter" data-filter="paint_needs">Nhu cầu</div>
                                <div class="needs-dropdown-menu" id="needsMenu">
                                    <div class="needs-dropdown-title mb-3 fw-bold">Chọn nhu cầu sử dụng</div>
                                    <div class="needs-option-list" id="h-needs-list">
                                        <div class="needs-option-item selected" data-value="all">Tất cả</div>
                                        <div class="needs-option-item" data-value="Phòng khách">Phòng khách</div>
                                        <div class="needs-option-item" data-value="Phòng trẻ em">Phòng trẻ em</div>
                                        <div class="needs-option-item" data-value="Phòng tắm / WC">Phòng tắm / WC</div>
                                        <div class="needs-option-item" data-value="Phòng ngủ">Phòng ngủ</div>
                                        <div class="needs-option-item" data-value="Phòng bếp">Phòng bếp</div>
                                        <div class="needs-option-item" data-value="Mặt tiền / ngoài trời">Mặt tiền / ngoài trời</div>
                                        <div class="needs-option-item" data-value="Chống thấm">Chống thấm</div>
                                        <div class="needs-option-item" data-value="Chống ẩm mốc">Chống ẩm mốc</div>
                                        <div class="needs-option-item" data-value="Chống bám bụi">Chống bám bụi</div>
                                        <div class="needs-option-item" data-value="Chống kiềm">Chống kiềm</div>
                                        <div class="needs-option-item" data-value="Chống UV">Chống UV</div>
                                        <div class="needs-option-item" data-value="Chống rỉ sét">Chống rỉ sét</div>
                                        <div class="needs-option-item" data-value="Chống nứt">Chống nứt</div>
                                    </div>
                                    <div class="dropdown-footer d-grid d-grid gap-2 d-md-flex justify-content-md-end">
                                        <button class="btn btn-sm btn-secondary" type="button" id="btn-needs-close">Đóng</button>
                                        <button class="btn btn-sm btn-primary" type="button" id="btn-needs-apply">Xem kết quả</button>
                                    </div>
                                </div>
                            </div>
                            
                        </div>
                    </div>
                    <div class="filter-h-section p-2">
                        <div class="filter-h-title">Sắp xếp theo</div>
                        <div class="filter-h-list" id="h-sort-options">
                            <div class="filter-h-item active" data-sort="newest"><i class="bi bi-star"></i> Phổ biến</div>
                            <div class="filter-h-item" data-sort="rating"><i class="bi bi-star"></i> Đánh giá</div>
                            <div class="filter-h-item" data-sort="promo"><i class="bi bi-percent"></i> Khuyến mãi</div>
                            <div class="filter-h-item" data-sort="price_asc"><i class="bi bi-sort-numeric-down"></i> Giá Thấp - Cao</div>
                            <div class="filter-h-item" data-sort="price_desc"><i class="bi bi-sort-numeric-up-alt"></i> Giá Cao - Thấp</div>
                        </div>
                    </div>
                </div>

                <!-- Mobile Filter Bar (Exactly matching user's reference image) -->
                <div class="mobile-filter-bar d-block d-md-none">
                    <!-- Mobile Search Box -->
                    <div class="search-wrapper mb-3">
                        <i class="bi bi-search search-icon"></i>
                        <input id="mobileSearchBox" class="search-input" placeholder="Tìm kiếm sản phẩm...">
                    </div>

                    <!-- Chọn theo tiêu chí Section -->
                    <div class="mobile-criteria-section">
                        <div class="mobile-filter-title">Chọn theo tiêu chí</div>
                        <div class="mobile-criteria-list">
                            <div class="mobile-criteria-item" id="m-criteria-stock" data-filter="stock">
                                <span class="criteria-icon"><i class="bi bi-truck"></i></span>
                                <span class="criteria-label">Sẵn hàng</span>
                            </div>
                            <div class="mobile-criteria-item price-trigger" id="m-criteria-price">
                                <span class="criteria-icon"><i class="bi bi-coin"></i></span>
                                <span class="criteria-label">Xem theo giá</span>
                            </div>
                            <div class="mobile-criteria-item" id="m-criteria-new" data-filter="new">
                                <span class="criteria-icon"><i class="bi bi-cart-plus"></i></span>
                                <span class="criteria-label">Hàng mới</span>
                            </div>
                            <div class="mobile-criteria-item" id="m-criteria-cat" data-filter="category">
                                <span class="criteria-icon"><i class="bi bi-grid"></i></span>
                                <span class="criteria-label">Danh mục</span>
                            </div>
                        </div>
                    </div>

                    <!-- Mobile Tabs Row -->
                    <div class="mobile-tab-bar">
                        <div class="mobile-tab-item active" id="m-tab-popular" data-sort="newest">Phổ biến</div>
                        <div class="mobile-tab-item" id="m-tab-promo" data-sort="promo">Khuyến mãi HOT</div>
                        <div class="mobile-tab-item" id="m-tab-price" data-sort="price_asc">Giá <i class="bi bi-chevron-expand"></i></div>
                        <div class="mobile-tab-item" id="m-tab-filter">Bộ lọc <i class="bi bi-funnel-fill text-muted ms-1"></i></div>
                    </div>

                    <!-- Semi-transparent overlay for mobile drawer/panel -->
                    <div class="filter-overlay" id="mobileFilterOverlay"></div>
                </div>
            </div>
            <!-- /./ -->
            <div id="activeFiltersSection" class="active-filters-section">
                <div class="active-filters-label">Đang lọc theo</div>
                <div class="active-filters-list" id="activeFiltersList">
                    <!-- Tags will be injected here -->
                </div>
            </div>
            <div id="productGrid" class="shopping product-list-container"></div>
            <div id="emptyProducts" class="text-center text-muted py-4" style="display:none;">Không tìm thấy sản phẩm.</div>
            <div class="text-center mt-3" id="loadMoreWrap" style="display:none;">
                <button class="btn btn-outline-primary btn-sm px-4" id="loadMoreBtn">Xem thêm</button>
            </div>
            <div id="productSentinel" style="height: 10px;"></div>
        </div>
    </section>
    </div>
</div>
<script>
(function(){
    const API = '<?= h($baseUrl) ?>/core_user/ecommerce/ajax/cart.php';
    const BASE_URL = (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') ? '' : '<?= h($baseUrl) ?>';
    const FALLBACK_IMG = '<?= h($site_fallback_logo ? rtrim((string)$baseUrl, "/") . "/" . ltrim((string)$site_fallback_logo, "/") : "") ?>';

    // Cấu hình số lượng sản phẩm trên mỗi hàng
    const PRODUCT_LIMIT_PC = 6;
    const PRODUCT_LIMIT_MOBILE = 2;
    const pageSize = 24;

    let page = 0; let hasMore = true; let loading = false;
    let miniCartState = [];
    const urlParams = new URLSearchParams(window.location.search);
    const initialSearch = String(urlParams.get('q') || '').trim();
    const initialCatId = Number(<?= json_encode($_GET['cat'] ?? 0) ?>) || Number(urlParams.get('cat') || 0);
    let initialCatSlug = String(<?= json_encode($_GET['cat_slug'] ?? '') ?> || urlParams.get('cat_slug') || '').trim();
    const initialBrand = String(urlParams.get('brand') || '').trim();
    const normalizeBrandKey = (value) => String(value || '')
        .toLowerCase()
        .replace(/[\u2010-\u2015]/g, '-')
        .replace(/\s+/g, '')
        .replace(/-+/g, '-')
        .trim();
    let searchTerm = initialSearch; let sortVal = 'newest';
    let initialFetchDone = false;
    let pendingResetFetch = false;
    let cats = [];
    let brands = [];
    const filterState = { catFilters: [], brandFilters: [], priceMin: null, priceMax: null, ratingMin: 0, stockOnly: true, promoOnly: false, paintSpaceFilters: [], paintPositionsFilters: [], paintNeedsFilters: [] };
    let initialBrandApplied = false;
    const FAVORITE_API = '<?= h($baseUrl) ?>/core_user/ecommerce/ajax/favorite.php';
    // Các phần tử DOM chính cần thao tác
    const $grid = $('#productGrid');
    const $search = $('#searchBox');
    const $loadMore = $('#loadMoreWrap');
    const $empty = $('#emptyProducts');
    const $filterCategories = $('#filterCategories');
    const $filterBrands = $('#filterBrands');
    const $activeBrandNotice = $('#activeBrandNotice');
    const $filterApply = $('#filterApplyBtn');
    const $filterClear = $('#filterClearBtn');
    const $filterTabs = $('.filter-tab');
    const $filterSections = $('.filter-section');


    // Áp dụng cấu hình grid từ JS
    if ($grid.length) {
        $grid[0].style.setProperty('--pc-cols', PRODUCT_LIMIT_PC);
        $grid[0].style.setProperty('--mobile-cols', PRODUCT_LIMIT_MOBILE);
    }

    const fmtPrice = (n) => {
        if (window.pmFormatPrice && typeof window.pmFormatPrice === 'function') {
            return window.pmFormatPrice(n);
        }
        const num = Number(n) || 0;
        return new Intl.NumberFormat('vi-VN').format(num) + 'đ';
    };
    function esc(str){
        return $('<div>').text(String(str || '')).html();
    }
    // Hàm làm nổi bật các phần text liên quan đến khuyến mãi trong promo_highlights
    function highlightPromoText(text){
        const raw = String(text || '');
        if (!raw) return '';
        let safe = $('<div>').text(raw).html();
        safe = safe.replace(/(\d[\d\.]*\s*(?:đ|₫|VNĐ|VND|%))/i, '<strong>$1</strong>');
        safe = safe.replace(/(deal\s*sốc)/i, '<strong>$1</strong>');
        return safe;
    }
    // Hàm chuyển URL ảnh thành đường dẫn tuyệt đối, nếu đã là URL tuyệt đối thì giữ nguyên
    function toAbs(url){
        const raw = String(url || '').trim();
        if (!raw) return '';
        if (/^(https?:)?\/\//i.test(raw) || /^data:/i.test(raw)) return raw;
        const base = String(BASE_URL || '').replace(/\/$/, '');
        if (!base) return raw;
        const path = raw.startsWith('/') ? raw : '/' + raw;
        return base + path;
    }




    function renderCategoryFilters(){
        if (!$filterCategories.length) return;
        const html = cats.map(c => {
            const name = $('<div>').text(c.name || 'Danh mục').html();
            const slug = String(c.slug || '').trim();
            return `<label class="filter-option"><input type="checkbox" data-cat="${c.id}" data-slug="${slug}"> ${name}</label>`;
        }).join('');
        $filterCategories.html(html || '<div class="text-muted small">Chưa có danh mục.</div>');
    }

    function renderBrandFilters(){
        if (!$filterBrands.length) return;
        const html = brands.map(b => {
            const safe = $('<div>').text(String(b || '').trim()).html();
            return `<label class="filter-option"><input type="checkbox" data-brand="${safe}"> ${safe}</label>`;
        }).join('');
        $filterBrands.html(html || '<div class="text-muted small">Chưa có hãng đối tác.</div>');
    }

    function runInitialFetch(){
        if (initialFetchDone) return;
        if (initialBrand && !initialBrandApplied) return;
        initialFetchDone = true;
        fetchProducts(true);
    }

    function renderCategoryDropdown(){
        if (!$('#h-category-grid').length) return;
        const html = cats.map(c => {
            const name = esc(c.name || 'Danh mục');
            const id = c.id;
            const isSelected = filterState.catFilters.includes(id);
            return `<div class="category-item-pill ${isSelected ? 'selected' : ''}" data-id="${id}" data-name="${name}">${name}</div>`;
        }).join('');
        $('#h-category-grid').html(html || '<div class="text-muted small">Chưa có danh mục.</div>');
    }

    function loadCats(){
        $.get(API, { ajax: 'categories' }, res => {
            cats = (res && res.ok) ? (res.data || []) : [];
            cats = cats.filter(c => {
                const id = Number(c.id || 0);
                const hasProducts = Number(c.product_count || 0) > 0;
                return hasProducts || id === 0 || id === initialCatId;
            });
            renderCategoryFilters();
            renderCategoryDropdown();
            if (initialCatId) {
                $filterCategories.find(`input[data-cat="${initialCatId}"]`).prop('checked', true);
                readFilters();
            } else if (initialCatSlug) {
                $filterCategories.find(`input[data-slug="${initialCatSlug}"]`).prop('checked', true);
                readFilters();
            }
            if (!initialBrand) {
                runInitialFetch();
            }
        }).fail(() => {
            if (!initialBrand) {
                runInitialFetch();
            }
        });
    }

    function loadBrands(){
        $.get(API, { ajax: 'brands' }, res => {
            brands = (res && res.ok) ? (res.data || []) : [];
            brands = [...new Set(brands.map(x => String(x || '').trim()).filter(Boolean))];
            renderBrandFilters();
            renderBrandDropdown();
            if (initialBrand) {
                const targetKey = normalizeBrandKey(initialBrand);
                let matched = false;
                $filterBrands.find('input[data-brand]').each(function(){
                    const val = String($(this).data('brand') || '').trim();
                    if (normalizeBrandKey(val) === targetKey) {
                        $(this).prop('checked', true);
                        matched = true;
                    }
                });
                readFilters();
                if (!matched) {
                    filterState.brandFilters = [initialBrand];
                    updateBrandNotice();
                }
                initialBrandApplied = true;
                fetchProducts(true);
            } else {
                runInitialFetch();
            }
        }).fail(() => {
            brands = [];
            renderBrandFilters();
            renderBrandDropdown();
            if (initialBrand && !initialBrandApplied) {
                filterState.brandFilters = [initialBrand];
                updateBrandNotice();
                initialBrandApplied = true;
                fetchProducts(true);
            } else {
                runInitialFetch();
            }
        });
    }

    function readFilters(manual = false){
        if (manual) initialCatSlug = '';
        // 1. Category
        const selectedCats = [];
        // From Sidebar (if exists)
        if ($filterCategories.length) {
            $filterCategories.find('input[data-cat]:checked').each(function(){
                const id = Number($(this).data('cat'));
                if (id) selectedCats.push(id);
            });
        }
        // From Horizontal Pills
        $('#h-category-grid .category-item-pill.selected').each(function(){
            const id = Number($(this).data('id'));
            if (id && !selectedCats.includes(id)) selectedCats.push(id);
        });
        filterState.catFilters = selectedCats;
        $('#h-category-filter').toggleClass('active', selectedCats.length > 0);

        // 2. Brand
        const selectedBrands = [];
        // From Sidebar
        if ($filterBrands.length) {
            $filterBrands.find('input[data-brand]:checked').each(function(){
                const name = String($(this).data('brand') || '').trim();
                if (name) selectedBrands.push(name);
            });
        }
        // From Horizontal Pills
        $('#h-brand-grid .brand-item-pill.selected').each(function(){
            const name = String($(this).data('brand') || '').trim();
            if (name && !selectedBrands.includes(name)) selectedBrands.push(name);
        });
        filterState.brandFilters = selectedBrands;
        $('#h-brand-filter').toggleClass('active', selectedBrands.length > 0);
        updateBrandNotice();

        // 3. Price
        const $price = $('input[name="priceFilter"]:checked');
        const pMin = $('#priceMin').val();
        const pMax = $('#priceMax').val();

        // Check horizontal price slider as well
        const hMin = $('#h-slider-min').val();
        const hMax = $('#h-slider-max').val();
        const hActive = $('#h-price-filter').hasClass('active');

        if (hActive) {
            filterState.priceMin = Number(hMin) || 0;
            filterState.priceMax = Number(hMax) || 50000000;
        } else if (pMin !== '' || pMax !== '') {
            filterState.priceMin = pMin !== '' ? Number(pMin) : null;
            filterState.priceMax = pMax !== '' ? Number(pMax) : null;
            if (manual) $('input[name="priceFilter"]').prop('checked', false);
        } else {
            filterState.priceMin = $price.length ? Number($price.data('min') || 0) : null;
            const maxRaw = $price.length ? String($price.data('max') ?? '') : '';
            filterState.priceMax = maxRaw === '' ? null : Number(maxRaw || 0);
        }
        $('#h-price-filter').toggleClass('active', filterState.priceMin !== null || filterState.priceMax !== null);

        // 4. Rating
        const $rating = $('input[name="ratingFilter"]:checked');
        filterState.ratingMin = $rating.length ? Number($rating.data('rating') || 0) : 0;

        // 5. Flags
        filterState.stockOnly = $('#stockOnly').is(':checked');
        filterState.promoOnly = $('#promoOnly').is(':checked');

        // 6. Paint Special Filters (Horizontal only)
        const selectedSpaces = [];
        $('#h-space-list .space-option-item.selected').each(function(){
            const val = $(this).data('value');
            if (val && val !== 'all') selectedSpaces.push(val);
        });
        filterState.paintSpaceFilters = selectedSpaces;
        $('#h-space-filter').toggleClass('active', selectedSpaces.length > 0);

        const selectedPositions = [];
        $('#h-positions-list .positions-option-item.selected').each(function(){
            const val = $(this).data('value');
            if (val && val !== 'all') selectedPositions.push(val);
        });
        filterState.paintPositionsFilters = selectedPositions;
        $('#h-positions-filter').toggleClass('active', selectedPositions.length > 0);

        const selectedNeeds = [];
        $('#h-needs-list .needs-option-item.selected').each(function(){
            const val = $(this).data('value');
            if (val && val !== 'all') selectedNeeds.push(val);
        });
        filterState.paintNeedsFilters = selectedNeeds;
        $('#h-needs-filter').toggleClass('active', selectedNeeds.length > 0);
    }

    function updateBrandNotice(){
        if (!$activeBrandNotice.length) return;
        const brands = Array.isArray(filterState.brandFilters) ? filterState.brandFilters.filter(Boolean) : [];
        if (!brands.length) {
            $activeBrandNotice.hide().find('span').text('');
            return;
        }
        $activeBrandNotice.find('span').text('Đang lọc theo hãng: ' + brands.join(', '));
        $activeBrandNotice.css('display', 'inline-flex');
    }

    function cardTemplate(p){
        const pid = Number(p.id || 0);
        const href = (window.pmBuildProductUrl
            ? window.pmBuildProductUrl(pid, p.product_name || p.name || '')
            : (BASE_URL + '/view-product/?pid=' + encodeURIComponent(pid)));

        const img = p.thumb
            ? toAbs(p.thumb)
            : <?= json_encode($site_fallback_logo ? rtrim($baseUrl, '/').'/'.ltrim($site_fallback_logo, '/') : '') ?>;

        const safeName = $('<div>').text(p.product_name || p.name || 'Sản phẩm').html();
        const priceMin = Number(p.gia_min ?? 0);
        let basePriceLabel = String(p.price_text || '').trim();
        if (!basePriceLabel) basePriceLabel = priceMin > 0 ? fmtPrice(priceMin) : 'Liên hệ';

        const safePrice = $('<div>').text(basePriceLabel).html();
        const oldPrice = p.old_price_text ? $('<div>').text(String(p.old_price_text)).html() : '';
        const newPrice = p.new_price_text ? $('<div>').text(String(p.new_price_text)).html() : '';

        const ratingCount = Number(p.rating_count || 0);
        const ratingVal = Number.isFinite(Number(p.rating_avg)) ? Number(p.rating_avg) : (Number(p.rating_value) || 0);

        const soldCount = Number(p.sold_count || p.sold || p.sold_qty || 0);
        const soldTextRaw = String(p.sold_text || '').trim();
        const fmtSoldCount = (n) => {
            const num = Number(n);
            if (!Number.isFinite(num) || num < 0) return '';
            if (num >= 1000) {
                const k = Math.floor(num / 100) / 10; // 30500 -> 30.5
                return String(k).replace(/\.0$/, '') + 'k+';
            }
            return String(num);
        };
        const soldText = soldTextRaw
            ? soldTextRaw
            : ('Đã bán ' + fmtSoldCount(soldCount));

        const promoSubtitle = p.promo_subtitle ? String(p.promo_subtitle) : '';
        const promoHighlights = Array.isArray(p.promo_highlights) ? p.promo_highlights : [];

        const discount = Number(p.discount_percent || 0);
        const voucherBadge = p.voucher_badge ? String(p.voucher_badge) : '';
        const hasShip = !!p.has_ship_demo;
        const shipLabel = p.ship_label ? String(p.ship_label) : '';

        // Badge giảm giá: ưu tiên voucher text
        let discountText = '';
        if (voucherBadge) {
            let raw = voucherBadge.toString().trim();
            let label = raw;
            const m = raw.match(/^Giảm\s+(\d+)\s*%?$/i);
            if (m) label = '-' + m[1] + '%';
            else if (/^\d+\s*[kK]$/.test(raw)) {
                const num = raw.replace(/[^0-9]/g, '');
                label = num ? '-' + num + 'K' : '-';
            } else if (/^Giảm\s+\d+[kK]?/i.test(raw)) label = '-' + raw.replace(/Giảm\s+/i, '');
            else if (/^\d+$/.test(raw)) label = '-' + raw + '%';
            else label = raw.replace(/^Giảm\s*/i, '-');
            discountText = label;
        } else if (discount > 0) {
            discountText = (discount >= 100) ? 'Free' : ('-' + discount + '%');
        }

        const discountBadgeHtml = discountText
            ? `<div class="badge-discount">${$('<div>').text(discountText).html()}</div>`
            : '';

        // Badge voucher/freeship
        let voucherHtml = '';
        if (hasShip) {
            const raw = (shipLabel || '').toString().trim();
            const line2 = (raw === '100%' || raw === '100') ? '100%' : ('Giảm ship ' + raw);
            voucherHtml = `<div class="badge-voucher">FREESHIP<br><span style="font-style:italic;">${$('<div>').text(line2).html()}</span></div>`;
        }

        // Danh mục (nếu có)
        const catName = String(p.category_name || p.category || '').trim();
        const catHtml = catName ? `<span class="shopping-product-category">${$('<div>').text(catName).html()}</span>` : '';

        // Promo line: lấy 1 dòng đầu tiên cho gọn (đúng layout mẫu). Vẫn ưu tiên promo_highlights.
        let promoLine = '';
        if (promoHighlights.length > 0) {
            promoLine = String(promoHighlights.find(t => String(t || '').trim()) || '').trim();
        }
        if (!promoLine && promoSubtitle) promoLine = String(promoSubtitle).trim();
        const promoHtml = promoLine ? `<div class="badge-promo">${highlightPromoText(promoLine)}</div>` : '';

        const priceHtml = (oldPrice && newPrice)
            ? `<span class="sp-price">${newPrice}</span><span class="sp-old-price">${oldPrice}</span>`
            : `<span class="sp-price">${safePrice}</span>`;

        const safeRating = Math.max(0, Math.min(5, ratingVal || 0));
        const starsHtml = `<i class="bi bi-star-fill is-on"></i>`;

        const ratingText = safeRating.toFixed(1) + ' (' + ratingCount + ')';

        const ratingHtml = `<div class="sp-rating"><span class="sp-stars">${starsHtml}</span><span>${$('<div>').text(ratingText).html()}</span></div>`;

        return `
            <a href="${$('<div>').text(href).html()}" class="shopping-product-card shadow-sm">
                <div class="shopping-img-wrapper">
                    <img src="${$('<div>').text(img).html()}" alt="${esc(safeName)}" loading="lazy" decoding="async" onerror="this.src='${esc(FALLBACK_IMG)}'">
                    ${discountBadgeHtml}
                    ${voucherHtml}
                </div>

                <div class="shopping-product-content">
                    <div class="shopping-product-title">${catHtml}${safeName}</div>
                    ${promoHtml}
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">${priceHtml}</div>
                       
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-1">
                        ${ratingHtml}
                        <div class="btn-fav-item d-none" data-pid="${pid}">
                            <i class="bi bi-heart"></i>
                            <span class="fav-text">Yêu thích</span>
                        </div>
                        <div class="sd-none sp-sold d-none">${$('<div>').text(soldText).html()}</div>
                    </div>
                </div>

                <div class="add-cart-btn">
                    <span type="button" class="product-card-add-cart" data-pid="${pid}" data-name="${safeName}">Thêm vào giỏ hàng</span>
                </div>
            </a>
        `;
    }

    function skeletonCardTemplate(){
        return `
            <article class="shopping-product-card is-skeleton" aria-hidden="true">
                <div class="shopping-img-wrapper">
                    <div class="shopping-skeleton" style="position:absolute;inset:0;"></div>
                </div>
                <div class="shopping-product-content">
                    <div class="shopping-skeleton" style="height:10px;width:92%;margin-bottom:8px;"></div>
                    <div class="shopping-skeleton" style="height:10px;width:72%;margin-bottom:10px;"></div>
                    <div class="shopping-skeleton" style="height:12px;width:60%;"></div>
                </div>
            </article>
        `;
    }

    function renderSkeleton(count = 8, replace = false){
        const safeCount = Math.max(1, Number(count) || 1);
        const html = new Array(safeCount).fill('').map(() => skeletonCardTemplate()).join('');
        if (replace) $grid.html(html);
        else $grid.append(html);
    }

    function clearSkeleton(){
        $grid.find('.shopping-product-card.is-skeleton, .product-card.is-skeleton, .pcard.is-skeleton').remove();
    }

    function fetchProducts(reset = false){
        if (loading) {
            if (reset) pendingResetFetch = true;
            return;
        }
        if (!hasMore && !reset) return;
        if (reset){
            page = 0;
            hasMore = true;
            renderSkeleton(Math.min(pageSize, 8), true);
        } else {
            renderSkeleton(4, false);
        }
        loading = true; $empty.hide();
        const start = page * pageSize;
        const params = {
            ajax: 'products',
            draw: 1,
            start,
            length: pageSize,
            'search[value]': searchTerm,
            custom_sort: sortVal,
            cat_slug: initialCatSlug
        };
        if (filterState.catFilters.length) {
            params.cat_filters = filterState.catFilters.join(',');
        }
        if (filterState.brandFilters.length) {
            params.brand_filters = filterState.brandFilters.join(',');
        }
        if (filterState.priceMin !== null) params.price_min = filterState.priceMin;
        if (filterState.priceMax !== null) params.price_max = filterState.priceMax;
        if (filterState.ratingMin) params.rating_min = filterState.ratingMin;
        if (filterState.stockOnly) params.stock_only = 1;
        if (filterState.promoOnly) params.promo_only = 1;
        if (filterState.paintSpaceFilters.length) {
            params.paint_space_filters = filterState.paintSpaceFilters.join(',');
        }
        if (filterState.paintPositionsFilters.length) {
            params.paint_positions_filters = filterState.paintPositionsFilters.join(',');
        }
        if (filterState.paintNeedsFilters.length) {
            params.paint_needs_filters = filterState.paintNeedsFilters.join(',');
        }

        updateActiveFiltersUI();

        $.get(API, params, res => {
            clearSkeleton();
            let list = [];
            let total = 0;

            if (Array.isArray(res)) {
                list = res;
                total = list.length;
            } else if (res && res.ok) {
                list = res.data || [];
                total = (typeof res.recordsFiltered !== 'undefined') ? (res.recordsFiltered || list.length) : list.length;
            } else if (res && Array.isArray(res.data)) {
                list = res.data;
                total = list.length;
            }

            if (reset && list.length === 0){ $empty.show(); $loadMore.hide(); }
            list.forEach(p => $grid.append(cardTemplate(p)));
            page += 1;
            hasMore = total ? (start + list.length < total) : false;
            $loadMore.toggle(hasMore);
        }).always(() => {
            clearSkeleton();
            loading = false;
            if (pendingResetFetch) {
                pendingResetFetch = false;
                fetchProducts(true);
            }
        });
    }

    if ($search.length && initialSearch) {
        $search.val(initialSearch);
    }

    $search.on('keyup', function(){
        searchTerm = $(this).val();
        fetchProducts(true);
    });

    $(document).on('change', '#filterCategories input, #filterBrands input, input[name="priceFilter"], input[name="ratingFilter"], input[name="sortFilter"], #stockOnly, #promoOnly', function(){
        if ($(this).attr('name') === 'priceFilter') {
            $('#priceMin, #priceMax').val('');
        }
        const $sort = $('input[name="sortFilter"]:checked');
        sortVal = $sort.length ? String($sort.data('sort') || 'newest') : 'newest';
        readFilters(true);
        fetchProducts(true);
    });

    $(document).on('input', '#priceMin, #priceMax', function(){
        // debounce if needed, but usually simple enough
        readFilters(true);
        fetchProducts(true);
    });

    $filterApply.on('click', function(){
        const $sort = $('input[name="sortFilter"]:checked');
        sortVal = $sort.length ? String($sort.data('sort') || 'newest') : 'newest';
        readFilters(true);
        fetchProducts(true);
    });

    $filterClear.on('click', function(){
        $('#filterCategories input, #filterBrands input').prop('checked', false);
        $('input[name="priceFilter"], input[name="ratingFilter"], input[name="sortFilter"]').prop('checked', false);
        $('#priceMin, #priceMax').val('');
        $('#stockOnly').prop('checked', true); // Ưu tiên Còn hàng
        $('#promoOnly').prop('checked', false);
        sortVal = 'newest';
        $('input[name="sortFilter"][data-sort="newest"]').prop('checked', true);
        
        // Reset horizontal UI & state
        filterState.paintSpaceFilters = [];
        filterState.paintPositionsFilters = [];
        $('.filter-h-item').removeClass('active');
        $('#h-brand-grid .brand-item-pill').removeClass('selected');
        $('#h-category-grid .category-item-pill').removeClass('selected');
        
        // Space
        $('#h-space-list .space-option-item').removeClass('selected');
        $('#h-space-list .space-option-item[data-value="all"]').addClass('selected');
        
        // Positions
        $('#h-positions-list .positions-option-item').removeClass('selected');
        $('#h-positions-list .positions-option-item[data-value="all"]').addClass('selected');
        
        // Needs
        $('#h-needs-list .needs-option-item').removeClass('selected');
        $('#h-needs-list .needs-option-item[data-value="all"]').addClass('selected');
        filterState.paintNeedsFilters = [];

        $('#h-sort-options .filter-h-item').removeClass('active');
        $('#h-sort-options .filter-h-item[data-sort="newest"]').addClass('active');

        readFilters(true);
        fetchProducts(true);
    });

    $filterTabs.on('click', function(){
        const key = String($(this).data('section') || '');
        if (!key) return;
        $filterTabs.removeClass('is-active');
        $(this).addClass('is-active');
        $filterSections.removeClass('is-active');
        $filterSections.filter(`[data-section="${key}"]`).addClass('is-active');
    });

    $('#loadMoreBtn').click(() => fetchProducts());

    // Infinite Scroll: Tự động load khi cuộn xuống
    if ('IntersectionObserver' in window) {
        const sentinel = document.getElementById('productSentinel');
        if (sentinel) {
            const observer = new IntersectionObserver((entries) => {
                if (entries[0].isIntersecting && !loading && hasMore) {
                    fetchProducts(false);
                }
            }, { rootMargin: '120px' });
            observer.observe(sentinel);
        }
    }

    // Nút thêm giỏ hàng trên từng product-card
    $grid.on('click', '.product-card-add-cart', function(ev){
        ev.preventDefault();
        ev.stopPropagation();
        const $btn = $(this);
        const pid = Number($btn.data('pid') || 0);
        const name = String($btn.data('name') || '').trim();
        
        // Fly-to-cart animation source
        const $card = $btn.closest('.shopping-product-card');
        const $img = $card.find('.shopping-img-wrapper img');
        
        if (window.addToCartFromCard) {
            window.addToCartFromCard(pid, name, $img[0]);
        }
    });
    // Favorite toggle from card
    $grid.on('click', '.btn-fav-item', function(ev){
        ev.preventDefault();
        ev.stopPropagation();
        const $btn = $(this);
        const pid = Number($btn.data('pid') || 0);
        if (!pid) return;
        
        $.post(FAVORITE_API, { action: 'toggle', pid: pid }, function(res){
            if (res && res.ok) {
                $btn.toggleClass('active', !!res.liked);
                if (window.toastr) {
                    if (res.liked) toastr.success('Đã thêm vào yêu thích');
                    else toastr.info('Đã bỏ yêu thích');
                }
            }
        });
    });

    // Event listeners cho bộ lọc ngang (Horizontal Filters)
    $('#h-sort-options .filter-h-item').on('click', function() {
        const $this = $(this);
        const newSort = $this.data('sort');
        
        if (newSort === 'promo') {
            // Khuyến mãi HOT: bật filter promoOnly và sắp xếp mới nhất
            $('#promoOnly').prop('checked', true).trigger('change');
            $('#h-sort-options .filter-h-item').removeClass('active');
            $this.addClass('active');
            return;
        }

        $('#h-sort-options .filter-h-item').removeClass('active');
        $this.addClass('active');
        
        // Cập nhật filter bên sidebar tương ứng (nếu có)
        if (newSort) {
            if (newSort !== 'promo') {
                // Nếu chọn sắp xếp khác, tắt promoOnly
                $('#promoOnly').prop('checked', false);
            }
            
            const $sidebarInput = $(`input[name="sortFilter"][data-sort="${newSort}"]`);
            if ($sidebarInput.length) {
                $sidebarInput.prop('checked', true).trigger('change');
            } else {
                sortVal = newSort;
                readFilters(true);
                fetchProducts(true);
            }
        }
    });

    $('#h-filter-criteria .filter-h-item').on('click', function() {
        const $this = $(this);
        const filterType = $this.data('filter');
        
        if (filterType === 'default') {
            $filterClear.click();
            return;
        }

        if (filterType === 'stock') {
            $this.toggleClass('active');
            $('#stockOnly').prop('checked', $this.hasClass('active')).trigger('change');
        } else if (filterType === 'new') {
            $('#h-sort-options .filter-h-item[data-sort="newest"]').click();
        } else if (filterType === 'price') {
            // Cuộn đến phần lọc giá ở sidebar
            $filterTabs.filter('[data-section="price"]').click();
            const $panel = $('.filter-panel');
            if ($panel.length) {
                $panel.animate({ scrollTop: $('#priceMin').offset().top - 200 }, 500);
            }
        }
    });

    $('#btn-toggle-sidebar-filter').on('click', function() {
        // Trên mobile, có thể cần toggle class hiển thị sidebar
        if (window.innerWidth <= 768) {
            $('.filter-panel').toggleClass('show'); 
        } else {
            // Trên desktop, có thể chỉ cần scroll lên đầu filter
            $('.filter-panel').animate({ scrollTop: 0 }, 300);
        }
    });

    // Đồng bộ ngược lại khi sidebar thay đổi
    $(document).on('change', 'input[name="sortFilter"]', function() {
        const val = $(this).data('sort');
        $('#h-sort-options .filter-h-item').removeClass('active');
        $(`#h-sort-options .filter-h-item[data-sort="${val}"]`).addClass('active');
    });

    $(document).on('change', '#stockOnly', function() {
        $('#h-filter-criteria .filter-h-item[data-filter="stock"]').toggleClass('active', $(this).is(':checked'));
    });

    $(document).on('change', '#promoOnly', function() {
        $('#h-sort-options .filter-h-item[data-sort="promo"]').toggleClass('active', $(this).is(':checked'));
        if ($(this).is(':checked')) {
            // Nếu bật khuyến mãi, bỏ active các mục sắp xếp khác
            $('#h-sort-options .filter-h-item').not('[data-sort="promo"]').removeClass('active');
        } else {
            // Nếu tắt khuyến mãi, quay lại mặc định (newest) nếu không có cái nào active
            if ($('#h-sort-options .filter-h-item.active').length === 0) {
                $('#h-sort-options .filter-h-item[data-sort="newest"]').addClass('active');
            }
        }
    });

    // Category Dropdown
    $('#h-category-filter').on('click', function(e) {
        e.stopPropagation();
        $('#categoryMenu').toggleClass('show');
        renderCategoryDropdown();
    });

    $('#h-category-grid').on('click', '.category-item-pill', function() {
        const $this = $(this);
        $this.toggleClass('selected');
    });

    $('#btn-category-close').on('click', function() {
        $('#categoryMenu').removeClass('show');
    });

    $('#btn-category-apply').on('click', function() {
        const selected = [];
        $('#h-category-grid .category-item-pill.selected').each(function() {
            const id = Number($(this).data('id'));
            if (id) selected.push(id);
        });

        filterState.catFilters = selected;

        // Đồng bộ lên sidebar
        $('#filterCategories input[type="checkbox"]').prop('checked', false);
        selected.forEach(id => {
            $('#filterCategories').find(`input[data-cat="${id}"]`).prop('checked', true);
        });

        $('#categoryMenu').removeClass('show');
        $('#h-category-filter').toggleClass('active', selected.length > 0);
        readFilters(true);
        fetchProducts(true);
    });

    $(document).on('click', function(e) {
        if (!$(e.target).closest('.category-dropdown-wrapper').length) {
            $('#categoryMenu').removeClass('show');
        }
    });

    // Dropdown Hãng sản xuất
    function renderBrandDropdown() {
        const $grid = $('#h-brand-grid');
        if (!$grid.length) return;
        
        let html = '';
        brands.forEach(brand => {
            const name = esc(brand);
            const isSelected = filterState.brandFilters.includes(brand);
            html += `<div class="brand-item-pill ${isSelected ? 'selected' : ''}" data-brand="${name}">${name}</div>`;
        });
        
        $grid.html(html || '<div class="text-muted small px-3">Chưa có hãng đối tác.</div>');
    }

    $('#h-brand-filter').on('click', function(e) {
        e.stopPropagation();
        $('#brandMenu').toggleClass('show');
        renderBrandDropdown(); // Refresh states
    });

    $(document).on('click', function(e) {
        if (!$(e.target).closest('.brand-dropdown-wrapper').length) {
            $('#brandMenu').removeClass('show');
        }
    });

    $('#brandMenu').on('click', function(e) {
        e.stopPropagation();
    });

    $('#h-brand-grid').on('click', '.brand-item-pill', function() {
        $(this).toggleClass('selected');
    });

    $('#btn-brand-close').on('click', function() {
        $('#brandMenu').removeClass('show');
    });

    $('#btn-brand-apply').on('click', function() {
        const selected = [];
        $('#h-brand-grid .brand-item-pill.selected').each(function() {
            selected.push($(this).data('brand'));
        });

        // Đồng bộ lên sidebar
        $('#filterBrands input[type="checkbox"]').prop('checked', false);
        selected.forEach(brand => {
            $('#filterBrands').find(`input[data-brand="${brand}"]`).prop('checked', true);
        });

        $('#h-brand-filter').toggleClass('active', selected.length > 0);
        $('#brandMenu').removeClass('show');
        readFilters(true);
        fetchProducts(true);
    });

    // Đồng bộ ngược từ sidebar sang dropdown nếu cần
    $(document).on('change', '#filterBrands input', function() {
        renderBrandDropdown();
    });

    // Dropdown Xem theo giá
    const $hPriceMin = $('#h-price-min');
    const $hPriceMax = $('#h-price-max');
    const $hSliderMin = $('#h-slider-min');
    const $hSliderMax = $('#h-slider-max');
    const $hPriceFill = $('#h-price-fill');

    function updatePriceSlider() {
        let min = parseInt($hSliderMin.val());
        let max = parseInt($hSliderMax.val());

        if (min > max) {
            let tmp = min;
            min = max;
            max = tmp;
        }

        $hPriceMin.val(fmtPrice(min).replace('đ', ''));
        $hPriceMax.val(fmtPrice(max).replace('đ', ''));

        const percent1 = (min / $hSliderMin.attr('max')) * 100;
        const percent2 = (max / $hSliderMax.attr('max')) * 100;
        $hPriceFill.css({
            left: percent1 + '%',
            width: (percent2 - percent1) + '%'
        });
    }

    $('#h-price-filter').on('click', function(e) {
        e.stopPropagation();
        $('#priceMenu').toggleClass('show');
        // Đồng bộ từ filterState hiện tại
        const min = filterState.priceMin || 0;
        const max = filterState.priceMax || 50000000;
        $hSliderMin.val(min);
        $hSliderMax.val(max);
        updatePriceSlider();
    });

    $hSliderMin.on('input', updatePriceSlider);
    $hSliderMax.on('input', updatePriceSlider);

    $hPriceMin.on('change', function() {
        let val = parseInt($(this).val().replace(/\./g, '')) || 0;
        $hSliderMin.val(val);
        updatePriceSlider();
    });

    $hPriceMax.on('change', function() {
        let val = parseInt($(this).val().replace(/\./g, '')) || 0;
        $hSliderMax.val(val);
        updatePriceSlider();
    });

    $('#btn-price-close').on('click', function() {
        $('#priceMenu').removeClass('show');
    });

    $('#btn-price-apply').on('click', function() {
        let min = parseInt($hSliderMin.val());
        let max = parseInt($hSliderMax.val());
        if (min > max) { let t = min; min = max; max = t; }
        
        filterState.priceMin = min;
        filterState.priceMax = max;

        // Đồng bộ sang sidebar
        $('#priceMin').val(min);
        $('#priceMax').val(max);
        $('input[name="priceFilter"]').prop('checked', false);

        $('#priceMenu').removeClass('show');
        $('#h-price-filter').addClass('active');
        fetchProducts(true);
    });

    $(document).on('click', function(e) {
        if (!$(e.target).closest('.price-dropdown-wrapper').length) {
            $('#priceMenu').removeClass('show');
        }
    });

    // Dropdown Không gian
    $('#h-space-filter').on('click', function(e) {
        e.stopPropagation();
        $('#spaceMenu').toggleClass('show');
    });

    $('#h-space-list').on('click', '.space-option-item', function() {
        const $this = $(this);
        const val = $this.data('value');
        
        if (val === 'all') {
            $('#h-space-list .space-option-item').removeClass('selected');
            $this.addClass('selected');
        } else {
            $('#h-space-list .space-option-item[data-value="all"]').removeClass('selected');
            $this.toggleClass('selected');
            
            // Nếu không còn cái nào được chọn, tự động quay về "Tất cả"
            if ($('#h-space-list .space-option-item.selected').length === 0) {
                $('#h-space-list .space-option-item[data-value="all"]').addClass('selected');
            }
        }
    });

    $('#btn-space-close').on('click', function() {
        $('#spaceMenu').removeClass('show');
    });

    $('#btn-space-apply').on('click', function() {
        const selected = [];
        $('#h-space-list .space-option-item.selected').each(function() {
            const val = $(this).data('value');
            if (val && val !== 'all') {
                selected.push(val);
            }
        });

        filterState.paintSpaceFilters = selected;
        $('#spaceMenu').removeClass('show');
        
        // Cập nhật trạng thái active cho nút filter
        $('#h-space-filter').toggleClass('active', selected.length > 0);
        
        fetchProducts(true);
    });

    $(document).on('click', function(e) {
        if (!$(e.target).closest('.space-dropdown-wrapper').length) {
            $('#spaceMenu').removeClass('show');
        }
    });

    // Dropdown Vị trí
    $('#h-positions-filter').on('click', function(e) {
        e.stopPropagation();
        $('#positionsMenu').toggleClass('show');
    });

    $('#h-positions-list').on('click', '.positions-option-item', function() {
        const $this = $(this);
        const val = $this.data('value');
        
        if (val === 'all') {
            $('#h-positions-list .positions-option-item').removeClass('selected');
            $this.addClass('selected');
        } else {
            $('#h-positions-list .positions-option-item[data-value="all"]').removeClass('selected');
            $this.toggleClass('selected');
            
            if ($('#h-positions-list .positions-option-item.selected').length === 0) {
                $('#h-positions-list .positions-option-item[data-value="all"]').addClass('selected');
            }
        }
    });

    $('#btn-positions-close').on('click', function() {
        $('#positionsMenu').removeClass('show');
    });

    $('#btn-positions-apply').on('click', function() {
        const selected = [];
        $('#h-positions-list .positions-option-item.selected').each(function() {
            const val = $(this).data('value');
            if (val && val !== 'all') {
                selected.push(val);
            }
        });

        filterState.paintPositionsFilters = selected;
        $('#positionsMenu').removeClass('show');
        
        $('#h-positions-filter').toggleClass('active', selected.length > 0);
        
        fetchProducts(true);
    });

    $(document).on('click', function(e) {
        if (!$(e.target).closest('.positions-dropdown-wrapper').length) {
            $('#positionsMenu').removeClass('show');
        }
    });

    // Dropdown Nhu cầu
    $('#h-needs-filter').on('click', function(e) {
        e.stopPropagation();
        $('#needsMenu').toggleClass('show');
    });

    $('#h-needs-list').on('click', '.needs-option-item', function() {
        const $this = $(this);
        const val = $this.data('value');
        
        if (val === 'all') {
            $('#h-needs-list .needs-option-item').removeClass('selected');
            $this.addClass('selected');
        } else {
            $('#h-needs-list .needs-option-item[data-value="all"]').removeClass('selected');
            $this.toggleClass('selected');
            
            if ($('#h-needs-list .needs-option-item.selected').length === 0) {
                $('#h-needs-list .needs-option-item[data-value="all"]').addClass('selected');
            }
        }
    });

    $('#btn-needs-close').on('click', function() {
        $('#needsMenu').removeClass('show');
    });

    $('#btn-needs-apply').on('click', function() {
        const selected = [];
        $('#h-needs-list .needs-option-item.selected').each(function() {
            const val = $(this).data('value');
            if (val && val !== 'all') {
                selected.push(val);
            }
        });

        filterState.paintNeedsFilters = selected;
        $('#needsMenu').removeClass('show');
        
        $('#h-needs-filter').toggleClass('active', selected.length > 0);
        
        fetchProducts(true);
    });

    $(document).on('click', function(e) {
        if (!$(e.target).closest('.needs-dropdown-wrapper').length) {
            $('#needsMenu').removeClass('show');
        }
    });

    // Grand Multi-Column Filter Popup
    $('#h-main-filter-btn').on('click', function(e) {
        e.stopPropagation();
        $('#mainFilterPopup').toggleClass('show');
        
        // Sync active states from filterState when opening
        syncMainFilterPopupFromState();
    });

    // Handle pill click in the main filter popup
    $('.main-filter-pills').on('click', '.filter-pill', function() {
        const $this = $(this);
        const val = $this.data('value');
        const $parent = $this.parent();
        
        if (val === 'all') {
            $parent.find('.filter-pill').removeClass('selected');
            $this.addClass('selected');
        } else {
            $parent.find('.filter-pill[data-value="all"]').removeClass('selected');
            $this.toggleClass('selected');
            
            // If nothing is selected, default back to "All"
            if ($parent.find('.filter-pill.selected').length === 0) {
                $parent.find('.filter-pill[data-value="all"]').addClass('selected');
            }
        }
    });

    // Close button of popup
    $('#btn-popup-close').on('click', function() {
        $('#mainFilterPopup').removeClass('show');
    });

    // Close when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.main-filter-popup-wrapper').length) {
            $('#mainFilterPopup').removeClass('show');
        }
    });

    // Helper to sync popup selections from current filterState
    function syncMainFilterPopupFromState() {
        // Space
        const spaces = filterState.paintSpaceFilters || [];
        $('#popup-space-list .filter-pill').removeClass('selected');
        if (spaces.length === 0) {
            $('#popup-space-list .filter-pill[data-value="all"]').addClass('selected');
        } else {
            spaces.forEach(val => {
                $(`#popup-space-list .filter-pill[data-value="${val}"]`).addClass('selected');
            });
        }

        // Positions
        const positions = filterState.paintPositionsFilters || [];
        $('#popup-positions-list .filter-pill').removeClass('selected');
        if (positions.length === 0) {
            $('#popup-positions-list .filter-pill[data-value="all"]').addClass('selected');
        } else {
            positions.forEach(val => {
                $(`#popup-positions-list .filter-pill[data-value="${val}"]`).addClass('selected');
            });
        }

        // Needs
        const needs = filterState.paintNeedsFilters || [];
        $('#popup-needs-list .filter-pill').removeClass('selected');
        if (needs.length === 0) {
            $('#popup-needs-list .filter-pill[data-value="all"]').addClass('selected');
        } else {
            needs.forEach(val => {
                $(`#popup-needs-list .filter-pill[data-value="${val}"]`).addClass('selected');
            });
        }
    }

    // Apply button click
    $('#btn-popup-apply').on('click', function() {
        // 1. Read Space
        const spaces = [];
        $('#popup-space-list .filter-pill.selected').each(function() {
            const val = $(this).data('value');
            if (val && val !== 'all') spaces.push(val);
        });
        filterState.paintSpaceFilters = spaces;

        // 2. Read Positions
        const positions = [];
        $('#popup-positions-list .filter-pill.selected').each(function() {
            const val = $(this).data('value');
            if (val && val !== 'all') positions.push(val);
        });
        filterState.paintPositionsFilters = positions;

        // 3. Read Needs
        const needs = [];
        $('#popup-needs-list .filter-pill.selected').each(function() {
            const val = $(this).data('value');
            if (val && val !== 'all') needs.push(val);
        });
        filterState.paintNeedsFilters = needs;

        // Sync back to individual desktop dropdown list active states so UI is unified
        // Space
        $('#h-space-list .space-option-item').removeClass('selected');
        if (spaces.length === 0) {
            $('#h-space-list .space-option-item[data-value="all"]').addClass('selected');
            $('#h-space-filter').removeClass('active');
        } else {
            spaces.forEach(val => {
                $(`#h-space-list .space-option-item[data-value="${val}"]`).addClass('selected');
            });
            $('#h-space-filter').addClass('active');
        }

        // Positions
        $('#h-positions-list .positions-option-item').removeClass('selected');
        if (positions.length === 0) {
            $('#h-positions-list .positions-option-item[data-value="all"]').addClass('selected');
            $('#h-positions-filter').removeClass('active');
        } else {
            positions.forEach(val => {
                $(`#h-positions-list .positions-option-item[data-value="${val}"]`).addClass('selected');
            });
            $('#h-positions-filter').addClass('active');
        }

        // Needs
        $('#h-needs-list .needs-option-item').removeClass('selected');
        if (needs.length === 0) {
            $('#h-needs-list .needs-option-item[data-value="all"]').addClass('selected');
            $('#h-needs-filter').removeClass('active');
        } else {
            needs.forEach(val => {
                $(`#h-needs-list .needs-option-item[data-value="${val}"]`).addClass('selected');
            });
            $('#h-needs-filter').addClass('active');
        }

        // Highlight main filter button if any of these are active
        const hasActiveMainFilters = spaces.length > 0 || positions.length > 0 || needs.length > 0;
        $('#h-main-filter-btn').toggleClass('active', hasActiveMainFilters);

        // Close popup
        $('#mainFilterPopup').removeClass('show');

        // Fetch products
        fetchProducts(true);
    });

    function updateActiveFiltersUI() {
        const $list = $('#activeFiltersList');
        const $section = $('#activeFiltersSection');
        $list.empty();
        let count = 0;

        const addTag = (label, value, type, originalValue) => {
            count++;
            const $tag = $(`
                <div class="filter-tag">
                    <span class="tag-label">${label}:</span>
                    <span class="tag-value">${value}</span>
                    <span class="tag-remove" data-type="${type}" data-value="${originalValue || value}"><i class="bi bi-x"></i></span>
                </div>
            `);
            $list.append($tag);
        };

        // Category Filters
        if (filterState.catFilters.length) {
            filterState.catFilters.forEach(id => {
                const name = $(`.category-item-pill[data-id="${id}"]`).data('name') || id;
                addTag('Danh mục', name, 'category', id);
            });
        }

        // Brand Filters
        if (filterState.brandFilters.length) {
            filterState.brandFilters.forEach(id => {
                const name = $(`.brand-item-pill[data-id="${id}"]`).data('name') || id;
                addTag('Thương hiệu', name, 'brand', id);
            });
        }

        // Space Filters
        if (filterState.paintSpaceFilters.length) {
            filterState.paintSpaceFilters.forEach(val => {
                addTag('Không gian', val, 'space');
            });
        }

        // Positions Filters
        if (filterState.paintPositionsFilters.length) {
            filterState.paintPositionsFilters.forEach(val => {
                addTag('Vị trí', val, 'positions');
            });
        }

        // Needs Filters
        if (filterState.paintNeedsFilters.length) {
            filterState.paintNeedsFilters.forEach(val => {
                addTag('Nhu cầu', val, 'needs');
            });
        }

        // Price Filters
        if (filterState.priceMin !== null || filterState.priceMax !== null) {
            let priceLabel = '';
            if (filterState.priceMin && filterState.priceMax) priceLabel = `${Number(filterState.priceMin).toLocaleString()}đ - ${Number(filterState.priceMax).toLocaleString()}đ`;
            else if (filterState.priceMin) priceLabel = `Trên ${Number(filterState.priceMin).toLocaleString()}đ`;
            else if (filterState.priceMax) priceLabel = `Dưới ${Number(filterState.priceMax).toLocaleString()}đ`;
            
            if (priceLabel) addTag('Giá', priceLabel, 'price');
        }

        if (count > 0) {
            if (!$('#btn-clear-all-tags').length) {
                $list.append('<span class="clear-all-filters" id="btn-clear-all-tags">Bỏ chọn tất cả</span>');
            }
            $section.addClass('show');
        } else {
            $section.removeClass('show');
        }
    }

    // Handle tag removal
    $('#activeFiltersList').on('click', '.tag-remove', function() {
        const type = $(this).data('type');
        const value = $(this).data('value');

        if (type === 'category') {
            filterState.catFilters = filterState.catFilters.filter(id => Number(id) !== Number(value));
            $(`.category-item-pill[data-id="${value}"]`).removeClass('selected');
            $(`#filterCategories input[data-cat="${value}"]`).prop('checked', false);
        } else if (type === 'brand') {
            filterState.brandFilters = filterState.brandFilters.filter(id => String(id) !== String(value));
            $(`.brand-item-pill[data-id="${value}"]`).removeClass('selected');
            $(`#filterBrands input[value="${value}"]`).prop('checked', false);
        } else if (type === 'space') {
            filterState.paintSpaceFilters = filterState.paintSpaceFilters.filter(v => v !== value);
            $(`.space-option-item[data-value="${value}"]`).removeClass('selected');
            if (filterState.paintSpaceFilters.length === 0) $('.space-option-item[data-value="all"]').addClass('selected');
        } else if (type === 'positions') {
            filterState.paintPositionsFilters = filterState.paintPositionsFilters.filter(v => v !== value);
            $(`.positions-option-item[data-value="${value}"]`).removeClass('selected');
            if (filterState.paintPositionsFilters.length === 0) $('.positions-option-item[data-value="all"]').addClass('selected');
        } else if (type === 'needs') {
            filterState.paintNeedsFilters = filterState.paintNeedsFilters.filter(v => v !== value);
            $(`.needs-option-item[data-value="${value}"]`).removeClass('selected');
            if (filterState.paintNeedsFilters.length === 0) $('.needs-option-item[data-value="all"]').addClass('selected');
        } else if (type === 'price') {
            filterState.priceMin = null;
            filterState.priceMax = null;
            $('#priceMin, #priceMax').val('');
            $('input[name="priceFilter"]').prop('checked', false);
        }

        fetchProducts(true);
    });

    $('#activeFiltersList').on('click', '#btn-clear-all-tags', function() {
        $('#filterClearBtn').trigger('click');
    });

    // ---------------- MOBILE FILTER INTERACTION ---------------- //
    const $mobileSearch = $('#mobileSearchBox');
    
    // Move bottom sheet menu elements to body on mobile screen size to avoid being hidden by d-none parent
    if (window.innerWidth <= 768) {
        $('#categoryMenu, #priceMenu, #brandMenu, #spaceMenu, #positionsMenu, #needsMenu').appendTo('body');
    }

    // Sync search input
    if ($mobileSearch.length && initialSearch) {
        $mobileSearch.val(initialSearch);
    }
    
    $mobileSearch.on('keyup', function() {
        searchTerm = $(this).val();
        $search.val(searchTerm); // Sync to desktop input as well
        fetchProducts(true);
    });

    // 1. Criteria "Sẵn hàng" (stock)
    $('#m-criteria-stock').on('click', function() {
        const $this = $(this);
        $this.toggleClass('active');
        const isActive = $this.hasClass('active');
        $('#stockOnly').prop('checked', isActive).trigger('change');
        // Sync desktop element if visible
        $('#h-filter-criteria .filter-h-item[data-filter="stock"]').toggleClass('active', isActive);
    });

    // Sync back when stock changes from anywhere
    $(document).on('change', '#stockOnly', function() {
        $('#m-criteria-stock').toggleClass('active', $(this).is(':checked'));
    });

    // 2. Criteria "Xem theo giá" (price)
    $('#m-criteria-price').on('click', function(e) {
        e.stopPropagation();
        $('#priceMenu').toggleClass('show');
        // Synchronize range
        const min = filterState.priceMin || 0;
        const max = filterState.priceMax || 50000000;
        $hSliderMin.val(min);
        $hSliderMax.val(max);
        updatePriceSlider();
    });

    // 3. Criteria "Hàng mới" (new)
    $('#m-criteria-new').on('click', function() {
        $('#m-tab-popular').click(); // switch tab to popular/newest
        // Or trigger desktop logic
        $('#h-sort-options .filter-h-item[data-sort="newest"]').click();
    });

    // 4. Criteria "Danh mục" (category)
    $('#m-criteria-cat').on('click', function(e) {
        e.stopPropagation();
        $('#categoryMenu').toggleClass('show');
        renderCategoryDropdown();
    });

    // 5. Mobile Tab "Phổ biến"
    $('#m-tab-popular').on('click', function() {
        $('.mobile-tab-item').removeClass('active');
        $(this).addClass('active');
        $('#h-sort-options .filter-h-item[data-sort="newest"]').click();
    });

    // 6. Mobile Tab "Khuyến mãi HOT"
    $('#m-tab-promo').on('click', function() {
        $('.mobile-tab-item').removeClass('active');
        $(this).addClass('active');
        $('#h-sort-options .filter-h-item[data-sort="promo"]').click();
    });

    // 7. Mobile Tab "Giá" (Ascending/Descending toggle)
    $('#m-tab-price').on('click', function() {
        $('.mobile-tab-item').removeClass('active');
        $(this).addClass('active');
        
        let currentSort = $(this).attr('data-sort');
        let nextSort = currentSort === 'price_asc' ? 'price_desc' : 'price_asc';
        $(this).attr('data-sort', nextSort);
        
        // Show corresponding icon
        if (nextSort === 'price_asc') {
            $(this).html('Giá <i class="bi bi-sort-numeric-down text-primary"></i>');
        } else {
            $(this).html('Giá <i class="bi bi-sort-numeric-up-alt text-primary"></i>');
        }
        
        $(`#h-sort-options .filter-h-item[data-sort="${nextSort}"]`).click();
    });

    // 8. Mobile Tab "Bộ lọc" (slide drawer)
    $('#m-tab-filter').on('click', function(e) {
        e.stopPropagation();
        $('.filter-panel').addClass('show');
        $('#mobileFilterOverlay').addClass('show');
    });

    // Close drawers on overlay click
    $('#mobileFilterOverlay').on('click', function() {
        $('.filter-panel').removeClass('show');
        $('#mobileFilterOverlay').removeClass('show');
    });

    // Also close on sidebar apply/clear
    $filterApply.on('click', function() {
        $('.filter-panel').removeClass('show');
        $('#mobileFilterOverlay').removeClass('show');
    });
    $filterClear.on('click', function() {
        $('.filter-panel').removeClass('show');
        $('#mobileFilterOverlay').removeClass('show');
        $('#m-criteria-stock').removeClass('active');
    });

    if (window.refreshCartBadge) window.refreshCartBadge();
    loadCats();
    loadBrands();
    readFilters();
    if (!initialCatId && !initialCatSlug && !initialBrand) runInitialFetch();
})();

// ── Sticky Filter Panel (JS-driven, works regardless of parent overflow) ──
(function() {
    if (window.innerWidth < 768) return; // mobile dùng drawer riêng

    var $panel     = $('.filter-panel');
    var $col       = $('.filter-panel-col');
    if (!$panel.length || !$col.length) return;

    var TOP_OFFSET = 80;  // khoảng cách từ top viewport (px), = chiều cao header
    var colTop     = 0;   // vị trí top của cột filter so với document
    var panelW     = 0;   // chiều rộng của cột filter

    function recalc() {
        // Tắt pinned tạm để đo đúng vị trí tự nhiên
        $panel.removeClass('is-pinned').css('width', '');
        var rect = $col[0].getBoundingClientRect();
        colTop   = rect.top + window.pageYOffset;
        panelW   = $col[0].offsetWidth;
    }

    function onScroll() {
        if (window.innerWidth < 768) {
            $panel.removeClass('is-pinned').css('width', '');
            return;
        }
        var scrollY = window.pageYOffset;
        if (scrollY + TOP_OFFSET > colTop) {
            // Pin: cố định vào viewport
            $panel.addClass('is-pinned').css('width', panelW + 'px');
        } else {
            // Unpin: trả về vị trí tự nhiên
            $panel.removeClass('is-pinned').css('width', '');
        }
    }

    // Tính toán lần đầu sau khi trang đã load xong
    setTimeout(recalc, 300);

    $(window).on('scroll.filterPin', onScroll);
    $(window).on('resize.filterPin', function() {
        recalc();
        onScroll();
    });
})();
</script>
