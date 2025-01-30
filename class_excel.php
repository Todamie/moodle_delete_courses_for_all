<?php

require_once('lib.php');

class local_courses_how_old_plugin_excel
{
    public $lib;

    //id ролей в бд
    public $studentroleid = 5;
    public $teacherroleid = 3;

    public function __construct()
    {
        $this->lib = new local_courses_how_old_plugin();
    }

    public function export_courses_to_excel()
    {
        global $DB, $CFG;
        //Вместо startdate можно timecreated
        $courses = $DB->get_records('course', null, 'startdate ASC');

        require_once($CFG->libdir . '/excellib.class.php');
        //Имя файла
        $filename = 'courses_list_' . date('d-m-Y_H-i') . '.xlsx';

        $workbook = new MoodleExcelWorkbook('-');
        $workbook->send($filename);

        $sheet = $workbook->add_worksheet('Courses');

        //Полный формат даты, не работает
        $dateformat = $workbook->add_format();
        //$dateformat->set_num_format('dd/mm/yyyy');

        //Заголовки таблицы
        $sheet->write(0, 0, 'ID');
        $sheet->write(0, 1, 'Название курса');
        $sheet->write(0, 2, 'Сокращенное название');
        $sheet->write(0, 3, 'Дата начала');
        $sheet->write(0, 4, 'Количество студентов');
        $sheet->write(0, 5, 'Количество преподавателей');
        $sheet->write(0, 6, 'Последний доступ');
        //$sheet->write(0, 7, 'Размер курса (MB)');
        $sheet->write(0, 7, 'Видимость курса');
        $sheet->write(0, 8, 'Категория');
        $sheet->write(0, 9, 'Список преподавателей');
        $row = 1;

        foreach ($courses as $course) {
            //отладка, выгрузка только 500 курсов из списка
            if ($row > 10) {
                break;
            }

            $category = core_course_category::get($course->category, IGNORE_MISSING);
            $categoryname = $category ? $this->lib->get_full_category_path($category) : '';

            $context = context_course::instance($course->id);

            $studentcount = $this->lib->get_user_count_from_db($context, $this->lib->studentroleid);
            $teachercount = $this->lib->get_user_count_from_db($context, $this->lib->teacherroleid);
            $lastaccess = $this->lib->get_time_last_access_from_db($course);
            //$coursesize = $this->lib->get_course_size_in_mb($course);
            $visibility = $course->visible ? 'Видимый' : 'Скрытый';


            //Получение списка преподавателей в курсе, если раскомментить, то только где нет студентов
            //if ($studentcount == 0) {
            $teacher_list_from_db = $this->lib->get_all_users_from_course_db($context, $this->lib->teacherroleid);
            $teacher_list = '';
            foreach ($teacher_list_from_db as $teacher) {
                $teacher_list .= $teacher->lastname . ' ' . $teacher->firstname . ', ';
            }
            $teacher_list = rtrim($teacher_list, ', ');
            /*} else {
                $teacher_list = '';
            }*/

            //Заполнение полей таблицы
            $sheet->write($row, 0, $course->id);
            $sheet->write($row, 1, $course->fullname);
            $sheet->write($row, 2, $course->shortname);
            $sheet->write_date($row, 3, $course->startdate, $dateformat);
            $sheet->write($row, 4, $studentcount);
            $sheet->write($row, 5, $teachercount);
            if ($lastaccess != 'Никогда') {
                $sheet->write_date($row, 6, $lastaccess, $dateformat);
            }
            $sheet->write($row, 7, $visibility);
            $sheet->write($row, 8, $categoryname);
            $sheet->write($row, 9, $teacher_list);
            $row++;
        }

        $workbook->close();
    }
}
