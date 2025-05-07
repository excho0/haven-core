<?php
$wasmFile = __DIR__ . '/dotlottie-player.wasm';

if (!file_exists($wasmFile)) {
    http_response_code(404);
    header('Content-Type: text/plain');
    echo 'WASM file not found.';
    exit;
}

header('Content-Type: application/wasm');
header('Content-Length: ' . filesize($wasmFile));
header('Cache-Control: public, max-age=31536000, immutable');
readfile($wasmFile);
exit;
