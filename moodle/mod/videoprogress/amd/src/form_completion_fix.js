/**
 * 修正活動完成設定的標籤文字
 *
 * @module     mod_videoprogress/form_completion_fix
 * @copyright  2024 Tzu Chi Medical Foundation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery'], function($) {
    return {
        init: function() {
            /**
             *
             */
            function fixLabel() {
                // 隱藏不需要的完成條件選項（videoprogress 有自己的完成邏輯）
                var hideTexts = [
                    '新增條件',
                    'Show activity completion conditions',
                    '學生必須手動標記此活動為已完成',
                    'Students can manually mark the activity as completed',
                    '依據完成門檻判斷完成',
                    'Completion based on threshold',
                    '當學生完成下列所有條件時，活動即為完成',
                    'Activity is completed when students do all the following'
                ];

                // 隱藏包含這些文字的元素
                $('label, span, div').filter(function() {
                    var text = $(this).text().trim();
                    for (var i = 0; i < hideTexts.length; i++) {
                        if (text === hideTexts[i] || text.indexOf(hideTexts[i]) === 0) {
                            return true;
                        }
                    }
                    return false;
                }).each(function() {
                    var $container = $(this).closest('.form-check, .radio, .fitem, .form-group');
                    if ($container.length) {
                        $container.hide();
                    } else {
                        $(this).parent().hide();
                    }
                });
            }

            // 頁面載入後執行
            $(document).ready(function() {
                setTimeout(fixLabel, 100);
            });

            // MutationObserver 備用
            var observer = new MutationObserver(function() {
                fixLabel();
            });
            if (document.body) {
                observer.observe(document.body, {childList: true, subtree: true});
            }
        }
    };
});
