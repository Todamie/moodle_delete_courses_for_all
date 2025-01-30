<?php

require('add_to_nav.php');


class local_courses_how_old_plugin
{
    public $studentroleid = 5;
    public $teacherroleid = 3;

    //Количество пользователей в курсе (в основном сюда передаются преподы и студенты)
    public function get_user_count_from_db($context, $roleid)
    {
        global $DB;

        return $DB->count_records_sql(
            "
            SELECT COUNT(mdl_role_assignments.id)
            FROM mdl_role_assignments
            JOIN mdl_user ON mdl_user.id = mdl_role_assignments.userid
            WHERE mdl_role_assignments.contextid = :contextid AND mdl_role_assignments.roleid = :roleid AND mdl_user.deleted = 0",
            ['contextid' => $context->id, 'roleid' => $roleid]
        );
    }

    //Получение списка преподавателей или студентов в курсе
    public function get_all_users_from_course_db($context, $roleid)
    {
        global $DB;

        return $DB->get_records_sql(
            "SELECT DISTINCT u.id, u.firstname, u.lastname
            FROM mdl_role_assignments ra
            JOIN mdl_user u ON u.id = ra.userid
            WHERE ra.contextid = :contextid 
            AND ra.roleid = :roleid 
            AND u.deleted = 0
            ORDER BY u.lastname, u.firstname",
            ['contextid' => $context->id, 'roleid' => $roleid]
        );
    }

    //Последний доступ к курсу
    public function get_time_last_access_from_db($course)
    {
        global $DB;

        $lastaccess = $DB->get_field_sql(
            "SELECT COALESCE(MAX(timeaccess), 0) as lastaccess
            FROM mdl_user_lastaccess
            WHERE courseid = :courseid",
            ['courseid' => $course->id]
        );

        // Если никогда не заходили либо если никого нет в курсе, тоже не находит последний доступ
        return $lastaccess ? $lastaccess : 'Никогда';
    }

    //Смотрит размер курса через базу, зачастую не правильно поэтому не используется в данный момент
    public function get_course_size_in_mb($course)
    {
        global $DB;

        $sql = "
            SELECT SUM(filesize) as total
            FROM mdl_files
            WHERE contextid = (
                SELECT id
                FROM mdl_context
                WHERE contextlevel = 50 AND instanceid = :courseid
            ) AND filesize > 0";

        $params = ['courseid' => $course->id];
        $totalbytes = $DB->get_field_sql($sql, $params);

        //Байты -> MB
        $totalmb = round(($totalbytes / (1024 * 1024)), 2);

        if ($totalmb == 0) {
            return '0';
        } else {
            return $totalmb . ' MB';
        }
    }


    //Полный путь до категории курса. Используется для выгрузки в Excel
    public function get_full_category_path($category)
    {
        $parents = $category->get_parents();
        $path = [];

        // Добавляем имена родительских категорий
        foreach ($parents as $parentid) {
            $parent = core_course_category::get($parentid, IGNORE_MISSING);
            if ($parent) {
                $path[] = $parent->get_formatted_name();
            }
        }

        // Добавляем текущую категорию
        $path[] = $category->get_formatted_name();

        // Соединяем все категории через разделитель
        return implode(' / ', $path);
    }

    public function get_basesql_and_params()
    {
        global $DB, $USER;

        //Список курсов в зависимости от роли:
        //Для управляющих или модераторов в категориях
        $categorymanagersql = "SELECT DISTINCT c.*
                              FROM {course} c
                              JOIN {course_categories} cc_course ON c.category = cc_course.id
                              JOIN {course_categories} cc_access ON cc_course.path LIKE CONCAT(cc_access.path, '/%')
                                  OR cc_course.path = cc_access.path
                              JOIN {context} ctxcat ON ctxcat.instanceid = cc_access.id 
                                  AND ctxcat.contextlevel = " . CONTEXT_COURSECAT . "
                              JOIN {role_assignments} ra ON ra.contextid = ctxcat.id
                              WHERE ra.userid = :userid 
                              AND ra.roleid IN (SELECT id FROM {role} 
                                              WHERE shortname IN ('manager', 'moderator'))";

        //Для преподавателей
        $usercoursesql = "SELECT DISTINCT c.*
                          FROM {course} c
                          JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = 50
                          JOIN {role_assignments} ra ON ra.contextid = ctx.id
                          WHERE ra.userid = :userid 
                          AND ra.roleid = :teacherroleid";

        //Для админов
        $admincoursesql = "SELECT c.* FROM {course} c";

        $is_category_manager = $DB->record_exists_sql(
            "SELECT 1 
             FROM {role_assignments} ra
             JOIN {context} ctx ON ra.contextid = ctx.id
             JOIN {role} r ON ra.roleid = r.id
             WHERE ra.userid = :userid 
             AND ctx.contextlevel = :contextlevel
             AND r.shortname IN ('manager', 'moderator')",
            ['userid' => $USER->id, 'contextlevel' => CONTEXT_COURSECAT]
        );

        // Выбираем подходящий SQL запрос
        if (is_siteadmin()) {
            $basesql = $admincoursesql;
            $params = [];
        } elseif ($is_category_manager) {
            $basesql = $categorymanagersql;
            $params = ['userid' => $USER->id];
        } else {
            $basesql = $usercoursesql;
            $params = [
                'userid' => $USER->id,
                'teacherroleid' => $this->teacherroleid
            ];
        }

        $basesql_and_params = [$basesql, $params, $is_category_manager];

        return $basesql_and_params;
    }

