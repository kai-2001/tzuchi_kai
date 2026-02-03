<?php
/**
 * 取得院區的子類別列表
 * api/hospital_admin/list_subcategories.php
 */
session_start();
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
header('Content-Type: application/json; charset=utf-8');

// 權限檢查
if (empty($_SESSION['is_hospital_admin']) && empty($_SESSION['is_admin'])) {
    http_response_code(403);
    die(json_encode(['success' => false, 'error' => '權限不足']));
}

// 取得院區對應的 Moodle 類別 ID
// 從 Session 或參數取得
$hospitalName = $_SESSION['institution'] ?? '';

try {
    // 先取得所有頂層類別，找到對應的院區
    $allCats = call_moodle($moodle_url, $moodle_token, 'core_course_get_categories', [
        'criteria' => []
    ]);

    if (!is_array($allCats)) {
        throw new Exception('無法取得 Moodle 類別');
    }

    // 找到匹配院區名稱的類別
    $parentCategoryId = null;
    foreach ($allCats as $cat) {
        if ($cat['parent'] == 0) { // 頂層類別
            // 模糊匹配院區名稱
            if (strpos($cat['name'], '台北') !== false && strpos($hospitalName, '台北') !== false) {
                $parentCategoryId = $cat['id'];
                break;
            }
            if (strpos($cat['name'], '花蓮') !== false && strpos($hospitalName, '花蓮') !== false) {
                $parentCategoryId = $cat['id'];
                break;
            }
            if (strpos($cat['name'], '大林') !== false && strpos($hospitalName, '大林') !== false) {
                $parentCategoryId = $cat['id'];
                break;
            }
        }
    }

    if (!$parentCategoryId) {
        // 如果找不到，嘗試用 Session 中的 hospital_id 對應
        // 或者返回空列表
        echo json_encode([
            'success' => true,
            'data' => [],
            'parent_category_id' => null,
            'message' => '找不到對應的院區類別'
        ]);
        exit;
    }

    // 取得子類別
    $subCats = call_moodle($moodle_url, $moodle_token, 'core_course_get_categories', [
        'criteria' => [['key' => 'parent', 'value' => $parentCategoryId]]
    ]);

    $subcategories = [];
    if (is_array($subCats)) {
        foreach ($subCats as $cat) {
            $subcategories[] = [
                'id' => (int) $cat['id'],
                'name' => $cat['name'],
                'coursecount' => $cat['coursecount'] ?? 0
            ];
        }
    }

    // 按名稱排序
    usort($subcategories, function ($a, $b) {
        return strcmp($a['name'], $b['name']);
    });

    echo json_encode([
        'success' => true,
        'data' => $subcategories,
        'parent_category_id' => $parentCategoryId
    ]);

} catch (Exception $e) {
    error_log("list_subcategories error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
