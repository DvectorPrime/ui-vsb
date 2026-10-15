<?php
/**
 * api/vehicles.php (Customer only)
 * GET: Lists customer vehicles.
 * POST: Handles action=add and action=delete (contract 2.8).
 */
require_once __DIR__ . '/../includes/bootstrap.php';

try {
    Auth::requireRole('customer');
    $user = Auth::currentUser();
    $userId = (int) $user['user_id'];
    $vehicleModel = new Vehicle();

    $method = $_SERVER['REQUEST_METHOD'] ?? '';

    if ($method === 'GET') {
        $vehicles = $vehicleModel->getByUser($userId);
        ApiResponse::success($vehicles, 'Vehicles retrieved');
    }

    if ($method === 'POST') {
        $input  = ApiResponse::jsonInput();
        $action = (string) ($input['action'] ?? '');

        if ($action === 'add') {
            $result = $vehicleModel->add($userId, $input);
            if ($result['ok']) {
                ApiResponse::success(['vehicle' => $result['vehicle']], 'Vehicle added successfully', 201);
            } else {
                ApiResponse::error('Validation failed', $result['code'], $result['errors']);
            }
        }

        if ($action === 'delete') {
            $vehicleId = filter_var($input['vehicle_id'] ?? null, FILTER_VALIDATE_INT);
            if ($vehicleId === false || $vehicleId <= 0) {
                ApiResponse::error('Vehicle ID is required', 400, ['vehicle_id' => 'Valid vehicle ID is required']);
            }

            $result = $vehicleModel->delete($vehicleId, $userId);
            if ($result['ok']) {
                ApiResponse::success(null, $result['message'], 200);
            } else {
                ApiResponse::error($result['message'], $result['code']);
            }
        }

        ApiResponse::error('Invalid action. Supported actions are "add" and "delete"', 400);
    }

    header('Allow: GET, POST');
    ApiResponse::error('Method not allowed', 405);
} catch (Throwable $e) {
    error_log('api/vehicles.php: ' . $e->getMessage());
    ApiResponse::error('Server error. Please try again.', 500);
}
