<?php

declare(strict_types=1);

namespace OpenRest\Core;

use PDO;
use RuntimeException;

/**
 * Gerenciador de conexão com o banco de dados.
 *
 * Implementa o padrão Singleton para garantir uma única conexão PDO
 * por ciclo de requisição. O driver é definido pela variável DB_DRIVER
 * no arquivo .env — sem alterar nenhuma linha de código.
 *
 * Drivers suportados: sqlite | pgsql | mysql
 *
 * Uso nos controllers:
 *   $db   = Database::connection();
 *   $rows = $db->query('SELECT * FROM contratos WHERE status = ?', ['ativo']);
 *   $row  = $db->queryOne('SELECT * FROM contratos WHERE id = ?', [$id]);
 *   $db->execute('UPDATE contratos SET status = ? WHERE id = ?', ['inativo', $id]);
 *
 * @package OpenRest\Core
 */
class Database
{
    /** @var Database|null Instância única da classe (Singleton). */
    private static ?Database $instance = null;

    /** @var PDO Conexão PDO ativa. */
    private PDO $pdo;

    /**
     * Construtor privado — use Database::connection().
     *
     * @throws RuntimeException Se o driver não for suportado ou a conexão falhar.
     */
    private function __construct()
    {
        $config = require __DIR__ . '/../config/database.php';
        $driver = $_ENV['DB_DRIVER'] ?? 'sqlite';

        if (!isset($config[$driver])) {
            throw new RuntimeException("Driver de banco '{$driver}' não configurado em config/database.php.");
        }

        $this->pdo = $this->createPdo($driver, $config[$driver]);
    }

    /**
     * Retorna a instância única do Database (Singleton).
     *
     * @return Database
     */
    public static function connection(): Database
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Executa uma query SELECT e retorna todas as linhas encontradas.
     *
     * @param string $sql    Instrução SQL com placeholders (ex: "SELECT * FROM tb WHERE status = ?").
     * @param array  $params Parâmetros para substituir os placeholders.
     *
     * @return array Lista de registros como arrays associativos.
     */
    public function query(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Executa uma query SELECT e retorna apenas a primeira linha.
     *
     * @param string $sql    Instrução SQL com placeholders.
     * @param array  $params Parâmetros para substituir os placeholders.
     *
     * @return array|null Registro encontrado ou null se não existir.
     */
    public function queryOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    /**
     * Executa uma instrução SQL que não retorna linhas (INSERT, UPDATE, DELETE).
     *
     * @param string $sql    Instrução SQL com placeholders.
     * @param array  $params Parâmetros para substituir os placeholders.
     *
     * @return int Número de linhas afetadas.
     */
    public function execute(string $sql, array $params = []): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    /**
     * Retorna o ID gerado pelo último INSERT executado.
     *
     * @return string ID do último registro inserido.
     */
    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }

    /**
     * Inicia uma transação no banco de dados.
     *
     * @return void
     */
    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    /**
     * Confirma e encerra a transação atual.
     *
     * @return void
     */
    public function commit(): void
    {
        $this->pdo->commit();
    }

    /**
     * Desfaz todas as operações da transação atual.
     *
     * @return void
     */
    public function rollback(): void
    {
        $this->pdo->rollBack();
    }

    /**
     * Cria a instância PDO de acordo com o driver selecionado.
     *
     * @param string $driver Nome do driver (sqlite, pgsql, mysql).
     * @param array  $config Configurações do driver lidas de config/database.php.
     *
     * @return PDO
     *
     * @throws RuntimeException Se o driver não for suportado.
     */
    private function createPdo(string $driver, array $config): PDO
    {
        $opcoes = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        return match ($driver) {
            'sqlite' => new PDO(
                "sqlite:{$config['path']}",
                options: $opcoes
            ),
            'pgsql' => new PDO(
                "pgsql:host={$config['host']};port={$config['port']};dbname={$config['database']}",
                $config['username'],
                $config['password'],
                $opcoes
            ),
            'mysql' => new PDO(
                "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset={$config['charset']}",
                $config['username'],
                $config['password'],
                $opcoes
            ),
            default => throw new RuntimeException(
                "Driver '{$driver}' não suportado. Valores aceitos: sqlite, pgsql, mysql."
            ),
        };
    }
}
