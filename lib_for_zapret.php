<?php

function main_zapret_function($zapret_delete)
{
    global $DB, $USER;

    //переменная для проверки новых заблокированных курсов для вывода уведомления
    $exis = all_blocked_courses();

    $data_decode = [];

    //какие сейчас курсы заблокированы
    $existing_sql = "SELECT option FROM mdl_options WHERE id_user = :id_user";
    $existing_json = $DB->get_field_sql($existing_sql, ['id_user' => $USER->id]);

    $data_from_db = $existing_json ? json_decode($existing_json, true) : [];

    $blocked_courses_from_db = [];

    //создание массива из сущеcтвующих запретов в data_from_db
    foreach ($data_from_db as $data) {
        if (in_array($data['courseid'], $zapret_delete)) {
            $blocked_courses_from_db[] = $data;
        }
    }

    //проверка на существующий курс в json
    $blocked_courses_from_db = check_for_dublicate_in_table($zapret_delete, $blocked_courses_from_db);

    //получение всех заблокированных курсов из бд и удаление элементов которые не принадлежат текущему юзеру
    $blocked_courses_from_db = delete_from_array_not_belong_to_curr_usr($blocked_courses_from_db);

    $zapret_json_to_db = json_encode($blocked_courses_from_db, JSON_FORCE_OBJECT);
    //проверка на пустой JSON
    if ($zapret_json_to_db == '{}') {
        $zapret_json_to_db = '';
    }

    //добавление пользователя если его нет
    add_user_to_db();

    //обновление в бд новыми заблокированными курсами
    $sql = "UPDATE mdl_options SET option = :option WHERE id_user = :id_user";
    $DB->execute($sql, ['option' => $zapret_json_to_db, 'id_user' => $USER->id]);


    //проверка новых заблокированных курсов для вывода уведомления

    foreach ($exis as $course) {
        $data_decode[] = $course['courseid'];
    }

    $data_decode = array_unique($data_decode);

    //уведомление о новых заблокированных курсах
    foreach ($zapret_delete as $courseid) {
        if (!in_array($courseid, $data_decode)) {
            $chekd_course = get_course($courseid);
            log_blocked_courses($courseid);
            echo html_writer::tag(
                'div',
                "Курс заблокирован для дальнейшего удаления: $chekd_course->fullname ( $chekd_course->shortname )" .
                    html_writer::tag('button', '&times;', [
                        'type' => 'button',
                        'class' => 'close',
                        'data-dismiss' => 'alert',
                        'aria-label' => 'Close'
                    ]),
                ['class' => 'alert alert-info alert-dismissible fade show']
            );
        }
    }
}

function all_blocked_courses()
{
    global $DB;

    $blocked = [];

    //все заблокированные курсы из бд
    $options_from_db = "SELECT id_user, option FROM mdl_options";
    $options_result = $DB->get_records_sql($options_from_db);

    foreach ($options_result as $opt_res) {
        if (!empty($opt_res->option)) {
            $decode = json_decode($opt_res->option, true);
            if (is_array($decode)) {
                $blocked = array_merge($blocked, $decode);
            }
        }
    }

    return $blocked;
}

function check_for_dublicate_in_table($zapret_delete, $blocked_courses_from_db)
{
    global $USER;

    //проверка на существующий курс в json
    foreach ($zapret_delete as $courseid) {
        $course_exists = false;
        foreach ($blocked_courses_from_db as $data) {
            if ($data['courseid'] == $courseid) {
                $course_exists = true;
                break;
            }
        }

        if (!$course_exists) {
            $blocked_courses_from_db[] = [
                'courseid' => $courseid,
                'userid' => $USER->id
            ];
        }
    }

    return $blocked_courses_from_db;
}

function delete_from_array_not_belong_to_curr_usr($blocked_courses_from_db)
{
    global $USER;

    $all_blocked_courses = all_blocked_courses();

    //удаление элемента из массива
    foreach ($blocked_courses_from_db as $key => $upd_data) {
        foreach ($all_blocked_courses as $blocked_course) {
            if ($blocked_course['courseid'] == $upd_data['courseid'] && $blocked_course['userid'] !== $USER->id) {
                unset($blocked_courses_from_db[$key]);
            }
        }
    }
    return $blocked_courses_from_db;
}

function add_user_to_db()
{
    global $DB, $USER;

    $current_user_sql = "SELECT id_user FROM mdl_options";
    $current_user_result = $DB->get_records_sql($current_user_sql);

    $vstavka = true;

    foreach ($current_user_result as $res) {
        if ($res->id_user == $USER->id) {
            $vstavka = false;
        }
    }

    if ($vstavka) {
        $insert = "INSERT INTO mdl_options(id_user) VALUES (:id_user)";
        $DB->execute($insert, ['id_user' => $USER->id]);
    }
}
