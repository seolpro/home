<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../core/PpurioClient.php';

/**
 * 계정 복구용 알림톡 템플릿을 관리목록에 자동 추가합니다.
 *
 * 비즈뿌리오에서 승인받은 템플릿 코드는
 * 관리자 > 알림톡 > 비밀번호 초기화 안내(password_reset)에 입력해야 합니다.
 *
 * 권장 승인 변수
 * - {var1}: 관리자 이름
 * - {var2}: 로그인 아이디
 * - {var3}: 임시 비밀번호
 */
function account_recovery_ensure_template(): void
{
    try {
        $sql = "
            INSERT INTO notification_templates
            (
                event_code,
                event_name,
                template_code,
                message_template,
                button_json,
                variable_help,
                is_active,
                sort_order
            )
            VALUES
            (
                'password_reset',
                '관리자 임시 비밀번호 안내',
                '',
                '{var1}님, HRM Plus 임시 비밀번호는 {var3}입니다.\n아이디: {var2}\n로그인 후 비밀번호를 변경해 주세요.',
                '',
                'var1=관리자 이름, var2=로그인 아이디, var3=임시 비밀번호',
                1,
                90
            )
            ON DUPLICATE KEY UPDATE
                event_name = VALUES(event_name),
                variable_help = VALUES(variable_help)
        ";
        db()->exec($sql);
    } catch (Throwable $e) {
        // 설치 구조가 완성되기 전 로그인 화면 접근 시에는 조용히 무시합니다.
    }
}

function account_recovery_phone(string $phone): string
{
    return PpurioClient::normalizePhone($phone);
}

function account_recovery_mask_phone(string $phone): string
{
    $phone = account_recovery_phone($phone);
    if (strlen($phone) < 10) {
        return '';
    }

    return substr($phone, 0, 3)
        . '-****-'
        . substr($phone, -4);
}

function account_recovery_temporary_password(): string
{
    $upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    $lower = 'abcdefghijkmnopqrstuvwxyz';
    $digits = '23456789';
    $special = '!@#$%';

    $all = $upper . $lower . $digits . $special;

    $chars = [
        $upper[random_int(0, strlen($upper) - 1)],
        $lower[random_int(0, strlen($lower) - 1)],
        $digits[random_int(0, strlen($digits) - 1)],
        $special[random_int(0, strlen($special) - 1)],
    ];

    while (count($chars) < 10) {
        $chars[] = $all[random_int(0, strlen($all) - 1)];
    }

    // str_shuffle보다 random_int 기반 섞기를 사용합니다.
    for ($i = count($chars) - 1; $i > 0; $i--) {
        $j = random_int(0, $i);
        [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
    }

    return implode('', $chars);
}

/**
 * 관리자 임시 비밀번호 알림톡을 즉시 발송합니다.
 *
 * @return array{ok:bool, masked_phone:string}
 */
function account_recovery_send_password_alimtalk(
    array $admin,
    string $temporaryPassword
): array {
    account_recovery_ensure_template();

    if (setting('alimtalk_enabled', '0') !== '1') {
        throw new RuntimeException(
            '알림톡 사용이 중지되어 있습니다. 관리자 설정에서 알림톡 사용을 활성화하세요.'
        );
    }

    if ((int)($admin['alimtalk_opt_in'] ?? 1) !== 1) {
        throw new RuntimeException(
            '해당 계정은 알림톡 수신이 중지되어 있습니다.'
        );
    }

    $phone = account_recovery_phone((string)($admin['phone'] ?? ''));
    if (!preg_match('/^01[016789][0-9]{7,8}$/', $phone)) {
        throw new RuntimeException(
            '권한계정에 올바른 휴대폰 번호가 등록되어 있지 않습니다.'
        );
    }

    $stmt = db()->prepare("
        SELECT *
        FROM notification_templates
        WHERE event_code = 'password_reset'
          AND is_active = 1
        LIMIT 1
    ");
    $stmt->execute();
    $template = $stmt->fetch();

    if (
        !$template
        || trim((string)($template['template_code'] ?? '')) === ''
    ) {
        throw new RuntimeException(
            '알림톡 관리에서 비밀번호 초기화 안내(password_reset)의 승인 템플릿 코드를 먼저 입력하세요.'
        );
    }

    $client = new PpurioClient([
        'account' => setting('ppurio_account', ''),
        'auth_key' => setting('ppurio_auth_key', ''),
        'sender_profile' => setting('ppurio_sender_profile', ''),
        'token_url' => setting(
            'ppurio_token_url',
            'https://message.ppurio.com/v1/token'
        ),
        'message_url' => setting(
            'ppurio_message_url',
            'https://message.ppurio.com/v1/kakao'
        ),
    ]);

    $result = $client->sendAlimtalk(
        (string)$template['template_code'],
        [[
            'to' => $phone,
            'name' => (string)($admin['name'] ?? ''),
            'changeWord' => [
                'var1' => (string)($admin['name'] ?? ''),
                'var2' => (string)($admin['username'] ?? ''),
                'var3' => $temporaryPassword,
            ],
        ]],
        'password_reset_'
            . (int)($admin['id'] ?? 0)
            . '_'
            . date('YmdHis')
    );

    if (!(bool)($result['ok'] ?? false)) {
        $description = (string)(
            $result['response']['json']['description']
            ?? $result['response']['json']['message']
            ?? $result['response']['raw']
            ?? '알림톡 발송 실패'
        );

        throw new RuntimeException(
            '임시 비밀번호 알림톡 발송 실패: ' . $description
        );
    }

    // 기존 발송로그와 동일한 테이블에 기록합니다.
    try {
        $log = db()->prepare("
            INSERT INTO notification_logs
            (
                queue_id,
                event_code,
                recipient_type,
                recipient_id,
                phone,
                template_code,
                message_text,
                payload,
                response,
                is_success,
                message_key,
                result_code,
                created_at
            )
            VALUES
            (
                NULL,
                'password_reset',
                'admin',
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                1,
                ?,
                ?,
                NOW()
            )
        ");

        $preview = str_replace(
            ['{var1}', '{var2}', '{var3}'],
            [
                (string)($admin['name'] ?? ''),
                (string)($admin['username'] ?? ''),
                '**********',
            ],
            (string)($template['message_template'] ?? '')
        );

        $log->execute([
            (int)($admin['id'] ?? 0),
            $phone,
            (string)$template['template_code'],
            $preview,
            json_encode(
                $result['payload'] ?? [],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ),
            json_encode(
                $result['response'] ?? [],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ),
            $result['message_key'] ?? null,
            $result['code'] ?? null,
        ]);
    } catch (Throwable $e) {
        // 발송 성공 후 로그 기록 실패 때문에 비밀번호 변경을 막지는 않습니다.
    }

    return [
        'ok' => true,
        'masked_phone' => account_recovery_mask_phone($phone),
    ];
}
