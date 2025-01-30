<?php

//Команда для запуска
//sudo -u www-data php /var/www/moodle/local/courses_how_old/runtrash.php

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/filestorage/file_storage.php');



//Украдено из /lib/filestorage/file_storage.php функция cron()
//Можно запускать вручную, изначально задача \core\task\file_trash_cleanup_task (lib/classes/task/file_trash_cleanup_task.php)


global $CFG, $DB;
require_once($CFG->libdir . '/cronlib.php');
$file_storage_object = new file_storage();

$count_deleted_draft = 0;
$count_deleted_preview = 0;
$count_deleted_documentconversion = 0;
$count_deleted_files = 0;
// find out all stale draft areas (older than 4 days) and purge them
// those are identified by time stamp of the /. root dir
mtrace('Удаление старых draft файлов (старше 4 дней)... ');
//cron_trace_time_and_memory();
$old = time() - 60 * 60 * 24 * 4;
$sql = "SELECT *
              FROM {files}
             WHERE component = 'user' AND filearea = 'draft' AND filepath = '/' 
                   AND timecreated < :old";
$rs = $DB->get_recordset_sql($sql, array('old' => $old));
foreach ($rs as $dir) {
    mtrace("Удаление: itemid = " . $dir->itemid);
    $file_storage_object->delete_area_files($dir->contextid, $dir->component, $dir->filearea, $dir->itemid);
    $count_deleted_draft++;
}
$rs->close();
mtrace('Удалено: ' . $count_deleted_draft . ' файлов draft');
//mtrace('done.');

// remove orphaned preview files (that is files in the core preview filearea without
// the existing original file)
mtrace('Удаление старых файлов preview... ');
//cron_trace_time_and_memory();
$sql = "SELECT p.*
              FROM {files} p
         LEFT JOIN {files} o ON (p.filename = o.contenthash)
             WHERE p.contextid = ? AND p.component = 'core' AND p.filearea = 'preview' AND p.itemid = 0
                   AND o.id IS NULL";
$syscontext = context_system::instance();
$rs = $DB->get_recordset_sql($sql, array($syscontext->id));
foreach ($rs as $orphan) {
    $file = $file_storage_object->get_file_instance($orphan);
    if (!$file->is_directory()) {
        $file->delete();
        $count_deleted_preview++;
    }
}
$rs->close();
//mtrace('done.');
mtrace('Удалено: ' . $count_deleted_preview . ' файлов preview');

// Remove orphaned converted files (that is files in the core documentconversion filearea without
// the existing original file).
mtrace('Удаление старых document conversion files... ');
//cron_trace_time_and_memory();
$sql = "SELECT p.*
              FROM {files} p
         LEFT JOIN {files} o ON (p.filename = o.contenthash)
             WHERE p.contextid = ? AND p.component = 'core' AND p.filearea = 'documentconversion' AND p.itemid = 0
                   AND o.id IS NULL";
$syscontext = context_system::instance();
$rs = $DB->get_recordset_sql($sql, array($syscontext->id));
foreach ($rs as $orphan) {
    $file = $file_storage_object->get_file_instance($orphan);
    if (!$file->is_directory()) {
        $file->delete();
        $count_deleted_documentconversion++;
    }
}
$rs->close();
//mtrace('done.');
mtrace('Удалено: ' . $count_deleted_documentconversion . ' файлов documentconversion');

// remove trash pool files once a day
// if you want to disable purging of trash put $CFG->fileslastcleanup=time(); into config.php
//if (empty($CFG->fileslastcleanup) or $CFG->fileslastcleanup < time() - 60*60*24) {
require_once($CFG->libdir . '/filelib.php');
// Delete files that are associated with a context that no longer exists.
mtrace('Удаление файлов из системы... ');
//cron_trace_time_and_memory();
$sql = "SELECT DISTINCT f.contextid
                FROM {files} f
                LEFT OUTER JOIN {context} c ON f.contextid = c.id
                WHERE c.id IS NULL";
$rs = $DB->get_recordset_sql($sql);
if ($rs->valid()) {
    $fs = get_file_storage();
    foreach ($rs as $ctx) {
        $fs->delete_area_files($ctx->contextid);
        $count_deleted_files++;
    }
}
$rs->close();
//mtrace('done.');
mtrace('Удалено: ' . $count_deleted_files . ' файлов из системы');

mtrace('Call filesystem cron tasks.', '');
cron_trace_time_and_memory();
$file_storage_object->get_file_system()->cron();
mtrace('done.');
//}

echo "Очистка завершена.\n";
