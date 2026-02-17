/**
 * RELAX TOURS - JavaScript Principal
 */

// Datos de la web
var siteData = siteData || null;
const WHATSAPP_NUMBER = '5491138593924';

document.addEventListener('DOMContentLoaded', async function() {
  await loadSiteData();
  initNavbar();
  initScrollAnimations();
  initWhatsAppFloat();
  renderConfig();
  renderPaquetes();
  renderOfertas();
  if (!window.location.pathname.includes('/blog/')) {
    renderBlog();
  }
  initHeroParallax();
});

function initHeroParallax() {
  const hero = document.querySelector('.hero');
  const plane = document.querySelector('.hero-plane-image');
  const stats = document.querySelector('.hero-stats');
  const buttons = document.querySelectorAll('.hero-btn-reveal');
  
  if (!hero || !plane) return;

  window.addEventListener('scroll', () => {
    // Mobile check - disable parallax/reveals
    if (window.innerWidth < 769) return;

    const scrolled = window.scrollY;
    const viewportHeight = window.innerHeight;
    
    // Only animate while the hero is visible (scroll within 0 to viewportHeight)
    if (scrolled > viewportHeight) return;

    // Progress: 0 at top, 1 when hero is fully scrolled out
    const progress = scrolled / viewportHeight;

    // 1. Plane Animation: Move down as user scrolls
    const planeOffset = scrolled * 0.4;
    plane.style.transform = `translate(-50%, calc(-50% + ${planeOffset}px))`;

    // 2. Reveal Stats (at ~20% scroll through hero)
    if (progress > 0.2) {
      if (stats) stats.classList.add('reveal-visible');
    } else {
      if (stats) stats.classList.remove('reveal-visible');
    }

    // 3. Reveal Buttons (at ~40% scroll through hero)
    if (progress > 0.4) {
      buttons.forEach(btn => btn.classList.add('reveal-visible'));
    } else {
      buttons.forEach(btn => btn.classList.remove('reveal-visible'));
    }
  }, { passive: true });
}

async function loadSiteData() {
  try {
    const basePath = window.location.pathname.includes('/blog/') ? '../' : '';
    const response = await fetch(basePath + 'data/content.json');
    siteData = await response.json();
  } catch (error) {
    console.error('Error cargando datos:', error);
    siteData = getFallbackData();
  }
}

function getFallbackData() {
  return {
    paquetes: [],
    ofertas: [],
    blog: [],
    config: { whatsapp: '54938593924', email: 'info@relaxtours.com.ar' }
  };
}

function initNavbar() {
  const navbar = document.querySelector('.navbar');
  window.addEventListener('scroll', () => {
    navbar.classList.toggle('scrolled', window.scrollY > 50);
  });
  
  document.querySelectorAll('a[href^="#"], a[href^="./#"]').forEach(anchor => {
    anchor.addEventListener('click', function(e) {
      const href = this.getAttribute('href');
      if (!href || href === '#' || href.length <= 1) return;
      
      const targetId = href.startsWith('./') ? href.split('#')[1] : href.substring(1);
      const target = document.getElementById(targetId);
      
      if (target) {
        e.preventDefault();
        // Mantener URL limpia sin #
        // window.history.pushState(null, null, ' '); // Opcional, si queremos borrar el hash
        
        target.scrollIntoView({ behavior: 'smooth' });
      }
    });
  });

  // Mobile menu toggle
  const toggle = document.querySelector('.navbar-toggle');
  const mobileMenu = document.getElementById('mobile-menu');
  
  if (toggle && mobileMenu) {
    toggle.addEventListener('click', () => {
      toggle.classList.toggle('active');
      mobileMenu.classList.toggle('active');
      navbar.classList.toggle('menu-open');
      document.body.style.overflow = mobileMenu.classList.contains('active') ? 'hidden' : '';
    });

    // Close menu when a link is clicked
    mobileMenu.querySelectorAll('a').forEach(link => {
      link.addEventListener('click', () => {
        toggle.classList.remove('active');
        mobileMenu.classList.remove('active');
        navbar.classList.remove('menu-open');
        document.body.style.overflow = '';
      });
    });
  }
}

