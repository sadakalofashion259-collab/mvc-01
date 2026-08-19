<?php
declare(strict_types=1);
if (!defined('SK_APP')) { http_response_code(403); exit('403 Forbidden'); }

/**
 * উচ্চ-স্তরের SMS পরিষেবা:
 *   - ডাটাবেজের sms_templates থেকে বার্তা রেন্ডার (এডমিন এডিটযোগ্য)
 *   - পাঠানো + sms_log-এ সংরক্ষণ
 *   - ড্যাশবোর্ডের জন্য টেমপ্লেট/গেটওয়ে CRUD ও পরিসংখ্যান
 */
final class SmsService
{
    public function __construct(
        private \PDO $conn,
        private SmsGateway $gateway,
        private SupplierModelInterface $model
    ) {}

    public function gateway(): SmsGateway
    {
        return $this->gateway;
    }

    /* ---------------- টেমপ্লেট রেন্ডার ---------------- */

    public function getTemplate(string $key): ?array
    {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM sms_templates WHERE template_key = ? LIMIT 1");
            $stmt->execute([$key]);
            return $stmt->fetch() ?: null;
        } catch (\PDOException $e) {
            Logger::error('getTemplate failed: ' . $key, $e);
            return null;
        }
    }

    /**
     * টেমপ্লেট + প্লেসহোল্ডার → চূড়ান্ত বার্তা।
     * টেমপ্লেট না থাকলে/নিষ্ক্রিয় হলে $fallback ব্যবহার হয়।
     * @param array<string,string|int|float> $vars
     */
    public function render(string $key, array $vars, string $fallback = ''): string
    {
        $tpl  = $this->getTemplate($key);
        $body = ($tpl && (int)$tpl['is_active'] === 1) ? (string)$tpl['body'] : $fallback;

        foreach ($vars as $k => $v) {
            $body = str_replace('{' . $k . '}', (string)$v, $body);
        }
        // যেসব প্লেসহোল্ডার পূরণ হয়নি সেগুলো ফাঁকা করে দিই
        $body = preg_replace('/\{[a-z_]+\}/', '', $body) ?? $body;
        return trim($body);
    }

    /** একটি সাপ্লায়ার-লেনদেন থেকে placeholder অ্যারে বানায় */
    private function transactionVars(array $supplier, array $tr, float $netDue): array
    {
        $bill = (float)($tr['bill_received'] ?? 0);
        $pay  = (float)($tr['payment_given'] ?? 0);
        return [
            'shop' => (string)($supplier['shop_name'] ?? ''),
            'date' => date('d-m-Y', strtotime((string)($tr['tr_date'] ?? 'now'))),
            'memo' => (!empty($tr['memo_no']) && $tr['memo_no'] !== '-') ? (string)$tr['memo_no'] : '',
            'bill' => $bill > 0 ? number_format($bill) : '0',
            'pay'  => $pay  > 0 ? number_format($pay)  : '0',
            'due'  => number_format($netDue),
            'user' => Security::username(),
        ];
    }

    /* ---------------- পাঠানো ---------------- */

    /**
     * লেনদেনের SMS পাঠায় এবং লগ করে।
     * @return array{ok:bool,msg:string}
     */
    public function sendTransactionSms(int $supplierId, array $supplier, array $tr, float $netDue, ?int $trId = null): array
    {
        $fallback = "{shop}\nতারিখ: {date}\nবিল: {bill} টাকা\nজমা: {pay} টাকা\nমোট বাকি: {due} টাকা\nধন্যবাদ - Sada Kalo Fashion";
        $message  = $this->render('transaction', $this->transactionVars($supplier, $tr, $netDue), $fallback);

        $res = $this->gateway->send((string)($supplier['phone'] ?? ''), $message);

        $this->model->logSms([
            'supplier_id' => $supplierId,
            'tr_id'       => $trId,
            'phone'       => (string)($supplier['phone'] ?? ''),
            'message'     => $message,
            'status'      => $res['ok'] ? 'sent' : 'failed',
            'trxn_id'     => (string)($res['trxnId'] ?? ''),
            'sent_by'     => Security::username(),
        ]);
        Logger::sms('TXN | to=' . ($supplier['phone'] ?? '') . ' | ok=' . ($res['ok'] ? 'YES' : 'NO')
            . ' | ' . ($res['msg'] ?? '') . (!empty($res['raw']) ? ' | raw=' . $res['raw'] : ''));

        return ['ok' => (bool)$res['ok'], 'msg' => (string)$res['msg']];
    }

    /** টেস্ট SMS */
    public function sendTest(string $phone, string $text): array
    {
        if ($text === '') {
            $text = $this->render('test', [], 'Sada Kalo Fashion — SMS পরীক্ষা বার্তা।');
        }
        $res = $this->gateway->send($phone, $text);

        $this->model->logSms([
            'supplier_id' => null,
            'tr_id'       => null,
            'phone'       => $phone,
            'message'     => $text,
            'status'      => $res['ok'] ? 'sent' : 'failed',
            'trxn_id'     => (string)($res['trxnId'] ?? ''),
            'sent_by'     => Security::username(),
        ]);
        Logger::sms('TEST | to=' . $phone . ' | ok=' . ($res['ok'] ? 'YES' : 'NO') . ' | ' . ($res['msg'] ?? ''));

        return ['ok' => (bool)$res['ok'], 'msg' => (string)$res['msg']];
    }

    /* ---------------- ড্যাশবোর্ড: CRUD ---------------- */

    /** @return array<int,array<string,mixed>> */
    public function allTemplates(): array
    {
        try {
            return $this->conn->query("SELECT * FROM sms_templates ORDER BY id ASC")->fetchAll() ?: [];
        } catch (\PDOException $e) {
            Logger::error('allTemplates failed', $e);
            return [];
        }
    }

    public function saveTemplate(int $id, string $title, string $body, bool $active): bool
    {
        try {
            $stmt = $this->conn->prepare(
                "UPDATE sms_templates SET title = ?, body = ?, is_active = ?, updated_by = ? WHERE id = ?"
            );
            return $stmt->execute([$title, $body, $active ? 1 : 0, Security::username(), $id]);
        } catch (\PDOException $e) {
            Logger::error('saveTemplate failed: ' . $id, $e);
            return false;
        }
    }

    public function saveGateway(string $sender, string $type, bool $enabled): bool
    {
        $type = ($type === 'P') ? 'P' : 'T';
        try {
            $stmt = $this->conn->prepare(
                "UPDATE sms_gateway SET sender_id = ?, trans_type = ?, is_enabled = ?, updated_by = ? WHERE id = 1"
            );
            return $stmt->execute([$sender, $type, $enabled ? 1 : 0, Security::username()]);
        } catch (\PDOException $e) {
            Logger::error('saveGateway failed', $e);
            return false;
        }
    }
}
