<?php
$page_title = "Blog - Relax Tours | Tips de Viaje";
$page_description = "Inspiración y consejos para tu próxima aventura. Tips de viaje de Relax Tours.";
$active_page = 'blog';
$base_url = '../';
include '../includes/header.php';
?>

<style>
    .blog-header-section {
        padding-top: 150px;
        padding-bottom: 60px;
        background: linear-gradient(135deg, var(--color-dark) 0%, #1a1a1a 100%);
        text-align: center;
        color: white;
    }
</style>

<header class="blog-header-section">
    <div class="container">
        <span class="section-tag" style="background: rgba(255,255,255,0.1); color: white;">Nuestro Blog</span>
        <h1 class="section-title">Tips de <span class="text-gradient">Viaje</span></h1>
        <p class="section-subtitle" style="color: rgba(255,255,255,0.7);">Inspiración y consejos para tu próxima
            aventura.</p>
    </div>
</header>

<section class="section">
    <div class="container">
        <div id="blog-grid" class="blog-grid">
            <!-- JS Rendering -->
        </div>
    </div>
</section>

<script>
    async function loadData() {
        try {
            const response = await fetch('<?php echo $base_url; ?>data/content.json');
            siteData = await response.json();
            renderBlogIndex();
        } catch (error) {
            console.error('Error cargando blog:', error);
        }
    }

    function renderBlogIndex() {
        const container = document.getElementById('blog-grid');
        if (!container || !siteData?.blog) return;

        const postsActivos = siteData.blog.filter(p => p.activo);

        container.innerHTML = postsActivos.map(post => {
            // Normalizar ruta de imagen (quitar '../' si viene del JSON para que se vea bien en esta vista)
            const imgPath = post.imagen.startsWith('../') ? post.imagen.substring(3) : post.imagen;
            
            // Link directo al archivo HTML generado por el backend (estamos en la misma carpeta)
            const postLink = `${post.slug}.html`;

            return `
            <article class="blog-card fade-in">
                <a href="${postLink}" class="blog-image-link" style="display:block; overflow:hidden;">
                    <div class="blog-image">
                        <img src="../${imgPath}" alt="${post.titulo}" loading="lazy">
                    </div>
                </a>
                <div class="blog-content">
                    <div class="blog-date">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                            <line x1="16" y1="2" x2="16" y2="6"/>
                            <line x1="8" y1="2" x2="8" y2="6"/>
                            <line x1="3" y1="10" x2="21" y2="10"/>
                        </svg>
                        ${post.fecha}
                    </div>
                    <h3 class="blog-title">
                        <a href="${postLink}" style="text-decoration:none; color:inherit;">${post.titulo}</a>
                    </h3>
                    <p class="blog-excerpt">${post.extracto}</p>
                    <a href="${postLink}" class="blog-link">
                        Leer más
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="5" y1="12" x2="19" y2="12"/>
                            <polyline points="12 5 19 12 12 19"/>
                        </svg>
                    </a>
                </div>
            </article>`;
        }).join('');

        if (window.scrollObserver) {
            container.querySelectorAll('.fade-in').forEach(el => window.scrollObserver.observe(el));
        }
    }

    loadData();
</script>

<?php include '../includes/footer.php'; ?>