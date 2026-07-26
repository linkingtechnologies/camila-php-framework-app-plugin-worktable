<?php
/**
 * worktable — Plugin API handlers
 * Base path: /app/<app-name>/cf_api.php/worktable
 *
 * ENDPOINTS
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * GET /status                                                        [PRIVATE]
 *   Simple liveness check. Returns: {status: "ok"}
 *
 * POST /mcp-proxy                                                    [PRIVATE]
 *   Forwards a single JSON-RPC 2.0 message to an arbitrary MCP Streamable HTTP
 *   endpoint, avoiding browser CORS restrictions. Used by the MCP Checker SPA.
 *   Body: { url, authHeader?, sessionId?, payload }
 *   Returns: { httpStatus, sessionId, body, raw }
 *
 * ─────────────────────────────────────────────────────────────────────────────
 */

return [

    // GET /worktable/status
    'GET /status' => function ($params, $body, $segments) {
        return ['status' => 'ok'];
    },

    // POST /worktable/mcp-proxy
    'POST /mcp-proxy' => function ($params, $body, $segments) {
        $input      = is_array($body) ? $body : [];
        $targetUrl  = is_string($input['url'] ?? null) ? trim($input['url']) : '';
        $authHeader = is_string($input['authHeader'] ?? null) ? trim($input['authHeader']) : '';
        $sessionId  = is_string($input['sessionId'] ?? null) ? trim($input['sessionId']) : '';
        $payload    = $input['payload'] ?? null;

        if ($targetUrl === '' || !preg_match('#^https?://#i', $targetUrl)) {
            return ['__status' => 400, 'error' => 'invalid_url'];
        }
        if ($payload === null) {
            return ['__status' => 400, 'error' => 'missing_payload'];
        }

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json, text/event-stream',
        ];
        if ($authHeader !== '') $headers[] = 'Authorization: ' . $authHeader;
        if ($sessionId  !== '') $headers[] = 'Mcp-Session-Id: ' . $sessionId;

        $ch = curl_init($targetUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        $raw = curl_exec($ch);

        if ($raw === false) {
            $curlError = curl_error($ch);
            curl_close($ch);
            return ['__status' => 502, 'error' => 'proxy_request_failed', 'message' => $curlError];
        }

        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $rawHeaders = substr($raw, 0, $headerSize);
        $rawBody    = substr($raw, $headerSize);

        $respSessionId = '';
        if (preg_match('/^Mcp-Session-Id:\s*(.+)$/mi', $rawHeaders, $m)) {
            $respSessionId = trim($m[1]);
        }

        $bodyJson = null;
        $trimmedBody = trim($rawBody);
        if ($trimmedBody !== '') {
            if (stripos($rawHeaders, 'text/event-stream') !== false) {
                foreach (explode("\n", $rawBody) as $line) {
                    $line = trim($line);
                    if (stripos($line, 'data:') === 0) {
                        $decoded = json_decode(trim(substr($line, 5)), true);
                        if ($decoded !== null) $bodyJson = $decoded;
                    }
                }
            } else {
                $bodyJson = json_decode($rawBody, true);
            }
        }

        return [
            'httpStatus' => $httpStatus,
            'sessionId'  => $respSessionId,
            'body'       => $bodyJson,
            'raw'        => $bodyJson === null ? $rawBody : null,
        ];
    },

];
