# [ISSUE-001] Subida de tomas permite extensión arbitraria controlada por el cliente (RCE vía `.php` en `/storage`)

- **Severidad:** Crítica
- **Componente:** `jg_filmmaker`
- **Ubicación:** `app/Livewire/Studio/ScriptEditor.php:1488-1501`, `app/Models/Take.php:80-88`, `docker/nginx.conf:69`
- **Categoría:** Seguridad

## 1. Descripción del Problema
`uploadTakeVideo()` valida el contenido con `mimes:mp4,mov,webm,m4v,quicktime` (inspección de contenido), pero **la extensión con la que se guarda el fichero procede de `getClientOriginalExtension()`**, es decir, del nombre que envía el cliente. No existe ninguna comprobación de que la extensión coincida con el tipo detectado ni lista blanca de extensiones.

Un fichero poliglota (cabecera `ftyp` válida de MP4 + código PHP al final) llamado `x.php` supera la validación, se almacena como `media/projects/{p}/scenes/{s}/takes/{take}.php` en el disco `public` y queda expuesto bajo `/storage/...`.

El `nginx.conf` del contenedor define `location ~ \.php$ { fastcgi_pass 127.0.0.1:9000; ... }` **sin `try_files` ni exclusión de `/storage/`**, por lo que cualquier `.php` bajo `public/storage` se ejecuta con el usuario `www-data`.

Además, el registro es abierto (ver ISSUE-008): cualquier persona con un email puede obtener sesión y llegar a este endpoint.

## 2. Evidencia de Código
```php
// ScriptEditor.php
$this->validate([
    'videoUpload' => 'required|file|mimes:mp4,mov,webm,m4v,quicktime|max:102400',
]);
...
$extension = $this->videoUpload->getClientOriginalExtension() ?: 'mp4';   // <- controlado por el cliente
$storagePath = $take->generateStoragePath($extension);
$this->videoUpload->storeAs('', $storagePath, 'public');
```
```php
// Take.php
$cleanExt = ltrim(strtolower($extension), '.');
return "media/projects/{$projectId}/scenes/{$sceneId}/takes/{$this->id}.{$cleanExt}";
```
```nginx
location ~ \.php$ {
    fastcgi_pass 127.0.0.1:9000;
    fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
```

## 3. Impacto Operativo o de Seguridad
Ejecución remota de código en el contenedor web como `www-data`: lectura de `.env` (APP_KEY, claves LLM, secreto HMAC de `jg_agente`), acceso a la base SQLite, pivote a la red `jg_network` y a `jg_agente`. Compromiso total de la plataforma por un usuario no privilegiado.

## 4. Solución Recomendada
1. Derivar la extensión del MIME detectado, nunca del nombre del cliente:
```php
$allowed = ['video/mp4' => 'mp4', 'video/quicktime' => 'mov', 'video/webm' => 'webm', 'video/x-m4v' => 'm4v'];
$mime = $this->videoUpload->getMimeType();
abort_unless(isset($allowed[$mime]), 422, 'Formato de vídeo no permitido.');
$storagePath = $take->generateStoragePath($allowed[$mime]);
```
2. `generateStoragePath()` debe aplicar lista blanca (`in_array($cleanExt, ['mp4','mov','webm','m4v'], true)`), lanzando excepción en caso contrario.
3. Defensa en profundidad en nginx: denegar ejecución bajo `/storage/` y forzar descarga:
```nginx
location ^~ /storage/ { location ~ \.php$ { return 403; } add_header X-Content-Type-Options nosniff; }
```
4. Añadir `try_files $uri =404;` dentro del `location ~ \.php$`.
5. Servir los medios desde un disco **privado** con rutas firmadas (ver ISSUE-006).
6. Test de regresión: subir `evil.php` con cabecera MP4 → debe fallar con 422.
