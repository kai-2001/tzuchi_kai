<?php
/**
 * Speech Portal - Unified Validator
 * 
 * Provides validation rules shared by frontend and backend.
 * Rules are based on database schema constraints.
 */

class Validator
{
    /**
     * Validation Rules Definition
     * Based on database schema (NOT NULL constraints, field lengths)
     * 
     * Database Reference:
     * - videos.title: varchar(255) NOT NULL
     * - videos.content_path: varchar(500) NOT NULL (handled by file upload)
     * - announcements.title: varchar(255) NOT NULL
     * - users.username: varchar(100) NOT NULL
     */
    const RULES = [
        // 上傳影片表單
        'upload' => [
            'title' => [
                'required' => true,
                'min' => 2,
                'max' => 255,
                'label' => '演講標題'
            ],
            'campus_id' => [
                'required' => true,
                'type' => 'select',
                'label' => '所屬院區'
            ],
            'event_date' => [
                'required' => true,
                'type' => 'date',
                'label' => '演講日期'
            ],
            'speaker_name' => [
                'required' => true,
                'min' => 2,
                'max' => 255,
                'label' => '講者姓名'
            ],
            'affiliation' => [
                'required' => false,
                'max' => 255,
                'label' => '服務單位'
            ],
            'position' => [
                'required' => false,
                'max' => 255,
                'label' => '職務'
            ]
        ],

        // 編輯影片表單 (與上傳類似，但影片檔案非必填)
        'edit_video' => [
            'title' => [
                'required' => true,
                'min' => 2,
                'max' => 255,
                'label' => '演講標題'
            ],
            'campus_id' => [
                'required' => true,
                'type' => 'select',
                'label' => '所屬院區'
            ],
            'event_date' => [
                'required' => true,
                'type' => 'date',
                'label' => '演講日期'
            ],
            'speaker_name' => [
                'required' => true,
                'min' => 2,
                'max' => 255,
                'label' => '講者姓名'
            ]
        ],

        // 新增公告表單
        'add_announcement' => [
            'title' => [
                'required' => true,
                'min' => 2,
                'max' => 255,
                'label' => '公告標題'
            ],
            'speaker_name' => [
                'required' => false,
                'max' => 255,
                'label' => '講者姓名'
            ],
            'affiliation' => [
                'required' => false,
                'max' => 255,
                'label' => '單位/職稱'
            ],
            'location' => [
                'required' => false,
                'max' => 255,
                'label' => '地點'
            ],
            'event_date' => [
                'required' => false,
                'type' => 'date',
                'label' => '活動日期'
            ]
        ],

        // 編輯公告表單 (與新增相同)
        'edit_announcement' => [
            'title' => [
                'required' => true,
                'min' => 2,
                'max' => 255,
                'label' => '公告標題'
            ]
        ],

        // 登入表單
        'login' => [
            'username' => [
                'required' => true,
                'min' => 2,
                'max' => 100,
                'label' => '帳號'
            ],
            'password' => [
                'required' => true,
                'min' => 4,
                'max' => 100,
                'label' => '密碼'
            ]
        ]
    ];

    /**
     * Validate form data against rules
     * 
     * @param array $data Form data ($_POST)
     * @param string $formName Form name from RULES
     * @return array ['valid' => bool, 'errors' => array]
     */
    public static function validate(array $data, string $formName): array
    {
        $rules = self::RULES[$formName] ?? [];
        $errors = [];

        foreach ($rules as $field => $rule) {
            $value = $data[$field] ?? '';
            $label = $rule['label'] ?? $field;

            // Required check
            if (!empty($rule['required']) && self::isEmpty($value)) {
                $errors[$field] = "「{$label}」為必填項目。";
                continue;
            }

            // Skip further checks if empty and not required
            if (self::isEmpty($value)) {
                continue;
            }

            // Min length check
            if (isset($rule['min']) && mb_strlen($value) < $rule['min']) {
                $errors[$field] = "「{$label}」至少需要 {$rule['min']} 個字元。";
                continue;
            }

            // Max length check
            if (isset($rule['max']) && mb_strlen($value) > $rule['max']) {
                $errors[$field] = "「{$label}」不可超過 {$rule['max']} 個字元。";
                continue;
            }

            // Type-specific validation
            if (isset($rule['type'])) {
                $typeError = self::validateType($value, $rule['type'], $label);
                if ($typeError) {
                    $errors[$field] = $typeError;
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Check if value is empty
     */
    private static function isEmpty($value): bool
    {
        return $value === null || $value === '' || (is_string($value) && trim($value) === '');
    }

    /**
     * Validate by type
     */
    private static function validateType($value, string $type, string $label): ?string
    {
        switch ($type) {
            case 'date':
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                    return "「{$label}」日期格式錯誤。";
                }
                break;
            case 'email':
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    return "「{$label}」Email 格式錯誤。";
                }
                break;
            case 'select':
                // Select validation - just ensure not empty (already handled by required)
                if ($value === '0' || $value === 0) {
                    return "「{$label}」請選擇有效選項。";
                }
                break;
        }
        return null;
    }

    /**
     * Get validation rules as JSON for frontend
     */
    public static function getRulesJson(string $formName): string
    {
        return json_encode(self::RULES[$formName] ?? [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Get first error message (for simple error display)
     */
    public static function getFirstError(array $errors): string
    {
        return reset($errors) ?: '';
    }
}
