<?php
/**
 * User
 * Everything about accounts: registering, logging in and looking people up.
 * All SQL uses prepared statements, so user input can never change the query.
 */
class User
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    /**
     * Creates a customer account (validation rules: contract 2.6).
     * @return array{ok:bool,code:int,errors:array,user:?array}
     */
    public function register(array $data): array
    {
        $fullName = trim((string) ($data['full_name'] ?? ''));
        $email    = strtolower(trim((string) ($data['email'] ?? '')));
        $phone    = trim((string) ($data['phone'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        $confirm  = (string) ($data['confirm_password'] ?? '');

        $errors = $this->validateRegistration($fullName, $email, $phone, $password, $confirm);
        if ($errors) {
            return ['ok' => false, 'code' => 400, 'errors' => $errors, 'user' => null];
        }
        if ($this->emailExists($email)) {
            return ['ok' => false, 'code' => 409, 'errors' => ['email' => 'Email is already registered'], 'user' => null];
        }

        // Role is NOT read from the request: public sign-up is always a customer.
        $sql = "INSERT INTO users (full_name, email, phone, password_hash, role)
                VALUES (:name, :email, :phone, :hash, 'customer')";
        try {
            $this->pdo->prepare($sql)->execute([
                ':name'  => $fullName,
                ':email' => $email,
                ':phone' => $phone,
                ':hash'  => password_hash($password, PASSWORD_DEFAULT),
            ]);
        } catch (PDOException $e) {
            // 23000 = duplicate key: two people registered the same email at the same moment.
            if ($e->getCode() === '23000') {
                return ['ok' => false, 'code' => 409, 'errors' => ['email' => 'Email is already registered'], 'user' => null];
            }
            throw $e;
        }

        return ['ok' => true, 'code' => 201, 'errors' => [], 'user' => $this->findById((int) $this->pdo->lastInsertId())];
    }

    /**
     * Checks an email and password. Returns the full user row, or null.
     * The caller cannot tell "unknown email" from "wrong password" (security).
     */
    public function login(string $email, string $password): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => strtolower(trim($email))]);
        $row = $stmt->fetch();

        if (!$row) {
            // Still run a hash check so an unknown email takes about as long as a wrong password.
            password_verify($password, '$2y$10$usesomesillystringforsalt.abcdefghijklmnopqrstuvwxyzAB');
            return null;
        }
        return password_verify($password, $row['password_hash']) ? $row : null;
    }

    /** Public user shape from the contract (never includes the password hash). */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE user_id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ? self::toPublic($row) : null;
    }

    /** Turns a database row into the User object the API returns. */
    public static function toPublic(array $row): array
    {
        return [
            'user_id'   => (int) $row['user_id'],
            'full_name' => $row['full_name'],
            'email'     => $row['email'],
            'phone'     => $row['phone'],
            'role'      => $row['role'],
        ];
    }

    /** Counts characters (not bytes) so names with accents are measured fairly; no mbstring needed. */
    private static function textLength(string $text): int
    {
        return (int) preg_match_all('/./us', $text);
    }

    private function emailExists(string $email): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        return (bool) $stmt->fetchColumn();
    }

    /** Returns a field => message array; empty means everything is valid. */
    private function validateRegistration(string $name, string $email, string $phone, string $pw, string $confirm): array
    {
        $errors = [];

        $len = self::textLength($name);
        if ($len < 2 || $len > 100) {
            $errors['full_name'] = 'Full name must be 2 to 100 characters';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || self::textLength($email) > 120) {
            $errors['email'] = 'Enter a valid email address';
        }
        if (!preg_match('/^\+?\d{10,15}$/', $phone)) {
            $errors['phone'] = 'Phone must be 10 to 15 digits';
        }
        if (strlen($pw) < 8 || !preg_match('/[A-Za-z]/', $pw) || !preg_match('/\d/', $pw)) {
            $errors['password'] = 'Password must be at least 8 characters with a letter and a digit';
        }
        if ($pw !== $confirm) {
            $errors['confirm_password'] = 'Passwords do not match';
        }
        return $errors;
    }
}
