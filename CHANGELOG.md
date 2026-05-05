# Registro de cambios

Todos los cambios relevantes de este plugin se documentan en este archivo.

## [Sin publicar]

### Agregado
- _Sin cambios aún._

## [0.2.0]

### Agregado
- Token público único por OT en meta `_otqr_public_token`.
- Nuevas rutas públicas por token: `/otqr/ver/{token}/` y `/otqr/cover/{token}/`.
- Migración automática de OTs existentes sin token al activar y al cargar gestor.

### Cambiado
- El QR de la carátula ahora apunta a `/otqr/ver/{token}/`.
- El gestor privado muestra enlaces públicos por token para PDF y carátula.

### Corregido
- Se eliminó `flush_rewrite_rules(false)` en cada subida pública.

### Seguridad
- Se bloqueó exposición pública por rutas numéricas: `/ot/NUM/`, `/ot/NUM/cover/` y `/otqr/?ot=NUM`.
- Tokens inválidos o inexistentes devuelven 404.

## [0.1.10]

### Corregido
- En `/otqr/upload/?k=CLAVE`, el botón **Gestionar** ahora apunta a `/otqr/manage/` sin exponer la clave pública.

## [0.1.9]

### Cambiado
- `/otqr/manage/` dejó de usar `k` en enlaces internos (edición, paginación y navegación relacionada).
- Las acciones del gestor pasaron a usar una acción nonce dedicada: `otqr_manage_action`.
- Los enlaces internos del gestor se alinearon para no propagar parámetros de clave pública.

### Seguridad
- Se endureció la redirección al login del gestor para no depender de URI de solicitud sin procesar.

## [0.1.8]

### Seguridad
- `/otqr/manage/` se restringió a usuarios autenticados con `current_user_can('manage_options')`.

## [0.1.7]

### Agregado
- Flujo de subida pública con clave en `/otqr/upload/?k=CLAVE`.
- Endpoint de gestión con clave y página de administración para ver/actualizar la clave.
- Flujo frontend para subir solo archivos PDF.
- Ruta de carátula OT `/ot/{NUM}/cover/`.

### Cambiado
- El parseo de nombre de archivo exige extracción de OT/modelo/cliente desde formato de nombre PDF.
