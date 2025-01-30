<?php

//логирование удаления курсов
function log_deleted_courses($courses)
{
    global $USER, $CFG;

    try {

        $log_dir = $CFG->dirroot . '/local/courses_how_old/logs';
        $logfile = $log_dir . '/deletion_log.txt';

        if (!file_exists($log_dir)) {
            if (!mkdir($log_dir, 0755, true)) {
                var_dump('Не удалось создать директорию для логов: ' . $log_dir);
                die();
                return false;
            }
        }

        if (!is_writable($log_dir)) {
            if (!chmod($log_dir, 0755)) {
                var_dump('Нет прав на запись в директорию логов: ' . $log_dir);
                die();
                return false;
            }
        }

        $timestamp = date('d.m.Y H:i:s');
        $user_fullname = fullname($USER);

        $log_message = "[$timestamp] Пользователь: $user_fullname ($USER->id)\n";
        $log_message .= "Удаленный курс:\n";

        /*foreach ($courses as $course) {
            $course_info = get_course($course);
            $log_message .= "- ID: {$course_info->id}, Название: {$course_info->fullname}\n";
        }*/

        $course_info = get_course($courses);

        $log_message .= "- ID: {$course_info->id}, Название: {$course_info->fullname}\n";

        $log_message .= "----------------------------------------\n";

        if (file_put_contents($logfile, $log_message, FILE_APPEND | LOCK_EX) === false) {
            debugging('Не удалось записать в файл логов: ' . $logfile);
            return false;
        }
    } catch(Exception $e) {
        redirect($CFG->wwwroot . '/local/courses_how_old/index.php');
        return false;
    }

    return true;
}

function log_blocked_courses($courses)
{
    global $USER, $CFG;

    try {

        $log_dir = $CFG->dirroot . '/local/courses_how_old/logs';
        $logfile = $log_dir . '/blocked_log.txt';

        if (!file_exists($log_dir)) {
            if (!mkdir($log_dir, 0755, true)) {
                var_dump('Не удалось создать директорию для логов: ' . $log_dir);
                die();
                return false;
            }
        }

        if (!is_writable($log_dir)) {
            if (!chmod($log_dir, 0755)) {
                var_dump('Нет прав на запись в директорию логов: ' . $log_dir);
                die();
                return false;
            }
        }

        $timestamp = date('d.m.Y H:i:s');
        $user_fullname = fullname($USER);

        $log_message = "[$timestamp] Пользователь: $user_fullname ($USER->id)\n";
        $log_message .= "Заблокированный курс:\n";

        /*foreach ($courses as $course) {
            $course_info = get_course($course);
            $log_message .= "- ID: {$course_info->id}, Название: {$course_info->fullname}\n";
        }*/

        $course_info = get_course($courses);

        $log_message .= "- ID: {$course_info->id}, Название: {$course_info->fullname}\n";

        $log_message .= "----------------------------------------\n";

        if (file_put_contents($logfile, $log_message, FILE_APPEND | LOCK_EX) === false) {
            debugging('Не удалось записать в файл логов: ' . $logfile);
            return false;
        }
    } catch(Exception $e) {
        redirect($CFG->wwwroot . '/local/courses_how_old/index.php');
        return false;
    }

    return true;
}
