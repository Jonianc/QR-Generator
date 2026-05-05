OT QR Automator — v0.2.0

Subida pública: SOLO PDF (sin campos), con clave.
- /otqr/upload/?k=CLAVE

Nombre de archivo OBLIGATORIO:
- OT 19230, MF3307, GARCES -MELIPILLA.pdf

Si el nombre no cumple el formato, la subida se rechaza.

Gestor privado:
- /otqr/manage/ (requiere login WP + manage_options)
- Permite:
  - editar Modelo/Cliente
  - reemplazar PDF
  - renombrar OT
  - eliminar OT
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
