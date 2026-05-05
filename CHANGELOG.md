# Registro de cambios

Todos los cambios relevantes de este plugin se documentan en este archivo.

El formato está basado en Keep a Changelog.

## [Sin publicar]

### Agregado
- _Sin cambios aún._

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
