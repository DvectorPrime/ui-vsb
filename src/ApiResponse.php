<?php
/**
 * ApiResponse
 * Every API file answers through this class so all responses share the
 * same envelope: { success, message, data, errors } (contract 2.2).
 */
class ApiResponse
{
    public static function success($data = null, string $message = 'OK', int $code = 200): void
    {
        self::send($code, true, $message, $data, null);
    }

    public static function error(string $message, int $code = 400, ?array $errors = null, $data = null): void
    {
        self::send($code, false, $message, $data, $errors);
    }

    public static function notImplemented(string $name): void
    {
        self::error('Not implemented yet: ' . $name, 501);
    }

    /** Stops with 405 unless the request uses the expected HTTP method. */
    public static function requireMethod(string $method): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== $method) {
            header('Allow: ' . $method);
            self::error('Method not allowed', 405);
        }
    }

    /** Reads the JSON body of a POST. Bad or empty JSON gives a 400. */
    public static function jsonInput(): array
    {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw === false ? '' : $raw, true);
        if (!is_array($data)) {
            self::error('Request body must be valid JSON', 400);
        }
        return $data;
    }

    private static function send(int $code, bool $success, string $message, $data, ?array $errors): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => $success,
            'message' => $message,
            'data'    => $data,
            'errors'  => $errors,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;   // nothing may be printed after the JSON
    }
}
