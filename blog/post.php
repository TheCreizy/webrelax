<?php
$page_title = "Cargando post... - Relax Tours";
$page_description = "Blog de viajes de Relax Tours.";
$active_page = 'blog';
$base_url = '../';
include '../includes/header.php';
?>

<style>
    .blog-post-header {
        padding-top: 150px;
        padding-bottom: 60px;
        background: linear-gradient(135deg, var(--color-dark) 0%, #1a1a1a 100%);
        text-align: center;
        color: white;
    }

    .back-to-blog {
        color: var(--color-orange);
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 24px;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 13px;
    }
</style>

<div id="post-container">
    <header class="blog-post-header">
        <div class="container">
            <a href="index.php" class="back-to-blog">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="19" y1="12" x2="5" y2="12" />
                    <polyline points="12 19 5 12 12 5" />
                </svg>
                Volver al Blog
            </a>
            <h1 id="post-title" class="section-title">...</h1>
            <div id="post-meta" style="color: rgba(255,255,255,0.6); font-size: 14px; margin-top: 16px;"></div>
        </div>
    </header>

    <article class="container" style="max-width: 800px;">
        <div id="post-image-container"
            style="margin-top: -40px; position: relative; border-radius: 24px; overflow: hidden; box-shadow: 0 20px 40px rgba(0,0,0,0.15);">
            <img id="post-image" src="" alt="" style="width: 100%; display: block;">
        </div>
        <div id="post-body" class="blog-post-content">
            <!-- Content here -->
        </div>

        <div
            style="margin-top: 60px; padding: 40px; background: #f9f9f9; border-radius: 24px; text-align: center; margin-bottom: 100px;">
            <h3 style="margin-bottom: 12px; font-size: 1.5rem;">¿Estás planeando tu próximo viaje?</h3>
            <p style="color: #6b7280; margin-bottom: 24px;">Dejanos ayudarte a crear la experiencia perfecta.
                Consultanos sin compromiso.</p>
            <button class="btn-primary" onclick="openWhatsApp()" style="padding: 16px 32px; font-size: 1rem;">
                Consultar por WhatsApp
            </button>
        </div>
    </article>
</div>

<script>
    async function loadPost() {
        const urlParams = new URLSearchParams(window.location.search);
        const slug = urlParams.get('slug');

        if (!slug) {
            window.location.href = 'index.php';
            return;
        }

        try {
            let response;
            try {
                response = await fetch('../data/content.json?v=' + Date.now());
                if (!response.ok) throw new Error('Status ' + response.status);
            } catch (e) {
                response = await fetch('../api/content?v=' + Date.now());
                if (!response.ok) {
                    response = await fetch('../api/content.php?v=' + Date.now());
                }
            }
            const data = await response.json();
            const post = data.blog.find(p => p.slug === slug);

            if (post) {
                document.title = post.titulo + ' - Relax Tours Blog';
                document.getElementById('post-title').textContent = post.titulo;
                document.getElementById('post-meta').innerHTML = `<span style="display: flex; align-items: center; justify-content: center; gap: 6px;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg> ${post.fecha}</span>`;
                const imgPath = (post.imagen || '').startsWith('../') ? post.imagen.substring(3) : (post.imagen || '');
                document.getElementById('post-image').src = '../' + imgPath;
                const normalizedBody = (post.contenido || '').replace(/src=(["'])(?:\.\/)?images\//gi, 'src=$1../images/');
                document.getElementById('post-body').innerHTML = normalizedBody;

                // SEO tags dynamic (basic)
                let meta = document.createElement('meta');
                meta.name = "description";
                meta.content = post.extracto;
                document.getElementsByTagName('head')[0].appendChild(meta);
            } else {
                document.getElementById('post-container').innerHTML = '<div class="container" style="padding: 100px 0; text-align: center;"><h1>Post no encontrado</h1><a href="index.php" class="btn-primary" style="margin-top: 24px;">Volver al Blog</a></div>';
            }
        } catch (error) {
            console.error('Error:', error);
        }
    }

    loadPost();
</script>

<?php include '../includes/footer.php'; ?>