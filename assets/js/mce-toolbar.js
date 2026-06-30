(function (window) {
    'use strict';

    const ensureString = (value) => String(value || '').trim();

    const toAbsoluteUrl = (baseUrl, rawUrl) => {
        const base = ensureString(baseUrl).replace(/\/$/, '');
        const url = ensureString(rawUrl);
        if (!url) return '';
        if (/^https?:\/\//i.test(url)) return url;
        if (!base) return url;
        if (url.startsWith('/')) return `${base}${url}`;
        return `${base}/${url}`;
    };

    const uploadMediaToServer = (uploadUrl, baseUrl, blobInfo, mediaKind) => {
        return new Promise((resolve, reject) => {
            const endpoint = ensureString(uploadUrl);
            if (!endpoint) {
                reject(new Error('Thiếu uploadUrl cho TinyMCE'));
                return;
            }

            const formData = new FormData();
            formData.append('action', 'upload_media');
            formData.append('media_kind', mediaKind === 'video' ? 'video' : 'image');
            formData.append('file', blobInfo.blob(), blobInfo.filename());

            fetch(endpoint, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
                .then((response) => response.json())
                .then((result) => {
                    if (!result || !result.ok || !result.url) {
                        reject(new Error((result && result.msg) || 'Upload ảnh thất bại'));
                        return;
                    }
                    const fullUrl = toAbsoluteUrl(baseUrl, result.url);
                    resolve(fullUrl || String(result.url));
                })
                .catch((error) => reject(error));
        });
    };

    window.initMceToolbar = function initMceToolbar(options) {
        const opts = options || {};
        const selector = ensureString(opts.selector || '#p_mo_ta');
        const uploadUrl = ensureString(opts.uploadUrl || '');
        const baseUrl = ensureString(opts.baseUrl || '');
        const languageUrl = ensureString(opts.languageUrl || (baseUrl ? (baseUrl.replace(/\/$/, '') + '/assets/tinymce/langs/vi.js') : ''));
        const montserratCss = ensureString(opts.montserratCss || toAbsoluteUrl(baseUrl, '/assets/css/montserrat.css'));
        const onChange = typeof opts.onChange === 'function' ? opts.onChange : null;
        const onReady = typeof opts.onReady === 'function' ? opts.onReady : null;

        if (!window.tinymce) {
            return false;
        }

        const existing = window.tinymce.get(selector.replace('#', ''));
        if (existing) {
            existing.remove();
        }

        window.tinymce.init({
            selector,
            height: 520,
            language: 'vi',
            language_url: languageUrl,
            menubar: false,
            branding: false,
            statusbar: true,
            promotion: false,
            plugins: 'advlist autolink lists link image media table code fullscreen charmap searchreplace wordcount visualblocks',
            toolbar: [
                'undo redo | blocks fontfamily fontsize',
                'bold italic underline forecolor backcolor | alignleft aligncenter alignright alignjustify',
                'bullist numlist outdent indent | link image media table | removeformat code fullscreen'
            ].join(' | '),
            toolbar_mode: 'sliding',
            block_formats: 'Đoạn văn=p; Tiêu đề 2=h2; Tiêu đề 3=h3; Tiêu đề 4=h4',
            font_family_formats: 'Montserrat=Montserrat,sans-serif; Arial=arial,helvetica,sans-serif; Tahoma=tahoma,arial,helvetica,sans-serif; Verdana=verdana,geneva,sans-serif; Times New Roman=times new roman,times,serif; Courier New=courier new,courier,monospace',
            font_family_default: 'Montserrat',
            font_size_formats: '12px 14px 16px 18px 20px 24px 28px 32px 36px',
            content_css: montserratCss ? [montserratCss] : undefined,
            content_style: 'body { font-family: Montserrat, sans-serif !important; }',
            image_title: true,
            automatic_uploads: true,
            file_picker_types: 'image media',
            images_upload_handler: (blobInfo) => uploadMediaToServer(uploadUrl, baseUrl, blobInfo, 'image'),
            file_picker_callback: (callback, value, meta) => {
                const type = meta.filetype === 'media' ? 'video' : 'image';
                if (typeof MediaLibrary !== 'undefined') {
                    MediaLibrary.open({
                        type: type,
                        onSelect: (items) => {
                            if (items.length > 0) {
                                callback(items[0].url, { 
                                    title: items[0].title || '',
                                    alt: items[0].alt || ''
                                });
                            }
                        }
                    });
                } else {
                    const input = document.createElement('input');
                    input.type = 'file';
                    input.accept = type === 'video' ? 'video/mp4' : 'image/*';
                    input.onchange = () => {
                        const file = input.files && input.files[0] ? input.files[0] : null;
                        if (!file) return;
                        const fakeBlobInfo = {
                            blob: () => file,
                            filename: () => file.name || `file-${Date.now()}`
                        };
                        uploadMediaToServer(uploadUrl, baseUrl, fakeBlobInfo, type)
                            .then((url) => callback(url, { title: file.name || '' }))
                            .catch(() => {
                                if (window.toastr) {
                                    window.toastr.error(type === 'video' ? 'Không upload được video mô tả' : 'Không upload được ảnh mô tả');
                                }
                            });
                    };
                    input.click();
                }
            },
            setup: (editor) => {
                const notify = () => {
                    if (!onChange) return;
                    onChange(editor.getContent() || '');
                };
                editor.on('init', () => {
                    if (!onReady) return;
                    try {
                        onReady(editor);
                    } catch (error) {
                        console.error('MCE onReady error:', error);
                    }
                });
                editor.on('init change input undo redo setcontent', notify);
            }
        });
        return true;
    };
})(window);
