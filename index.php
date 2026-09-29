<?php
declare(strict_types=1);

session_start();

$table = 'usuarios';
$error = '';
$notice = '';
$columns = [];
$primaryKey = null;
$editing = null;
$users = [];

function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function quoteIdentifier(string $identifier): string
{
    return '`' . str_replace('`', '``', $identifier) . '`';
}

function isSensitive(string $name): bool
{
    return (bool) preg_match('/password|passwd|secret|token/i', $name);
}

function inputType(array $column): string
{
    $type = strtolower((string) $column['Type']);
    $name = strtolower((string) $column['Field']);

    if (isSensitive($name)) {
        return 'password';
    }
    if (str_contains($type, 'date') && str_contains($type, 'time')) {
        return 'datetime-local';
    }
    if (str_starts_with($type, 'date')) {
        return 'date';
    }
    if (str_starts_with($type, 'time')) {
        return 'time';
    }
    if (preg_match('/^(tinyint|smallint|mediumint|int|bigint|decimal|numeric|float|double)/', $type)) {
        return 'number';
    }
    if (str_contains($name, 'email') || str_contains($name, 'correo')) {
        return 'email';
    }
    return 'text';
}

try {
    require __DIR__ . '/config.php';
    $columns = $pdo->query('SHOW COLUMNS FROM ' . quoteIdentifier($table))->fetchAll();
    if (!$columns) {
        throw new RuntimeException('La tabla usuarios no tiene columnas.');
    }

    foreach ($columns as $column) {
        if ($column['Key'] === 'PRI') {
            $primaryKey = $column['Field'];
            break;
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf_token'], $_SESSION['csrf_token']) ||
            !hash_equals($_SESSION['csrf_token'], (string) $_POST['csrf_token'])) {
            throw new RuntimeException('La sesión del formulario expiró. Recarga la página e inténtalo de nuevo.');
        }

        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'delete') {
            if ($primaryKey === null) {
                throw new RuntimeException('No se puede eliminar: la tabla no tiene clave primaria.');
            }
            $statement = $pdo->prepare(
                'DELETE FROM ' . quoteIdentifier($table) . ' WHERE ' . quoteIdentifier($primaryKey) . ' = :id'
            );
            $statement->execute(['id' => $_POST['id'] ?? '']);
            header('Location: index.php?mensaje=' . rawurlencode('Usuario eliminado correctamente.'));
            exit;
        }

        if ($action === 'create' || $action === 'update') {
            if ($action === 'update' && $primaryKey === null) {
                throw new RuntimeException('No se puede editar: la tabla no tiene clave primaria.');
            }

            $values = [];
            foreach ($columns as $column) {
                $field = (string) $column['Field'];
                $isGenerated = str_contains(strtolower((string) $column['Extra']), 'auto_increment');
                if ($isGenerated || ($action === 'update' && $field === $primaryKey)) {
                    continue;
                }

                if ($action === 'update' && isSensitive($field) && trim((string) ($_POST[$field] ?? '')) === '') {
                    continue;
                }
                if (array_key_exists($field, $_POST)) {
                    $value = $_POST[$field] === '' ? null : $_POST[$field];
                    if (inputType($column) === 'datetime-local' && is_string($value)) {
                        $value = str_replace('T', ' ', $value);
                    }
                    $values[$field] = $value;
                }
            }

            if (!$values) {
                throw new RuntimeException('No se recibieron campos para guardar.');
            }

            if ($action === 'create') {
                $names = array_keys($values);
                $sql = 'INSERT INTO ' . quoteIdentifier($table) . ' (' .
                    implode(', ', array_map('quoteIdentifier', $names)) . ') VALUES (' .
                    implode(', ', array_fill(0, count($names), '?')) . ')';
                $pdo->prepare($sql)->execute(array_values($values));
                $message = 'Usuario creado correctamente.';
            } else {
                $assignments = [];
                $parameters = [];
                foreach ($values as $name => $value) {
                    $parameter = 'value_' . count($parameters);
                    $assignments[] = quoteIdentifier($name) . ' = :' . $parameter;
                    $parameters[$parameter] = $value;
                }
                $parameters['id'] = $_POST['id'] ?? '';
                $sql = 'UPDATE ' . quoteIdentifier($table) . ' SET ' . implode(', ', $assignments) .
                    ' WHERE ' . quoteIdentifier($primaryKey) . ' = :id';
                $pdo->prepare($sql)->execute($parameters);
                $message = 'Usuario actualizado correctamente.';
            }

            header('Location: index.php?mensaje=' . rawurlencode($message));
            exit;
        }
    }

    $notice = (string) ($_GET['mensaje'] ?? '');
    $editing = null;
    if (isset($_GET['editar']) && $primaryKey !== null) {
        $statement = $pdo->prepare(
            'SELECT * FROM ' . quoteIdentifier($table) . ' WHERE ' . quoteIdentifier($primaryKey) . ' = :id'
        );
        $statement->execute(['id' => $_GET['editar']]);
        $editing = $statement->fetch() ?: null;
    }

    $users = $pdo->query('SELECT * FROM ' . quoteIdentifier($table) .
        ($primaryKey !== null ? ' ORDER BY ' . quoteIdentifier($primaryKey) . ' DESC' : ''))->fetchAll();
} catch (Throwable $exception) {
    http_response_code(500);
    $error = $exception instanceof PDOException
        ? 'No fue posible conectar con la base de datos o consultar la tabla usuarios. Verifica la configuración y los permisos.'
        : $exception->getMessage();
    $users = [];
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Usuarios | Juegos 6A</title>
    <style>
        :root { color-scheme: light; --ink: #17233b; --muted: #65718a; --line: #e4e9f2; --blue: #315efb; --bg: #f4f7fc; }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--bg); color: var(--ink); font: 15px/1.5 Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        header { background: linear-gradient(120deg, #182c58, #315efb); color: white; padding: 34px max(24px, calc((100% - 1180px)/2)); }
        header p { margin: 5px 0 0; color: #d5defd; }
        h1 { margin: 0; font-size: clamp(25px, 4vw, 34px); letter-spacing: -.04em; }
        main { width: min(1180px, calc(100% - 32px)); margin: 28px auto 60px; }
        .layout { display: grid; grid-template-columns: minmax(260px, 330px) minmax(0, 1fr); gap: 20px; align-items: start; }
        .card { background: white; border: 1px solid var(--line); border-radius: 16px; box-shadow: 0 8px 30px #1d31510b; overflow: hidden; }
        .card-head { padding: 20px 22px 14px; }
        .card h2 { margin: 0; font-size: 18px; }
        .card-head p { margin: 4px 0 0; color: var(--muted); font-size: 13px; }
        form.editor { padding: 0 22px 22px; }
        label { display: block; margin-top: 14px; color: #384660; font-size: 13px; font-weight: 650; }
        input { width: 100%; margin-top: 6px; border: 1px solid #d8dfeb; border-radius: 9px; padding: 10px 11px; font: inherit; color: var(--ink); outline: none; }
        input:focus { border-color: var(--blue); box-shadow: 0 0 0 3px #315efb1a; }
        button, .button { display: inline-flex; align-items: center; justify-content: center; border: 0; border-radius: 9px; padding: 10px 14px; background: var(--blue); color: white; font: inherit; font-weight: 650; text-decoration: none; cursor: pointer; }
        .editor > button { width: 100%; margin-top: 20px; }
        .button.secondary { background: #edf1fa; color: #33415e; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; white-space: nowrap; }
        th, td { padding: 13px 16px; border-top: 1px solid var(--line); text-align: left; }
        th { color: var(--muted); background: #fafbfe; font-size: 11px; text-transform: uppercase; letter-spacing: .07em; }
        td { max-width: 240px; overflow: hidden; text-overflow: ellipsis; }
        .actions { display: flex; gap: 7px; }
        .actions a, .actions button { padding: 7px 10px; font-size: 12px; }
        .actions .delete { background: #fff0f0; color: #c13535; }
        .actions form { margin: 0; }
        .empty { padding: 28px 16px; text-align: center; color: var(--muted); }
        .alert { margin-bottom: 18px; padding: 12px 15px; border-radius: 10px; }
        .alert.error { color: #922e2e; background: #fff0f0; border: 1px solid #f3cccc; }
        .alert.success { color: #176444; background: #eaf8f1; border: 1px solid #ccebdc; }
        .warning { margin: 0 22px 18px; padding: 10px; background: #fff8e8; color: #795c18; border-radius: 8px; font-size: 13px; }
        .count { margin-left: auto; color: var(--muted); font-size: 13px; }
        .list-heading { display: flex; align-items: center; gap: 12px; padding-right: 20px; }
        @media (max-width: 820px) { .layout { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<header>
    <h1>Administración de usuarios</h1>
    <p>Consulta y administra los registros de la tabla usuarios.</p>
</header>
<main>
    <?php if ($error !== ''): ?><div class="alert error"><?= e($error) ?> Revisa las variables de conexión descritas en README.md.</div><?php endif; ?>
    <?php if ($notice !== ''): ?><div class="alert success"><?= e($notice) ?></div><?php endif; ?>
    <div class="layout">
        <section class="card">
            <div class="card-head">
                <h2><?= $editing ? 'Editar usuario' : 'Agregar usuario' ?></h2>
                <p><?= $editing ? 'Deja la contraseña vacía para conservarla.' : 'Completa los campos para crear un registro.' ?></p>
            </div>
            <?php if ($columns): ?>
                <?php if ($primaryKey === null): ?><p class="warning">La tabla no tiene clave primaria: solo se puede consultar y agregar.</p><?php endif; ?>
                <form class="editor" method="post">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
                    <?php if ($editing && $primaryKey !== null): ?><input type="hidden" name="id" value="<?= e($editing[$primaryKey]) ?>"><?php endif; ?>
                    <?php foreach ($columns as $column):
                        $field = (string) $column['Field'];
                        if (str_contains(strtolower((string) $column['Extra']), 'auto_increment') || ($editing && $field === $primaryKey)) continue;
                        $value = $editing ? ($editing[$field] ?? '') : '';
                        if (inputType($column) === 'datetime-local' && $value !== '') $value = str_replace(' ', 'T', substr((string) $value, 0, 16));
                        $required = !$editing && $column['Null'] === 'NO' && $column['Default'] === null;
                    ?>
                        <label for="field-<?= e($field) ?>"><?= e(ucfirst(str_replace('_', ' ', $field))) ?>
                            <input id="field-<?= e($field) ?>" name="<?= e($field) ?>" type="<?= e(inputType($column)) ?>" value="<?= isSensitive($field) ? '' : e($value) ?>" <?= $required ? 'required' : '' ?> <?= inputType($column) === 'number' ? 'step="any"' : '' ?> autocomplete="<?= isSensitive($field) ? 'new-password' : 'off' ?>">
                        </label>
                    <?php endforeach; ?>
                    <button type="submit"><?= $editing ? 'Guardar cambios' : 'Crear usuario' ?></button>
                    <?php if ($editing): ?><a class="button secondary" style="width:100%;margin-top:9px" href="index.php">Cancelar edición</a><?php endif; ?>
                </form>
            <?php endif; ?>
        </section>

        <section class="card">
            <div class="card-head list-heading">
                <div><h2>Usuarios registrados</h2><p>Datos almacenados en la base de datos juevos6A.</p></div>
                <span class="count"><?= count($users ?? []) ?> registros</span>
            </div>
            <?php if (!$users): ?>
                <div class="empty">No hay usuarios para mostrar.</div>
            <?php else: ?>
                <div class="table-wrap"><table>
                    <thead><tr><?php foreach ($columns as $column): ?><th><?= e(ucfirst(str_replace('_', ' ', (string) $column['Field']))) ?></th><?php endforeach; ?><?php if ($primaryKey !== null): ?><th>Acciones</th><?php endif; ?></tr></thead>
                    <tbody><?php foreach ($users as $user): ?><tr>
                        <?php foreach ($columns as $column): $field = (string) $column['Field']; ?>
                            <td><?= isSensitive($field) && !empty($user[$field]) ? '••••••••' : e($user[$field] ?? '') ?></td>
                        <?php endforeach; ?>
                        <?php if ($primaryKey !== null): ?><td><div class="actions">
                            <a class="button secondary" href="?editar=<?= rawurlencode((string) $user[$primaryKey]) ?>">Editar</a>
                            <form method="post" onsubmit="return confirm('¿Eliminar este usuario? Esta acción no se puede deshacer.');">
                                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= e($user[$primaryKey]) ?>">
                                <button class="delete" type="submit">Eliminar</button>
                            </form>
                        </div></td><?php endif; ?>
                    </tr><?php endforeach; ?></tbody>
                </table></div>
            <?php endif; ?>
        </section>
    </div>
</main>
</body>
</html>