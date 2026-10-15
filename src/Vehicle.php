<?php
/**
 * Vehicle
 * Manages customer vehicles: registration, lookups, ownership verification,
 * and deletion rules (contract 2.5, 2.6, 2.7).
 */
class Vehicle
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    /**
     * Adds a vehicle for the given customer.
     * @return array{ok:bool,code:int,errors:array,vehicle:?array,message?:string}
     */
    public function add(int $userId, array $data): array
    {
        $regNumber = strtoupper(trim((string) ($data['reg_number'] ?? '')));
        $make      = trim((string) ($data['make'] ?? ''));
        $model     = trim((string) ($data['model'] ?? ''));
        $year      = filter_var($data['year'] ?? null, FILTER_VALIDATE_INT);

        $errors = $this->validateVehicle($regNumber, $make, $model, $year !== false ? $year : null);
        if ($errors) {
            return ['ok' => false, 'code' => 400, 'errors' => $errors, 'vehicle' => null];
        }

        if ($this->regNumberExists($regNumber)) {
            return [
                'ok'      => false,
                'code'    => 409,
                'errors'  => ['reg_number' => 'Registration number already exists'],
                'vehicle' => null,
            ];
        }

        $sql = "INSERT INTO vehicles (user_id, reg_number, make, model, year)
                VALUES (:uid, :reg, :make, :model, :year)";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':uid'   => $userId,
                ':reg'   => $regNumber,
                ':make'  => $make,
                ':model' => $model,
                ':year'  => $year,
            ]);
        } catch (PDOException $e) {
            // 23000 = unique constraint violation (concurrency edge case)
            if ($e->getCode() === '23000') {
                return [
                    'ok'      => false,
                    'code'    => 409,
                    'errors'  => ['reg_number' => 'Registration number already exists'],
                    'vehicle' => null,
                ];
            }
            throw $e;
        }

        $newId = (int) $this->pdo->lastInsertId();
        return [
            'ok'      => true,
            'code'    => 201,
            'errors'  => [],
            'vehicle' => $this->findById($newId),
        ];
    }

    /**
     * Returns all vehicles belonging to a specific customer, newest first.
     * @return array<int, array>
     */
    public function getByUser(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM vehicles WHERE user_id = :uid ORDER BY vehicle_id DESC');
        $stmt->execute([':uid' => $userId]);
        $rows = $stmt->fetchAll();

        return array_map([self::class, 'toPublic'], $rows);
    }

    /**
     * Finds a single vehicle by ID.
     */
    public function findById(int $vehicleId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM vehicles WHERE vehicle_id = :id LIMIT 1');
        $stmt->execute([':id' => $vehicleId]);
        $row = $stmt->fetch();

        return $row ? self::toPublic($row) : null;
    }

    /**
     * Checks if a vehicle belongs to the given user.
     */
    public function belongsTo(int $vehicleId, int $userId): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM vehicles WHERE vehicle_id = :vid AND user_id = :uid LIMIT 1');
        $stmt->execute([':vid' => $vehicleId, ':uid' => $userId]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Checks if a vehicle has ever been booked.
     */
    public function hasBookings(int $vehicleId): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM bookings WHERE vehicle_id = :vid LIMIT 1');
        $stmt->execute([':vid' => $vehicleId]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Deletes a vehicle after checking ownership and booking history (contract 2.5).
     * @return array{ok:bool,code:int,message:string}
     */
    public function delete(int $vehicleId, int $userId): array
    {
        // 1. Verify existence and ownership
        if (!$this->belongsTo($vehicleId, $userId)) {
            return [
                'ok'      => false,
                'code'    => 403,
                'message' => 'You do not have permission to delete this vehicle',
            ];
        }

        // 2. Cannot delete a vehicle that has bookings
        if ($this->hasBookings($vehicleId)) {
            return [
                'ok'      => false,
                'code'    => 409,
                'message' => 'Vehicle has existing bookings and cannot be deleted',
            ];
        }

        $stmt = $this->pdo->prepare('DELETE FROM vehicles WHERE vehicle_id = :vid AND user_id = :uid');
        $stmt->execute([':vid' => $vehicleId, ':uid' => $userId]);

        return [
            'ok'      => true,
            'code'    => 200,
            'message' => 'Vehicle deleted successfully',
        ];
    }

    /**
     * Formats a database row to match the Vehicle contract shape (contract 2.7).
     */
    public static function toPublic(array $row): array
    {
        return [
            'vehicle_id' => (int) $row['vehicle_id'],
            'reg_number' => (string) $row['reg_number'],
            'make'       => (string) $row['make'],
            'model'      => (string) $row['model'],
            'year'       => (int) $row['year'],
        ];
    }

    private function regNumberExists(string $regNumber): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM vehicles WHERE reg_number = :reg LIMIT 1');
        $stmt->execute([':reg' => $regNumber]);
        return (bool) $stmt->fetchColumn();
    }

    private function validateVehicle(string $reg, string $make, string $model, ?int $year): array
    {
        $errors = [];

        // reg_number: 3 to 15 chars, letters/digits/space/dash
        if (!preg_match('/^[A-Z0-9 -]{3,15}$/', $reg)) {
            $errors['reg_number'] = 'Registration number must be 3 to 15 characters (letters, numbers, spaces or dashes)';
        }

        $makeLen = strlen($make);
        if ($makeLen < 1 || $makeLen > 50) {
            $errors['make'] = 'Make must be between 1 and 50 characters';
        }

        $modelLen = strlen($model);
        if ($modelLen < 1 || $modelLen > 50) {
            $errors['model'] = 'Model must be between 1 and 50 characters';
        }

        $maxYear = (int) date('Y') + 1;
        if ($year === null || $year < 1980 || $year > $maxYear) {
            $errors['year'] = "Year must be an integer between 1980 and {$maxYear}";
        }

        return $errors;
    }
}
