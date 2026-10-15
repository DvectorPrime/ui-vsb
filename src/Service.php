<?php
/**
 * Service
 * Manages the workshop service catalogue (contract 2.5, 2.6, 2.7).
 * Ensures correct numeric and boolean type casting for JSON output.
 */
class Service
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    /**
     * Returns all active services for the customer catalogue.
     * @return array<int, array>
     */
    public function getAllActive(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM services WHERE is_active = 1 ORDER BY service_id ASC');
        $rows = $stmt->fetchAll();

        return array_map([self::class, 'toPublic'], $rows);
    }

    /**
     * Returns all services (active and inactive) for admin management.
     * @return array<int, array>
     */
    public function getAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM services ORDER BY service_id ASC');
        $rows = $stmt->fetchAll();

        return array_map([self::class, 'toPublic'], $rows);
    }

    /**
     * Finds a service by ID.
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM services WHERE service_id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row ? self::toPublic($row) : null;
    }

    /**
     * Creates a new service item.
     * @return array{ok:bool,code:int,errors:array,service:?array}
     */
    public function create(array $data): array
    {
        $name         = trim((string) ($data['name'] ?? ''));
        $description  = trim((string) ($data['description'] ?? ''));
        $price        = filter_var($data['price'] ?? null, FILTER_VALIDATE_FLOAT);
        $durationMins = filter_var($data['duration_mins'] ?? null, FILTER_VALIDATE_INT);

        $errors = $this->validateService($name, $price !== false ? $price : null, $durationMins !== false ? $durationMins : null);
        if ($errors) {
            return ['ok' => false, 'code' => 400, 'errors' => $errors, 'service' => null];
        }

        $sql = "INSERT INTO services (name, description, price, duration_mins, is_active)
                VALUES (:name, :desc, :price, :duration, 1)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':name'     => $name,
            ':desc'     => $description,
            ':price'    => $price,
            ':duration' => $durationMins,
        ]);

        $newId = (int) $this->pdo->lastInsertId();
        return [
            'ok'      => true,
            'code'    => 201,
            'errors'  => [],
            'service' => $this->getById($newId),
        ];
    }

    /**
     * Updates an existing service.
     * @return array{ok:bool,code:int,errors:array,service:?array,message?:string}
     */
    public function update(int $id, array $data): array
    {
        $existing = $this->getById($id);
        if ($existing === null) {
            return [
                'ok'      => false,
                'code'    => 404,
                'errors'  => [],
                'service' => null,
                'message' => 'Service not found',
            ];
        }

        $name         = trim((string) ($data['name'] ?? $existing['name']));
        $description  = trim((string) ($data['description'] ?? $existing['description']));
        $price        = isset($data['price']) ? filter_var($data['price'], FILTER_VALIDATE_FLOAT) : $existing['price'];
        $durationMins = isset($data['duration_mins']) ? filter_var($data['duration_mins'], FILTER_VALIDATE_INT) : $existing['duration_mins'];
        $isActive     = isset($data['is_active']) ? (bool) $data['is_active'] : $existing['is_active'];

        $errors = $this->validateService($name, $price !== false ? $price : null, $durationMins !== false ? $durationMins : null);
        if ($errors) {
            return ['ok' => false, 'code' => 400, 'errors' => $errors, 'service' => null];
        }

        $sql = "UPDATE services
                SET name = :name, description = :desc, price = :price, duration_mins = :duration, is_active = :is_active
                WHERE service_id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':name'      => $name,
            ':desc'      => $description,
            ':price'     => $price,
            ':duration'  => $durationMins,
            ':is_active' => $isActive ? 1 : 0,
            ':id'        => $id,
        ]);

        return [
            'ok'      => true,
            'code'    => 200,
            'errors'  => [],
            'service' => $this->getById($id),
        ];
    }

    /**
     * Deletes or deactivates a service (contract 2.5).
     * If the service has existing bookings, it is deactivated (is_active = 0)
     * instead of deleting to preserve history.
     * @return array{ok:bool,code:int,deactivated:bool,message:string}
     */
    public function delete(int $id): array
    {
        $existing = $this->getById($id);
        if ($existing === null) {
            return [
                'ok'          => false,
                'code'        => 404,
                'deactivated' => false,
                'message'     => 'Service not found',
            ];
        }

        if ($this->hasBookings($id)) {
            $stmt = $this->pdo->prepare('UPDATE services SET is_active = 0 WHERE service_id = :id');
            $stmt->execute([':id' => $id]);

            return [
                'ok'          => true,
                'code'        => 200,
                'deactivated' => true,
                'message'     => 'Service deactivated because it has bookings',
            ];
        }

        $stmt = $this->pdo->prepare('DELETE FROM services WHERE service_id = :id');
        $stmt->execute([':id' => $id]);

        return [
            'ok'          => true,
            'code'        => 200,
            'deactivated' => false,
            'message'     => 'Service deleted',
        ];
    }

    /**
     * Checks if a service has any bookings associated with it.
     */
    public function hasBookings(int $serviceId): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM bookings WHERE service_id = :id LIMIT 1');
        $stmt->execute([':id' => $serviceId]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Casts database row types into the contract-defined Service shape.
     */
    public static function toPublic(array $row): array
    {
        return [
            'service_id'    => (int) $row['service_id'],
            'name'          => (string) $row['name'],
            'description'   => (string) ($row['description'] ?? ''),
            'price'         => (float) $row['price'],
            'duration_mins' => (int) $row['duration_mins'],
            'is_active'     => (bool) $row['is_active'],
        ];
    }

    private function validateService(string $name, ?float $price, ?int $durationMins): array
    {
        $errors = [];

        $nameLen = strlen($name);
        if ($nameLen < 2 || $nameLen > 100) {
            $errors['name'] = 'Service name must be between 2 and 100 characters';
        }

        if ($price === null || $price <= 0) {
            $errors['price'] = 'Price must be greater than zero';
        }

        if ($durationMins === null || $durationMins < 15 || $durationMins > 480) {
            $errors['duration_mins'] = 'Duration must be between 15 and 480 minutes';
        }

        return $errors;
    }
}
