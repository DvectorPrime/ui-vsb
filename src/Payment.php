<?php
/**
 * Payment
 * Model for recording and querying payments and issuing refunds (contract 2.4, 2.7, 2.8).
 * Note: Card charging & gateway simulation belongs to Phase B4; this class provides
 * the data querying, audit logging, and refund operations needed by Phase B5.
 */
class Payment
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    /**
     * Marks any successful payment for a booking as refunded (used during booking rejection).
     * @return int Number of payment rows updated
     */
    public function refund(int $bookingId): int
    {
        $sql = "UPDATE payments
                SET status = 'refunded'
                WHERE booking_id = :bid AND status = 'success'";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':bid' => $bookingId]);
        return $stmt->rowCount();
    }

    /**
     * Retrieves all payments matching the given filters for the admin payments report.
     * @param array{status?:string,date_from?:string,date_to?:string} $filters
     * @return array{total_success:float,payments:array<int,array>}
     */
    public function getAll(array $filters = []): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = "p.status = :status";
            $params[':status'] = trim($filters['status']);
        }

        if (!empty($filters['date_from'])) {
            $where[] = "DATE(p.paid_at) >= :date_from";
            $params[':date_from'] = trim($filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $where[] = "DATE(p.paid_at) <= :date_to";
            $params[':date_to'] = trim($filters['date_to']);
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT p.payment_id, p.transaction_ref, b.booking_ref, u.full_name AS customer_name,
                       p.amount, p.status, p.failure_reason, p.card_last4, p.paid_at
                FROM payments p
                INNER JOIN bookings b ON p.booking_id = b.booking_id
                INNER JOIN users u ON b.user_id = u.user_id
                {$whereClause}
                ORDER BY p.payment_id DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        // Calculate total successful payments across the entire system (contract 2.8)
        $totalSuccessStmt = $this->pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'success'");
        $totalSuccess = (float) $totalSuccessStmt->fetchColumn();

        $payments = array_map(function (array $r): array {
            return [
                'payment_id'      => (int) $r['payment_id'],
                'transaction_ref' => (string) $r['transaction_ref'],
                'booking_ref'     => (string) $r['booking_ref'],
                'customer_name'   => (string) $r['customer_name'],
                'amount'          => (float) $r['amount'],
                'status'          => (string) $r['status'],
                'failure_reason'  => $r['failure_reason'] !== null ? (string) $r['failure_reason'] : null,
                'card_last4'      => $r['card_last4'] !== null ? (string) $r['card_last4'] : null,
                'paid_at'         => (string) $r['paid_at'],
            ];
        }, $rows);

        return [
            'total_success' => $totalSuccess,
            'payments'      => $payments,
        ];
    }

    /**
     * Finds the latest successful or refunded payment for a specific booking.
     */
    public function getByBooking(int $bookingId): ?array
    {
        $sql = "SELECT * FROM payments
                WHERE booking_id = :bid AND status IN ('success', 'refunded')
                ORDER BY payment_id DESC LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':bid' => $bookingId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
