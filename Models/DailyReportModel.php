<?php
declare(strict_types=1);

interface DailyReportModelInterface {
    public function getReports(string $fromDate, string $toDate, string $search): array;
    public function getStaff7DaysSummary(): array;
    public function deleteEntry(string $table, int $id): void;
}

class DailyReportModel implements DailyReportModelInterface {
    private PDO $conn;

    public function __construct(PDO $conn) {
        $this->conn = $conn;
    }

    public function getReports(string $fromDate, string $toDate, string $search): array {
        $data = [
            'sales' => [], 'colls' => [], 'exps' => [], 
            'sup_pay' => [], 'cust_trans' => []
        ];

        try {
            if ($search !== '') {
                $lk = "%$search%";
                $stmt = $this->conn->prepare("SELECT s.*, dr.report_date, dr.prepared_by FROM sales_entries s JOIN daily_reports dr ON s.report_id = dr.id WHERE (s.customer_name LIKE ? OR s.memo_no LIKE ? OR dr.prepared_by LIKE ?) ORDER BY s.id DESC");
                $stmt->execute([$lk, $lk, $lk]); $data['sales'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $stmt = $this->conn->prepare("SELECT c.*, dr.report_date, dr.prepared_by FROM collection_entries c JOIN daily_reports dr ON c.report_id = dr.id WHERE (c.payer_name LIKE ? OR dr.prepared_by LIKE ?) ORDER BY c.id DESC");
                $stmt->execute([$lk, $lk]); $data['colls'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $stmt = $this->conn->prepare("SELECT e.*, dr.report_date, dr.prepared_by FROM expense_entries e JOIN daily_reports dr ON e.report_id = dr.id WHERE (e.description LIKE ? OR dr.prepared_by LIKE ?) ORDER BY e.id DESC");
                $stmt->execute([$lk, $lk]); $data['exps'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $stmt = $this->conn->prepare("SELECT st.*, sup.name as sup_name, sup.shop_name FROM supplier_transactions st JOIN suppliers sup ON st.supplier_id = sup.id WHERE (sup.name LIKE ? OR sup.shop_name LIKE ? OR st.memo_no LIKE ?) ORDER BY st.id DESC");
                $stmt->execute([$lk, $lk, $lk]); $data['sup_pay'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $stmt = $this->conn->prepare("SELECT ct.*, cust.customer_name, cust.shop_name FROM customer_transactions ct JOIN customers cust ON ct.customer_id = cust.id WHERE (cust.customer_name LIKE ? OR cust.shop_name LIKE ? OR ct.description LIKE ?) ORDER BY ct.id DESC");
                $stmt->execute([$lk, $lk, $lk]); $data['cust_trans'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            } else {
                $stmt = $this->conn->prepare("SELECT s.*, dr.report_date, dr.prepared_by FROM sales_entries s JOIN daily_reports dr ON s.report_id = dr.id WHERE dr.report_date BETWEEN ? AND ? ORDER BY s.id DESC");
                $stmt->execute([$fromDate, $toDate]); $data['sales'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $stmt = $this->conn->prepare("SELECT c.*, dr.report_date, dr.prepared_by FROM collection_entries c JOIN daily_reports dr ON c.report_id = dr.id WHERE dr.report_date BETWEEN ? AND ? ORDER BY c.id DESC");
                $stmt->execute([$fromDate, $toDate]); $data['colls'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $stmt = $this->conn->prepare("SELECT e.*, dr.report_date, dr.prepared_by FROM expense_entries e JOIN daily_reports dr ON e.report_id = dr.id WHERE dr.report_date BETWEEN ? AND ? ORDER BY e.id DESC");
                $stmt->execute([$fromDate, $toDate]); $data['exps'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $stmt = $this->conn->prepare("SELECT st.*, sup.name as sup_name, sup.shop_name FROM supplier_transactions st JOIN suppliers sup ON st.supplier_id = sup.id WHERE st.tr_date BETWEEN ? AND ? ORDER BY st.id DESC");
                $stmt->execute([$fromDate, $toDate]); $data['sup_pay'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $stmt = $this->conn->prepare("SELECT ct.*, cust.customer_name, cust.shop_name FROM customer_transactions ct JOIN customers cust ON ct.customer_id = cust.id WHERE ct.tr_date BETWEEN ? AND ? ORDER BY ct.id DESC");
                $stmt->execute([$fromDate, $toDate]); $data['cust_trans'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (Throwable $e) { throw $e; }

        return $data;
    }

    public function getStaff7DaysSummary(): array {
        $endDate = date('Y-m-d');
        $startDate = date('Y-m-d', strtotime('-7 days'));
        
        $summary = [
            'cash_sales' => 0.0,
            'expenses' => 0.0,
            'supplier_payments' => 0.0,
            'customer_collections' => 0.0
        ];

        try {
            $stmt = $this->conn->prepare("SELECT COALESCE(SUM(s.paid_amount), 0) FROM sales_entries s JOIN daily_reports dr ON s.report_id = dr.id WHERE dr.report_date BETWEEN ? AND ?");
            $stmt->execute([$startDate, $endDate]);
            $summary['cash_sales'] = (float)$stmt->fetchColumn();

            $stmt = $this->conn->prepare("SELECT COALESCE(SUM(e.amount), 0) FROM expense_entries e JOIN daily_reports dr ON e.report_id = dr.id WHERE dr.report_date BETWEEN ? AND ?");
            $stmt->execute([$startDate, $endDate]);
            $summary['expenses'] = (float)$stmt->fetchColumn();

            $stmt = $this->conn->prepare("SELECT COALESCE(SUM(payment_given), 0) FROM supplier_transactions WHERE tr_date BETWEEN ? AND ?");
            $stmt->execute([$startDate, $endDate]);
            $summary['supplier_payments'] = (float)$stmt->fetchColumn();

            $stmt1 = $this->conn->prepare("SELECT COALESCE(SUM(c.total_deposit), 0) FROM collection_entries c JOIN daily_reports dr ON c.report_id = dr.id WHERE dr.report_date BETWEEN ? AND ?");
            $stmt1->execute([$startDate, $endDate]);
            $colls = (float)$stmt1->fetchColumn();

            $stmt2 = $this->conn->prepare("SELECT COALESCE(SUM(received_amount), 0) FROM customer_transactions WHERE tr_date BETWEEN ? AND ?");
            $stmt2->execute([$startDate, $endDate]);
            $cust_rcv = (float)$stmt2->fetchColumn();

            $summary['customer_collections'] = $colls + $cust_rcv;

        } catch (Throwable $e) {}

        return $summary;
    }

    public function deleteEntry(string $table, int $id): void {
        $this->conn->beginTransaction();
        $col = null;
        if (in_array($table, ['sales_entries', 'expense_entries', 'supplier_transactions'])) {
            $col = 'photo';
        } elseif ($table === 'customer_transactions') {
            $col = 'image_path';
        }
        
        if ($col) {
            $stmt = $this->conn->prepare("SELECT $col FROM $table WHERE id = ?");
            $stmt->execute([$id]);
            $file_path = $stmt->fetchColumn();
            if (!empty($file_path) && file_exists((string)$file_path)) {
                @unlink((string)$file_path);
            }
        }
        
        $delStmt = $this->conn->prepare("DELETE FROM $table WHERE id = ?");
        $delStmt->execute([$id]);
        $this->conn->commit();
    }
}