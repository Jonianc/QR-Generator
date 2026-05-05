OT QR Automator — v0.1.9

Subida pública: SOLO PDF (sin campos), con clave.
- /otqr/upload/?k=CLAVE

Nombre de archivo OBLIGATORIO:
- OT 19230, MF3307, GARCES -MELIPILLA.pdf

Si el nombre no cumple el formato, rechaza la subida.

Gestionar y corregir (privado: requiere login WP + manage_options):
- /otqr/manage/
  - Edita Modelo/Cliente
  - Reemplaza PDF
  - Renombra OT
  - Elimina OT

Carátula pública:
- /ot/{NUM}/cover (muestra OT + MODELO + CLIENTE)

Changelog
- v0.1.9:
  - El gestor /otqr/manage/ deja de usar ?k= en enlaces, paginación, edición y acciones.
  - El botón Gestionar en subida pública ahora apunta a /otqr/manage/ sin clave.
  - El enlace Gestionar en admin WP ahora apunta a /otqr/manage/ sin clave.
  - El gestor usa nonce propio `otqr_manage_action` (la clave queda solo para upload).
  - Redirección a login endurecida sin depender de REQUEST_URI.
- v0.1.8:
  - Se restringe /otqr/manage/ a usuarios logueados con capacidad manage_options.
  - Se redirige al login de WordPress cuando no hay sesión.
  - Se mantiene /otqr/upload/?k=CLAVE como flujo público de subida.

404 en /otqr/* => Ajustes → Enlaces permanentes → Guardar cambios.