    public function get_basesql_and_params_with_search($searchterm)
    {
        global $DB, $USER;

        //Список курсов в зависимости от роли:
        $is_category_manager = $DB->record_exists_sql(
            "SELECT 1 
             FROM {role_assignments} ra
             JOIN {context} ctx ON ra.contextid = ctx.id
             JOIN {role} r ON ra.roleid = r.id
             WHERE ra.userid = :userid 
             AND ctx.contextlevel = :contextlevel
             AND r.shortname IN ('manager', 'moderator')",
            ['userid' => $USER->id, 'contextlevel' => CONTEXT_COURSECAT]
        );

        if (is_siteadmin()) {
            $basesql = "SELECT c.*
                        FROM {course} c
                        WHERE " . $DB->sql_like('c.fullname', ':searchterm', false);
            $params = ['searchterm' => $searchterm];
        } elseif ($is_category_manager) {
            $basesql = "SELECT DISTINCT c.*
                        FROM {course} c
                        JOIN {course_categories} cc_course ON c.category = cc_course.id
                        JOIN {course_categories} cc_access ON cc_course.path LIKE CONCAT(cc_access.path, '/%')
                            OR cc_course.path = cc_access.path
                        JOIN {context} ctxcat ON ctxcat.instanceid = cc_access.id 
                            AND ctxcat.contextlevel = " . CONTEXT_COURSECAT . "
                        JOIN {role_assignments} ra ON ra.contextid = ctxcat.id
                        WHERE ra.userid = :userid 
                        AND ra.roleid IN (SELECT id FROM {role} 
                                        WHERE shortname IN ('manager', 'moderator'))
                        AND " . $DB->sql_like('c.fullname', ':searchterm', false);
            $params = ['userid' => $USER->id, 'searchterm' => $searchterm];
        } else {
            $basesql = "SELECT DISTINCT c.*
                        FROM {course} c
                        JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = 50
                        JOIN {role_assignments} ra ON ra.contextid = ctx.id
                        WHERE ra.userid = :userid 
                        AND ra.roleid = :teacherroleid
                        AND " . $DB->sql_like('c.fullname', ':searchterm', false);
            $params = [
                'userid' => $USER->id,
                'teacherroleid' => $this->teacherroleid,
                'searchterm' => $searchterm
            ];
        }

        $basesql_and_params = [$basesql, $params];

        return $basesql_and_params;
    }

    public function sort_by_additional_fields($sortby, $sortorder, $basesql, $params, $offset, $perpage){
        global $DB;

         // Сортировка по доп полям (lastaccess, studentcount, teachercount)
         if ($sortby === 'lastaccess') {

            $sql = "SELECT mdl_course.*, COALESCE(MAX(mdl_user_lastaccess.timeaccess), 0) as lastaccess
                    FROM ({$basesql}) mdl_course
                    LEFT JOIN mdl_user_lastaccess ON mdl_course.id = mdl_user_lastaccess.courseid
                    GROUP BY mdl_course.id
                    ORDER BY lastaccess $sortorder";
            $courses = $DB->get_records_sql($sql, $params, $offset, $perpage);
        } elseif ($sortby === 'studentcount' || $sortby === 'teachercount') {

            $roleid = ($sortby === 'studentcount') ? $this->studentroleid : $this->teacherroleid;
            $countfield = ($sortby === 'studentcount') ? 'studentcount' : 'teachercount';

            $sql = "SELECT mdl_course.*, COUNT(DISTINCT ra2.id) as {$countfield}
                    FROM ({$basesql}) mdl_course
                    LEFT JOIN mdl_context ON mdl_context.instanceid = mdl_course.id AND mdl_context.contextlevel = 50
                    LEFT JOIN mdl_role_assignments ra2 ON ra2.contextid = mdl_context.id AND ra2.roleid = :roleid
                    LEFT JOIN mdl_user ON mdl_user.id = ra2.userid AND mdl_user.deleted = 0
                    GROUP BY mdl_course.id
                    ORDER BY {$countfield} {$sortorder}";

            $params['roleid'] = $roleid;
            $courses = $DB->get_records_sql($sql, $params, $offset, $perpage);
        } else {
            // Базовая сортировка
            $sort = $sortby ? "$sortby $sortorder" : '';
            $courses = $DB->get_records_sql(
                $basesql . ($sort ? " ORDER BY $sort" : ''), $params, $offset, $perpage);
        }

        return $courses;
    }
}

