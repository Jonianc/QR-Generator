OT QR Automator — v0.4.0

Subida pública: PDF + BOX obligatorio, con clave.
- /otqr/upload/?k=CLAVE

Nombre de archivo OBLIGATORIO:
- OT 19230, MF3307, GARCES -MELIPILLA.pdf

Si el nombre no cumple el formato, la subida se rechaza.
Si no seleccionas BOX activo, la subida también se rechaza.

Almacenamiento PDF privado:
- Los PDFs ya no se publican en Media Library para acceso público directo.
- Se guardan en carpeta privada del plugin: `wp-content/uploads/otqr-private/`.
- Protección mínima incluida: `index.php`, `.htaccess` y `web.config`.
- Los PDFs se sirven solo por ruta tokenizada: `/otqr/ver/{token}/`.
- No se deben abrir ni compartir URLs directas de `/wp-content/uploads/`.
- Nota Nginx: `.htaccess` no aplica en Nginx; si la carpeta privada queda bajo `uploads`, puede requerirse regla adicional de servidor.

Gestión de BOX:
- Admin WP → OT QR → BOX
- Permite:
  - crear BOX
  - editar nombre
  - activar/desactivar
  - eliminar solo si no tiene OTs asignadas

Gestor privado:
- /otqr/manage/ (requiere login WP + manage_options)
- Permite:
  - editar Modelo/Cliente
  - asignar/cambiar BOX
  - reemplazar PDF
  - renombrar OT
  - eliminar OT
- Incluye columna BOX y filtro por BOX (incluye “Sin asignar”).
- Muestra enlaces públicos con token para abrir PDF y carátula.

URLs públicas con token:
- /otqr/ver/{token}/
- /otqr/cover/{token}/

Seguridad:
- Las rutas numéricas antiguas (/ot/NUM/, /ot/NUM/cover/, /otqr/?ot=NUM) ya no son públicas.
- El número OT se mantiene como dato interno administrativo.

Historial de cambios completo:
- Ver CHANGELOG.md

404 en /otqr/* => Ajustes → Enlaces permanentes → Guardar cambios.
