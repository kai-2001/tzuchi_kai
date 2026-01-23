<?php
/**
 * Speech Portal - Helper Functions
 */

/**
 * Validate required fields in POST/GET data
 */
function validate_required($data, $fields)
{
    $errors = [];
    foreach ($fields as $field => $label) {
        if (!isset($data[$field]) || trim($data[$field]) === '') {
            $errors[] = "「{$label}」為必填項目。";
        }
    }
    return $errors;
}

/**
 * Redirect with message
 */
function redirect_with_msg($url, $msg, $type = 'success')
{
    $param = ($type === 'success') ? 'msg' : 'error';
    $separator = (strpos($url, '?') !== false) ? '&' : '?';
    header("Location: {$url}{$separator}{$param}=" . urlencode($msg));
    exit;
}
