<?php
declare(strict_types=1);

/**
 * LoginLogModel — login_logs টেবিলের ডাটা লেয়ার (PDO, Prepared Statements)।
 * ────────────────────────────────────────────────────────────────────────
 *  • record()        — একটি লগইন ইভেন্ট সেভ করে
 *  • fetchLogs()      — ফিল্টারসহ তালিকা (status/username/তারিখ/সার্চ)
 *  • countLogs()      — ঐ ফিল্টারে মোট সংখ্যা (pagination-এর জন্য)
 *  • userLoginStats() — প্রতি ইউজারের সফল/ব্যর্থ লগইন কাউন্ট
 *  • dailyStats()     — প্রতিদিনের সফল/ব্যর্থ সারসংক্ষেপ
 *  • distinctUsernames() — ফিল্টার ড্রপডাউনের জন্য ইউজারনেম তালিকা
 */
final class LoginLogModel
{
    public function __construct(private \PDO $db) {}

    /**
     * একটি ইভেন্ট সেভ করে। ব্যর্থ হলে false, সফল হলে insert id।
     * @param array<string,mixed> $data
     */
    public function record(array $data): int|false
    {
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO login_logs
                    (user_id, username, event, status, method, ip_address,
                     operating_system, browser, browser_version, device_model,
                     device_type, user_agent, note, created_at)
                 VALUES
                    (:user_id, :username, :event, :status, :method, :ip_address,
                     :operating_system, :browser, :browser_version, :device_model,
                     :device_type, :user_agent, :note, NOW())'
            );
            $stmt->execute([
                ':user_id'          => $data['user_id']          ?? null,
                ':username'         => $data['username']         ?? null,
                ':event'            => (string) ($data['event']  ?? 'UNKNOWN'),
                ':status'           => (string) ($data['status'] ?? 'info'),
                ':method'           => $data['method']           ?? null,
                ':ip_address'       => $data['ip_address']       ?? null,
                ':operating_system' => $data['operating_system'] ?? null,
                ':browser'          => $data['browser']          ?? null,
                ':browser_version'  => $data['browser_version']  ?? null,
                ':device_model'     => $data['device_model']     ?? null,
                ':device_type'      => $data['device_type']      ?? null,
                ':user_agent'       => $data['user_agent']       ?? null,
                ':note'             => $data['note']             ?? null,
            ]);
            return (int) $this->db->lastInsertId();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * ফিল্টার থেকে WHERE ক্লজ ও প্যারামিটার তৈরি (কোড পুনর্ব্যবহারের জন্য)।
     * @param array<string,mixed> $filters
     * @return array{0:string,1:array<string,mixed>}
     */
    private function buildWhere(array $filters): array
    {
        $where  = [];
        $params = [];

        $status = (string) ($filters['status'] ?? '');
        if ($status !== '' && in_array($status, ['success', 'failed', 'info'], true)) {
            $where[]           = 'status = :status';
            $params[':status'] = $status;
        }

        $username = trim((string) ($filters['username'] ?? ''));
        if ($username !== '') {
            $where[]             = 'username = :username';
            $params[':username'] = $username;
        }

        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        if ($dateFrom !== '') {
            $where[]              = 'created_at >= :date_from';
            $params[':date_from'] = $dateFrom . ' 00:00:00';
        }

        $dateTo = trim((string) ($filters['date_to'] ?? ''));
        if ($dateTo !== '') {
            $where[]            = 'created_at <= :date_to';
            $params[':date_to'] = $dateTo . ' 23:59:59';
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $where[]            = '(username LIKE :search OR ip_address LIKE :search
                                    OR device_model LIKE :search OR browser LIKE :search
                                    OR operating_system LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }

        $sql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';
        return [$sql, $params];
    }

