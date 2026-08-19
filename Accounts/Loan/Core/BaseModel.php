<?php

declare(strict_types=1);

/**
 * BaseModel
 *
 * Thin, safe wrapper around PDO. Every query in the application goes
 * through these four methods, so prepared statements are guaranteed and
 * no module can accidentally concatenate user input into SQL.
 */
abstract class BaseModel
{
    protected PDO $db;

    /** Rows shown per page across the whole application. */
    public const PER_PAGE = 20;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    /** @return array<int, array<string, mixed>> */
    protected function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string, mixed>|null */
    protected function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    protected function fetchValue(string $sql, array $params = []): mixed
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    protected function execute(string $sql, array $params = []): int
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    protected function lastInsertId(): int
    {
        return (int)$this->db->lastInsertId();
    }

    /**
     * Runs a callback inside a transaction, rolling back on any exception.
     * Nested calls reuse the outer transaction instead of failing.
     */
    protected function transaction(callable $callback): mixed
    {
        $ownsTransaction = !$this->db->inTransaction();

        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $result = $callback();
            if ($ownsTransaction) {
                $this->db->commit();
            }
            return $result;
        } catch (Throwable $e) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Builds LIMIT/OFFSET safely. Page and per-page are always integers
     * derived from arithmetic, never interpolated from raw input.
     *
     * @return array{page:int, pages:int, total:int, limit:int, offset:int}
     */
    protected function buildPagination(int $totalRows, int $requestedPage, int $perPage = self::PER_PAGE): array
    {
        $perPage = max(1, $perPage);
        $pages   = max(1, (int)ceil($totalRows / $perPage));
        $page    = max(1, min($requestedPage, $pages));

        return [
            'page'   => $page,
            'pages'  => $pages,
            'total'  => $totalRows,
            'limit'  => $perPage,
            'offset' => ($page - 1) * $perPage,
        ];
    }
}
