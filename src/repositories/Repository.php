<?php

declare(strict_types=1);

require_once __DIR__ . '/../../Database.php';

abstract class Repository
{
    protected Database $database;

    public function __construct()
    {
        $this->database = new Database();
    }

    protected function connection(): PDO
    {
        return $this->database->connect();
    }
}
