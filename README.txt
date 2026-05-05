OT QR Automator — v0.1.8

Subida: SOLO PDF (sin campos).
- /otqr/upload/?k=CLAVE

Nombre de archivo OBLIGATORIO:
- OT 19230, MF3307, GARCES -MELIPILLA.pdf

Si el nombre no cumple el formato, rechaza la subida.

Gestionar y corregir (requiere login WP con permisos de administración):
- /otqr/manage/
  - Edita Modelo/Cliente
  - Reemplaza PDF
  - Renombra OT

Carátula:
- /ot/{NUM}/cover (muestra OT + MODELO + CLIENTE)

Changelog
- v0.1.8:
  - Se restringe /otqr/manage/ a usuarios logueados con capacidad manage_options.
  - Se redirige al login de WordPress cuando no hay sesión.
  - Se mantiene /otqr/upload/?k=CLAVE como flujo público de subida.

404 en /otqr/* => Ajustes → Enlaces permanentes → Guardar cambios.
