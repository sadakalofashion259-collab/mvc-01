<?php
declare(strict_types=1);

/**
 * public_html/Audit/AuditLogModel.php
 *
 * audit_logs টেবিলে লেখা ও পড়ার একমাত্র জায়গা।
 * $conn রুটের db_connect.php থেকে আসে — নতুন connection তৈরি হয় না।
 */
class AuditLogModel
{
    public function __construct(private readonly PDO $pdo) {}

    // ──────────────────────────────────────────────────────────────────────
    //  WRITE
    // ──────────────────────────────────────────────────────────────────────

    /**
     * একটি অডিট এন্ট্রি সংরক্ষণ।
     *
     * @param array{
     *   action:       'CREATE'|'UPDATE'|'DELETE'|'EXPORT',
     *   module:       string,
     *   record_id?:   string|int|null,
     *   description?: string|null,
     *   old_data?:    array|null,
     *   new_data?:    array|null,
     *   user_id?:     int|null,
     *   username?:    string|null,
     * } $data
     */
    public function log(array $data): bool
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO audit_logs
                (user_id, username, action, module, record_id,
                 description, old_data, new_data, ip_address, user_agent)
            VALUES
                (:user_id, :username, :action, :module, :record_id,
                 :description, :old_data, :new_data, :ip_address, :user_agent)
        ');

        return $stmt->execute([
            ':user_id'     => $data['user_id']  ?? $_SESSION['user_id']  ?? null,
            ':username'    => $data['username'] ?? $_SESSION['username'] ?? null,
            ':action'      => $data['action'],
            ':module'      => $data['module'],
            ':record_id'   => isset($data['record_id'])  ? (string) $data['record_id']  : null,
            ':description' => $data['description'] ?? null,
            ':old_data'    => isset($data['old_data'])
                                ? json_encode($data['old_data'],  JSON_UNESCAPED_UNICODE) : null,
            ':new_data'    => isset($data['new_data'])
                                ? json_encode($data['new_data'],  JSON_UNESCAPED_UNICODE) : null,
            ':ip_address'  => $this->resolveIp(),
            ':user_agent'  => isset($_SERVER['HTTP_USER_AGENT'])
                                ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : null,
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────
    //  READ  (Admin Panel)
    // ──────────────────────────────────────────────────────────────────────

    /**
     * ফিল্টার সহ পেজিনেটেড লগ লিস্ট।
     *
     * @param array{
     *   action?:    string,
     *   module?:    string,
     *   date_from?: string,
     *   date_to?:   string,
     *   search?:    string,
     *   page?:      int,
     *   per_page?:  int,
     * } $f
     * @return array{ data: list<array>, total: int, pages: int }
     */
    public function getAll(array $f = []): array
    {
        $page    = max(1, (int) ($f['page']     ?? 1));
        $perPage = max(1, (int) ($f['per_page'] ?? 25));
        $offset  = ($page - 1) * $perPage;

        [$where, $params] = $this->buildWhere($f);

        // মোট সংখ্যা
        $cStmt = $this->pdo->prepare("SELECT COUNT(*) FROM audit_logs $where");
        $cStmt->execute($params);
        $total = (int) $cStmt->fetchColumn();

        // paginated rows
        $sql = "
            SELECT id, user_id, username, action, module, record_id,
                   description, ip_address, created_at
            FROM   audit_logs
            $where
            ORDER  BY created_at DESC
            LIMIT  :lim OFFSET :off
        ";
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset,  PDO::PARAM_INT);
        $stmt->execute();

        return [
            'data'  => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total,
            'pages' => $total > 0 ? (int) ceil($total / $perPage) : 1,
        ];
    }

    /** বিস্তারিত (AJAX modal) — old_data, new_data সহ */
    public function getById(int $id): array|false
    {
        $stmt = $this->pdo->prepare('SELECT * FROM audit_logs WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /** Module ড্রপডাউনের জন্য distinct নাম */
    public function getModules(): array
    {
        return $this->pdo
            ->query('SELECT DISTINCT module FROM audit_logs ORDER BY module')
            ->fetchAll(PDO::FETCH_COLUMN);
    }

    /** Action-wise মোট count (stats cards) */
    public function getActionCounts(): array
    {
        $rows = $this->pdo
            ->query("SELECT action, COUNT(*) AS cnt FROM audit_logs GROUP BY action")
            ->fetchAll(PDO::FETCH_KEY_PAIR);

        return [
            'CREATE' => (int) ($rows['CREATE'] ?? 0),
            'UPDATE' => (int) ($rows['UPDATE'] ?? 0),
            'DELETE' => (int) ($rows['DELETE'] ?? 0),
            'EXPORT' => (int) ($rows['EXPORT'] ?? 0),
        ];
    }

    // ──────────────────────────────────────────────────────────────────────
    //  Private helpers
    // ──────────────────────────────────────────────────────────────────────

    /** @return array{string, array<string,mixed>} */
    private function buildWhere(array $f): array
    {
        $conds  = [];
        $params = [];

        $allowed = ['CREATE', 'UPDATE', 'DELETE', 'EXPORT'];
        if (!empty($f['action']) && in_array(strtoupper($f['action']), $allowed, true)) {
            $conds[]           = 'action = :action';
            $params[':action'] = strtoupper($f['action']);
        }

        if (!empty($f['module'])) {
            $conds[]            = 'module = :module';
            $params[':module']  = $f['module'];
        }

        if (!empty($f['date_from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['date_from'])) {
            $conds[]              = 'DATE(created_at) >= :date_from';
            $params[':date_from'] = $f['date_from'];
        }

        if (!empty($f['date_to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['date_to'])) {
            $conds[]            = 'DATE(created_at) <= :date_to';
            $params[':date_to'] = $f['date_to'];
        }

        // সংশোধিত Search ব্লক
        if (!empty($f['search'])) {
            $conds[]       = '(username LIKE :s1 OR description LIKE :s2 OR module LIKE :s3)';
            $searchTerm    = '%' . $f['search'] . '%';
            
            $params[':s1'] = $searchTerm;
            $params[':s2'] = $searchTerm;
            $params[':s3'] = $searchTerm;
        }

        return [
            $conds ? 'WHERE ' . implode(' AND ', $conds) : '',
            $params,
        ];
    }

    private function resolveIp(): string
    {
        foreach (['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $k) {
            if (!empty($_SERVER[$k])) {
                $ip = trim(explode(',', $_SERVER[$k])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '0.0.0.0';
    }
}