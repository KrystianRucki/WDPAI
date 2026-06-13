<?php

declare(strict_types=1);

$configPath = __DIR__ . '/config.php';
if (file_exists($configPath)) {
    require_once $configPath;
}

final class Database
{
    private static ?PDO $connection = null;

    public function connect(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $username = getenv('DB_USER') ?: (defined('USERNAME') ? USERNAME : 'docker');
        $password = getenv('DB_PASSWORD') ?: (defined('PASSWORD') ? PASSWORD : 'docker');
        $database = getenv('DB_NAME') ?: (defined('DATABASE') ? DATABASE : 'db');

        $configuredHost = getenv('DB_HOST') ?: (defined('HOST') ? HOST : 'db');
        $configuredPort = getenv('DB_PORT') ?: (defined('PORT') ? PORT : '5432');

        $candidates = $this->connectionCandidates($configuredHost, (string) $configuredPort);
        $lastError = null;

        foreach ($candidates as $candidate) {
            for ($attempt = 1; $attempt <= 5; $attempt++) {
                try {
                    self::$connection = new PDO(
                        sprintf(
                            'pgsql:host=%s;port=%s;dbname=%s;connect_timeout=3',
                            $candidate['host'],
                            $candidate['port'],
                            $database
                        ),
                        $username,
                        $password,
                        [
                            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                            PDO::ATTR_EMULATE_PREPARES => false,
                        ]
                    );

                    return self::$connection;
                } catch (PDOException $exception) {
                    $lastError = sprintf(
                        '%s:%s -> %s',
                        $candidate['host'],
                        $candidate['port'],
                        $exception->getMessage()
                    );

                    usleep(300000);
                }
            }
        }

        error_log('Reevio database connection failed: ' . ($lastError ?? 'unknown error'));

        throw new RuntimeException(
            'Database connection failed. Check Docker services or config.php. Last tried connection: '
            . (string) ($lastError ?? 'unknown error')
        );
    }

    private function connectionCandidates(string $host, string $port): array
    {
        $rawCandidates = [
            ['host' => $host, 'port' => $port],
            ['host' => 'db', 'port' => '5432'],
            ['host' => 'localhost', 'port' => '5433'],
            ['host' => '127.0.0.1', 'port' => '5433'],
        ];

        $unique = [];
        $candidates = [];

        foreach ($rawCandidates as $candidate) {
            $key = $candidate['host'] . ':' . $candidate['port'];

            if (isset($unique[$key])) {
                continue;
            }

            $unique[$key] = true;
            $candidates[] = $candidate;
        }

        return $candidates;
    }
}

