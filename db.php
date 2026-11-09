<?php

declare(strict_types=1);

const DB_HOST = 'localhost';
const DB_PORT = '5432';
const DB_NAME = 'chennai_emergency';
const DB_USER = 'postgres';
const DB_PASSWORD = 'password';

function db(): PgSql\Connection
{
    static $connection = null;

    if ($connection instanceof PgSql\Connection) {
        return $connection;
    }

    $connection = pg_connect(
        'host=' . DB_HOST .
        ' port=' . DB_PORT .
        ' dbname=' . DB_NAME .
        ' user=' . DB_USER .
        ' password=' . DB_PASSWORD
    );

    if (!$connection) {
        http_response_code(500);
        exit('PostgreSQL connection failed. Check db.php.');
    }

    return $connection;
}

function json_output(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
