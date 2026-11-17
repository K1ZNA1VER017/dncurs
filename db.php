<?php
/**
 * Подключение к БД. Основной вариант — MySQL (OpenServer), резервный — SQLite,
 * чтобы аналитический модуль запускался и без MySQL (демонстрация, защита курсовой).
 */
require_once __DIR__ . '/analytics_schema.php';

// Open Server обычно слушает локальный адрес MySQL; параметры можно переопределить
// переменными окружения DB_HOST, DB_PORT, DB_NAME, DB_USER и DB_PASS.
$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$dbname = getenv('DB_NAME') ?: 'favorit_db';
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASS') ?: '';

$pdo = null;
try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_TIMEOUT => 3]
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Позволяет явно потребовать MySQL: иначе ошибка подключения незаметно уводила
    // приложение на SQLite, из-за чего phpMyAdmin показывал пустую базу.
    if (strtolower((string) getenv('DB_DRIVER')) === 'mysql') {
        http_response_code(500);
        die('Не удалось подключиться к MySQL. Проверьте службу Open Server, базу favorit_db и параметры DB_HOST/DB_PORT/DB_USER/DB_PASS. Подробности доступны в журнале PHP.');
    }
    // SQLite-резерв: ищем каталог, куда реально может писать PHP (Desktop/OneDrive часто закрыт).
    $candidates = [
        (string) getenv('FAVORIT_DATA_DIR'),
        __DIR__ . '/data',
        __DIR__ . '/../favorit-data',
        sys_get_temp_dir() . '/favorit',
        (string) getenv('LOCALAPPDATA') . '/favorit',
    ];
    $file = null;
    foreach ($candidates as $dir) {
        if ($dir === '') {
            continue;
        }
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        if (!is_dir($dir)) {
            continue;
        }
        $probe = $dir . '/.write-test';
        if (@file_put_contents($probe, 'ok') !== false) {
            @unlink($probe);
            $file = $dir . '/favorit.sqlite';
            break;
        }
    }
    if ($file === null) {
        die('Не удалось открыть SQLite-базу: нет каталога с правами на запись.');
    }
    $pdo = new PDO('sqlite:' . $file);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');
}

an_init_schema($pdo);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
