<?php
/**
 * 비즈뿌리오 알림톡 공통 클라이언트
 * - POST https://message.ppurio.com/v1/token
 * - POST https://message.ppurio.com/v1/kakao
 * - messageType: ALT
 * - targets[].changeWord 변수 치환 방식
 */
class PpurioClient
{
    private string $account;
    private string $authKey;
    private string $senderProfile;
    private string $tokenUrl;
    private string $messageUrl;
    private string $tokenFile;

    public function __construct(array $config)
    {
        $this->account = trim((string)($config['account'] ?? ''));
        $this->authKey = trim((string)($config['auth_key'] ?? ''));
        $this->senderProfile = trim((string)($config['sender_profile'] ?? $config['sender_key'] ?? ''));

        $tokenUrl = trim((string)($config['token_url'] ?? ''));
        $messageUrl = trim((string)($config['message_url'] ?? ''));

        // 이전 V3 기본값이 DB에 남아 있어도 기존 정상 사용 규격으로 자동 보정합니다.
        if ($tokenUrl === '' || str_contains($tokenUrl, 'api.bizppurio.com')) {
            $tokenUrl = 'https://message.ppurio.com/v1/token';
        }
        if ($messageUrl === '' || str_contains($messageUrl, '/v3/message') || str_contains($messageUrl, 'api.bizppurio.com')) {
            $messageUrl = 'https://message.ppurio.com/v1/kakao';
        }

        $this->tokenUrl = $tokenUrl;
        $this->messageUrl = $messageUrl;
        $this->tokenFile = (string)($config['token_file'] ?? dirname(__DIR__) . '/storage/ppurio_token.json');
    }

    public function validate(): void
    {
        foreach ([
            'account' => $this->account,
            'auth_key' => $this->authKey,
            'sender_profile' => $this->senderProfile,
        ] as $key => $value) {
            if ($value === '') {
                throw new RuntimeException('비즈뿌리오 설정 누락: ' . $key);
            }
        }

        if (!function_exists('curl_init')) {
            throw new RuntimeException('PHP cURL 확장이 필요합니다.');
        }
    }

    public static function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone) ?? '';
        if (strlen($phone) === 10 && $phone !== '' && $phone[0] !== '0') {
            $phone = '0' . $phone;
        }
        return $phone;
    }

    private function request(string $url, array $headers, string $body = ''): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $body,
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $raw = $response === false ? '' : (string)$response;
        $json = json_decode($raw, true);

        return [
            'http_status' => $status,
            'raw' => $raw,
            'json' => is_array($json) ? $json : [],
            'error' => $error,
        ];
    }

    private function issueToken(): string
    {
        $this->validate();

        $result = $this->request(
            $this->tokenUrl,
            [
                'Authorization: Basic ' . base64_encode($this->account . ':' . $this->authKey),
                'Content-Type: application/json; charset=utf-8',
            ],
            '{}'
        );

        $token = (string)(
            $result['json']['token']
            ?? $result['json']['access_token']
            ?? $result['json']['accesstoken']
            ?? ''
        );

        if ($token === '') {
            throw new RuntimeException('비즈뿌리오 토큰 발급 실패: ' . ($result['error'] ?: $result['raw']));
        }

        $dir = dirname($this->tokenFile);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('토큰 저장 폴더를 생성하지 못했습니다: ' . $dir);
        }

        @file_put_contents(
            $this->tokenFile,
            json_encode([
                'token' => $token,
                'saved_at' => date('Y-m-d H:i:s'),
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            LOCK_EX
        );

        return $token;
    }

    private function loadToken(): string
    {
        if (!is_file($this->tokenFile)) {
            return $this->issueToken();
        }

        $saved = json_decode((string)@file_get_contents($this->tokenFile), true);
        $token = is_array($saved) ? (string)($saved['token'] ?? '') : '';

        return $token !== '' ? $token : $this->issueToken();
    }

    /**
     * @param string $templateCode 비즈뿌리오 승인 템플릿 코드
     * @param array  $targets      [['to'=>'010...', 'name'=>'홍길동', 'changeWord'=>['var1'=>'...']]]
     */
    public function sendAlimtalk(string $templateCode, array $targets, ?string $refKey = null): array
    {
        $this->validate();
        $templateCode = trim($templateCode);
        if ($templateCode === '') {
            throw new InvalidArgumentException('알림톡 템플릿 코드가 없습니다.');
        }

        $normalizedTargets = [];
        foreach ($targets as $target) {
            $phone = self::normalizePhone((string)($target['to'] ?? ''));
            if (!preg_match('/^01[016789][0-9]{7,8}$/', $phone)) {
                throw new InvalidArgumentException('올바르지 않은 수신번호입니다: ' . $phone);
            }

            $changeWord = [];
            foreach ((array)($target['changeWord'] ?? []) as $key => $value) {
                $key = trim((string)$key);
                if ($key === '') {
                    continue;
                }
                $changeWord[$key] = is_scalar($value) || $value === null
                    ? (string)$value
                    : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            $normalizedTargets[] = [
                'to' => $phone,
                'name' => trim((string)($target['name'] ?? '')),
                'changeWord' => $changeWord,
            ];
        }

        if (!$normalizedTargets) {
            throw new InvalidArgumentException('알림톡 수신 대상이 없습니다.');
        }

        $payload = [
            'account' => $this->account,
            'messageType' => 'ALT',
            'senderProfile' => $this->senderProfile,
            'templateCode' => $templateCode,
            'duplicateFlag' => 'Y',
            'isResend' => 'N',
            'targetCount' => count($normalizedTargets),
            'targets' => $normalizedTargets,
            'refKey' => $refKey ?: 'hrm_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)),
        ];

        $token = $this->loadToken();
        $result = $this->sendRequest($token, $payload);

        $raw = strtolower((string)($result['raw'] ?? ''));
        if (
            (int)($result['http_status'] ?? 0) === 401
            || str_contains($raw, 'jwt expired')
            || str_contains($raw, 'token issue failed')
            || str_contains($raw, 'invalid token')
        ) {
            $token = $this->issueToken();
            $result = $this->sendRequest($token, $payload);
        }

        $json = (array)($result['json'] ?? []);
        $code = (string)($json['code'] ?? $json['resultCode'] ?? '');
        $httpOk = (int)$result['http_status'] >= 200 && (int)$result['http_status'] < 300;
        $apiOk = $code === '' || in_array($code, ['0', '200', '1000'], true);
        $ok = $result['error'] === '' && $httpOk && $apiOk;

        return [
            'ok' => $ok,
            'payload' => $payload,
            'response' => $result,
            'message_key' => $json['messageKey'] ?? $json['messagekey'] ?? $json['refKey'] ?? null,
            'code' => $code,
        ];
    }

    private function sendRequest(string $token, array $payload): array
    {
        return $this->request(
            $this->messageUrl,
            [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json; charset=utf-8',
            ],
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }
}
