/**
 * Media Gallery Library
 * Premium WordPress-style media management for Paint&More
 */

const MediaLibrary = {
    options: {
        multiple: false,
        type: '', // '', 'image', 'video'
        onSelect: null
    },
    data: [],
    selected: [],
    currentTab: 'library',

    init() {
        this.cacheDOM();
        this.bindEvents();
    },

    cacheDOM() {
        this.modal = document.getElementById('mediaGalleryModal');
        this.grid = this.modal.querySelector('.mg-grid');
        this.tabs = this.modal.querySelectorAll('.mg-tab');
        this.uploadArea = this.modal.querySelector('.mg-upload-area');
        this.fileInput = this.modal.querySelector('#mgFileInput');
        this.sidebar = this.modal.querySelector('.mg-sidebar');
        this.searchInput = this.modal.querySelector('.mg-input');
        this.filterType = this.modal.querySelector('#mgFilterType');
        this.btnSelect = this.modal.querySelector('#mgBtnSelect');
        this.progressContainer = this.modal.querySelector('.mg-progress-container');
        this.progressFill = this.modal.querySelector('.mg-progress-fill');
        this.progressText = this.modal.querySelector('.mg-progress-text');
        this.mobileInfo = this.modal.querySelector('.mg-mobile-info');
        this.btnSync = this.modal.querySelector('#mgBtnSync');
    },

    bindEvents() {
        // Tab switching
        this.tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                const target = tab.dataset.tab;
                this.switchTab(target);
            });
        });

        // Close modal
        this.modal.querySelector('.mg-close').addEventListener('click', () => this.close());
        this.modal.querySelector('.mg-btn-secondary').addEventListener('click', () => this.close());

        // Search & Filter
        this.searchInput.addEventListener('input', this.debounce(() => this.fetchMedia(), 300));
        this.filterType.addEventListener('change', () => this.fetchMedia());

        // Upload
        this.uploadArea.addEventListener('click', () => this.fileInput.click());
        this.fileInput.addEventListener('change', (e) => this.handleUpload(e.target.files));

        // Drag and Drop upload
        this.uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            this.uploadArea.style.borderColor = 'var(--mg-primary)';
        });
        this.uploadArea.addEventListener('dragleave', () => {
            this.uploadArea.style.borderColor = '';
        });
        this.uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            this.uploadArea.style.borderColor = '';
            this.handleUpload(e.dataTransfer.files);
        });

        // Select button
        this.btnSelect.addEventListener('click', () => {
            if (this.selected.length > 0 && this.options.onSelect) {
                this.options.onSelect(this.selected);
                this.close();
            }
        });

        // Mobile Info Toggle
        this.mobileInfo.addEventListener('click', () => {
            this.sidebar.classList.toggle('active');
        });

        // Sync button
        if (this.btnSync) {
            this.btnSync.addEventListener('click', () => this.syncMedia());
        }
    },

    open(options = {}) {
        this.options = { ...this.options, ...options };
        this.selected = [];
        this.updateBtnState();
        this.modal.classList.add('active');
        this.switchTab('library');
        this.fetchMedia();
        document.body.style.overflow = 'hidden';
    },

    close() {
        this.modal.classList.remove('active');
        this.sidebar.classList.remove('active');
        document.body.style.overflow = '';
    },

    switchTab(tab) {
        this.currentTab = tab;
        this.tabs.forEach(t => t.classList.toggle('active', t.dataset.tab === tab));
        
        const libraryView = this.modal.querySelector('#mgViewLibrary');
        const uploadView = this.modal.querySelector('#mgViewUpload');
        
        if (tab === 'library') {
            libraryView.style.display = 'flex';
            uploadView.style.display = 'none';
        } else {
            libraryView.style.display = 'none';
            uploadView.style.display = 'flex';
        }
    },

    async syncMedia() {
        if (this.isSyncing) return;
        this.isSyncing = true;
        
        const btn = this.btnSync;
        const originalHtml = btn.innerHTML;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Đang quét...';
        btn.disabled = true;

        try {
            const response = await fetch(`${baseUrl}/core_user/ajax/media.php?action=sync`);
            const res = await response.json();
            
            if (res.ok) {
                toastr.success(`Đã đồng bộ thành công ${res.added} tệp mới.`);
                this.fetchMedia();
            } else {
                toastr.error(res.msg || 'Lỗi đồng bộ.');
            }
        } catch (error) {
            console.error('Sync error:', error);
            toastr.error('Lỗi kết nối server.');
        } finally {
            this.isSyncing = false;
            btn.innerHTML = originalHtml;
            btn.disabled = false;
        }
    },

    async fetchMedia() {
        const search = this.searchInput.value;
        const type = this.filterType.value || this.options.type;
        
        this.grid.innerHTML = '<div style="padding: 20px; color: var(--mg-text-muted)">Đang tải...</div>';
        
        try {
            const response = await fetch(`${baseUrl}/core_user/ajax/media.php?action=list&search=${encodeURIComponent(search)}&type=${encodeURIComponent(type)}`);
            const res = await response.json();
            
            if (res.ok) {
                this.data = res.data;
                this.renderGrid();
            } else {
                this.grid.innerHTML = `<div style="padding: 20px; color: red">${res.msg || 'Lỗi tải dữ liệu.'}</div>`;
            }
        } catch (error) {
            console.error('Fetch error:', error);
            this.grid.innerHTML = `<div style="padding: 20px; color: red">Lỗi tải dữ liệu: ${error.message}</div>`;
        }
    },

    renderGrid() {
        if (this.data.length === 0) {
            this.grid.innerHTML = '<div style="padding: 20px; color: var(--mg-text-muted)">Không tìm thấy tệp nào.</div>';
            return;
        }

        this.grid.innerHTML = '';
        this.data.forEach(item => {
            const div = document.createElement('div');
            div.className = 'mg-item';
            div.dataset.id = item.id;
            
            if (item.file_type.startsWith('image/')) {
                div.innerHTML = `<img src="${item.url}" alt="${item.title}">`;
            } else if (item.file_type.startsWith('video/')) {
                div.innerHTML = `<video src="${item.url}#t=0.1" preload="metadata" muted></video>
                                 <div class="mg-video-badge"><i class="fa-solid fa-play"></i></div>`;
            } else {
                div.innerHTML = `<div class="mg-icon"><i class="fa-solid fa-file"></i></div>`;
            }

            div.addEventListener('click', () => this.toggleSelect(item, div));
            this.grid.appendChild(div);
        });
    },

    toggleSelect(item, element) {
        if (this.options.multiple) {
            const index = this.selected.findIndex(i => i.id === item.id);
            if (index > -1) {
                this.selected.splice(index, 1);
                element.classList.remove('selected');
            } else {
                this.selected.push(item);
                element.classList.add('selected');
            }
        } else {
            this.modal.querySelectorAll('.mg-item').forEach(i => i.classList.remove('selected'));
            this.selected = [item];
            element.classList.add('selected');
        }
        
        this.updateSidebar(item);
        this.updateBtnState();
    },

    updateSidebar(item) {
        if (!item) {
            this.sidebar.innerHTML = '<div style="color: var(--mg-text-muted); text-align:center">Chọn một tệp để xem chi tiết</div>';
            return;
        }

        const isImage = item.file_type.startsWith('image/');
        const previewHtml = isImage 
            ? `<img src="${item.url}" alt="">` 
            : item.file_type.startsWith('video/') 
                ? `<video src="${item.url}" controls></video>`
                : `<i class="fa-solid fa-file-lines" style="font-size: 3rem; color: #fff"></i>`;

        this.sidebar.innerHTML = `
            <div class="mg-sidebar-header">
                <h4>Chi tiết tệp</h4>
                <button class="mg-sidebar-close" onclick="document.querySelector('.mg-sidebar').classList.remove('active')">&times;</button>
            </div>
            <div class="mg-preview">${previewHtml}</div>
            <div class="mg-details">
                <div class="mg-field">
                    <label>Tên tệp</label>
                    <input type="text" value="${item.file_name}" readonly title="${item.file_name}">
                </div>
                <div class="mg-field">
                    <label>Loại tệp</label>
                    <input type="text" value="${item.file_type}" readonly>
                </div>
                <div class="mg-field">
                    <label>Dung lượng</label>
                    <input type="text" value="${(item.file_size / 1024 / 1024).toFixed(2)} MB" readonly>
                </div>
                <hr>
                <div class="mg-field">
                    <label>Tiêu đề</label>
                    <input type="text" value="${item.title || ''}" data-field="title" onchange="MediaLibrary.saveMetadata(${item.id}, this)">
                </div>
                <div class="mg-field">
                    <label>Mô tả SEO (Alt)</label>
                    <input type="text" value="${item.alt_text || ''}" data-field="alt_text" onchange="MediaLibrary.saveMetadata(${item.id}, this)">
                </div>
                <div class="mg-field">
                    <label>URL tệp</label>
                    <div style="display: flex; gap: 5px">
                        <input type="text" value="${item.url}" id="mgFileUrl" readonly>
                        <button class="mg-btn mg-btn-secondary" style="padding: 8px 12px" onclick="MediaLibrary.copyUrl()"><i class="fa-solid fa-copy"></i></button>
                    </div>
                </div>
            </div>
            <button class="mg-btn mg-btn-secondary" style="margin-top: 20px; color: #ef4444; border-color: #fee2e2; background: #fff" onclick="MediaLibrary.deleteMedia(${item.id})">
                <i class="fa-solid fa-trash-can"></i> Xóa vĩnh viễn
            </button>
        `;
    },

    async saveMetadata(id, input) {
        const field = input.dataset.field;
        const value = input.value;
        const formData = new FormData();
        formData.append('action', 'update');
        formData.append('id', id);
        formData.append(field, value);

        try {
            await fetch(`${baseUrl}/core_user/ajax/media.php`, {
                method: 'POST',
                body: formData
            });
        } catch (error) {
            console.error('Save error:', error);
        }
    },

    async deleteMedia(id) {
        if (!confirm('Bạn có chắc chắn muốn xóa tệp này? Hành động này không thể hoàn tác.')) return;
        
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('id', id);

        try {
            const response = await fetch(`${baseUrl}/core_user/ajax/media.php`, {
                method: 'POST',
                body: formData
            });
            const res = await response.json();
            if (res.ok) {
                this.fetchMedia();
                this.updateSidebar(null);
            } else {
                alert(res.msg);
            }
        } catch (error) {
            console.error('Delete error:', error);
        }
    },

    copyUrl() {
        const input = document.getElementById('mgFileUrl');
        input.select();
        document.execCommand('copy');
        alert('Đã sao chép liên kết!');
    },

    updateBtnState() {
        this.btnSelect.disabled = this.selected.length === 0;
        this.btnSelect.textContent = this.selected.length > 1 
            ? `Chọn (${this.selected.length}) media` 
            : 'Chọn media';
    },

    handleUpload(files) {
        if (!files.length) return;

        const formData = new FormData();
        formData.append('action', 'upload');
        for (let i = 0; i < files.length; i++) {
            formData.append('files[]', files[i]);
        }

        this.progressContainer.classList.add('active');
        this.progressFill.style.width = '0%';
        this.progressText.textContent = 'Đang chuẩn bị...';

        const xhr = new XMLHttpRequest();
        xhr.open('POST', `${baseUrl}/core_user/ajax/media.php`, true);

        xhr.upload.onprogress = (e) => {
            if (e.lengthComputable) {
                const percent = Math.round((e.loaded / e.total) * 100);
                this.progressFill.style.width = percent + '%';
                this.progressText.textContent = `Đang tải lên: ${percent}%`;
            }
        };

        xhr.onload = () => {
            const res = JSON.parse(xhr.responseText);
            this.progressContainer.classList.remove('active');
            if (res.ok) {
                this.switchTab('library');
                this.fetchMedia();
                if (res.errors && res.errors.length > 0) {
                    alert(res.errors.join('\n'));
                }
            } else {
                alert(res.msg || 'Lỗi tải lên.');
            }
        };

        xhr.onerror = () => {
            this.progressContainer.classList.remove('active');
            alert('Lỗi kết nối mạng.');
        };

        xhr.send(formData);
    },

    debounce(func, wait) {
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), wait);
        };
    }
};

// Initialize when DOM ready
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('mediaGalleryModal')) {
        MediaLibrary.init();
    }
});
