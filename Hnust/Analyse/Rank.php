<?php

namespace Hnust\Analyse;

use Hnust\Config;
use Hnust\Utils\Mysql;

require_once __DIR__ . '/../../library/PHPExcel.php';

Class Rank
{
    public $sid;
    public $class;
    public $major;
    public $grade;
    public $school;
    public $title;
    public $terms;
    public $courses;
    public $rankName;

    public function __construct($sid)
    {
        $this->sid = $sid;
    }

    public function getRank($term, $scope, $by)
    {
        //获取学生信息
        $sql = 'SELECT `class`, `major`, `grade`, `school` FROM `student` WHERE `sid` = ? LIMIT 1';
        $result = Mysql::execute($sql, array($this->sid));
        $this->class  = $result[0]['class'];
        $this->major  = $result[0]['major'];
        $this->grade  = $result[0]['grade'];
        $this->school = $result[0]['school'];

        //sql语句及sql数组
        if ('class' === $scope) {
            $sql = "SELECT `a`.`sid`, `a`.`name`, `a`.`class`, `a`.`major`,
                      IF(`b`.`score` IS NULL, '[]', `b`.`score`) `score`
                    FROM `student` `a`
                    LEFT JOIN `score` `b` ON `a`.`sid` = `b`.`sid`
                    WHERE `a`.`class` = ?";
            $students = Mysql::execute($sql, array($this->class));
            $this->title = $this->class . $term . (($by == 'term')? '学期排名':'学年排名');
            $this->rankName = $this->class;
        } else {
            $sql = "SELECT `a`.`sid`, `a`.`name`, `a`.`class`, `a`.`major`,
                      IF(`b`.`score` IS NULL, '[]', `b`.`score`) `score`
                    FROM `student` `a`
                    LEFT JOIN `score` `b` ON `a`.`sid` = `b`.`sid`
                    WHERE `a`.`major` = ? AND `a`.`grade` = ? AND `a`.`school` = ?";
            $students = Mysql::execute($sql, array($this->major, $this->grade, $this->school));
            $this->title = $this->major . $term . (($by == 'term')? '学期排名':'学年排名');
            $this->rankName = $this->major;
        }

        //选择要排名的学期及成绩
        $this->terms = array();
        $rankCourse  = array();

        for ($i = 0; $i < count($students); $i++) {
            $score = @json_decode($students[$i]['score'], true);
            $rankScore = array();
            //统计学期和获取待排名成绩
            foreach ($score as $scoreTerm => $termScore) {
                //区分学期排名与学年排名
                $tempTerm = ($by != 'term')? substr($scoreTerm, 0, 9):$scoreTerm;
                //统计学期
                for ($j = 0; $j < count($this->terms); $j++) {
                    if ($this->terms[$j] == $tempTerm) {
                        break;
                    }
                }
                if ($j == count($this->terms)) {
                    $this->terms[$j] = $tempTerm;
                }
                //去除补考
                for ($j = 0; $j < count($score[$scoreTerm]); $j++) {
                    if ($score[$scoreTerm][$j]['resit'] == true) {
                        array_splice($score[$scoreTerm], $j--, 1);
                    }
                }
                //获取待排名成绩
                if ($term == $tempTerm) {
                    $rankScore = array_merge($rankScore, $score[$scoreTerm]);
                }
            }
            //遍历待排名所有成绩
            for ($j = 0; $j < count($rankScore); $j++) {
                //统计科目
                for ($k = 0; $k < count($rankCourse); $k++) {
                    if ($rankScore[$j]['course'] == $rankCourse[$k]['course']) {
                        break;
                    }
                }
                if (($k == count($rankCourse)) && !empty($rankScore[$j]['credit'])) {
                    $rankCourse[$k] = array(
                        'course' => $rankScore[$j]['course'],
                        'mode'   => $rankScore[$j]['mode'],
                        'credit' => $rankScore[$j]['credit'],
                        'count'  => 1
                    );
                } elseif ($k != count($rankCourse)) {
                    $rankCourse[$k]['count']++;
                }
                //遍历其他成绩
                foreach ($score as $termScore) {
                    for ($k = 0; $k < count($termScore); $k++) {
                        //取科目最高分
                        if ($rankScore[$j]['course'] == $termScore[$k]['course'] && \Hnust\scoreCompare($rankScore[$j]['mark'], $termScore[$k]['mark'])) {
                            $rankScore[$j]['mark'] = $termScore[$k]['mark'];
                        }
                    }
                }
            }
            $students[$i]['rankScore'] = $rankScore;
        }

        //删除选修等科目
        for ($i = 0; $i < count($rankCourse); $i++) {
            if ($rankCourse[$i]['count'] < count($students) * 0.8) {
                array_splice($rankCourse, $i--, 1);
            }
        }

        //课程全部存入info数组
        $this->courses = array();
        for ($i = 0; $i < count($rankCourse); $i++) {
            $this->courses[$i] = $rankCourse[$i]['course'];
        }

        //计算
        for ($i = 0; $i < count($students); $i++) {
            $courseMark = array();
            $rankScore = $students[$i]['rankScore'];
            $countFail = $countMark = $countCredit = $totalCredit = $countGpa = $countPoint = 0;
            for ($j = 0; $j < count($rankCourse); $j++) {
                //获取单科分数
                $courseMark[$j] = 0;
                for ($k = 0; $k < count($rankScore); $k++) {
                    if ($rankCourse[$j]['course'] == $rankScore[$k]['course'] && \Hnust\scoreCompare($courseMark[$j], $rankScore[$k]['mark'])) {
                        $courseMark[$j] = $rankScore[$k]['mark'];
                    }
                }
                //将等级制转换为分数
                $tempMark = (float) str_replace(array('优', '良', '中', '及格', '不及格'), array(95.02, 84.02, 74.02, 60.02, 0), $courseMark[$j]);
                //分数转化为对应绩点
                $pointArray = array(90 => 4, 85 => 3.7, 82 => 3.3, 78 => 3.0, 75 => 2.7, 71 => 2.3, 66 => 2.0, 62 => 1.5, 60 => 1, 0  => 0);
                foreach ($pointArray as $mark => $point) {
                    if ($mark <= $tempMark) {
                        $tempPoint = $point;
                        break;
                    }
                }

                $countFail   += ($tempMark >= 60) ? 0 : 1;
                $countMark   += $tempMark;
                $countCredit += ($tempMark >= 60) ? $rankCourse[$j]['credit'] : 0;
                $totalCredit += $rankCourse[$j]['credit'];
                $countGpa    += $tempMark * $rankCourse[$j]['credit'];
                $countPoint  += $tempPoint * $rankCourse[$j]['credit'];
            }
            $avgMark     = count($rankCourse) ? round($countMark / count($rankCourse), 2) : 0;
            $countMark   = round($countMark, 2);
            $countCredit = round($countCredit, 2);
            $avgGpa      = $totalCredit ? round($countGpa / $totalCredit, 2) : 0;
            $avgPoint    = $totalCredit ? round($countPoint / $totalCredit, 2) : 0;

            $rank[] = array(
                'name'        => $students[$i]['name'],
                'sid'         => $students[$i]['sid'],
                'course'      => $courseMark,
                'countFail'   => $countFail,
                'avgMark'     => $avgMark,
                'countMark'   => $countMark,
                'countCredit' => $countCredit,
                'avgGpa'      => $avgGpa,
                'avgPoint'    => $avgPoint
            );
        }
        //名次排序
        usort($rank, function($a, $b) {
            $re = ($a['avgGpa'] == $b['avgGpa'])? ($a['sid'] < $b['sid']):($a['avgGpa'] > $b['avgGpa']);
            return $re ? -1 : 1;
        });
        for ($i = 0; $i < count($rank); $i++) {
            $rank[$i]['rank'] = ($i + 1);
        }
        return $rank;
    }

    public function getExcel($uid, $term, $scope, $by)
    {
        //获取排名情况
        $rank = $this->getRank($term, $scope, $by);

        $objPHPExcel = new \PHPExcel();
        $objPHPExcel->setActiveSheetIndex(0);

        //默认样式
        $objPHPExcel->getActiveSheet()->setTitle($this->title);
        $objPHPExcel->getDefaultStyle()->getFont()->setName('宋体');
        $objPHPExcel->getDefaultStyle()->getFont()->setSize(10);
        $objPHPExcel->getDefaultStyle()->getAlignment()->setWrapText(true);
        $objPHPExcel->getDefaultStyle()->getAlignment()->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
        $objPHPExcel->getDefaultStyle()->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);

        $activeSheet = $objPHPExcel->getActiveSheet();

        //总长度及高度
        $excelWidth  = count($this->courses) + 10 ;
        $excelHeight = count($rank) + 2;
        $allIndex = 'A1:' . \Hnust\num2alpha($excelWidth) . ($excelHeight);

        //设置边框
        $activeSheet->getStyle($allIndex)->applyFromArray(array(
            'borders' => array(
                'allborders' => array(
                    'style' => \PHPExcel_Style_Border::BORDER_THIN
                ),
            ),
        ));

        //第一行
        $col = array_merge(
            array('序号', '姓名', '学号'),
            $this->courses,
            array('科目数', '不及格门数', '平均分', '总分', '所得学分', '平均学分绩', '平均学分绩点')
        );
        for ($i = 0; $i < count($col); $i++) {
            $excelIndex = \Hnust\num2alpha(($i + 1)).'1';
            $activeSheet->setCellValue($excelIndex, $col[$i]);
            $activeSheet->getStyle($excelIndex)->getFill()->setFillType(\PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setARGB('00C5CFCA');
            $activeSheet->getColumnDimension('B')->setWidth(10);
            if(($i != 0) && ($i != 1)) {
                $activeSheet->getColumnDimension(\Hnust\num2alpha(($i + 1)))->setWidth(15);
            }
        }
        $activeSheet->getRowDimension('1')->setRowHeight(25);
        $activeSheet->freezePane('A2');

        //输出所有成绩
        for($i = 0; $i < count($rank); $i++) {
            $col = array_merge(
                array(($i + 1), $rank[$i]['name'], $rank[$i]['sid']),
                $rank[$i]['course'],
                array(count($rank[$i]['course']), $rank[$i]['countFail'], $rank[$i]['avgMark'], $rank[$i]['countMark'], $rank[$i]['countCredit'], $rank[$i]['avgGpa'], $rank[$i]['avgPoint'])
            );
            for ($j = 0; $j < count($col); $j++) {
                $excelIndex = \Hnust\num2alpha(($j + 1)).($i + 2);
                $activeSheet->setCellValue($excelIndex, $col[$j]);
                //单双行背景
                if (($i % 2) == 0) {
                    $activeSheet->getStyle($excelIndex)->getFill()->setFillType(\PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setARGB('00eeeeee');
                }
                if (($j > 2) && ($j < (count($rank[$i]['course']) + 3))) {
                    if ((is_numeric($col[$j]) && $col[$j] < 60) || ($col[$j] == '不及格')) {
                        $activeSheet->getStyle($excelIndex)->getFont()->getColor()->setARGB(\PHPExcel_Style_Color::COLOR_RED);
                    }
                }
            }
        }

        //备注
        $markIndex  = 'A' . $excelHeight;
        $marksIndex = $markIndex . ':' . \Hnust\num2alpha($excelWidth) . $excelHeight;
        $content    = '    注：此排名为第三方统计，不代表学校最后统计结果；此统计仅供参考。By:Tick网络工作室';
        $activeSheet->setCellValue($markIndex, $content);
        $activeSheet->mergeCells($marksIndex);
        $activeSheet->getStyle($marksIndex)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_LEFT);

        //缓存与下载
        $download  = new Download($uid);
        $fileName  = $this->title . '.xls';
        $fileInfo  = $download->set($fileName);
        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
        $objWriter->save($fileInfo['path']);
        $download->rewrite($fileInfo['rand']);
    }
}