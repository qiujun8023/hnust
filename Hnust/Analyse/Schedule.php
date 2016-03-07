<?php

namespace Hnust\Analyse;

use Hnust\Config;
use Hnust\Utils\Mysql;
use Hnust\Utils\Cache;

require_once __DIR__ . '/../../library/PHPExcel.php';

Class Schedule extends \Hnust\Crawler\Schedule
{
    public $error;

    public function login()
    {
        if ($this->logined) {
            return true;
        }

        //随机登陆
        $passwdClass = new Passwd($this->sid);
        $students = $passwdClass->randJwc();
        foreach ($students as $student) {
            $this->sid = $student['sid'];
            $this->passwd = $student['jwc'];
            try {
                return parent::login();
            } catch (\Exception $e) {
                if (Config::RETURN_NEED_PASSWORD === $e->getCode()) {
                    continue;
                }
                throw new \Exception($e->getMessage(), $e->getCode());
            }
        }
    }

    public function weeks($week)
    {
        $result = '000000000000000000000';
        //标记单双周
        if (false !== strpos($week, '单周')) {
            $flag = 1;
        } elseif (false !== strpos($week, '双周')) {
            $flag = 2;
        } else {
            $flag = 0;
        }
        //去除干扰字符
        $week = trim(str_replace(array('(', ')', '单', '双', '周'), '', $week), ',');
        //校验字符串
        if (0 === strlen($week)) {
            throw new Exception("周次有误。");
        } elseif (!preg_match('/^[0-9|,|-]*$/', $week)) {
            throw new Exception("周次无法识别");
        }
        //分割字符串
        $week = explode(',', $week);
        foreach ($week as $item) {
            if (is_numeric($item)) {
                $item = $item . '-' . $item;
            }
            list($start, $end) = sscanf($item, '%d-%d');
            if (($start < 0) || ($end > 20)) {
                throw new \Exception("周次时间有误");
            }
            for ($i = $start; $i <= $end; $i++) {
                $result[$i] = 1;
            }
        }
        //处理单双周
        if (in_array($flag, array(1, 2))) {
            for ($i = $flag / 2; $i < strlen($result); $i += 2) {
                $result[$i] = 0;
            }
        }
        return $result;
    }

    public function type($schedule, $type, $week = -1)
    {
        $type = strlen($type)? (int)$type:0;
        $type = in_array($type, array(0, 1, 2))? $type:0;
        $week = strlen($week)? (int)$week:-1;
        $week = (($week < 0) || ($week > 20))? -1:$week;

        //做标记
        for ($i = 1; $i <= 7; $i++) {
            for ($j = 1; $j <= 5; $j++) {
                for ($k = 0; $k < count($schedule[$i][$j]); $k++) {
                    $item = &$schedule[$i][$j][$k];
                    $item['weeks'] = $this->weeks($item['time']);
                    if ((0 === $type) && (-1 !== $week) && ('0' === $item['weeks'][$week])) {
                        array_splice($schedule[$i][$j], $k--, 1);
                    }
                }
            }
        }

        //返回课表类似 [星期][节次][不同周次课程][课程]{课程信息}
        if (0 === $type) {
            return $schedule;

        //返回课表类似 [周次][星期][节次][课程]{课程信息}
        } elseif (1 === $type) {
            $result = array();
            for ($w = (($week === -1)? 0:$week); $w <= (($week === -1)? 20:$week); $w++) {
                for ($i = 1; $i <= 7; $i++) {
                    for ($j = 1; $j <= 5; $j++) {
                        $result[$w][$i][$j] = array();
                        for ($k = 0; $k < count($schedule[$i][$j]); $k++) {
                            if ('1' === $schedule[$i][$j][$k]['weeks'][$w]) {
                                $result[$w][$i][$j] = $schedule[$i][$j][$k];
                            }
                        }
                    }
                }
            }
            if ($week !== -1) {
                $result = $result[$week];
            }
            $result['remarks'] = $schedule['remarks'];
            return $result;

        //返回课表类似 [所有课程][课程]{课程信息}
        } elseif (2 === $type) {
            $result = array('remarks' => $schedule['remarks']);
            for ($i = 1; $i <= 7; $i++) {
                for ($j = 1; $j <= 5; $j++) {
                    for ($k = 0; $k < count($schedule[$i][$j]); $k++) {
                        $result[] = array_merge($schedule[$i][$j][$k], array(
                            'day'     => $i,
                            'session' => $j
                        ));
                    }
                }
            }
            return $result;
        }
    }

    //计算选修课
    public function getElectiveList($course, $day, $session)
    {
        $start = 0;
        $limit = 5000;
        $term  = Config::getConfig('current_term');
        $result = array();
        while (true) {
            $sql = "SELECT `a`.`sid`, `a`.`name`, `a`.`class`, `b`.`schedule`
                    FROM `student` `a`, `schedule` `b`
                    WHERE `a`.`sid` = `b`.`sid` AND `b`.`term` = ?
                    ORDER BY `a`.`sid` ASC LIMIT {$start}, {$limit}";
            if (!($students = Mysql::execute($sql, array($term)))) {
                break;
            }
            foreach ($students as $student) {
                $schedule = json_decode($student['schedule'], true);
                foreach ($schedule[$day][$session] as $item) {
                    if ($item['course'] === $course) {
                        unset($student['schedule']);
                        $result[] = $student;
                        break;
                    }
                }
            }
            $start += $limit;
        }
        if (empty($result)) {
            throw new \Exception('未找到相关结果', Config::RETURN_ERROR);
        } else {
            return $result;
        }
    }

    //计算空闲时间
    public function getFreeTime($list, $week)
    {
        //获取课表信息
        $param = trim(str_repeat('?, ', count($list)), ' ,');
        $term  = Config::getConfig('current_term');
        $sql   = "SELECT `a`.`sid`, `a`.`name`, `b`.`schedule` FROM `student` `a`, `schedule` `b`
                WHERE `a`.`sid` = `b`.`sid` AND `a`.`sid` IN ({$param}) AND `b`.`term` = ?";
        $students = Mysql::execute($sql, array_merge($list, array($term)));

        //初始化
        $numToZh = array('', '一', '二', '三', '四', '五', '六', '日');
        for ($i = 0; $i < 35; $i++) {
            $day = ($i + 1) / 5 + ((($i + 1) % 5)? 1:0);
            $session = $i % 5 + 1;
            $result[] = array(
                'title' => "星期{$numToZh[$day]} 第{$numToZh[$session]}节大课",
                'list'  => array(),
                'free'  => array()
            );
        }

        //统计上课成员
        $error = $list;
        foreach ($students as $student) {
            unset($error[array_search($student['sid'], $error)]);
            $schedule = json_decode($student['schedule'], true);
            $schedule = $this->type($schedule, 2);
            unset($schedule['remarks']);
            foreach ($schedule as $item) {
                if ('0' == $item['weeks'][$week]) {
                    continue;
                }
                $key = ($item['day'] - 1) * 5 + $item['session'] - 1;
                array_push($result[$key]['list'], array(
                    'sid'  => $student['sid'],
                    'name' => $student['name']
                ));
            }
        }

        return array(
            'error' => $error,
            'data'  => $result
        );
    }

    //通过教室获取上课班级
    public function getCourse($week, $day, $session, $classroom)
    {
        $start = 0;
        $limit = 5000;
        $term = Config::getConfig('current_term');
        while (true) {
            $sql = "SELECT `schedule` FROM `schedule` WHERE `term` = ?
                    ORDER BY `sid` DESC LIMIT {$start}, {$limit}";
            if (!($result = Mysql::execute($sql, array($term)))) {
                break;
            }
            foreach ($result as $item) {
                $item = json_decode($item['schedule'], true);
                foreach ($item[$day][$session] as $course) {
                    try {
                        $weeks = $this->weeks($course['time']);
                    } catch (\Exception $e) {
                        continue;
                    }
                    if ('1' != $weeks[$week]) {
                        continue;
                    }
                    if ($course['classroom'] === $classroom) {
                        return $course;
                    }
                }
            }
            $start += $limit;
        }
        throw new \Exception('未找到相关上课班级', Config::RETURN_ERROR);
    }

    //获取课表
    public function getSchdule($sid, $term, $type = 0, $week = -1)
    {
        $cache = new Cache('schedule');
        $cacheData = $cache->get($sid);
        $cacheData = empty($cacheData)? array():$cacheData;
        if (time() - $cacheData[$term]['time'] <= \Hnust\Config::getConfig('schedule_cache_time')) {
            return $this->type($cacheData[$term]['data'], $type, $week);
        }
        try {
            $schedule = parent::getSchdule($sid, $term);

            //更新课表数据库
            if ($term === Config::getConfig('current_term')) {
                $sql = "INSERT INTO `schedule`(`sid`, `schedule`, `term`, `time`) VALUES(?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE `schedule` = ?, `time` = ?";
                $jsonSchedule = json_encode($schedule, JSON_UNESCAPED_UNICODE);
                Mysql::execute($sql, array($sid, $jsonSchedule, $term, $time, $jsonSchedule, $time));
            }

            //设置缓存
            $cacheData[$term] = array(
                'time' => time(),
                'data' => $schedule
            );
            $cache->set($sid, $cacheData);
        } catch (\Exception $e) {
            if (empty($cacheData[$term]) || ($e->getCode() !== Config::RETURN_ERROR)) {
                throw new \Exception($e->getMessage(), $e->getCode());
            }
            $time = date('Y-m-d H:i:s', $cacheData[$term]['time']);
            $this->error = $e->getMessage() . "\n当前数据更新时间为：" . $time;

            $schedule = $cacheData[$term]['data'];
        }
        return $this->type($schedule, $type, $week);
    }

    //生成EXcel
    public function getExcel($sid, $term)
    {
        $data = $this->getSchdule($sid, $term);

        $objPHPExcel = new \PHPExcel();
        $objPHPExcel->setActiveSheetIndex(0);

        //默认样式
        $objPHPExcel->getActiveSheet()->setTitle('课表');
        $objPHPExcel->getDefaultStyle()->getFont()->setName('宋体');
        $objPHPExcel->getDefaultStyle()->getFont()->setSize(10);
        $objPHPExcel->getDefaultStyle()->getAlignment()->setWrapText(true);
        $objPHPExcel->getDefaultStyle()->getAlignment()->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
        $objPHPExcel->getDefaultStyle()->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);

        $activeSheet = $objPHPExcel->getActiveSheet();

        //总长度及高度
        $excelWidth  = 8;
        $excelHeight = 8;
        $allIndex = 'A1:' . \Hnust\num2alpha($excelWidth) . ($excelHeight);

        //设置边框
        $activeSheet->getStyle($allIndex)->applyFromArray(array(
            'borders' => array(
                'allborders' => array(
                    'style' => \PHPExcel_Style_Border::BORDER_THIN
                ),
            ),
        ));

        //设置行宽
        $activeSheet->getColumnDimension('A')->setWidth(10);
        for ($i = 2; $i <= $excelWidth; $i++) {
            $activeSheet->getColumnDimension(\Hnust\num2alpha($i))->setWidth(20);
        }
        //设置行高
        $activeSheet->getRowDimension('1')->setRowHeight(30);
        for ($i = 3; $i <= ($excelHeight - 1); $i++) {
            $activeSheet->getRowDimension($i)->setRowHeight(80);
        }

        $headline = "湖南科技大学 {$term} 学年学期 {$sid} 个人课表";
        $week     = array('', '星期一', '星期二', '星期三', '星期四', '星期五', '星期六', '星期日');
        $periods  = array('', '第一二节', '第三四节', '第五六节', '第七八节', '第九十节');

        //填充大小标题
        $activeSheet->setCellValue('A1', $headline);
        $activeSheet->getStyle('A1')->getFont()->setBold(true);
        $activeSheet->getStyle('A1')->getFont()->setSize(16);
        $activeSheet->mergeCells('A1:' . \Hnust\num2alpha($excelWidth) . '1');

        //填充课表
        for ($i = 1; $i <= $excelWidth; $i++) {
            for ($j = 2; $j <= ($excelHeight - 1); $j++) {
                $excelIndex = \Hnust\num2alpha($i) . $j;
                //第几节课
                if ($i == 1) {
                    $activeSheet->setCellValue($excelIndex, $periods[$j - 2]);
                    $activeSheet->getStyle($excelIndex)->getFont()->setBold(true);
                //星期几
                } else if ($j == 2) {
                    $activeSheet->setCellValue($excelIndex, $week[$i - 1]);
                    $activeSheet->getStyle($excelIndex)->getFont()->setBold(true);
                //上什么课
                } else {
                    $cellValue = '';
                    $courses = $data[$i - 1][$j - 2];
                    foreach ($courses as $course) {
                        $cellValue .= "{$course['course']}\n{$course['teacher']}\n{$course['time']} {$course['classroom']}";
                        if ($course != end($courses)) {
                            $cellValue .= "\n---------------\n";
                        }
                    }
                    $activeSheet->setCellValue($excelIndex, $cellValue);
                    $activeSheet->getStyle($excelIndex)->getFont()->setSize(9);
                }
            }
        }

        //备注
        $remarks = '备注：' . $data['remarks'] . '; By:Tick网络工作室';
        $markIndex  = 'A' . $excelHeight;
        $marksIndex = $markIndex . ':' . \Hnust\num2alpha($excelWidth) . $excelHeight;
        $activeSheet->setCellValue($markIndex, $remarks);
        $activeSheet->getStyle($markIndex)->getFont()->setSize(8);
        $activeSheet->mergeCells($marksIndex);

        //缓存与下载
        $fileName = $headline . $term . '.xls';
        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
        $objWriter->save(Config::BASE_PATH . Config::TEMP_PATH . '/' . $fileName);
        return Config::TEMP_PATH . '/' . $fileName;
    }
}