function initScrollAnimations() {
  const observerOptions = {
    threshold: 0.1,
    rootMargin: '0px 0px -50px 0px'
  };

  const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry, index) => { 
      if (entry.isIntersecting) {
        // Añadir retraso escalonado (stagger) para mejorar el movimiento
        if (!entry.target.classList.contains('visible')) {
          setTimeout(() => {
            entry.target.classList.add('visible');
          }, index * 250); 
        }
      } 
    });
  }, observerOptions);
  
  // Exponer observer globalmente para poder usarlo con contenido dinámico
  window.scrollObserver = observer;
  
  document.querySelectorAll('.fade-in, .paquete-card, .oferta-card, .blog-card, .bento-item').forEach(el => observer.observe(el));
  
  initBentoInteractions();
}

function initBentoInteractions() {
  const items = document.querySelectorAll('.bento-item');
  
  items.forEach(item => {
    item.addEventListener('mousemove', (e) => {
      const rect = item.getBoundingClientRect();
      const x = e.clientX - rect.left;
      const y = e.clientY - rect.top;
      
      const centerX = rect.width / 2;
      const centerY = rect.height / 2;
      
      // Much subtler values (max 4 degrees)
      const rotateX = ((y - centerY) / centerY) * -4;
      const rotateY = ((x - centerX) / centerX) * 4;
      
      item.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) scale3d(1.02, 1.02, 1.02)`;
      
      // Moving individual elements slightly for depth (Parallax)
      const content = item.querySelector('.bento-content');
      if (content) {
        content.style.transform = `translateZ(20px)`;
      }
    });
    
    item.addEventListener('mouseleave', () => {
      item.style.transform = 'perspective(1000px) rotateX(0deg) rotateY(0deg) scale3d(1, 1, 1)';
      const content = item.querySelector('.bento-content');
      if (content) {
        content.style.transform = 'translateZ(0px)';
      }
    });
  });
}

function initWhatsAppFloat() {
  const tooltip = document.querySelector('.whatsapp-tooltip');
  const closeBtn = document.querySelector('.whatsapp-tooltip-close');
  
  setTimeout(() => { if (tooltip) tooltip.style.display = 'block'; }, 5000);
  if (closeBtn) closeBtn.addEventListener('click', () => { if (tooltip) tooltip.style.display = 'none'; });
  setTimeout(() => { if (tooltip) tooltip.style.display = 'none'; }, 13000);
}

function openWhatsApp(message = '') {
  const text = message || 'Hola! Vengo de la web de Relax Tours y quiero consultar sobre un viaje.';
  const number = siteData?.config?.whatsapp || WHATSAPP_NUMBER;
  
  const isMobile = /iPhone|iPad|iPod|Android/i.test(navigator.userAgent);
  const url = isMobile 
    ? `https://wa.me/${number}?text=${encodeURIComponent(text)}`
    : `https://web.whatsapp.com/send?phone=${number}&text=${encodeURIComponent(text)}`;
    
  window.open(url, '_blank');
}

function renderConfig() {
  if (!siteData?.config) return;
  const c = siteData.config;
  
  // Update footer info using data attributes
  const updateEl = (key, value) => {
    const el = document.querySelector(`[data-config="${key}"]`);
    if (el) el.textContent = value;
  };

  updateEl('direccion', c.direccion);
  updateEl('telefono', c.telefono);
  updateEl('email', c.email);
  
  const footerLegajo = document.querySelector('.footer-legajo');
  if (footerLegajo) footerLegajo.textContent = `Leg. ${c.legajo}`;
  
  // Update social links
  const igHandle = c.instagram.replace('@', '');
  const fbHandle = c.facebook;

  document.querySelectorAll('a[href*="instagram.com"]').forEach(link => {
    link.href = `https://instagram.com/${igHandle}`;
  });
  
  document.querySelectorAll('a[href*="facebook.com"]').forEach(link => {
    link.href = `https://facebook.com/${fbHandle}`;
  });
}

/* CAROUSEL LOGIC */
let carouselInterval = null;
let carouselCurrentIndex = 0;
let carouselItemsData = [];

function renderPaquetes() {
  if (!siteData?.paquetes) return;
  const paquetesActivos = siteData.paquetes.filter(p => p.activo);

  // Check for Grid (paquetes.php)
  const gridContainer = document.getElementById('paquetes-grid');
  if (gridContainer) {
    renderPaquetesGrid(gridContainer, paquetesActivos);
  }

  // Check for Carousel (index.php)
  const carouselContainer = document.getElementById('paquetes-carousel');
  if (carouselContainer) {
    if (paquetesActivos.length === 0) return;
    carouselItemsData = paquetesActivos;
    renderPaquetesCarousel(carouselContainer);
  }
}

function renderPaquetesGrid(container, paquetes) {
  container.innerHTML = paquetes.map(paquete => generatePaqueteCardHTML(paquete, false)).join('');
  initScrollAnimations();
}

function renderPaquetesCarousel(container) {
  // Create HTML for all items, but wrapped in carousel-item
  container.innerHTML = carouselItemsData.map((paquete, index) => `
    <div class="carousel-item" data-index="${index}">
      ${generatePaqueteCardHTML(paquete, true)}
    </div>
  `).join('');

  // Initial state
  updateCarouselState();
  
  // Auto play
  startCarouselAutoPlay();

  // Pause on hover ONLY on the active item
  // We use event delegation on the container because class 'active' changes dynamically
  if (container) {
    container.addEventListener('mousemove', (e) => {
      // Check if hovering over an active item (or its children)
      const target = e.target.closest('.carousel-item');
      if (target && target.classList.contains('active')) {
        stopCarouselAutoPlay();
      } else {
        // If moved out of active item (even if still in container), resume
        // But we need to be careful not to restart constantly.
        // startCarouselAutoPlay checks if interval exists so it's safe.
        startCarouselAutoPlay();
      }
    });
    
    container.addEventListener('mouseleave', startCarouselAutoPlay);
  }
}

function generatePaqueteCardHTML(paquete, isCarousel) {
  // Same HTML generation for consistency, wrapped or not
  // If isCarousel is true, we might want fewer animations or different classes if needed,
  // but for now we reuse the card structure.
  // Note: key change is removing 'fade-in' class for carousel items to avoid conflict with carousel transitions
  const animationClass = isCarousel ? '' : 'fade-in';
  
  return `
    <div class="paquete-card ${animationClass}">
      <div class="paquete-image">
        <img src="${paquete.imagen}" alt="${paquete.nombre}" loading="lazy">
        ${paquete.popular ? `<span class="paquete-badge"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="12" height="12"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>Popular</span>` : ''}
      </div>
      <div class="paquete-content">
        <h3 class="paquete-title">${paquete.nombre}</h3>
        <div class="paquete-info">
          <div class="paquete-info-item">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
            <div><span class="paquete-info-label">Destino</span><span class="paquete-info-value">${paquete.destino}</span></div>
          </div>
          <div class="paquete-info-item">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            <div><span class="paquete-info-label">Duración</span><span class="paquete-info-value">${paquete.duracion}</span></div>
          </div>
        </div>
        <div class="paquete-incluye">
          ${paquete.incluye.map(item => `<span class="paquete-incluye-item"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>${item}</span>`).join('')}
        </div>
        <div class="paquete-footer">
          <div class="paquete-price-row">
            <div><span class="paquete-precio-label">Precio</span><div class="paquete-precio">Desde $${paquete.precio.toLocaleString()} ${paquete.moneda || 'USD'}</div></div>
          </div>
          <div class="paquete-footer-actions">
            <button class="btn-whatsapp-dark" onclick="toggleConsultar(event, '${paquete.id}')">
              Consultar Ahora
            </button>
            <div id="consultar-dropdown-${paquete.id}" class="consultar-dropdown">
              <a href="#" class="btn-action-wa" onclick="openWhatsApp('Hola! Quiero consultar sobre el paquete ${paquete.nombre}'); return false;">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg> WhatsApp
              </a>
              <a href="#contacto" class="btn-action-email" onclick="scrollToContact(); return false;">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg> Email
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
  `;
}

function updateCarouselState() {
  const items = document.querySelectorAll('.carousel-item');
  if (!items.length) return;

  const total = items.length;
  
  items.forEach((item, index) => {
    // Clean classes
    item.classList.remove('active', 'prev', 'next', 'hidden');
    
    // Determine relative position in circular array
    let offset = (index - carouselCurrentIndex + total) % total;
    
    if (offset === 0) {
      item.classList.add('active');
    } else if (offset === total - 1) { // Same as -1
      item.classList.add('prev');
    } else if (offset === 1) {
      item.classList.add('next');
    } else {
      item.classList.add('hidden');
    }
  });
}

function moveCarousel(direction) {
  const total = carouselItemsData.length;
  if (!total) return;
  
  carouselCurrentIndex = (carouselCurrentIndex + direction + total) % total;
  updateCarouselState();
}

function startCarouselAutoPlay() {
  stopCarouselAutoPlay(); // clear existing if any
  carouselInterval = setInterval(() => {
    moveCarousel(1);
  }, 3000);
}

function stopCarouselAutoPlay() {
  if (carouselInterval) {
    clearInterval(carouselInterval);
    carouselInterval = null;
  }
}

// Expose moveCarousel to global scope for buttons
window.moveCarousel = moveCarousel;

function renderOfertas() {
  const container = document.getElementById('ofertas-grid');
  if (!container || !siteData?.ofertas) return;
  
  const ofertasActivas = siteData.ofertas.filter(o => o.activa);
  
  container.innerHTML = ofertasActivas.map(oferta => `
    <div class="oferta-card fade-in">
      <div class="oferta-image" onclick="enlargeImage('${oferta.image || oferta.imagen}')">
        <img src="${oferta.image || oferta.imagen}" alt="${oferta.titulo}" loading="lazy">
        <div class="oferta-hover-overlay">
          <div class="ver-promocion-text">VER PROMOCION</div>
        </div>
        <span class="oferta-badge ${oferta.badgeColor}">${oferta.badge}</span>
        <div class="oferta-timer"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>${oferta.tiempo}</div>
        <div class="oferta-content">
          <h3 class="oferta-title">${oferta.titulo}</h3>
          <p class="oferta-desc">${oferta.descripcion}</p>
        </div>
        <a href="#contacto" class="btn-oferta-email" onclick="event.stopPropagation(); scrollToContact(); return false;">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
        </a>
        <a href="#" class="oferta-whatsapp" onclick="event.stopPropagation(); openWhatsApp('Hola! Quiero consultar sobre la oferta: “${oferta.titulo}”'); return false;">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
        </a>
      </div>
    </div>
  `).join('');
  
  initScrollAnimations();
}

function enlargeImage(src) {
  const overlay = document.createElement('div');
  overlay.className = 'image-overlay';
  overlay.innerHTML = `
    <div class="image-overlay-content">
      <img src="${src}" alt="Oferta">
      <button class="image-overlay-close">&times;</button>
    </div>
  `;
  document.body.appendChild(overlay);
  setTimeout(() => overlay.classList.add('active'), 10);
  
  overlay.onclick = (e) => {
    if (e.target.classList.contains('image-overlay') || e.target.classList.contains('image-overlay-close')) {
      overlay.classList.remove('active');
      setTimeout(() => overlay.remove(), 300);
    }
  };
}

function renderBlog() {
  const container = document.getElementById('blog-grid');
  if (!container || !siteData?.blog) return;
  
  // Mostrar solo los últimos 6 posts activos
  const postsActivos = siteData.blog.filter(p => p.activo).slice(0, 6);
  
  // Detectar path: Si estamos en root, el link es "blog/slug.html". Si ya estamos en /blog/, es "slug.html"
  const linkPrefix = window.location.pathname.includes('/blog/') ? '' : 'blog/';

  container.innerHTML = postsActivos.map(post => `
    <article class="blog-card fade-in">
      <a href="${linkPrefix}${post.slug}.html" class="blog-image-link" style="display:block; overflow:hidden;">
        <div class="blog-image">
            <img src="${post.imagen}" alt="${post.titulo}" loading="lazy">
        </div>
      </a>
      <div class="blog-content">
        <div class="blog-date">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            ${post.fecha}
        </div>
        <h3 class="blog-title">
            <a href="${linkPrefix}${post.slug}.html" style="text-decoration:none; color:inherit;">
                ${post.titulo}
            </a>
        </h3>
        <p class="blog-excerpt">${post.extracto}</p>
        <a href="${linkPrefix}${post.slug}.html" class="blog-link">
            Leer tip de ${post.destino || 'viaje'}
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
        </a>
      </div>
    </article>
  `).join('');
  
  initScrollAnimations();
}

// Helpers & Handlers
function openBlogModal(slug) {
  const post = siteData?.blog?.find(p => p.slug === slug);
  if (!post) {
    // If not found in siteData (rare), try to go to the page
    window.location.href = `blog/post.php?slug=${slug}`;
    return;
  }

  const modal = document.getElementById('blog-modal');
  if (modal) {
    const basePath = window.location.pathname.includes('/blog/') ? '../' : '';
    document.getElementById('blog-modal-title').textContent = post.titulo;
    document.getElementById('blog-modal-image').src = basePath + post.imagen;
    document.getElementById('blog-modal-meta').innerHTML = `<span>${post.fecha}</span>`;
    document.getElementById('blog-modal-body').innerHTML = post.contenido;
    
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
    
    // Update hash for deep link
    window.history.pushState(null, null, `#blog-${slug}`);
  }
}

function closeBlogModal() {
  const modal = document.getElementById('blog-modal');
  if (modal) {
    modal.classList.remove('active');
    document.body.style.overflow = '';
    // Clean up hash
    if (window.location.hash.includes('#blog-')) {
      window.history.pushState(null, null, window.location.pathname + window.location.search);
    }
  }
}

function openArrepentimientoModal() {
  const modal = document.getElementById('arrepentimiento-modal');
  if (modal) {
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
  }
}

function closeArrepentimientoModal() {
  const modal = document.getElementById('arrepentimiento-modal');
  if (modal) {
    modal.classList.remove('active');
    document.body.style.overflow = '';
  }
}

// Close active modals on Escape key
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') {
    const activeModal = document.querySelector('.modal.active');
    if (activeModal) {
      activeModal.classList.remove('active');
      document.body.style.overflow = '';
      // Clean up blog hash if needed
      if (window.location.hash.includes('#blog-')) {
        window.history.pushState(null, null, window.location.pathname + window.location.search);
      }
    }
  }
});

