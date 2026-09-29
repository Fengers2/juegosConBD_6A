
# CRUD de usuarios — Juegos 6A

Aplicación web en PHP para listar, crear, editar y eliminar registros de la tabla `usuarios` en MySQL. La interfaz detecta automáticamente las columnas y la clave primaria de la tabla. Los campos cuyo nombre incluye `password`, `passwd`, `secret` o `token` se ocultan en el listado; al editar, se conservan si se dejan vacíos.

## Requisitos

- PHP 8 o posterior con las extensiones `pdo` y `pdo_mysql`.
- MySQL/MariaDB con la base de datos `juevos6A` y la tabla `usuarios` ya creadas.
- Un usuario de base de datos con permisos `SELECT`, `INSERT`, `UPDATE` y `DELETE` sobre esa base.

## Configurar y ejecutar

Desde la carpeta del proyecto, define las variables de conexión y levanta el servidor integrado de PHP:

```sh
export DB_HOST=127.0.0.1
export DB_PORT=3306
export DB_NAME=juevos6A
export DB_USER=crud_app
export DB_PASSWORD='tu_contraseña'
php -S 127.0.0.1:8000
```

Abre `http://127.0.0.1:8000` en el navegador. Si no se definen variables, se usan `127.0.0.1:3306`, `juevos6A`, `crud_app` y contraseña vacía. No se debe publicar este servidor de desarrollo directamente en Internet.

> La base de datos y la tabla deben existir antes de iniciar la aplicación. La conexión usa PDO y consultas preparadas para los valores enviados por los formularios.