//Модалка для подтверждения удаления курсов
function delete_courses_ok()
{
    $output = html_writer::start_tag('div', [
        'class' => 'modal fade',
        'id' => 'confirmDeleteModal',
        'tabindex' => '-1',
        'role' => 'dialog',
        'aria-labelledby' => 'confirmDeleteModalLabel',
        'aria-hidden' => 'true'
    ]);
    $output .= html_writer::start_tag('div', ['class' => 'modal-dialog', 'role' => 'document']);
    $output .= html_writer::start_tag('div', ['class' => 'modal-content']);
    $output .= html_writer::start_tag('div', ['class' => 'modal-header']);
    $output .= html_writer::tag('h5', 'Подтверждение удаления', ['class' => 'modal-title', 'id' => 'confirmDeleteModalLabel']);
    $output .= html_writer::tag('button', '&times;', [
        'type' => 'button',
        'class' => 'close',
        'data-dismiss' => 'modal',
        'aria-label' => 'Close'
    ]);
    $output .= html_writer::end_tag('div');
    $output .= html_writer::start_tag('div', ['class' => 'modal-body']);
    $output .= html_writer::tag('p', 'Вы уверены, что хотите удалить выбранные курсы?');
    $output .= html_writer::end_tag('div');
    $output .= html_writer::start_tag('div', ['class' => 'modal-footer']);
    $output .= html_writer::tag('button', 'Отмена', [
        'type' => 'button',
        'class' => 'btn btn-secondary',
        'data-dismiss' => 'modal'
    ]);
    $output .= html_writer::tag('button', 'Удалить', [
        'type' => 'submit',
        'name' => 'action',
        'value' => 'delete',
        'class' => 'btn btn-primary'
    ]);
    $output .= html_writer::end_tag('div');
    $output .= html_writer::end_tag('div');
    $output .= html_writer::end_tag('div');
    $output .= html_writer::end_tag('div');

    return $output;
}

function delete_courses_error($how_many_courses_can_delete)
{
    $output = html_writer::start_tag('div', [
        'class' => 'modal fade',
        'id' => 'errorDeleteModal',
        'tabindex' => '-1',
        'role' => 'dialog',
        'aria-labelledby' => 'errorDeleteModalLabel',
        'aria-hidden' => 'true'
    ]);
    $output .= html_writer::start_tag('div', ['class' => 'modal-dialog', 'role' => 'document']);
    $output .= html_writer::start_tag('div', ['class' => 'modal-content']);
    $output .= html_writer::start_tag('div', ['class' => 'modal-header']);
    $output .= html_writer::tag('h5', 'Ошибка удаления', ['class' => 'modal-title', 'id' => 'errorDeleteModalLabel']);
    $output .= html_writer::tag('button', '&times;', [
        'type' => 'button',
        'class' => 'close',
        'data-dismiss' => 'modal',
        'aria-label' => 'Close'
    ]);
    $output .= html_writer::end_tag('div');
    $output .= html_writer::start_tag('div', ['class' => 'modal-body']);
    $output .= html_writer::tag('p', "Удаляется не более $how_many_courses_can_delete курсов за раз");
    $output .= html_writer::end_tag('div');
    $output .= html_writer::start_tag('div', ['class' => 'modal-footer']);
    $output .= html_writer::tag('button', 'Продолжить', [
        'type' => 'button',
        'class' => 'btn btn-primary',
        'data-dismiss' => 'modal'
    ]);
    $output .= html_writer::end_tag('div');
    $output .= html_writer::end_tag('div');
    $output .= html_writer::end_tag('div');
    $output .= html_writer::end_tag('div');

    return $output;
}

//Тестовый. Нужен только для проверки sql запросов. Вызывается вместо удаления курса в index
function delete_course_custom($courseid, $showfeedback = true)
{
    global $DB;

    // Получаем контекст курса
    $context = context_course::instance($courseid);

    // Для дебага запросов из базы
    $sql = "SELECT * FROM moodle.mdl_files where filename = '.' and filearea = 'draft'";

    $duplicates = $DB->get_records_sql($sql); // Используем get_records_sql вместо get_field_sql
    echo "<pre>";
    var_dump($duplicates);
    echo "</pre>";
    die();
}

?>

<script>
    function how_many_courses_selected($how_many_courses_can_delete) {
        var selectedCourses = document.querySelectorAll('input[name="selectedcourses[]"]:checked');
        if (selectedCourses.length <= $how_many_courses_can_delete && selectedCourses.length > 0) {
            $('#confirmDeleteModal').modal('show');
        } else if (selectedCourses.length == 0) {
            return;
        } else {
            $('#errorDeleteModal').modal('show');
        }
    }
</script>

<style>
    input[type="checkbox"].disabled-checkbox:checked {
        accent-color: gray;
        pointer-events: none;
        user-select: none;
    }
</style>