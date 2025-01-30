<?php

require('../../config.php');
require('lib.php');
require('class_excel.php');
require('class_table.php');
require('save_in_logs.php');
require('lib_for_zapret.php');

defined('MOODLE_INTERNAL');

require_login();

$PAGE->set_context(context_system::instance());

$plugin_table = new local_courses_how_old_plugin_table();
$plugin_excel = new local_courses_how_old_plugin_excel();

$page = optional_param('page', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$sortby = optional_param('sortby', '', PARAM_ALPHA);
$sortorder = optional_param('sortorder', '', PARAM_ALPHA);
$selectedcourses = optional_param_array('selectedcourses', [], PARAM_INT);
$zapret_delete = optional_param_array('zapret_delete', [], PARAM_INT);
$searchterm = optional_param('searchterm', '', PARAM_RAW_TRIMMED);

$blocked_courses = [];
$checked_courses = [];

$hidepage = get_config('local_courses_how_old_settings', 'hidepage');
$how_many_courses_can_delete = get_config('local_courses_how_old_settings', 'how_many_courses_can_delete');
$enablecheckbox = get_config('local_courses_how_old_settings', 'enablecheckbox');

if ($action === 'export') {
    $plugin_excel->export_courses_to_excel();
    exit;
}

$PAGE->set_url(new moodle_url('/local/courses_how_old/index.php'));
$PAGE->set_title('Удаление курсов');
$PAGE->set_heading('Удаление курсов');

echo $OUTPUT->header();

if ($hidepage && !is_siteadmin()) {
    echo html_writer::tag('div', 'Плагин отключен', ['class' => 'alert alert-danger', 'style' => 'display: flex; justify-content: center']);
    echo html_writer::start_tag('div', ['style' => 'display: flex; justify-content: center']);
    echo html_writer::tag('button', 'Вернуться на главную', [
        'type' => 'button',
        'class' => 'btn btn-primary',
        'onclick' => 'window.location.href = "/"'
    ]);
    echo html_writer::end_tag('div');
    echo $OUTPUT->footer();
    exit;
} else if ($hidepage) {
    echo html_writer::tag('div', "Сейчас плагин доступен только админам <br>Для изменения перейдите в настройки плагина в разделе <br>Плагины->Локальные плагины->Удаление курсов", ['class' => 'alert alert-danger']);
}

echo html_writer::tag('div', "Удаляется не более $how_many_courses_can_delete курсов за раз", ['class' => 'alert alert-danger']);

if ($action === 'delete' && !empty($selectedcourses)) {
    require_sesskey();

    //Стандартная функция чистки мудла
    foreach ($selectedcourses as $courseid) {
        $course = get_course($courseid);

        // Проверка, запрещено ли удаление курса
        if (in_array($courseid, $zapret_delete)) {
            echo html_writer::tag(
                'div',
                'Курс ' . $course->fullname . ' ( ' . $course->shortname . ' ) запрещён для удаления' .
                    html_writer::tag('button', '&times;', [
                        'type' => 'button',
                        'class' => 'close',
                        'data-dismiss' => 'alert',
                        'aria-label' => 'Close'
                    ]),
                ['class' => 'alert alert-warning alert-dismissible fade show']
            );
            continue; // Пропустить удаление этого курса
        }

        //логирование удаления курсов
        log_deleted_courses($courseid);

        delete_course($courseid, false);

        //Вывод сообщения о том что курс удален
        echo html_writer::tag(
            'div',
            'Курс удалён:  ' . $course->fullname . ' ( ' . $course->shortname . ' )' .
                html_writer::tag('button', '&times;', [
                    'type' => 'button',
                    'class' => 'close',
                    'data-dismiss' => 'alert',
                    'aria-label' => 'Close'
                ]),
            ['class' => 'alert alert-info alert-dismissible fade show']
        );
    }

    // Обновление данных после удаления
    $courses_data = $plugin_table->get_paginated_courses($page + 1, 15, $sortby, $sortorder);
}

//все функции в lib_for_zapret.php
if ($action === 'zapret') {
    require_sesskey();

    main_zapret_function($zapret_delete);
}

if (!empty($searchterm)) {
    $courses_data = $plugin_table->search_courses_by_name($searchterm, $page + 1, 15, $sortby, $sortorder);
} else {
    $courses_data = $plugin_table->get_paginated_courses($page + 1, 15, $sortby, $sortorder);
}

//Функция для сортировки
$createSortLink = function ($field, $text) use ($sortby, $sortorder, $PAGE, $page, $searchterm) {
    $newsortorder = ($sortby === $field && $sortorder === 'ASC') ? 'DESC' : 'ASC';

    $url = new moodle_url($PAGE->url, [
        'sortby' => $field,
        'sortorder' => $newsortorder,
        'page' => $page,
        'searchterm' => $searchterm
    ]);

    $arrow = '';

    if ($sortby === $field) {
        $arrow = $sortorder === 'ASC' ? ' ↑' : ' ↓';
    }

    return html_writer::link($url, $text . $arrow);
};


//Заголовок таблицы на странице
$output = html_writer::start_tag('form', [
    'method' => 'post',
    'action' => $PAGE->url,
    'id' => 'coursesform'
]);
$output .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
$output .= html_writer::input_hidden_params($PAGE->url);

//Вывод сообщения о том что нет доступных курсов
if (empty($courses_data['courses'])) {
    $output .= html_writer::tag('div', 'Нет доступных курсов', ['class' => 'alert alert-info']);
} else {
    $blocked_courses = [];

    $blocked = all_blocked_courses();

    foreach ($blocked as $course) {
        $blocked_courses[] = $course['courseid'];
    }

    $blocked_courses = array_unique($blocked_courses);

    $output .= html_writer::start_tag('div', ['class' => 'courses-pagination']);
    $output .= html_writer::start_tag('table', ['class' => 'generaltable']);
    $output .= html_writer::start_tag('thead');
    $output .= html_writer::start_tag('tr');

    if ($enablecheckbox || is_siteadmin()) {
        $output .= html_writer::tag('th', '', ['class' => 'checkbox']);
    }

    $output .= html_writer::tag('th', $createSortLink('id', 'id'));
    $output .= html_writer::tag('th', $createSortLink('fullname', 'Название курса'));
    $output .= html_writer::tag('th', $createSortLink('startdate', 'Дата начала'));
    $output .= html_writer::tag('th', $createSortLink('studentcount', 'Количество студентов'));
    $output .= html_writer::tag('th', $createSortLink('teachercount', 'Количество преподавателей'));
    $output .= html_writer::tag('th', $createSortLink('lastaccess', 'Последний доступ'));
    //$output .= html_writer::tag('th', 'Размер курса (MB)');
    $output .= html_writer::tag('th', $createSortLink('visible', 'Видимость курса'));
    $output .= html_writer::tag('th', 'Запретить удаление', ['class' => 'checkbox']);
    $output .= html_writer::end_tag('tr');
    $output .= html_writer::end_tag('thead');
    $output .= html_writer::start_tag('tbody');

    //Тело таблицы на странице
    foreach ($courses_data['courses'] as $course) {
        $courseurl = new moodle_url('/course/view.php', ['id' => $course->id]);
        $output .= html_writer::start_tag('tr');

        if ($enablecheckbox || is_siteadmin()) {
            $output .= html_writer::tag('td', html_writer::checkbox('selectedcourses[]', $course->id, false), ['class' => 'checkbox']);
        }

        $output .= html_writer::tag('td', $course->id);
        $output .= html_writer::tag('td', html_writer::link($courseurl, format_string($course->fullname)));
        $output .= html_writer::tag('td', userdate($course->startdate, '%d.%m.%Y, %H:%M'));
        $output .= html_writer::tag('td', $course->studentcount);
        $output .= html_writer::tag('td', $course->teachercount);
        $output .= html_writer::tag('td', userdate($course->lastaccess, '%d.%m.%Y, %H:%M'));
        //$output .= html_writer::tag('td', $course->coursesize);
        $output .= html_writer::tag('td', $course->visible ? 'Видимый' : 'Скрытый');

        // Проверяем, заблокирован ли курс, и устанавливаем атрибут checked
        $is_checked = in_array($course->id, $blocked_courses);
        foreach ($blocked as $bld) {
            if ($bld['courseid'] == $course->id && $bld['userid'] !== $USER->id) {
                echo '<script>
                    document.addEventListener("DOMContentLoaded", function() {
                        let checkbox = document.querySelector(\'input[name="zapret_delete[]"][value="' . $course->id . '"]\');
                        if (checkbox) {
                            checkbox.addEventListener("click", (event) => {
                                event.preventDefault();
                                return;
                            });
                            checkbox.classList.add(\'disabled-checkbox\');
                        }
                    });
                </script>';
            }
        }


        $output .= html_writer::tag('td', html_writer::checkbox('zapret_delete[]', $course->id, $is_checked, '',), ['class' => 'checkbox']);

        $output .= html_writer::end_tag('tr');
    }

    $output .= html_writer::end_tag('tbody');
    $output .= html_writer::end_tag('table');

    //Пагинация на странице
    $baseurl = new moodle_url('/local/courses_how_old/index.php', [
        'sortby' => $sortby,
        'sortorder' => $sortorder,
        'page' => $page,
        'searchterm' => $searchterm
    ]);

    $output .= $OUTPUT->paging_bar(
        $courses_data['totalcount'],
        $page,
        $courses_data['perpage'],
        $baseurl
    );

    $output .= html_writer::end_tag('div');

    $output .= delete_courses_ok();
    $output .= delete_courses_error($how_many_courses_can_delete);

    echo $output;

    echo html_writer::tag('button', 'Запретить удаление курсов', [
        'type' => 'submit',
        'name' => 'action',
        'value' => 'zapret',
        'class' => 'btn btn-secondary m-y-1 float-right',
    ]);

    if ($enablecheckbox || is_siteadmin()) {
        //Кнопка для удаления выбранных курсов
        echo html_writer::start_tag('div');
        echo html_writer::tag('button', 'Удалить выбранные курсы', [
            'type' => 'button',
            'class' => 'btn btn-secondary m-y-1',
            'onclick' => "how_many_courses_selected($how_many_courses_can_delete)",
            'style' => 'display: block'
        ]);
    }

    // Форма поиска
    echo html_writer::start_tag('div', ['class' => 'search-container', 'style' => 'display: flex; justify-content: center; align-items: center; margin-top: 20px']);
    echo html_writer::start_tag('form', ['method' => 'get', 'action' => $PAGE->url, 'style' => 'display: flex; align-items: center;']);
    echo html_writer::empty_tag('input', [
        'type' => 'text',
        'name' => 'searchterm',
        'value' => $searchterm,
        'placeholder' => 'Поиск по названию курса',
        'class' => 'form-control',
        'style' => 'width: 30%; margin-right: 10px;'
    ]);
    echo html_writer::tag('button', 'Поиск', ['type' => 'submit', 'class' => 'btn btn-primary']);
    echo html_writer::end_tag('form');
    echo html_writer::end_tag('div');

    //Экспорт в эксель
    if (is_siteadmin()) {
        echo html_writer::tag('button', 'Экспорт в эксель', [
            'type' => 'submit',
            'name' => 'action',
            'value' => 'export',
            'class' => 'btn btn-secondary m-y-1',
            'style' => 'display: block'
        ]);
    }

    echo html_writer::end_tag('div');
}

echo html_writer::end_tag('form');

if (is_siteadmin()) {
    echo 'Это сообщение видно только админам <br>Логи удаления можно посмотреть по /local/courses_how_old/logs/deletion_log.txt <br>Логи заблокированных курсов можно посмотреть по /local/courses_how_old/logs/blocked_log.txt';
}



echo $OUTPUT->footer();
