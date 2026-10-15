<?php
/**
 * Booking
 * Handles slot calculations, pessimistic locking for concurrency,
 * booking creation, 30-minute hold expiry, administrative workflows
 * (approve, reject, service status forward transitions), and dashboards (contract 2.4, 2.5, 2.6, 2.7, 2.8).
 */
class Booking
{
    private PDO $pdo;

    public const SERVICE_STATUS_ORDER = [
        'not_started'      => 0,
        'received'         => 1,
        'in_progress'      => 2,
        'completed'        => 3,
        'ready_for_pickup' => 4,
    ];

    public const SERVICE_STATUS_LABELS = [
        'not_started'      => 'Not started',
        'received'         => 'Received',
        'in_progress'      => 'In progress',
        'completed'        => 'Completed',
        'ready_for_pickup' => 'Ready for pickup',
    ];

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    /**
     * Automatically marks stale pending_payment bookings as cancelled (contract 2.5).
     * Any booking older than BOOKING_HOLD_MINUTES (default 30 mins) without payment is expired.
     */
    public function expireStale(): void
    {
        $holdMinutes = (int) BOOKING_HOLD_MINUTES;

        $sql = "SELECT booking_id FROM bookings
                WHERE booking_status = 'pending_payment'
                  AND created_at < (NOW() - INTERVAL :hold MINUTE)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':hold' => $holdMinutes]);
        $staleIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($staleIds)) {
            return;
        }

        foreach ($staleIds as $id) {
            $update = $this->pdo->prepare("UPDATE bookings SET booking_status = 'cancelled' WHERE booking_id = :id AND booking_status = 'pending_payment'");
            $update->execute([':id' => $id]);
            if ($update->rowCount() > 0) {
                $this->addHistory((int) $id, 'cancelled', 'Booking expired (payment window exceeded)', null);
            }
        }
    }

    /**
     * Calculates available workshop slots for a given date (contract 2.5, 2.8).
     * @return array{ok:bool,code:int,closed?:bool,message:string,data?:array,errors?:array}
     */
    public function getAvailableSlots(string $date, ?int $serviceId = null): array
    {
        $this->expireStale();

        $dateErrors = $this->validateBookingDate($date);
        if ($dateErrors) {
            return ['ok' => false, 'code' => 400, 'message' => 'Validation failed', 'errors' => $dateErrors];
        }

        $timestamp = strtotime($date);
        if (date('w', $timestamp) === '0') {
            return [
                'ok'      => true,
                'code'    => 200,
                'closed'  => true,
                'message' => 'The workshop is closed on Sundays',
                'data'    => [],
            ];
        }

        $holdMinutes = (int) BOOKING_HOLD_MINUTES;
        $sql = "SELECT slot_time, COUNT(*) as booked_count
                FROM bookings
                WHERE booking_date = :bdate
                  AND (
                    booking_status IN ('pending_approval', 'approved')
                    OR (booking_status = 'pending_payment' AND created_at >= (NOW() - INTERVAL :hold MINUTE))
                  )
                GROUP BY slot_time";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':bdate' => $date,
            ':hold'  => $holdMinutes,
        ]);
        $counts = [];
        while ($row = $stmt->fetch()) {
            $timeKey = substr((string) $row['slot_time'], 0, 5);
            $counts[$timeKey] = (int) $row['booked_count'];
        }

        $capacity = (int) SLOT_CAPACITY;
        $slotTimes = SLOT_TIMES;
        $todayStr = date('Y-m-d');
        $currentTime = date('H:i');
        $isToday = ($date === $todayStr);

        $slots = [];
        foreach ($slotTimes as $time) {
            $booked = $counts[$time] ?? 0;
            $remaining = max(0, $capacity - $booked);
            $available = ($remaining > 0);

            if ($isToday && $time <= $currentTime) {
                $available = false;
                $remaining = 0;
            }

            $slots[] = [
                'time'      => $time,
                'available' => $available,
                'remaining' => $remaining,
            ];
        }

        return [
            'ok'      => true,
            'code'    => 200,
            'message' => 'Slots loaded',
            'data'    => $slots,
        ];
    }

    /**
     * Creates a new booking with concurrency protection (pessimistic lock) and snapshot pricing.
     * @return array{ok:bool,code:int,message:string,data?:array,errors?:array}
     */
    public function create(int $userId, array $data): array
    {
        $this->expireStale();

        $vehicleId = filter_var($data['vehicle_id'] ?? null, FILTER_VALIDATE_INT);
        $serviceId = filter_var($data['service_id'] ?? null, FILTER_VALIDATE_INT);
        $date      = trim((string) ($data['date'] ?? ''));
        $slotTime  = trim((string) ($data['slot_time'] ?? ''));
        $notes     = isset($data['notes']) ? trim((string) $data['notes']) : null;

        $errors = [];

        $vehicleModel = new Vehicle();
        if ($vehicleId === false || $vehicleId <= 0) {
            $errors['vehicle_id'] = 'Valid vehicle_id is required';
        } else {
            $vehicle = $vehicleModel->findById($vehicleId);
            if (!$vehicle) {
                $errors['vehicle_id'] = 'Vehicle not found';
            } elseif (!$vehicleModel->belongsTo($vehicleId, $userId)) {
                return [
                    'ok'      => false,
                    'code'    => 403,
                    'message' => 'You do not own this vehicle',
                ];
            }
        }

        $serviceModel = new Service();
        $service = null;
        if ($serviceId === false || $serviceId <= 0) {
            $errors['service_id'] = 'Valid service_id is required';
        } else {
            $service = $serviceModel->getById($serviceId);
            if (!$service || !$service['is_active']) {
                $errors['service_id'] = 'Selected service is not currently available';
            }
        }

        $dateErrors = $this->validateBookingDate($date);
        if ($dateErrors) {
            $errors = array_merge($errors, $dateErrors);
        } elseif (date('w', strtotime($date)) === '0') {
            $errors['date'] = 'The workshop is closed on Sundays';
        }

        if (!in_array($slotTime, SLOT_TIMES, true)) {
            $errors['slot_time'] = 'Invalid slot time selected';
        } elseif ($date === date('Y-m-d') && $slotTime <= date('H:i')) {
            $errors['slot_time'] = 'Slot time has already passed for today';
        }

        if (!empty($errors)) {
            return [
                'ok'      => false,
                'code'    => 400,
                'message' => 'Validation failed',
                'errors'  => $errors,
            ];
        }

        try {
            $this->pdo->beginTransaction();

            $holdMinutes = (int) BOOKING_HOLD_MINUTES;
            $capacity = (int) SLOT_CAPACITY;

            $lockSql = "SELECT booking_id, booking_status, created_at
                        FROM bookings
                        WHERE booking_date = :bdate AND slot_time = :stime
                        FOR UPDATE";
            $lockStmt = $this->pdo->prepare($lockSql);
            $lockStmt->execute([
                ':bdate' => $date,
                ':stime' => $slotTime,
            ]);
            $existingRows = $lockStmt->fetchAll();

            $activeCount = 0;
            $cutoff = time() - ($holdMinutes * 60);
            foreach ($existingRows as $row) {
                $status = $row['booking_status'];
                if ($status === 'pending_approval' || $status === 'approved') {
                    $activeCount++;
                } elseif ($status === 'pending_payment') {
                    $createdAtTs = strtotime($row['created_at']);
                    if ($createdAtTs >= $cutoff) {
                        $activeCount++;
                    }
                }
            }

            if ($activeCount >= $capacity) {
                $this->pdo->rollBack();
                return [
                    'ok'      => false,
                    'code'    => 409,
                    'message' => 'Sorry, that slot was just taken. Please choose another.',
                ];
            }

            $bookingRef = $this->generateUniqueBookingRef();
            $amountSnapshot = (float) $service['price'];

            $insertSql = "INSERT INTO bookings
                          (booking_ref, user_id, vehicle_id, service_id, booking_date, slot_time, amount, booking_status, service_status, notes)
                          VALUES (:ref, :uid, :vid, :sid, :bdate, :stime, :amt, 'pending_payment', 'not_started', :notes)";
            $insertStmt = $this->pdo->prepare($insertSql);
            $insertStmt->execute([
                ':ref'   => $bookingRef,
                ':uid'   => $userId,
                ':vid'   => $vehicleId,
                ':sid'   => $serviceId,
                ':bdate' => $date,
                ':stime' => $slotTime,
                ':amt'   => $amountSnapshot,
                ':notes' => $notes,
            ]);

            $newBookingId = (int) $this->pdo->lastInsertId();
            $this->addHistory($newBookingId, 'pending_payment', 'Booking created', $userId);

            $this->pdo->commit();

            $expiresAt = date('Y-m-d H:i:s', time() + ($holdMinutes * 60));

            return [
                'ok'      => true,
                'code'    => 201,
                'message' => 'Booking created. Please complete payment within 30 minutes.',
                'data'    => [
                    'booking_id'  => $newBookingId,
                    'booking_ref' => $bookingRef,
                    'amount'      => $amountSnapshot,
                    'expires_at'  => $expiresAt,
                ],
            ];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Retrieves all bookings for a customer (contract 2.7, 2.8).
     * @return array<int, array>
     */
    public function getByUser(int $userId): array
    {
        $this->expireStale();

        $sql = "SELECT b.*,
                       v.reg_number, v.make, v.model, v.year,
                       s.name AS service_name, s.price AS service_current_price,
                       p.transaction_ref, p.status AS payment_status, p.amount AS payment_amount,
                       p.card_last4, p.paid_at
                FROM bookings b
                INNER JOIN vehicles v ON b.vehicle_id = v.vehicle_id
                INNER JOIN services s ON b.service_id = s.service_id
                LEFT JOIN payments p ON b.booking_id = p.booking_id AND p.status IN ('success', 'refunded')
                WHERE b.user_id = :uid
                ORDER BY b.booking_id DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':uid' => $userId]);
        $rows = $stmt->fetchAll();

        return array_map(function (array $row): array {
            return self::formatBooking($row, false);
        }, $rows);
    }

    /**
     * Retrieves a single booking by reference for a customer, including full history.
     */
    public function getByRef(string $ref, int $userId): ?array
    {
        $this->expireStale();

        $sql = "SELECT b.*,
                       v.reg_number, v.make, v.model, v.year,
                       s.name AS service_name, s.price AS service_current_price,
                       p.transaction_ref, p.status AS payment_status, p.amount AS payment_amount,
                       p.card_last4, p.paid_at
                FROM bookings b
                INNER JOIN vehicles v ON b.vehicle_id = v.vehicle_id
                INNER JOIN services s ON b.service_id = s.service_id
                LEFT JOIN payments p ON b.booking_id = p.booking_id AND p.status IN ('success', 'refunded')
                WHERE b.booking_ref = :ref AND b.user_id = :uid
                LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':ref' => trim($ref), ':uid' => $userId]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        $booking = self::formatBooking($row, true);
        $booking['history'] = $this->getHistory((int) $row['booking_id']);

        return $booking;
    }

    // =========================================================================
    // ADMIN METHODS (Phase B5)
    // =========================================================================

    /**
     * Retrieves all bookings matching optional filters for Admin (contract 2.8).
     * Includes customer sub-object and payment information.
     * @param array{q?:string,status?:string,date_from?:string,date_to?:string,limit?:int} $filters
     * @return array<int, array>
     */
    public function getAll(array $filters = []): array
    {
        $this->expireStale();

        $where = [];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = "b.booking_status = :status";
            $params[':status'] = trim($filters['status']);
        }

        if (!empty($filters['date_from'])) {
            $where[] = "b.booking_date >= :date_from";
            $params[':date_from'] = trim($filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $where[] = "b.booking_date <= :date_to";
            $params[':date_to'] = trim($filters['date_to']);
        }

        if (!empty($filters['q'])) {
            $q = '%' . trim($filters['q']) . '%';
            $where[] = "(b.booking_ref LIKE :q1 OR v.reg_number LIKE :q2 OR u.full_name LIKE :q3 OR u.email LIKE :q4)";
            $params[':q1'] = $q;
            $params[':q2'] = $q;
            $params[':q3'] = $q;
            $params[':q4'] = $q;
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $limitClause = !empty($filters['limit']) ? 'LIMIT ' . (int) $filters['limit'] : '';

        $sql = "SELECT b.*,
                       v.reg_number, v.make, v.model, v.year,
                       s.name AS service_name, s.price AS service_current_price,
                       u.user_id AS customer_id, u.full_name AS customer_name, u.email AS customer_email, u.phone AS customer_phone,
                       p.transaction_ref, p.status AS payment_status, p.amount AS payment_amount,
                       p.card_last4, p.paid_at
                FROM bookings b
                INNER JOIN users u ON b.user_id = u.user_id
                INNER JOIN vehicles v ON b.vehicle_id = v.vehicle_id
                INNER JOIN services s ON b.service_id = s.service_id
                LEFT JOIN payments p ON b.booking_id = p.booking_id AND p.status IN ('success', 'refunded')
                {$whereClause}
                ORDER BY b.booking_id DESC
                {$limitClause}";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        return array_map(function (array $row): array {
            return self::formatBooking($row, false);
        }, $rows);
    }

    /**
     * Retrieves a single booking for Admin by reference, including full customer info and history.
     */
    public function getByRefAdmin(string $ref): ?array
    {
        $this->expireStale();

        $sql = "SELECT b.*,
                       v.reg_number, v.make, v.model, v.year,
                       s.name AS service_name, s.price AS service_current_price,
                       u.user_id AS customer_id, u.full_name AS customer_name, u.email AS customer_email, u.phone AS customer_phone,
                       p.transaction_ref, p.status AS payment_status, p.amount AS payment_amount,
                       p.card_last4, p.paid_at
                FROM bookings b
                INNER JOIN users u ON b.user_id = u.user_id
                INNER JOIN vehicles v ON b.vehicle_id = v.vehicle_id
                INNER JOIN services s ON b.service_id = s.service_id
                LEFT JOIN payments p ON b.booking_id = p.booking_id AND p.status IN ('success', 'refunded')
                WHERE b.booking_ref = :ref
                LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':ref' => trim($ref)]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        $booking = self::formatBooking($row, true);
        $booking['history'] = $this->getHistory((int) $row['booking_id']);
        return $booking;
    }

    /**
     * Approves a pending booking (contract 2.5, 2.8).
     * Only allowed when booking_status = 'pending_approval'.
     * @return array{ok:bool,code:int,message:string,booking?:array}
     */
    public function approve(int $bookingId, int $adminId, ?string $note = null): array
    {
        $booking = $this->getRawById($bookingId);
        if (!$booking) {
            return ['ok' => false, 'code' => 404, 'message' => 'Booking not found'];
        }

        if ($booking['booking_status'] !== 'pending_approval') {
            return ['ok' => false, 'code' => 409, 'message' => 'Booking must be awaiting approval to be approved'];
        }

        $stmt = $this->pdo->prepare("UPDATE bookings SET booking_status = 'approved' WHERE booking_id = :id");
        $stmt->execute([':id' => $bookingId]);

        $logNote = $note ?: 'Booking approved by workshop admin';
        $this->addHistory($bookingId, 'approved', $logNote, $adminId);

        return [
            'ok'      => true,
            'code'    => 200,
            'message' => 'Booking approved successfully',
            'booking' => $this->getByRefAdmin($booking['booking_ref']),
        ];
    }

    /**
     * Rejects a pending booking and refunds the payment (contract 2.5, 2.8).
     * Only allowed when booking_status = 'pending_approval'.
     * @return array{ok:bool,code:int,message:string,booking?:array}
     */
    public function reject(int $bookingId, int $adminId, ?string $note = null): array
    {
        $booking = $this->getRawById($bookingId);
        if (!$booking) {
            return ['ok' => false, 'code' => 404, 'message' => 'Booking not found'];
        }

        if ($booking['booking_status'] !== 'pending_approval') {
            return ['ok' => false, 'code' => 409, 'message' => 'Booking must be awaiting approval to be rejected'];
        }

        $stmt = $this->pdo->prepare("UPDATE bookings SET booking_status = 'rejected' WHERE booking_id = :id");
        $stmt->execute([':id' => $bookingId]);

        // Automatically refund any successful payment
        (new Payment())->refund($bookingId);

        $logNote = $note ?: 'Booking rejected by workshop admin';
        $this->addHistory($bookingId, 'rejected', $logNote, $adminId);

        return [
            'ok'      => true,
            'code'    => 200,
            'message' => 'Booking rejected and payment refunded',
            'booking' => $this->getByRefAdmin($booking['booking_ref']),
        ];
    }

    /**
     * Moves a service status forward (contract 2.5, 2.8).
     * Only allowed when booking_status = 'approved', and only forward in enum order.
     * @return array{ok:bool,code:int,message:string,booking?:array}
     */
    public function updateServiceStatus(int $bookingId, string $newStatus, ?string $note, int $adminId): array
    {
        $booking = $this->getRawById($bookingId);
        if (!$booking) {
            return ['ok' => false, 'code' => 404, 'message' => 'Booking not found'];
        }

        if ($booking['booking_status'] !== 'approved') {
            return ['ok' => false, 'code' => 409, 'message' => 'Service status can only be updated for approved bookings'];
        }

        if (!isset(self::SERVICE_STATUS_ORDER[$newStatus])) {
            return ['ok' => false, 'code' => 400, 'message' => 'Invalid service status'];
        }

        $currentStatus = $booking['service_status'];
        $currentOrder = self::SERVICE_STATUS_ORDER[$currentStatus] ?? 0;
        $newOrder = self::SERVICE_STATUS_ORDER[$newStatus];

        if ($newOrder <= $currentOrder) {
            return ['ok' => false, 'code' => 409, 'message' => 'Service status can only move forward in the progression'];
        }

        $stmt = $this->pdo->prepare("UPDATE bookings SET service_status = :status WHERE booking_id = :id");
        $stmt->execute([':status' => $newStatus, ':id' => $bookingId]);

        $label = self::SERVICE_STATUS_LABELS[$newStatus] ?? $newStatus;
        $logNote = $note ?: "Service status updated to {$label}";
        $this->addHistory($bookingId, $newStatus, $logNote, $adminId);

        return [
            'ok'      => true,
            'code'    => 200,
            'message' => 'Service status updated successfully',
            'booking' => $this->getByRefAdmin($booking['booking_ref']),
        ];
    }

    /**
     * Search service records (contract 2.8 api/admin/records.php).
     * @param array{q?:string,service_id?:int,service_status?:string,date_from?:string,date_to?:string} $filters
     * @return array<int, array>
     */
    public function searchRecords(array $filters = []): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['service_id'])) {
            $where[] = "b.service_id = :sid";
            $params[':sid'] = (int) $filters['service_id'];
        }

        if (!empty($filters['service_status'])) {
            $where[] = "b.service_status = :sstatus";
            $params[':sstatus'] = trim($filters['service_status']);
        }

        if (!empty($filters['date_from'])) {
            $where[] = "b.booking_date >= :date_from";
            $params[':date_from'] = trim($filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $where[] = "b.booking_date <= :date_to";
            $params[':date_to'] = trim($filters['date_to']);
        }

        if (!empty($filters['q'])) {
            $q = '%' . trim($filters['q']) . '%';
            $where[] = "(b.booking_ref LIKE :q1 OR v.reg_number LIKE :q2 OR v.make LIKE :q3 OR v.model LIKE :q4 OR u.full_name LIKE :q5)";
            $params[':q1'] = $q;
            $params[':q2'] = $q;
            $params[':q3'] = $q;
            $params[':q4'] = $q;
            $params[':q5'] = $q;
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT b.*,
                       v.reg_number, v.make, v.model, v.year,
                       s.name AS service_name, s.price AS service_current_price,
                       u.user_id AS customer_id, u.full_name AS customer_name, u.email AS customer_email, u.phone AS customer_phone,
                       p.transaction_ref, p.status AS payment_status, p.amount AS payment_amount,
                       p.card_last4, p.paid_at
                FROM bookings b
                INNER JOIN users u ON b.user_id = u.user_id
                INNER JOIN vehicles v ON b.vehicle_id = v.vehicle_id
                INNER JOIN services s ON b.service_id = s.service_id
                LEFT JOIN payments p ON b.booking_id = p.booking_id AND p.status IN ('success', 'refunded')
                {$whereClause}
                ORDER BY b.booking_date DESC, b.booking_id DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        return array_map(function (array $row): array {
            return self::formatBooking($row, false);
        }, $rows);
    }

    /**
     * Calculates admin dashboard metrics (contract 2.8).
     * @return array{bookings_today:int,pending_approvals:int,total_revenue:float,active_services:int,recent_bookings:array}
     */
    public function getAdminDashboardStats(): array
    {
        $today = date('Y-m-d');

        $stmtToday = $this->pdo->prepare("SELECT COUNT(*) FROM bookings WHERE booking_date = :today");
        $stmtToday->execute([':today' => $today]);
        $bookingsToday = (int) $stmtToday->fetchColumn();

        $stmtPending = $this->pdo->query("SELECT COUNT(*) FROM bookings WHERE booking_status = 'pending_approval'");
        $pendingApprovals = (int) $stmtPending->fetchColumn();

        $stmtRev = $this->pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'success'");
        $totalRevenue = (float) $stmtRev->fetchColumn();

        $stmtSvc = $this->pdo->query("SELECT COUNT(*) FROM services WHERE is_active = 1");
        $activeServices = (int) $stmtSvc->fetchColumn();

        $recentBookings = $this->getAll(['limit' => 5]);

        return [
            'bookings_today'    => $bookingsToday,
            'pending_approvals' => $pendingApprovals,
            'total_revenue'     => $totalRevenue,
            'active_services'   => $activeServices,
            'recent_bookings'   => $recentBookings,
        ];
    }

    /**
     * Calculates customer dashboard metrics (contract 2.8).
     * @return array{vehicle_count:int,active_bookings:int,latest_booking:?array}
     */
    public function getCustomerDashboardStats(int $userId): array
    {
        $stmtVeh = $this->pdo->prepare("SELECT COUNT(*) FROM vehicles WHERE user_id = :uid");
        $stmtVeh->execute([':uid' => $userId]);
        $vehicleCount = (int) $stmtVeh->fetchColumn();

        // Active bookings = bookings not rejected, not cancelled, and not completed & picked up
        $stmtActive = $this->pdo->prepare("SELECT COUNT(*) FROM bookings
                                           WHERE user_id = :uid
                                             AND booking_status NOT IN ('rejected', 'cancelled')
                                             AND NOT (booking_status = 'approved' AND service_status = 'ready_for_pickup')");
        $stmtActive->execute([':uid' => $userId]);
        $activeBookings = (int) $stmtActive->fetchColumn();

        $myBookings = $this->getByUser($userId);
        $latestBooking = !empty($myBookings) ? $myBookings[0] : null;

        return [
            'vehicle_count'   => $vehicleCount,
            'active_bookings' => $activeBookings,
            'latest_booking'  => $latestBooking,
        ];
    }

    /**
     * Appends an event to the status_history table.
     */
    public function addHistory(int $bookingId, string $status, ?string $note, ?int $userId): void
    {
        $sql = "INSERT INTO status_history (booking_id, status, note, changed_by)
                VALUES (:bid, :status, :note, :uid)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':bid'    => $bookingId,
            ':status' => $status,
            ':note'   => $note,
            ':uid'    => $userId,
        ]);
    }

    /**
     * Returns history events for a booking, sorted chronologically.
     */
    public function getHistory(int $bookingId): array
    {
        $sql = "SELECT status, note, changed_at
                FROM status_history
                WHERE booking_id = :bid
                ORDER BY changed_at ASC, history_id ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':bid' => $bookingId]);
        $rows = $stmt->fetchAll();

        return array_map(function (array $r): array {
            return [
                'status'     => (string) $r['status'],
                'note'       => $r['note'] !== null ? (string) $r['note'] : null,
                'changed_at' => (string) $r['changed_at'],
            ];
        }, $rows);
    }

    /**
     * Formats database rows into the unified Booking contract shape (contract 2.7).
     */
    public static function formatBooking(array $row, bool $includeHistory): array
    {
        $payment = null;
        if (!empty($row['transaction_ref']) && !empty($row['payment_status'])) {
            $payment = [
                'transaction_ref' => (string) $row['transaction_ref'],
                'status'          => (string) $row['payment_status'],
                'amount'          => (float) $row['payment_amount'],
                'card_last4'      => (string) $row['card_last4'],
                'paid_at'         => (string) $row['paid_at'],
            ];
        }

        $out = [
            'booking_id'     => (int) $row['booking_id'],
            'booking_ref'    => (string) $row['booking_ref'],
            'booking_date'   => (string) $row['booking_date'],
            'slot_time'      => substr((string) $row['slot_time'], 0, 5),
            'amount'         => (float) $row['amount'],
            'booking_status' => (string) $row['booking_status'],
            'service_status' => (string) $row['service_status'],
            'notes'          => $row['notes'] !== null ? (string) $row['notes'] : null,
            'created_at'     => (string) $row['created_at'],
            'vehicle'        => [
                'vehicle_id' => (int) $row['vehicle_id'],
                'reg_number' => (string) $row['reg_number'],
                'make'       => (string) $row['make'],
                'model'      => (string) $row['model'],
                'year'       => (int) $row['year'],
            ],
            'service'        => [
                'service_id' => (int) $row['service_id'],
                'name'       => (string) $row['service_name'],
                'price'      => (float) $row['amount'],
            ],
            'payment'        => $payment,
        ];

        // Include customer info only in admin responses (contract 2.7)
        if (!empty($row['customer_id'])) {
            $out['customer'] = [
                'user_id'   => (int) $row['customer_id'],
                'full_name' => (string) $row['customer_name'],
                'email'     => (string) $row['customer_email'],
                'phone'     => (string) $row['customer_phone'],
            ];
        }

        return $out;
    }

    private function getRawById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM bookings WHERE booking_id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function validateBookingDate(string $date): array
    {
        $errors = [];
        $d = DateTime::createFromFormat('Y-m-d', $date);
        if (!$d || $d->format('Y-m-d') !== $date) {
            $errors['date'] = 'Date must be a valid date in YYYY-MM-DD format';
            return $errors;
        }

        $today = new DateTime('today');
        $target = new DateTime($date);

        if ($target < $today) {
            $errors['date'] = 'Date cannot be in the past';
            return $errors;
        }

        $maxDays = (int) BOOKING_WINDOW_DAYS;
        $maxDate = (clone $today)->modify("+{$maxDays} days");
        if ($target > $maxDate) {
            $errors['date'] = "Date must be within {$maxDays} days from today";
            return $errors;
        }

        return $errors;
    }

    private function generateUniqueBookingRef(): string
    {
        $datePrefix = date('Ymd');
        $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $maxIndex = strlen($chars) - 1;

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $randomPart = '';
            for ($i = 0; $i < 4; $i++) {
                $randomPart .= $chars[random_int(0, $maxIndex)];
            }
            $candidate = "VSB-{$datePrefix}-{$randomPart}";

            $stmt = $this->pdo->prepare('SELECT 1 FROM bookings WHERE booking_ref = :ref LIMIT 1');
            $stmt->execute([':ref' => $candidate]);
            if (!$stmt->fetchColumn()) {
                return $candidate;
            }
        }

        return "VSB-{$datePrefix}-" . strtoupper(substr(uniqid(), -4));
    }
}
