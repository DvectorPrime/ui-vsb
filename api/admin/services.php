<?php
/**
 * api/admin/services.php (Admin only)
 * GET: Lists all services including inactive.
 * POST: Handles action=create, action=update, and action=delete (contract 2.8).
 */
require_once __DIR__ . '/../../includes/bootstrap.php';

try {
    Auth::requireRole('admin');
    $serviceModel = new Service();

    $method = $_SERVER['REQUEST_METHOD'] ?? '';

    if ($method === 'GET') {
        $services = $serviceModel->getAll();
        ApiResponse::success($services, 'Services retrieved');
    }

    if ($method === 'POST') {
        $input  = ApiResponse::jsonInput();
        $action = (string) ($input['action'] ?? '');

        if ($action === 'create') {
            $result = $serviceModel->create($input);
            if ($result['ok']) {
                ApiResponse::success(['service' => $result['service']], 'Service created successfully', 201);
            } else {
                ApiResponse::error('Validation failed', $result['code'], $result['errors']);
            }
        }

        if ($action === 'update') {
            $serviceId = filter_var($input['service_id'] ?? null, FILTER_VALIDATE_INT);
            if ($serviceId === false || $serviceId <= 0) {
                ApiResponse::error('Valid service_id is required', 400, ['service_id' => 'Valid service_id is required']);
            }

            $result = $serviceModel->update($serviceId, $input);
            if ($result['ok']) {
                ApiResponse::success(['service' => $result['service']], 'Service updated successfully', 200);
            } else {
                ApiResponse::error($result['message'] ?? 'Validation failed', $result['code'], $result['errors'] ?? null);
            }
        }

        if ($action === 'delete') {
            $serviceId = filter_var($input['service_id'] ?? null, FILTER_VALIDATE_INT);
            if ($serviceId === false || $serviceId <= 0) {
                ApiResponse::error('Valid service_id is required', 400, ['service_id' => 'Valid service_id is required']);
            }

            $result = $serviceModel->delete($serviceId);
            if ($result['ok']) {
                ApiResponse::success(null, $result['message'], 200);
            } else {
                ApiResponse::error($result['message'], $result['code']);
            }
        }

        ApiResponse::error('Invalid action. Supported actions are "create", "update", and "delete"', 400);
    }

    header('Allow: GET, POST');
    ApiResponse::error('Method not allowed', 405);
} catch (Throwable $e) {
    error_log('api/admin/services.php: ' . $e->getMessage());
    ApiResponse::error('Server error. Please try again.', 500);
}
