<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/PpurioClient.php';
require_once __DIR__ . '/TemplateEngine.php';

class Notification
{
    public static function phone(string $value): string
    {
        return PpurioClient::normalizePhone($value);
    }

    public static function template(string $eventCode): ?array
    {
        $s = db()->prepare('SELECT * FROM notification_templates WHERE event_code=? AND is_active=1 LIMIT 1');
        $s->execute([$eventCode]);
        $row = $s->fetch();
        return $row ?: null;
    }

    public static function enqueue(
        string $eventCode,
        string $recipientType,
        ?int $recipientId,
        string $phone,
        array $variables = [],
        ?string $dedupeKey = null
    ): int {
        $phone = self::phone($phone);
        $variables = TemplateEngine::normalizeVariables($variables);
        $dedupeKey = $dedupeKey ?: hash(
            'sha256',
            $eventCode . '|' . $recipientType . '|' . ($recipientId ?? 0) . '|' . $phone . '|'
            . json_encode($variables, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        $s = db()->prepare(
            "INSERT INTO notification_queue
             (event_code,recipient_type,recipient_id,phone,variables_json,dedupe_key,status,available_at,created_at)
             VALUES(?,?,?,?,?,?,'pending',NOW(),NOW())
             ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)"
        );
        $s->execute([
            $eventCode,
            $recipientType,
            $recipientId,
            $phone,
            json_encode($variables, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $dedupeKey,
        ]);

        return (int)db()->lastInsertId();
    }

    public static function dispatch(
        string $eventCode,
        string $recipientType,
        ?int $recipientId,
        string $phone,
        array $variables = [],
        ?string $dedupeKey = null
    ): bool {
        if (setting('alimtalk_enabled', '0') !== '1') {
            self::writeLog(
                null,
                $eventCode,
                $recipientType,
                $recipientId,
                self::phone($phone),
                '',
                '',
                [],
                false,
                'DISABLED'
            );
            return false;
        }

        $queueId = self::enqueue($eventCode, $recipientType, $recipientId, $phone, $variables, $dedupeKey);

        if (setting('notification_dispatch_mode', 'immediate') === 'queue') {
            return true;
        }

        return self::processOne($queueId);
    }

    public static function processOne(int $queueId): bool
    {
        $pdo = db();
        $pdo->beginTransaction();

        try {
            $s = $pdo->prepare('SELECT * FROM notification_queue WHERE id=? FOR UPDATE');
            $s->execute([$queueId]);
            $q = $s->fetch();

            if (!$q || !in_array($q['status'], ['pending', 'retry'], true)) {
                $pdo->commit();
                return (bool)($q && $q['status'] === 'sent');
            }

            $pdo->prepare("UPDATE notification_queue SET status='processing',locked_at=NOW(),attempts=attempts+1 WHERE id=?")
                ->execute([$queueId]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        $template = self::template((string)$q['event_code']);
        $vars = TemplateEngine::normalizeVariables(
            json_decode((string)$q['variables_json'], true) ?: []
        );

        if (!$template) {
            self::failQueue($q, 'TEMPLATE_NOT_FOUND');
            return false;
        }
        if (self::phone((string)$q['phone']) === '') {
            self::failQueue($q, 'PHONE_MISSING');
            return false;
        }

        // 로그·관리자 미리보기용 완성 메시지입니다.
        // 실제 API에는 message_text가 아닌 targets[].changeWord가 전송됩니다.
        $previewMessage = TemplateEngine::render((string)$template['message_template'], $vars);

        try {
            $client = new PpurioClient([
                'account' => setting('ppurio_account', ''),
                'auth_key' => setting('ppurio_auth_key', ''),
                'sender_profile' => setting('ppurio_sender_profile', ''),
                'token_url' => setting('ppurio_token_url', 'https://message.ppurio.com/v1/token'),
                'message_url' => setting('ppurio_message_url', 'https://message.ppurio.com/v1/kakao'),
            ]);

            $recipientName = self::recipientName((string)$q['recipient_type'], $q['recipient_id'] !== null ? (int)$q['recipient_id'] : null);
            $targets = [[
                'to' => (string)$q['phone'],
                'name' => $recipientName,
                'changeWord' => TemplateEngine::changeWords($vars),
            ]];

            $result = $client->sendAlimtalk(
                (string)$template['template_code'],
                $targets,
                'hrm_' . (int)$q['id'] . '_' . date('YmdHis')
            );

            self::writeLog(
                (int)$q['id'],
                (string)$q['event_code'],
                (string)$q['recipient_type'],
                $q['recipient_id'] !== null ? (int)$q['recipient_id'] : null,
                (string)$q['phone'],
                (string)$template['template_code'],
                $previewMessage,
                (array)$result['payload'],
                (bool)$result['ok'],
                json_encode($result['response'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $result['message_key'] ?? null,
                $result['code'] ?? null
            );

            if ($result['ok']) {
                db()->prepare("UPDATE notification_queue SET status='sent',sent_at=NOW(),last_error=NULL,locked_at=NULL WHERE id=?")
                    ->execute([(int)$q['id']]);
                return true;
            }

            $description = (string)(
                $result['response']['json']['description']
                ?? $result['response']['json']['message']
                ?? $result['response']['raw']
                ?? ''
            );
            self::failQueue($q, 'API_ERROR ' . ($result['code'] ?? '') . ': ' . $description);
            return false;
        } catch (Throwable $e) {
            self::writeLog(
                (int)$q['id'],
                (string)$q['event_code'],
                (string)$q['recipient_type'],
                $q['recipient_id'] !== null ? (int)$q['recipient_id'] : null,
                (string)$q['phone'],
                (string)$template['template_code'],
                $previewMessage,
                [],
                false,
                $e->getMessage()
            );
            self::failQueue($q, $e->getMessage());
            return false;
        }
    }

    private static function recipientName(string $recipientType, ?int $recipientId): string
    {
        if (!$recipientId) {
            return '';
        }

        // 현재 결재권자와 신청자는 모두 employees를 기준으로 조회합니다.
        if (in_array($recipientType, ['employee', 'admin', 'approver'], true)) {
            $s = db()->prepare('SELECT name FROM employees WHERE id=? LIMIT 1');
            $s->execute([$recipientId]);
            $name = $s->fetchColumn();
            if ($name !== false) {
                return (string)$name;
            }
        }

        return '';
    }

    private static function failQueue(array $q, string $error): void
    {
        $max = max(1, (int)setting('notification_max_attempts', '3'));
        $attempts = (int)$q['attempts'] + 1;
        $status = $attempts >= $max ? 'failed' : 'retry';
        $minutes = min(60, max(1, $attempts * 5));

        db()->prepare(
            'UPDATE notification_queue
             SET status=?,last_error=?,available_at=DATE_ADD(NOW(),INTERVAL ? MINUTE),locked_at=NULL
             WHERE id=?'
        )->execute([$status, mb_substr($error, 0, 1000), $minutes, (int)$q['id']]);
    }

    public static function processPending(int $limit = 30): array
    {
        $limit = max(1, min(200, $limit));
        $ids = db()->query(
            "SELECT id FROM notification_queue
             WHERE status IN ('pending','retry') AND available_at<=NOW()
             ORDER BY id LIMIT {$limit}"
        )->fetchAll(PDO::FETCH_COLUMN);

        $sent = 0;
        $failed = 0;
        foreach ($ids as $id) {
            self::processOne((int)$id) ? $sent++ : $failed++;
        }

        return ['total' => count($ids), 'sent' => $sent, 'failed' => $failed];
    }

    public static function retry(int $queueId): void
    {
        db()->prepare(
            "UPDATE notification_queue
             SET status='pending',attempts=0,last_error=NULL,available_at=NOW(),locked_at=NULL
             WHERE id=?"
        )->execute([$queueId]);
    }

    private static function writeLog(
        ?int $queueId,
        string $event,
        string $recipientType,
        ?int $recipientId,
        string $phone,
        string $templateCode,
        string $message,
        array $payload,
        bool $ok,
        string $response,
        ?string $messageKey = null,
        ?string $resultCode = null
    ): void {
        $s = db()->prepare(
            'INSERT INTO notification_logs
             (queue_id,event_code,recipient_type,recipient_id,phone,template_code,message_text,payload,response,is_success,message_key,result_code,created_at)
             VALUES(?,?,?,?,?,?,?,?,?,?,?,?,NOW())'
        );
        $s->execute([
            $queueId,
            $event,
            $recipientType,
            $recipientId,
            self::phone($phone),
            $templateCode,
            $message,
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $response,
            $ok ? 1 : 0,
            $messageKey,
            $resultCode,
        ]);
    }
}
