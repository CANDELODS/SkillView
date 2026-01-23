(function () {
    //---------------MODAL DE LAS CARDS DE LA SECCIÓN BLOG----------------//
    const blogModal = document.getElementById('sv-blog-modal');

    if (blogModal) {
        const blogCloseTriggers = blogModal.querySelectorAll('[data-sv-blog-close]');
        const blogTitleEl = blogModal.querySelector('[data-sv-blog-title]');
        const blogDescEl = blogModal.querySelector('[data-sv-blog-desc]');
        const blogContentEl = blogModal.querySelector('[data-sv-blog-content]');
        const blogTagsWrap = blogModal.querySelector('[data-sv-blog-tags]');
        const blogImg = blogModal.querySelector('#sv-blog-modal-img');
        const blogImgWebp = blogModal.querySelector('#sv-blog-modal-img-webp');

        const openBlogModal = () => {
            blogModal.classList.add('is-open');
            blogModal.setAttribute('aria-hidden', 'false');
            //Antes usabamos: document.body.style.overflow = 'hidden';
            //Ahora unificamos con el helper global (app.js): window.SV.lockScroll()
            if (window.SV && typeof window.SV.lockScroll === 'function') {
                window.SV.lockScroll();
            } else {
                //Fallback por si por alguna razón no cargó app.js primero
                document.body.classList.add('no-scroll');
            }
        };

        const closeBlogModal = () => {
            blogModal.classList.remove('is-open');
            blogModal.setAttribute('aria-hidden', 'true');
            //Antes usabamos: document.body.style.overflow = '';
            //Ahora unificamos con el helper global (app.js): window.SV.unlockScroll()
            if (window.SV && typeof window.SV.unlockScroll === 'function') {
                window.SV.unlockScroll();
            } else {
                //Fallback por si por alguna razón no cargó app.js primero
                document.body.classList.remove('no-scroll');
            }
        };

        blogCloseTriggers.forEach(btn => btn.addEventListener('click', closeBlogModal));

        document.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-sv-blog-open]');
            if (!btn) return;

            const title = btn.dataset.title || 'Artículo';
            const desc = btn.dataset.desc || '';
            const content = btn.dataset.content || '';
            const tagsStr = btn.dataset.tags || '';
            const imgWebp = btn.dataset.imageWebp || '';
            const imgJpg = btn.dataset.imageJpg || '';

            if (blogTitleEl) blogTitleEl.textContent = title;
            if (blogDescEl) blogDescEl.textContent = desc;

            // Contenido: lo pintamos como texto (seguro). Si luego quieres HTML, lo controlamos.
            if (blogContentEl) blogContentEl.textContent = content;

            // Imagen
            if (blogImgWebp) blogImgWebp.setAttribute('srcset', imgWebp);
            if (blogImg) {
                blogImg.setAttribute('src', imgJpg);
                blogImg.setAttribute('alt', `Imagen del artículo ${title}`);
            }

            // Tags
            if (blogTagsWrap) {
                blogTagsWrap.innerHTML = '';
                const tags = tagsStr.split(',').map(t => t.trim()).filter(Boolean);
                tags.forEach(tag => {
                    const span = document.createElement('span');
                    span.className = 'sv-blog-modal__tag';
                    span.textContent = tag;
                    blogTagsWrap.appendChild(span);
                });
            }

            openBlogModal();
        });
    }
    //---------------FIN MODAL DE LAS CARDS DE LA SECCIÓN BLOG----------------//
})();
