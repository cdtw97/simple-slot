<?php
declare(strict_types=1);
require_once __DIR__ . '/src/Repository.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');

function readAction(): array
{
    if (strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])) !== 'application/json') {
        throw new GameError('invalid_json', 'Send the action as JSON.', 415);
    }
    $raw = file_get_contents('php://input', false, null, 0, 4097);
    $action = strlen($raw) <= 4096 ? json_decode($raw, true) : null;
    if (!is_array($action) || !is_string($action['action'] ?? null)
        || !is_string($action['actionId'] ?? null) || !preg_match('/^[a-zA-Z0-9_-]{12,80}$/D', $action['actionId'])
        || !is_int($action['expectedRevision'] ?? null) || $action['expectedRevision'] < 0
        || !is_string($action['csrf'] ?? null) || !isset($action['mode'])
        || array_diff(array_keys($action), ['action','actionId','expectedRevision','mode','csrf','reel','palette'])) {
        throw new GameError('invalid_request', 'The game action is incomplete. Reload your save.', 422);
    }
    return $action;
}

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? '';
    if (!in_array($method, ['GET', 'POST'], true)) {
        header('Allow: GET, POST');
        throw new GameError('invalid_method', 'Use a state read or a game action.', 405);
    }
    $input = $method === 'POST' ? readAction() : null;
    $mode = $input['mode'] ?? $_GET['mode'] ?? 'free';
    if (!in_array($mode, ['free'], true)) {
        throw new GameError('invalid_mode', 'Choose an available game mode.', 422);
    }
    $secret = $_COOKIE['slot_guest'] ?? '';
    $new = !is_string($secret) || !preg_match('/^[a-f0-9]{64}$/D', $secret);
    if ($new) {
        $secret = bin2hex(random_bytes(32));
    }
    $csrf = hash_hmac('sha256', 'simple-slot/action/v1', $secret);
    if ($input !== null && ($new || !hash_equals($csrf, $input['csrf']))) {
        throw new GameError('guest_changed', 'Reconnect to load your guest save.', 403);
    }
    $legacyWallet = null;
    if ($new && isset($_COOKIE[session_name()])) {
        session_start(['read_and_close' => true, 'use_strict_mode' => true]);
        if (isset($_SESSION['tokens']) && is_numeric($_SESSION['tokens'])) {
            $legacyWallet = max(0, (int)round(100 * (float)$_SESSION['tokens']));
        }
    }
    $repo = new Repository(Repository::connect());
    $response = $repo->load(hash('sha256', $secret), $mode, $input, $legacyWallet);
    if ($new) {
        $path = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
        setcookie('slot_guest', $secret, ['expires' => time() + 365 * 86400, 'path' => $path . '/',
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'httponly' => true, 'samesite' => 'Lax']);
    }
    $rules = Rules::get($response['state']['rulesVersion']);
    foreach ($rules['symbols'] as $symbol => $metadata) {
        $tier = $metadata['tier'];
        if (isset($rules['paytableUnits'][$tier]) || $symbol === 'wild') continue;
        foreach ($rules['paytableBps'][$tier]['row'] as $length => $value) {
            $rules['paytableUnits'][$tier]['row'][$length] = Engine::payoutUnits($rules, $symbol, 'row', $length);
        }
        foreach (['diagonal' => 3, 'corners' => 4] as $type => $length) {
            $rules['paytableUnits'][$tier][$type] = Engine::payoutUnits($rules, $symbol, $type, $length);
        }
    }
    echo json_encode($response + ['rules' => $rules, 'csrf' => $csrf], JSON_THROW_ON_ERROR);
} catch (GameError $e) {
    http_response_code($e->status);
    echo json_encode(['error' => ['code' => $e->reason, 'message' => $e->getMessage()]]);
} catch (Throwable $e) {
    error_log('Simple Slot: ' . $e->getMessage());
    http_response_code(503);
    echo json_encode(['error' => ['code' => 'unavailable', 'message' => 'The game could not reach your save. Reconnect and retry.']]);
}
