# Relax Tours - Sitio Web

Sitio web de la agencia de viajes Relax Tours, ubicada en Villa Devoto, Buenos Aires.

## 📁 Estructura del Proyecto

```
relaxrours-static/
├── index.html              # Página principal
├── .htaccess              # Configuración Apache
├── css/
│   └── style.css          # Estilos principales
├── js/
│   └── main.js            # JavaScript principal
├── data/
│   └── content.json       # Datos del sitio (paquetes, ofertas, blog)
├── images/                # Imágenes del sitio
├── blog/                  # Páginas individuales de blog (generadas automáticamente)
└── admin/
    └── index.php          # Panel de administración
```

## 🚀 Instalación en Hostinger

### Paso 1: Subir archivos

1. Comprime todos los archivos en un archivo ZIP
2. Ingresa al panel de Hostinger
3. Ve a **Archivos** > **Administrador de archivos**
4. Sube el ZIP a la carpeta `public_html`
5. Descomprime el archivo

### Paso 2: Configurar permisos (IMPORTANTE)

Asegúrate de que estas carpetas tengan **permisos 755**:
- `data/` - Para guardar los cambios del backoffice
- `images/` - Para subir imágenes
- `blog/` - Para generar páginas de blog

Para cambiar permisos en Hostinger:
1. Selecciona la carpeta
2. Click en "Permisos" o "Permissions"
3. Selecciona 755
4. Aplica los cambios

### Paso 3: Acceder al backoffice

1. Ve a `tudominio.com/admin`
2. Usuario: `admin`
3. Contraseña: `relax2024`

**IMPORTANTE:** Cambia la contraseña en el archivo `admin/index.php`:
```php
define('ADMIN_PASS', 'tu-nueva-contraseña');
```

## 📱 Funcionalidades

### Sitio Web
- ✅ Hero con animaciones
- ✅ 9 paquetes destacados
- ✅ 5 ofertas flash
- ✅ Feed de Instagram (widget Elfsight)
- ✅ 6 artículos de blog
- ✅ Newsletter integrado con Mailchimp
- ✅ WhatsApp flotante
- ✅ Totalmente responsive

### Backoffice
- ✅ Gestión de paquetes (crear, editar, eliminar, subir imágenes)
- ✅ Gestión de ofertas flash
- ✅ Gestión de blog (genera páginas HTML automáticamente)
- ✅ Configuración general
- ✅ Botón "Guardar Cambios" en todos los formularios

## 🔧 Personalización

### Cambiar número de WhatsApp

Usa el backoffice en la pestaña "Configuración", o edita `data/content.json`:
```json
"config": {
    "whatsapp": "54911XXXXXXXX"
}
```

### Agregar más contenido

1. Ingresa al backoffice
2. Ve a la sección correspondiente
3. Click en "Nuevo"
4. Completa los datos
5. Click en "Guardar Cambios"

Los cambios se reflejan inmediatamente en el sitio.

## 🎨 Colores

- Naranja principal: `#F97316`
- Naranja oscuro: `#EA580C`
- Turquesa: `#14B8A6`
- Azul: `#3B82F6`
- Fondo oscuro: `#0F0F0F`

## 📞 Soporte

Para cualquier consulta:
- Email: info@relaxtours.com.ar

---

**Relax Tours** - Agencia de Viajes Familiar  
Leg. 16649 | Villa Devoto, CABA
