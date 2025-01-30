<?php

//Добавление в навигацию слева
//Смотрит по ролям в категории показывать ли кнопку
function local_courses_how_old_extend_navigation(global_navigation $navigation)
{
    global $DB, $USER;
    $showmenu = false;

    $enablebutton = get_config('local_courses_how_old_settings', 'enablebutton');



    if (is_siteadmin()) {
        $showmenu = true;
    } else {
        if ($enablebutton) {
            $is_category_manager = $DB->record_exists_sql(
                "SELECT 1 
                FROM {role_assignments} ra
                JOIN {context} ctx ON ra.contextid = ctx.id
                JOIN {role} r ON ra.roleid = r.id
                WHERE ra.userid = :userid 
                AND ctx.contextlevel = :contextlevel
                AND r.shortname IN ('manager', 'moderator', 'coursecreator')",
                ['userid' => $USER->id, 'contextlevel' => CONTEXT_COURSECAT]
            );

            if ($is_category_manager) {
                $showmenu = true;
            }
        }
    }

    if ($showmenu) {
        $node = navigation_node::create('Удаление курсов', new moodle_url('/local/courses_how_old/index.php'), navigation_node::NODETYPE_BRANCH, null, 'courses_how_old');
        $node->showinflatnavigation = true;
        $node->icon = null;
        $navigation->add_node($node, null, 'addnewcourse');
    }
}
