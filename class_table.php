<?php

//require('lib.php');

//TODO 
// сделать чтобы преводилось в дату по столбам последнего посещения и даты создания
//сортировка слетает после перехода по пагинации

class local_courses_how_old_plugin_table
{
    public $functions_lib;
    //id ролей в бд
    public $studentroleid = 5;
    public $teacherroleid = 3;

    public function __construct()
    {
        $this->functions_lib = new local_courses_how_old_plugin();
    }

    public function get_paginated_courses($page = 1, $perpage = 15, $sortby = 'startdate', $sortorder = 'ASC')
    {
        global $DB, $USER;

        $offset = ($page - 1) * $perpage;

        //Список курсов в зависимости от роли:
        $basesql_and_params = $this->functions_lib->get_basesql_and_params();
        $basesql = $basesql_and_params[0];
        $params = $basesql_and_params[1];
        $is_category_manager = $basesql_and_params[2];

        $courses = $this->functions_lib->sort_by_additional_fields($sortby, $sortorder, $basesql, $params, $offset, $perpage);

        // Получаем общее количество курсов, для пагинации
        if (is_siteadmin()) {

            //Для админов
            $totalcount = $DB->count_records('course');

        } elseif ($is_category_manager) {

            //Для управляющих в категориях
            $totalcount = $DB->count_records_sql(
                "SELECT COUNT(DISTINCT c.id)
                 FROM {course} c
                 JOIN {course_categories} cc_course ON c.category = cc_course.id
                 JOIN {course_categories} cc_access ON cc_course.path LIKE CONCAT(cc_access.path, '/%')
                 OR cc_course.path = cc_access.path
                 JOIN {context} ctxcat ON ctxcat.instanceid = cc_access.id 
                 AND ctxcat.contextlevel = " . CONTEXT_COURSECAT . "
                 JOIN {role_assignments} ra ON ra.contextid = ctxcat.id
                 WHERE ra.userid = :userid 
                 AND ra.roleid IN (SELECT id FROM {role} 
                 WHERE shortname IN ('manager', 'moderator'))",
                ['userid' => $USER->id]
            );

        } else {

            //Для преподавателей
            $totalcount = $DB->count_records_sql(
                "SELECT COUNT(DISTINCT c.id) 
                 FROM {course} c
                 JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = 50
                 JOIN {role_assignments} ra ON ra.contextid = ctx.id
                 WHERE ra.userid = :userid",
                ['userid' => $USER->id]
            );

        }

        // Отброс повторного запроса в бд
        $this->add_additional_course_data($courses);

        return [
            'courses' => $courses,
            'totalcount' => $totalcount,
            'perpage' => $perpage,
            'currentpage' => $page - 1
        ];
    }

    public function search_courses_by_name($searchterm, $page = 1, $perpage = 15, $sortby = 'startdate', $sortorder = 'ASC')
    {
        global $DB, $USER;

        $offset = ($page - 1) * $perpage;
        $searchterm = '%' . $DB->sql_like_escape($searchterm) . '%';

        // Определяем SQL-запрос в зависимости от роли пользователя
        $basesql_and_params = $this->functions_lib->get_basesql_and_params_with_search($searchterm);
        $basesql = $basesql_and_params[0];
        $params = $basesql_and_params[1];

        // Сортировка
        $courses = $this->functions_lib->sort_by_additional_fields($sortby, $sortorder, $basesql, $params, $offset, $perpage);

        if (empty($courses)) {
            echo html_writer::start_tag('div');
            echo html_writer::tag('div', 'Результатов не найдено', [
                'class' => 'alert alert-warning', 
                'style' => 'font-size: 20px; margin-bottom: 20px; display: flex; justify-content: center'
            ]);
            echo html_writer::start_tag('div', ['style' => 'display: flex; justify-content: center']);
            echo html_writer::tag('button', 'Вернуться назад', [
                'type' => 'button',
                'class' => 'btn btn-primary',
                'onclick' => 'window.location.href = "/local/courses_how_old/index.php"'
            ]);
            echo html_writer::end_tag('div');
            echo html_writer::end_tag('div');
        } else {

            // Получаем общее количество курсов, для пагинации
            $totalcount = $DB->count_records_sql(
                "SELECT COUNT(1)
             FROM ({$basesql}) subquery",
                $params
            );

            // Отброс повторного запроса в бд и получение доп полей (студенты, преподаватели, последний доступ)
            $this->add_additional_course_data($courses);

            return [
                'courses' => $courses,
                'totalcount' => $totalcount,
                'perpage' => $perpage,
                'currentpage' => $page - 1
            ];
        }
    }

    // Отброс повторного запроса в бд и получение доп полей (студенты, преподаватели, последний доступ)
    private function add_additional_course_data(&$courses)
    {
        foreach ($courses as $course) {
            $context = context_course::instance($course->id);

            if (!isset($course->studentcount)) {
                $course->studentcount = $this->functions_lib->get_user_count_from_db($context, $this->studentroleid);
            }

            if (!isset($course->teachercount)) {
                $course->teachercount = $this->functions_lib->get_user_count_from_db($context, $this->teacherroleid);
            }

            if (!isset($course->lastaccess)) {
                $course->lastaccess = $this->functions_lib->get_time_last_access_from_db($course);
            }

            //$course->coursesize = $this->get_course_size_in_mb($course);
        }
    }
}
