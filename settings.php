<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {

    $ADMIN->add('localplugins', new admin_externalpage('local_courses_how_old',get_string('pluginname', 'local_courses_how_old'), new moodle_url('/local/courses_how_old/index.php')));

    $settings = new admin_settingpage(
        'local_courses_how_old_settings',
        'Настройки плагина удаления курсов'
    );

    $settings->add(new admin_setting_configcheckbox(
        'local_courses_how_old_settings/enablebutton',
        "Включить кнопку удаления курсов",
        "Включение кнопки удаления курсов в левом меню навигации",
        false, //по умолчанию
        PARAM_BOOL
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_courses_how_old_settings/enablecheckbox',
        "Включить чекбоксы удаления",
        "Включение/выключение чекбоксов удаления курсов",
        false, //по умолчанию
        PARAM_BOOL
    ));

    $settings->add(new admin_setting_configtext(
        'local_courses_how_old_settings/how_many_courses_can_delete',
        "Сколько курсов можно удалить за раз",
        "",
        3, //по умолчанию
        PARAM_INT
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_courses_how_old_settings/hidepage',
        "Отключить страницу",
        "Отключить страницу удаления курсов по /courses_how_old/index.php<br>Включать параметр только если плагин больше не нужен, чтобы нельзя было зайти на страницу",
        false, //по умолчанию
        PARAM_BOOL
    ));

	$ADMIN->add('localplugins', $settings);
    
}

