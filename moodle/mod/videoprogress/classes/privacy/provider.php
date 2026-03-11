<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * mod_videoprogress 隱私權子系統實作
 *
 * @package    mod_videoprogress
 * @copyright  2024 Tzu Chi Medical Foundation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videoprogress\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

defined('MOODLE_INTERNAL') || die();

/**
 * mod_videoprogress 隱私權子系統，實作 metadata 與 plugin providers
 *
 * @copyright  2024 Tzu Chi Medical Foundation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider
{

    /**
     * 取得此系統的元資料
     *
     * @param collection $collection 已初始化的 collection
     * @return collection 此系統儲存的使用者資料列表
     */
    public static function get_metadata(collection $collection): collection
    {
        // 資料表：videoprogress_segments（觀看片段）
        $collection->add_database_table(
            'videoprogress_segments',
            [
                'userid' => 'privacy:metadata:segments:userid',
                'segmentstart' => 'privacy:metadata:segments:segmentstart',
                'segmentend' => 'privacy:metadata:segments:segmentend',
                'timecreated' => 'privacy:metadata:segments:timecreated',
            ],
            'privacy:metadata:segments'
        );

        // 資料表：videoprogress_progress（觀看進度）
        $collection->add_database_table(
            'videoprogress_progress',
            [
                'userid' => 'privacy:metadata:progress:userid',
                'currentposition' => 'privacy:metadata:progress:currentposition',
                'percentcomplete' => 'privacy:metadata:progress:percentcomplete',
                'completed' => 'privacy:metadata:progress:completed',
                'timemodified' => 'privacy:metadata:progress:timemodified',
            ],
            'privacy:metadata:progress'
        );

        return $collection;
    }

    /**
     * 取得包含指定使用者資訊的上下文列表
     *
     * @param int $userid 要搜尋的使用者 ID
     * @return contextlist 包含此插件使用的上下文列表
     */
    public static function get_contexts_for_userid(int $userid): contextlist
    {
        $contextlist = new contextlist();

        // 從 segments 資料表取得上下文
        $sql = "SELECT c.id
                  FROM {context} c
                  JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {videoprogress} vp ON vp.id = cm.instance
                  JOIN {videoprogress_segments} s ON s.videoprogressid = vp.id
                 WHERE s.userid = :userid";

        $params = [
            'contextlevel' => CONTEXT_MODULE,
            'modname' => 'videoprogress',
            'userid' => $userid
        ];

        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * 取得指定上下文中有資料的使用者列表
     *
     * @param userlist $userlist 包含此上下文/插件組合中有資料的使用者列表
     */
    public static function get_users_in_context(userlist $userlist)
    {
        $context = $userlist->get_context();

        if (!$context instanceof \context_module) {
            return;
        }

        $params = [
            'cmid' => $context->instanceid,
            'modname' => 'videoprogress',
        ];

        // 從 segments 資料表取得使用者
        $sql = "SELECT s.userid
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {videoprogress} vp ON vp.id = cm.instance
                  JOIN {videoprogress_segments} s ON s.videoprogressid = vp.id
                 WHERE cm.id = :cmid";

        $userlist->add_from_sql('userid', $sql, $params);
    }

    /**
     * 匯出指定使用者在指定上下文中的所有資料
     *
     * @param approved_contextlist $contextlist 已核准的上下文列表
     */
    public static function export_user_data(approved_contextlist $contextlist)
    {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $user = $contextlist->get_user();

        list($contextsql, $contextparams) = $DB->get_in_or_equal($contextlist->get_contextids(), SQL_PARAMS_NAMED);
        $params = $contextparams + ['userid' => $user->id, 'contextlevel' => CONTEXT_MODULE];

        // 取得 videoprogress 實例
        $sql = "SELECT cm.id AS cmid,
                       vp.name,
                       vp.id AS videoprogressid
                  FROM {context} c
                  JOIN {course_modules} cm ON cm.id = c.instanceid
                  JOIN {videoprogress} vp ON vp.id = cm.instance
                 WHERE c.id {$contextsql}
                   AND c.contextlevel = :contextlevel";

        $instances = $DB->get_records_sql($sql, $params);

        foreach ($instances as $instance) {
            $context = \context_module::instance($instance->cmid);

            // 匯出觀看片段
            $segments = $DB->get_records('videoprogress_segments', [
                'videoprogressid' => $instance->videoprogressid,
                'userid' => $user->id
            ]);

            $segmentdata = [];
            foreach ($segments as $segment) {
                $segmentdata[] = [
                    'segment_start' => $segment->segmentstart,
                    'segment_end' => $segment->segmentend,
                    'watched_at' => \core_privacy\local\request\transform::datetime($segment->timecreated),
                ];
            }

            // 匯出觀看進度
            $progress = $DB->get_record('videoprogress_progress', [
                'videoprogressid' => $instance->videoprogressid,
                'userid' => $user->id
            ]);

            $progressdata = [];
            if ($progress) {
                $progressdata = [
                    'current_position' => $progress->currentposition,
                    'percent_complete' => $progress->percentcomplete,
                    'completed' => $progress->completed ? get_string('yes') : get_string('no'),
                    'last_updated' => \core_privacy\local\request\transform::datetime($progress->timemodified),
                ];
            }

            $data = (object) [
                'activity_name' => $instance->name,
                'segments' => $segmentdata,
                'progress' => $progressdata,
            ];

            writer::with_context($context)->export_data([], $data);
        }
    }

    /**
     * 刪除指定上下文中所有使用者的資料
     *
     * @param \context $context 要刪除資料的特定上下文
     */
    public static function delete_data_for_all_users_in_context(\context $context)
    {
        global $DB;

        if (!$context instanceof \context_module) {
            return;
        }

        $cm = get_coursemodule_from_id('videoprogress', $context->instanceid);
        if (!$cm) {
            return;
        }

        $DB->delete_records('videoprogress_segments', ['videoprogressid' => $cm->instance]);
        $DB->delete_records('videoprogress_progress', ['videoprogressid' => $cm->instance]);
    }

    /**
     * 刪除指定使用者在指定上下文中的所有資料
     *
     * @param approved_contextlist $contextlist 已核准的上下文與使用者資訊
     */
    public static function delete_data_for_user(approved_contextlist $contextlist)
    {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }

            $cm = get_coursemodule_from_id('videoprogress', $context->instanceid);
            if (!$cm) {
                continue;
            }

            $DB->delete_records('videoprogress_segments', [
                'videoprogressid' => $cm->instance,
                'userid' => $userid
            ]);

            $DB->delete_records('videoprogress_progress', [
                'videoprogressid' => $cm->instance,
                'userid' => $userid
            ]);
        }
    }

    /**
     * 刪除單一上下文中多個使用者的資料
     *
     * @param approved_userlist $userlist 已核准的上下文與使用者資訊
     */
    public static function delete_data_for_users(approved_userlist $userlist)
    {
        global $DB;

        $context = $userlist->get_context();

        if (!$context instanceof \context_module) {
            return;
        }

        $cm = get_coursemodule_from_id('videoprogress', $context->instanceid);
        if (!$cm) {
            return;
        }

        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }

        list($usersql, $userparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params = $userparams + ['videoprogressid' => $cm->instance];

        $DB->delete_records_select(
            'videoprogress_segments',
            "videoprogressid = :videoprogressid AND userid $usersql",
            $params
        );

        $DB->delete_records_select(
            'videoprogress_progress',
            "videoprogressid = :videoprogressid AND userid $usersql",
            $params
        );
    }
}
