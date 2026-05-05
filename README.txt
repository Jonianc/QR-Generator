OT QR Automator — v0.2.2

Subida pública: PDF + BOX obligatorio, con clave.
- /otqr/upload/?k=CLAVE

Nombre de archivo OBLIGATORIO:
- OT 19230, MF3307, GARCES -MELIPILLA.pdf

Si el nombre no cumple el formato, la subida se rechaza.
Si no seleccionas BOX activo, la subida también se rechaza.

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