// Check for hash on load
window.addEventListener('load', () => {
  const hash = window.location.hash;
  if (hash.startsWith('#blog-')) {
    const slug = hash.replace('#blog-', '');
    // Wait for data to be ready
    const checkData = setInterval(() => {
      if (siteData) {
        openBlogModal(slug);
        clearInterval(checkData);
      }
    }, 100);
    setTimeout(() => clearInterval(checkData), 5000); // safety
  }
});

function toggleConsultar(event, id) {
  event.stopPropagation();
  const dropdown = document.getElementById(`consultar-dropdown-${id}`);
  const allDropdowns = document.querySelectorAll('.consultar-dropdown');
  
  allDropdowns.forEach(d => {
    if (d.id !== `consultar-dropdown-${id}`) d.classList.remove('active');
  });
  
  dropdown.classList.toggle('active');
}

document.addEventListener('click', () => {
  document.querySelectorAll('.consultar-dropdown').forEach(d => d.classList.remove('active'));
});

function scrollToContact() {
  const contactSection = document.getElementById('contacto');
  if (contactSection) {
    contactSection.scrollIntoView({ behavior: 'smooth' });
  }
}

async function handleFormSubmit(form, statusId) {
  const statusEl = document.getElementById(statusId);
  const submitBtn = form.querySelector('button[type="submit"]');
  const originalBtnText = submitBtn.textContent;
  
  statusEl.textContent = 'Enviando…';
  statusEl.className = 'form-status';
  submitBtn.disabled = true;
  submitBtn.classList.add('btn-loading');
  submitBtn.textContent = 'Enviando…';

  try {
    const formData = new FormData(form);
    const response = await fetch('send-mail.php', {
      method: 'POST',
      body: formData
    });
    
    const result = await response.json();
    
    if (result.status === 'success') {
      statusEl.textContent = result.message;
      statusEl.className = 'form-status success';
      form.reset();
      if (statusId === 'arrepentimiento-form-status') {
        setTimeout(closeArrepentimientoModal, 3000);
      }
    } else {
      statusEl.textContent = result.message;
      statusEl.className = 'form-status error';
    }
  } catch (error) {
    statusEl.textContent = 'Error al enviar el mensaje. Intente nuevamente.';
    statusEl.className = 'form-status error';
  } finally {
    submitBtn.disabled = false;
    submitBtn.classList.remove('btn-loading');
    submitBtn.textContent = originalBtnText;
  }
}

// Global scope expose
window.openWhatsApp = openWhatsApp;
window.openArrepentimientoModal = openArrepentimientoModal;
window.closeArrepentimientoModal = closeArrepentimientoModal;
window.openBlogModal = openBlogModal;
window.closeBlogModal = closeBlogModal;
window.toggleConsultar = toggleConsultar;
window.scrollToContact = scrollToContact;

document.addEventListener('DOMContentLoaded', () => {
  const contactForm = document.getElementById('contact-form');
  if (contactForm) {
    contactForm.addEventListener('submit', (e) => {
      e.preventDefault();
      handleFormSubmit(contactForm, 'contact-form-status');
    });
  }

  const arrepentimientoForm = document.getElementById('arrepentimiento-form');
  if (arrepentimientoForm) {
    arrepentimientoForm.addEventListener('submit', (e) => {
      e.preventDefault();
      handleFormSubmit(arrepentimientoForm, 'arrepentimiento-form-status');
    });
  }
});

window.reloadSiteData = async function() {
  await loadSiteData();
  renderPaquetes();
  renderOfertas();
  renderBlog();
};
