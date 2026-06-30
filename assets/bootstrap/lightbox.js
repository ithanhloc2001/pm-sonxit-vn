/*!
 * Lightbox for Bootstrap 5 v1.8.5 (Custom Themed - Premium UI)
 * Original: https://trvswgnr.github.io/bs5-lightbox/
 */
!(function () {
    "use strict";
    
    // Inject Custom Styles
    if (!document.getElementById('bs5-lightbox-styles')) {
        const style = document.createElement('style');
        style.id = 'bs5-lightbox-styles';
        style.textContent = `
            .lightbox-modal.modal {
                z-index: 10000;
                padding: 0 !important;
                overflow: hidden !important;
                display: flex !important;
                align-items: center;
                justify-content: center;
            }
            .lightbox-modal .modal-dialog {
                max-width: min(1100px, 90vw);
                width: 100%;
                height: 88vh;
                height: 88dvh;
                margin: 0 auto;
                display: flex;
                align-items: center;
            }
            .lightbox-modal .modal-dialog > .modal-content { width: 100%; }
            .lightbox-modal .modal-content {
                background: #fff;
                border: 1px solid rgba(15, 23, 42, 0.08);
                box-shadow: 0 24px 60px rgba(15, 23, 42, 0.25);
                border-radius: 16px;
                height: 100%;
                display: flex;
                flex-direction: column;
                overflow: hidden;
            }
            .lightbox-modal .modal-body {
                flex: 1 1 0%;
                min-height: 0;
                position: relative;
                overflow: hidden;
                background: #f8fafc;
            }
            .lightbox-toolbar {
                height: 56px;
                background: #fff;
                border-bottom: 1px solid rgba(15, 23, 42, 0.06);
                z-index: 10002;
                flex: 0 0 auto;
            }
            .lightbox-counter {
                font-size: 0.95rem;
                font-weight: 600;
                letter-spacing: 0.05em;
                color: #0f172a;
            }
            .btn-lightbox-action {
                width: 38px;
                height: 38px;
                border-radius: 50%;
                border: 1px solid rgba(15, 23, 42, 0.1);
                background: #fff;
                color: #334155;
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                transition: all 0.2s ease;
                font-size: 1rem;
                box-shadow: 0 2px 4px rgba(15, 23, 42, 0.04);
            }
            .btn-lightbox-action:hover {
                background: #f1f5f9;
                border-color: rgba(15, 23, 42, 0.2);
                color: #0f172a;
                transform: scale(1.05);
            }
            .btn-lightbox-action:active {
                transform: scale(0.95);
            }
            .lightbox-footer {
                background: #fff;
                border-top: 1px solid rgba(15, 23, 42, 0.06);
                z-index: 10002;
                flex: 0 0 auto;
                width: 100%;
            }
            .lightbox-caption-container {
                background: #f8fafc;
                border-bottom: 1px solid rgba(15, 23, 42, 0.05);
                color: #334155;
                font-weight: 500;
            }
            .lightbox-thumbnails-container {
                max-width: 100%;
                scrollbar-width: thin;
                scrollbar-color: rgba(15, 23, 42, 0.2) transparent;
                overflow-x: auto;
                overflow-y: hidden;
                -webkit-overflow-scrolling: touch;
            }
            .lightbox-thumbnails-container::-webkit-scrollbar {
                height: 6px;
            }
            .lightbox-thumbnails-container::-webkit-scrollbar-thumb {
                background: rgba(15, 23, 42, 0.2);
                border-radius: 3px;
            }
            .lightbox-thumbnails {
                display: flex;
                gap: 8px;
                padding: 8px 16px;
                justify-content: center;
                flex-wrap: nowrap !important;
            }
            .lightbox-thumb-item {
                width: 60px;
                height: 60px;
                border-radius: 8px;
                border: 2px solid rgba(15, 23, 42, 0.1);
                cursor: pointer;
                overflow: hidden;
                transition: all 0.2s ease;
                opacity: 0.7;
                flex-shrink: 0;
                position: relative;
                background: #f8fafc;
            }
            .lightbox-thumb-item img, .lightbox-thumb-item video {
                width: 100%;
                height: 100%;
                object-fit: cover;
            }
            .lightbox-thumb-item.active {
                border-color: var(--theme-primary, #0c4c29);
                box-shadow: 0 0 10px rgba(12, 76, 41, 0.35);
                opacity: 1;
                transform: scale(1.05);
            }
            .lightbox-thumb-item:hover {
                opacity: 1;
            }
            .lightbox-thumb-video-icon {
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                color: #fff;
                font-size: 1.2rem;
                text-shadow: 0 2px 4px rgba(0,0,0,0.5);
            }
            .lightbox-carousel {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
            }
            .lightbox-carousel .carousel-inner { width: 100%; height: 100%; }
            .lightbox-carousel .carousel-item { height: 100%; }
            .lightbox-item-container {
                width: 100%;
                height: 100%;
                display: flex;
                align-items: center;
                justify-content: center;
                position: relative;
                overflow: hidden;
                padding: 16px;
                box-sizing: border-box;
            }
            .lightbox-item-container img, .lightbox-item-container video {
                max-width: 100%;
                max-height: 100%;
                width: auto;
                height: auto;
                object-fit: contain;
                display: block;
                margin: auto;
                transition: transform 0.25s cubic-bezier(0.1, 0.8, 0.3, 1);
                transform-origin: center center;
            }
            .carousel-item .lightbox-caption { display: none !important; }
            .carousel-control-prev, .carousel-control-next { width: 8%; opacity: 1; z-index: 10001; }
            .lightbox-nav {
                display: flex; align-items: center; justify-content: center;
                transition: all 0.2s ease;
                color: #334155;
                background: #fff;
                width: 44px;
                height: 44px;
                border-radius: 50%;
                box-shadow: 0 4px 12px rgba(15, 23, 42, 0.12);
                border: 1px solid rgba(15, 23, 42, 0.08);
            }
            .carousel-control-prev:hover .lightbox-nav, .carousel-control-next:hover .lightbox-nav {
                background: #f8fafc;
                color: #0f172a;
                transform: scale(1.05);
            }
            .lightbox-nav i { font-size: 1.5rem; line-height: 1; }
            
            @media (max-width: 768px) {
                .lightbox-modal .modal-dialog {
                    max-width: 100vw !important;
                    width: 100vw !important;
                    height: 100dvh !important;
                    height: 100vh !important;
                    margin: 0 !important;
                    padding: 0 !important;
                }
                .lightbox-modal .modal-content {
                    height: 100% !important;
                    border-radius: 0 !important;
                    border: none !important;
                }
                .lightbox-nav { display: none; }
                .btn-lightbox-action { width: 36px; height: 36px; font-size: 0.95rem; }
                .lightbox-thumb-item { width: 50px; height: 50px; }
                .lightbox-thumbnails {
                    justify-content: start;
                }
            }
        `;
        document.head.appendChild(style);
    }

    var t = {
            d: function (e, s) {
                for (var a in s)
                    t.o(s, a) &&
                        !t.o(e, a) &&
                        Object.defineProperty(e, a, {
                            enumerable: !0,
                            get: s[a],
                        });
            },
            o: function (t, e) {
                return Object.prototype.hasOwnProperty.call(t, e);
            },
        },
        e = {};
    t.d(e, {
        default: function () {
            return i;
        },
    });
    var s = window.bootstrap;
    const a = { Modal: s.Modal, Carousel: s.Carousel };
    class o {
        constructor(t) {
            let e =
                arguments.length > 1 && void 0 !== arguments[1]
                    ? arguments[1]
                    : {};
            ((this.hash = this.randomHash()),
                (this.settings = Object.assign(
                    Object.assign(
                        Object.assign({}, a.Modal.Default),
                        a.Carousel.Default,
                    ),
                    {
                        interval: !1,
                        target: '[data-toggle="lightbox"]',
                        gallery: "",
                        size: "xl",
                        constrain: !0,
                    },
                )),
                (this.settings = Object.assign(
                    Object.assign({}, this.settings),
                    e,
                )),
                (this.modalOptions = (() => {
                    const opts = this.setOptionsFromSettings(a.Modal.Default);
                    opts.backdrop = true;
                    return opts;
                })()),
                (this.carouselOptions = (() =>
                    this.setOptionsFromSettings(a.Carousel.Default))()),
                "string" == typeof t &&
                    ((this.settings.target = t),
                    (t = document.querySelector(this.settings.target))),
                (this.el = t),
                (this.type = t.dataset.type || ""),
                t.dataset.size && (this.settings.size = t.dataset.size),
                (this.src = this.getSrc(t)),
                (this.sources = this.getGalleryItems()),
                (this.startIdx = this.findGalleryItemIndex(this.sources, this.type && "image" !== this.type ? this.type + this.src : this.src)),
                this.createCarousel(),
                this.createModal());
        }
        show() {
            (document.body.appendChild(this.modalElement), this.modal.show());
            setTimeout(() => {
                const backdrops = document.querySelectorAll('.modal-backdrop');
                if (backdrops.length) {
                    backdrops[backdrops.length - 1].classList.add('lightbox-backdrop');
                }
            }, 0);
        }
        hide() {
            this.modal.hide();
        }
        setOptionsFromSettings(t) {
            return Object.keys(t).reduce(
                (t, e) => Object.assign(t, { [e]: this.settings[e] }),
                {},
            );
        }
        getSrc(t) {
            let e =
                t.dataset.src ||
                t.dataset.remote ||
                t.href ||
                "http://via.placeholder.com/1600x900";
            if ("html" === t.dataset.type) return e;
            /\:\/\//.test(e) || (e = window.location.origin + e);
            const s = new URL(e);
            return (
                (t.dataset.footer || t.dataset.caption) &&
                    s.searchParams.set(
                        "caption",
                        t.dataset.footer || t.dataset.caption,
                    ),
                s.toString()
            );
        }
        getGalleryItems() {
            let t;
            if (this.settings.gallery) {
                if (Array.isArray(this.settings.gallery))
                    return this.settings.gallery;
                t = this.settings.gallery;
            } else this.el.dataset.gallery && (t = this.el.dataset.gallery);
            return t
                ? [
                      ...new Set(
                          Array.from(
                              document.querySelectorAll(
                                  '[data-gallery="'.concat(t, '"]'),
                              ),
                              (t) =>
                                  ""
                                      .concat(
                                          t.dataset.type ? t.dataset.type : "",
                                      )
                                      .concat(this.getSrc(t)),
                          ),
                      ),
                  ]
                : ["".concat(this.type ? this.type : "").concat(this.src)];
        }
        getYoutubeId(t) {
            if (!t) return !1;
            const e = t.match(
                /^.*(youtu.be\/|v\/|u\/\w\/|embed\/|watch\?v=|&v=)([^#&?]*).*/,
            );
            return !(!e || 11 !== e[2].length) && e[2];
        }
        getYoutubeLink(t) {
            const e = this.getYoutubeId(t);
            if (!e) return !1;
            const s = t.split("?");
            let a = s.length > 1 ? "?" + s[1] : "";
            return "https://www.youtube.com/embed/".concat(e).concat(a);
        }
        getInstagramEmbed(t) {
            if (/instagram/.test(t))
                return (
                    (t += /\/embed$/.test(t) ? "" : "/embed"),
                    '<iframe src="'.concat(
                        t,
                        '" class="start-50 translate-middle-x" style="max-width: 500px" frameborder="0" scrolling="no" allowtransparency="true"></iframe>',
                    )
                );
        }
        isEmbed(t) {
            const e = new RegExp(
                    "(" + o.allowedEmbedTypes.join("|") + ")",
                ).test(t),
                s =
                    /\.(png|jpe?g|gif|svg|webp)/i.test(t) ||
                    "image" === this.el.dataset.type;
            return e || !s;
        }
        createCarousel() {
            const t = document.createElement("template"),
                e = o.allowedMediaTypes.join("|"),
                s = this.sources
                    .map((t, s) => {
                        t = t.replace(/\/$/, "");
                        const a = new RegExp("^(".concat(e, ")"), "i"),
                            o = /^html/.test(t),
                            i = /^image/.test(t),
                            isVideo = /^video/.test(t) || /\.(mp4|webm|ogg)/i.test(t);
                            
                        a.test(t) && (t = t.replace(a, ""));
                        const l = new URLSearchParams(t.split("?")[1]);
                        let r = "",
                            c = t;
                        if (l.get("caption")) {
                            try {
                                ((c = new URL(t)),
                                    c.searchParams.delete("caption"),
                                    (c = c.toString()));
                            } catch (e) {
                                c = t;
                            }
                            r =
                                '<div class="lightbox-caption"><div class="lightbox-caption-text">'.concat(
                                    l.get("caption"),
                                    "</div></div>",
                                );
                        }
                        
                        let d = "";
                        const onLoaded = "this.closest('.carousel-item').querySelector('.lightbox-spinner').style.display='none'";
                        
                        if (isVideo) {
                            d = '<video src="'
                                .concat(c, '" class="d-block img-fluid" controls playsinline onloadedmetadata="')
                                .concat(onLoaded, '" onerror="')
                                .concat(onLoaded, '"></video>');
                        } else if (i || !this.isEmbed(t)) {
                            d = '<img src="'
                                .concat(c, '" class="d-block img-fluid" onload="')
                                .concat(onLoaded, '" onerror="')
                                .concat(onLoaded, '" />');
                        } else {
                            const u = this.getInstagramEmbed(t),
                                m = this.getYoutubeLink(t);
                            let h = "";
                            if (m) {
                                t = m;
                                h = 'title="YouTube video player" frameborder="0" allow="accelerometer autoplay clipboard-write encrypted-media gyroscope picture-in-picture"';
                            }
                            d = u || '<iframe src="'
                                .concat(t, '" ')
                                .concat(h, ' allowfullscreen onload="')
                                .concat(onLoaded, '"></iframe>');
                        }

                        if (o) d = t;

                        return '\n\t\t\t\t<div class="carousel-item '
                            .concat(s === this.startIdx ? "active" : "", '">\n\t\t\t\t\t')
                            .concat(
                                '<div class="lightbox-spinner position-absolute top-50 start-50 translate-middle text-white"><div class="spinner-border" style="width: 3rem; height: 3rem" role="status"></div></div>',
                                '\n\t\t\t\t\t<div class="lightbox-item-container">',
                            )
                            .concat(d, "</div>\n\t\t\t\t\t")
                            .concat(r, "\n\t\t\t\t</div>");
                    })
                    .join(""),
                i =
                    this.sources.length < 2
                        ? ""
                        : '\n\t\t\t<button id="lightboxCarousel-'
                              .concat(
                                  this.hash,
                                  '-prev" class="carousel-control carousel-control-prev" type="button" data-bs-target="#lightboxCarousel-',
                              )
                              .concat(
                                  this.hash,
                                  '" data-bs-slide="prev">\n\t\t\t\t<div class="lightbox-nav"><i class="bi bi-chevron-left"></i></div>\n\t\t\t</button>\n\t\t\t<button id="lightboxCarousel-',
                              )
                              .concat(
                                  this.hash,
                                  '-next" class="carousel-control carousel-control-next" type="button" data-bs-target="#lightboxCarousel-',
                              )
                              .concat(
                                  this.hash,
                                  '" data-bs-slide="next">\n\t\t\t\t<div class="lightbox-nav"><i class="bi bi-chevron-right"></i></div>\n\t\t\t</button>',
                              );
            let n = "lightbox-carousel carousel slide";
            "fullscreen" === this.settings.size &&
                (n +=
                    " position-absolute w-100 translate-middle top-50 start-50");
            
            const l = '\n\t\t\t<div id="lightboxCarousel-'
                .concat(this.hash, '" class="')
                .concat(n, '" data-bs-ride="carousel" data-bs-interval="')
                .concat(
                    this.carouselOptions.interval,
                    '">\n\t\t\t\t<div class="carousel-inner">\n\t\t\t\t\t',
                )
                .concat(s, "\n\t\t\t\t</div>\n\t\t\t\t")
                .concat(i, "\n\t\t\t</div>");
            ((t.innerHTML = l.trim()),
                (this.carouselElement = t.content.firstChild));
            const r = Object.assign(Object.assign({}, this.carouselOptions), {
                keyboard: !1,
            });
            this.carousel = new a.Carousel(this.carouselElement, r);
            
            this.carousel.to(this.startIdx);
            
            this.carouselElement.addEventListener('slide.bs.carousel', e => {
                const counter = this.modalElement.querySelector('.lightbox-counter');
                if (counter) counter.innerText = `${e.to + 1} / ${this.sources.length}`;

                // Pause all videos in the carousel
                this.carouselElement.querySelectorAll('video').forEach(v => v.pause());
            });

            this.carouselElement.addEventListener('slid.bs.carousel', e => {
                // Play video in the active slide
                const activeVideo = e.relatedTarget.querySelector('video');
                if (activeVideo) {
                    activeVideo.volume = 0.2;
                    activeVideo.play().catch(() => {});
                }
            });

            return (
                !0 === this.carouselOptions.keyboard &&
                    document.addEventListener("keydown", (t) => {
                        if ("ArrowLeft" === t.code) {
                            const t = document.getElementById(
                                "lightboxCarousel-".concat(this.hash, "-prev"),
                            );
                            return (t && t.click(), !1);
                        }
                        if ("ArrowRight" === t.code) {
                            const t = document.getElementById(
                                "lightboxCarousel-".concat(this.hash, "-next"),
                            );
                            return (t && t.click(), !1);
                        }
                    }),
                this.carousel
            );
        }
        findGalleryItemIndex(t, e) {
            let s = 0;
            for (const a of t) {
                if (a.includes(e)) return s;
                s++;
            }
            return 0;
        }
        createThumbnails() {
            const e = o.allowedMediaTypes.join("|");
            const a = new RegExp("^(".concat(e, ")"), "i");
            
            const thumbItemsHtml = this.sources.map((item, idx) => {
                let url = item;
                const isVideo = /^video/.test(url) || /\.(mp4|webm|ogg)/i.test(url);
                url = url.replace(a, ""); // clean prefix
                
                let innerHtml = '';
                if (isVideo) {
                    innerHtml = `<video src="${url}" muted playsinline></video><div class="lightbox-thumb-video-icon"><i class="bi bi-play-circle-fill"></i></div>`;
                } else {
                    innerHtml = `<img src="${url}" alt="thumb-${idx}" />`;
                }
                
                return `<div class="lightbox-thumb-item" data-idx="${idx}">${innerHtml}</div>`;
            }).join('');
            
            return `<div class="lightbox-thumbnails-container w-100 py-3 overflow-auto">
                <div class="lightbox-thumbnails d-flex justify-content-center gap-2 px-3">
                    ${thumbItemsHtml}
                </div>
            </div>`;
        }
        setActiveThumbnail(idx) {
            if (!this.modalElement) return;
            const thumbs = this.modalElement.querySelectorAll('.lightbox-thumb-item');
            thumbs.forEach((t, i) => {
                if (i === idx) {
                    t.classList.add('active');
                    t.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
                } else {
                    t.classList.remove('active');
                }
            });
        }
        bindThumbnailsClick() {
            if (!this.modalElement) return;
            const thumbs = this.modalElement.querySelectorAll('.lightbox-thumb-item');
            thumbs.forEach(t => {
                t.addEventListener('click', () => {
                    const idx = parseInt(t.dataset.idx, 10);
                    this.carousel.to(idx);
                });
            });
        }
        updateControlsState(idx) {
            if (!this.modalElement) return;
            const btnZoomIn = this.modalElement.querySelector('.btn-zoom-in');
            const btnZoomOut = this.modalElement.querySelector('.btn-zoom-out');
            
            const item = this.sources[idx];
            const isVideo = /^video/.test(item) || /\.(mp4|webm|ogg)/i.test(item);
            
            if (btnZoomIn && btnZoomOut) {
                if (isVideo) {
                    btnZoomIn.style.display = 'none';
                    btnZoomOut.style.display = 'none';
                } else {
                    btnZoomIn.style.display = 'flex';
                    btnZoomOut.style.display = 'flex';
                }
            }
        }
        updateCaption(idx = null) {
            const index = idx !== null ? idx : this.startIdx;
            const container = this.modalElement.querySelector('.lightbox-caption-container');
            if (!container) return;

            const activeItem = this.carouselElement.querySelectorAll('.carousel-item')[index];
            const captionEl = activeItem ? activeItem.querySelector('.lightbox-caption-text') : null;
            
            if (captionEl && captionEl.innerHTML.trim()) {
                container.innerHTML = captionEl.innerHTML;
                container.style.display = 'block';
            } else {
                container.innerHTML = '';
                container.style.display = 'none';
            }
        }
        createModal() {
            const t = document.createElement("template"),
                e = `
                <div class="modal lightbox-modal fade" id="lightboxModal-${this.hash}" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <!-- Toolbar -->
                            <div class="lightbox-toolbar d-flex align-items-center justify-content-between px-3 py-2">
                                <div class="lightbox-counter text-white fw-bold"></div>
                                <div class="lightbox-actions d-flex align-items-center gap-2">
                                    <button type="button" class="btn-lightbox-action btn-zoom-in" title="Phóng to"><i class="bi bi-zoom-in"></i></button>
                                    <button type="button" class="btn-lightbox-action btn-zoom-out" title="Thu nhỏ"><i class="bi bi-zoom-out"></i></button>
                                    <button type="button" class="btn-lightbox-action btn-download" title="Tải xuống"><i class="bi bi-download"></i></button>
                                    <button type="button" class="btn-lightbox-action btn-close-lightbox" data-bs-dismiss="modal" aria-label="Close"><i class="bi bi-x-lg"></i></button>
                                </div>
                            </div>
                            <!-- Body -->
                            <div class="modal-body p-0">
                            </div>
                            <!-- Footer -->
                            <div class="lightbox-footer d-flex flex-column align-items-center w-100">
                                <div class="lightbox-caption-container text-white py-2 px-3 text-center small w-100" style="background: rgba(0, 0, 0, 0.4); display: none;"></div>
                                ${this.sources.length > 1 ? this.createThumbnails() : ''}
                            </div>
                        </div>
                    </div>
                </div>
                `;
            t.innerHTML = e.trim();
            this.modalElement = t.content.firstChild;
            
            // Append carousel inside modal-body
            this.modalElement.querySelector(".modal-body").appendChild(this.carouselElement);
            
            this.updateCaption();
            this.bindThumbnailsClick();
            
            // Zoom & Pan Variables
            let currentScale = 1;
            let isDragging = false;
            let startX = 0, startY = 0;
            let translateX = 0, translateY = 0;

            const getActiveImg = () => {
                return this.carouselElement.querySelector('.carousel-item.active img');
            };

            const updateTransform = (img) => {
                if (img) {
                    img.style.transform = `scale(${currentScale}) translate(${translateX}px, ${translateY}px)`;
                }
            };

            const resetZoom = (img) => {
                currentScale = 1;
                translateX = 0;
                translateY = 0;
                isDragging = false;
                if (img) {
                    img.style.transform = '';
                    img.style.cursor = '';
                }
            };

            const btnZoomIn = this.modalElement.querySelector('.btn-zoom-in');
            const btnZoomOut = this.modalElement.querySelector('.btn-zoom-out');
            const btnDownload = this.modalElement.querySelector('.btn-download');

            if (btnZoomIn) {
                btnZoomIn.addEventListener('click', () => {
                    const img = getActiveImg();
                    if (!img) return;
                    currentScale = Math.min(4, currentScale + 0.5);
                    img.style.cursor = 'grab';
                    updateTransform(img);
                });
            }

            if (btnZoomOut) {
                btnZoomOut.addEventListener('click', () => {
                    const img = getActiveImg();
                    if (!img) return;
                    currentScale = Math.max(1, currentScale - 0.5);
                    if (currentScale <= 1) {
                        resetZoom(img);
                    } else {
                        updateTransform(img);
                    }
                });
            }

            this.carouselElement.addEventListener('mousedown', function(e) {
                const img = getActiveImg();
                if (!img || currentScale <= 1) return;
                e.preventDefault();
                isDragging = true;
                startX = e.clientX - translateX * currentScale;
                startY = e.clientY - translateY * currentScale;
                img.style.cursor = 'grabbing';
            });

            window.addEventListener('mousemove', function(e) {
                if (!isDragging || currentScale <= 1) return;
                const img = getActiveImg();
                if (!img) return;
                translateX = (e.clientX - startX) / currentScale;
                translateY = (e.clientY - startY) / currentScale;
                updateTransform(img);
            });

            window.addEventListener('mouseup', function() {
                if (isDragging) {
                    isDragging = false;
                    const img = getActiveImg();
                    if (img) img.style.cursor = 'grab';
                }
            });

            this.carouselElement.addEventListener('touchstart', function(e) {
                const img = getActiveImg();
                if (!img || currentScale <= 1) return;
                isDragging = true;
                const touch = e.touches[0];
                startX = touch.clientX - translateX * currentScale;
                startY = touch.clientY - translateY * currentScale;
            });

            this.carouselElement.addEventListener('touchmove', function(e) {
                if (!isDragging || currentScale <= 1) return;
                const img = getActiveImg();
                if (!img) return;
                const touch = e.touches[0];
                translateX = (touch.clientX - startX) / currentScale;
                translateY = (touch.clientY - startY) / currentScale;
                updateTransform(img);
            });

            this.carouselElement.addEventListener('touchend', function() {
                isDragging = false;
            });

            if (btnDownload) {
                btnDownload.addEventListener('click', () => {
                    const activeItem = this.carouselElement.querySelector('.carousel-item.active');
                    if (!activeItem) return;
                    const media = activeItem.querySelector('img, video');
                    if (media && media.src) {
                        const url = media.src;
                        const a = document.createElement('a');
                        a.href = url;
                        const filename = url.split('/').pop() || 'download';
                        a.download = filename;
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);
                    }
                });
            }

            this.carouselElement.addEventListener('slide.bs.carousel', e => {
                const counter = this.modalElement.querySelector('.lightbox-counter');
                if (counter) counter.innerText = `${e.to + 1} / ${this.sources.length}`;

                const oldActiveItem = this.carouselElement.querySelector('.carousel-item.active');
                if (oldActiveItem) {
                    const oldImg = oldActiveItem.querySelector('img');
                    if (oldImg) resetZoom(oldImg);
                }
                currentScale = 1;
                translateX = 0;
                translateY = 0;
                isDragging = false;

                this.setActiveThumbnail(e.to);
                this.updateControlsState(e.to);
                this.updateCaption(e.to);
            });

            this.modalElement.addEventListener("hidden.bs.modal", () => {
                this.modalElement.remove();
            });

            this.modalElement.addEventListener("shown.bs.modal", () => {
                const counter = this.modalElement.querySelector('.lightbox-counter');
                if (counter) counter.innerText = `${this.startIdx + 1} / ${this.sources.length}`;

                this.updateControlsState(this.startIdx);
                this.setActiveThumbnail(this.startIdx);
                this.updateCaption(this.startIdx);

                const activeVideo = this.modalElement.querySelector('.carousel-item.active video');
                if (activeVideo) {
                    activeVideo.volume = 0.2;
                    activeVideo.play().catch(() => {});
                }
            });

            this.modalElement.querySelector("[data-bs-dismiss]").addEventListener("click", () => this.modal.hide());
            this.modal = new a.Modal(this.modalElement, this.modalOptions);
            return this.modal;
        }
        randomHash() {
            let t =
                arguments.length > 0 && void 0 !== arguments[0]
                    ? arguments[0]
                    : 8;
            return Array.from({ length: t }, () =>
                Math.floor(36 * Math.random()).toString(36),
            ).join("");
        }
    }
    ((o.allowedEmbedTypes = ["embed", "youtube", "vimeo", "instagram", "url"]),
        (o.allowedMediaTypes = [...o.allowedEmbedTypes, "image", "video", "html"]),
        (o.defaultSelector = '[data-toggle="lightbox"]'),
        (o.initialize = function (t) {
            t.preventDefault();
            new o(this).show();
        }),
        document
            .querySelectorAll(o.defaultSelector)
            .forEach((t) => t.addEventListener("click", o.initialize)),
        "undefined" != typeof window &&
            window.bootstrap &&
            (window.bootstrap.Lightbox = o));
    var i = o;
    window.Lightbox = e.default;
})();