    /**
     * ফিল্টারসহ লগ তালিকা (নতুন আগে)।
     * @param array<string,mixed> $filters
     * @return array<int,array<string,mixed>>
     */
    public function fetchLogs(array $filters, int $limit = 50, int $offset = 0): array
    {
        [$whereSql, $params] = $this->buildWhere($filters);
        $limit  = max(1, min(500, $limit));
        $offset = max(0, $offset);

        $stmt = $this->db->prepare(
            'SELECT * FROM login_logs' . $whereSql .
            ' ORDER BY id DESC LIMIT ' . $limit . ' OFFSET ' . $offset
        );
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * ঐ ফিল্টারে মোট সারি সংখ্যা।
     * @param array<string,mixed> $filters
     */
    public function countLogs(array $filters): int
    {
        [$whereSql, $params] = $this->buildWhere($filters);
        $stmt = $this->db->prepare('SELECT COUNT(*) AS c FROM login_logs' . $whereSql);
        $stmt->execute($params);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return (int) ($row['c'] ?? 0);
    }

    /**
     * প্রতি ইউজারের লগইন পরিসংখ্যান — কতবার সফল/ব্যর্থ, শেষ কবে।
     * @return array<int,array<string,mixed>>
     */
    public function userLoginStats(int $limit = 200): array
    {
        $limit = max(1, min(1000, $limit));
        $stmt  = $this->db->query(
            "SELECT
                COALESCE(username, '(অজানা)') AS username,
                SUM(status = 'success') AS success_count,
                SUM(status = 'failed')  AS failed_count,
                COUNT(*)                AS total_count,
                MAX(created_at)         AS last_seen
             FROM login_logs
             GROUP BY username
             ORDER BY last_seen DESC
             LIMIT " . $limit
        );
        return $stmt ? ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: []) : [];
    }

    /**
     * প্রতিদিনের সারসংক্ষেপ (সাম্প্রতিক দিন আগে)।
     * @return array<int,array<string,mixed>>
     */
    public function dailyStats(int $days = 30): array
    {
        $days = max(1, min(365, $days));
        $stmt = $this->db->prepare(
            "SELECT
                DATE(created_at) AS day,
                SUM(status = 'success') AS success_count,
                SUM(status = 'failed')  AS failed_count,
                COUNT(*)                AS total_count
             FROM login_logs
             WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
             GROUP BY DATE(created_at)
             ORDER BY day DESC"
        );
        $stmt->bindValue(':days', $days, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * ফিল্টার ড্রপডাউনের জন্য স্বতন্ত্র ইউজারনেম তালিকা।
     * @return array<int,string>
     */
    public function distinctUsernames(int $limit = 300): array
    {
        $limit = max(1, min(1000, $limit));
        $stmt  = $this->db->query(
            "SELECT DISTINCT username FROM login_logs
             WHERE username IS NOT NULL AND username <> ''
             ORDER BY username ASC LIMIT " . $limit
        );
        if (!$stmt) { return []; }
        return array_map('strval', array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'username'));
    }

    /**
     * হেডার কার্ডের জন্য সামগ্রিক সংখ্যা।
     * @return array{success:int,failed:int,total:int,today:int}
     */
    public function summaryCounts(): array
    {
        $out = ['success' => 0, 'failed' => 0, 'total' => 0, 'today' => 0];
        try {
            $row = $this->db->query(
                "SELECT
                    SUM(status = 'success') AS success,
                    SUM(status = 'failed')  AS failed,
                    COUNT(*)                AS total,
                    SUM(DATE(created_at) = CURDATE()) AS today
                 FROM login_logs"
            )->fetch(\PDO::FETCH_ASSOC);
            if (is_array($row)) {
                $out['success'] = (int) ($row['success'] ?? 0);
                $out['failed']  = (int) ($row['failed']  ?? 0);
                $out['total']   = (int) ($row['total']   ?? 0);
                $out['today']   = (int) ($row['today']   ?? 0);
            }
        } catch (\Throwable $e) {
            // টেবিল না থাকলে শূন্য ফেরত।
        }
        return $out;
    }
}
