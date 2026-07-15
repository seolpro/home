<?php
/**
 * 알림톡 변수 처리 도우미
 *
 * 실제 /v1/kakao 발송에서는 완성 메시지가 아니라 changeWord가 전달됩니다.
 * 이 클래스의 render()는 관리자 미리보기와 로그 표시용입니다.
 */
class TemplateEngine
{
    public static function normalizeVariables(array $variables): array
    {
        $normalized = [];

        foreach ($variables as $key => $value) {
            $key = trim((string)$key);
            if ($key === '') {
                continue;
            }

            // 1, "1", #{var1}, {var1}, var1 모두 var1로 통일
            if (preg_match('/^(?:#?\{)?var(\d+)(?:\})?$/i', $key, $m)) {
                $key = 'var' . (int)$m[1];
            } elseif (ctype_digit($key)) {
                $key = 'var' . (int)$key;
            }

            $normalized[$key] = is_scalar($value) || $value === null
                ? (string)$value
                : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return $normalized;
    }

    public static function changeWords(array $variables): array
    {
        return self::normalizeVariables($variables);
    }

    public static function render(string $template, array $variables): string
    {
        $replace = [];

        foreach (self::normalizeVariables($variables) as $key => $value) {
            $replace['{' . $key . '}'] = $value;       // 비즈뿌리오 승인 템플릿 형식
            $replace['#{' . $key . '}'] = $value;     // 기존 HRM 형식
            $replace['{{' . $key . '}}'] = $value;    // 일반 템플릿 형식
        }

        return strtr($template, $replace);
    }

    public static function buttons(string $json, array $variables): array
    {
        // /v1/kakao + changeWord 방식에서는 버튼은 승인 템플릿에 포함되어 있으므로
        // API payload에 별도 전송하지 않습니다. 관리자 미리보기 호환을 위해 파싱만 유지합니다.
        if (trim($json) === '') {
            return [];
        }

        $decoded = json_decode(self::render($json, $variables), true);
        return is_array($decoded) ? $decoded : [];
    }
}
