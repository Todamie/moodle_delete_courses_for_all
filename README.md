Плагин размещается в local/  
sudo chown -R www-data:www-data /var/www/moodle/local/courses_how_old/logs - чтобы был доступ к записи логов в папку

Надо почистить файл логов /var/www/moodle/local/courses_how_old/logs/deletion_log.txt

Создание таблицы для работы с блокировками курсов в плагине:  
CREATE TABLE mdl_options (  
id INT(11) AUTO_INCREMENT PRIMARY KEY,  
id_user INT(11),  
\`option\` TEXT  
);  

/var/www/moodle/lib/filestorage/file_storage.php - где хранится cron задача. Нужно удалить условие:
if (empty($CFG->fileslastcleanup) or $CFG->fileslastcleanup < time() - 60*60*24) {
Так как зачастую он просто выдает false и не чистит

Сама крон задача:
/var/www/moodle/lib/classes/task/file_trash_cleanup_task.php

Альтернативно есть функция runtrash.php внутри плагина. Она просто перекопирована оттуда и можно вручную запускать её:
sudo -u www-data php /var/www/moodle/local/courses_how_old/runtrash.php

Коротко по файлам:
add_to_nav.php - добавление в навигацию левого меню

class_excel.php/class_table.php - функции отображения в таблице на странице и выгрузки в эксель

lib_for_zapret.php - все функции для работы с запрещёнными для удаления курсами

lib.php - функции, необходимые для работы class_excel и class_table. Также содержит модалки для удаления курсов, включая код для js. Также есть css для того чтобы придавать галочкам серый некликабельный вид

runtrash.php - описана выше, ручной запуск фукнции очиски

save_in_logs.php - запись логов при удалении курса и при блокировке
