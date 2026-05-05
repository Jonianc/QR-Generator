# Registro de cambios

Todos los cambios relevantes de este plugin se documentan en este archivo.

## [Sin publicar]

- _Sin cambios aún._

## [0.3.0]

### Agregado
- Almacenamiento privado para PDFs en `uploads/otqr-private` con archivos de protección (`index.php`, `.htaccess`, `web.config`).
- Entrega de PDFs mediante ruta tokenizada `/otqr/ver/{token}/` controlada por PHP.
- Metadatos privados por OT: ruta, nombre original, tamaño, hash y fecha de actualización.
- Migración automática (una sola vez por versión) de PDFs existentes desde Media Library/uploads al almacenamiento privado.

### Seguridad
- Los PDFs ya no quedan expuestos como enlaces directos de `/wp-content/uploads/` tras migración exitosa.
- Validación de ruta privada con `realpath()` para evitar path traversal al servir PDFs.

## [0.2.4]

### Corregido
- Orden visual del selector BOX y campo PDF en subida pública.
- Orden visual del selector BOX en edición de OT.
- Restaurada sección [Sin publicar] del changelog formal.

## [0.2.3]

### Corregido
- Validación de BOX inválido/inactivo en subida pública: ahora se detiene el flujo y no crea/actualiza OTs.
- Edición de OT sin asignar BOX accidentalmente: se agregó opción `Sin asignar` y al seleccionarla se limpia `_otqr_box_id`.
- Filtro `Sin asignar` ahora considera OTs sin meta y con meta vacío.
- Paginación del gestor conserva el filtro BOX activo.
- Orden visual en subida pública: primero BOX y luego PDF.

## [0.2.2]

### Agregado
- Gestión editable de BOX desde admin (`OT QR -> BOX`): crear, renombrar, activar/desactivar y eliminar con bloqueo si tiene OTs asignadas.
- Selector obligatorio **“BOX asignado al QR”** en `/otqr/upload/?k=CLAVE`, mostrando solo BOX activos.
- Guardado de BOX por OT en meta `_otqr_box_id`.
- Columna BOX y filtro por BOX (incluye “Sin asignar”) en `/otqr/manage/`.
- Visualización de BOX en `/otqr/cover/{token}/`.

### Compatibilidad
- OTs existentes sin BOX se mantienen operativas y se muestran como **“Sin asignar”**.

## [0.2.1]

### Corregido
- Enlaces PDF del gestor ahora usan ruta tokenizada `/otqr/ver/{token}/`.
- Restaurado CSS completo de impresión en `/otqr/cover/{token}/`.
- Eliminado código muerto del bloque `/otqr/` que quedó tras retorno 404.
- Agregada migración/flush de rewrite una sola vez por cambio de versión.

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
