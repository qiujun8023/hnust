<?php

namespace Hnust\Analyse;

use Hnust\Config;
use Hnust\Utils\Http;
use Hnust\Utils\Mysql;

Class ScoreAll
{
    public $sid;
    public $class;
    public $major;
    public $grade;
    public $school;
    public $credit;
    public $scoreName;

    public function __construct($sid)
    {
        $this->sid = $sid;
    }

    public function scoreAll($scope, $course, $re = true)
    {
        if (empty($this->sid) || empty($course)) {
            throw new \Exception('参数有误', Config::RETURN_ERROR);
        }

        //获取学生信息
        $sql = 'SELECT `class`, `major`, `grade`, `school` FROM `student` WHERE `sid` = ? LIMIT 1';
        $result = Mysql::execute($sql, array($this->sid));
        $this->class  = $result[0]['class'];
        $this->major  = $result[0]['major'];
        $this->grade  = $result[0]['grade'];
        $this->school = $result[0]['school'];

        //获取所有学生列表
        if ('class' === $scope) {
            $sql = "SELECT `a`.`sid`, `a`.`name`, `a`.`idcard`,
                      IF(`b`.`score` IS NULL, '[]', `b`.`score`) `score`
                    FROM `student` `a`
                    LEFT JOIN `score` `b` ON `a`.`sid` = `b`.`sid`
                    WHERE `a`.`class` = ?";
            $students = Mysql::execute($sql, array($this->class));
            $this->scoreName = $this->class;
        } else {
            $sql = "SELECT `a`.`sid`, `a`.`name`, `a`.`idcard`,
                      IF(`b`.`score` IS NULL, '[]', `b`.`score`) `score`
                    FROM `student` `a`
                    LEFT JOIN `score` `b` ON `a`.`sid` = `b`.`sid`
                    WHERE `a`.`major` = ? AND `a`.`grade` = ? AND `a`.`school` = ?";
            $students = Mysql::execute($sql, array($this->major, $this->grade, $this->school));
            $this->scoreName = $this->major;
        }

        $failures = array();
        foreach ($students as $student) {
            $score = json_decode($student['score'], true);
            foreach ($score as $termScore) {
                foreach ($termScore as $courseScore) {
                    if ($courseScore['course'] == $course) {
                        $this->credit = empty($this->credit)? $courseScore['credit']:$this->credit;
                        $data[] = array(
                            "sid"   => $student['sid'],
                            "name"  => $student['name'],
                            "score" => $courseScore['mark'],
                            'resit' => $courseScore['resit'],
                        );
                    }
                }
            }
            $temp = empty($data)? array():end($data);
            if ($temp['sid'] != $student['sid']) {
                $failures[] = array(
                    'sid'    => $student['sid'],
                    'name'   => $student['name'],
                    'idcard' => $student['idcard'],
                );
            }
        }

        //多线程与多进程更新成绩
        if (\Hnust\input('re\b', false)) {
            $failures = $students;
        }
        if (!empty($failures) && $re) {
            $url = Config::getConfig('local_base_url') . 'Update/score';
            Http::multi($url, $failures);
            return $this->scoreAll($scope, $course, false);
        } elseif (!empty($failures)) {
            foreach ($failures as $student) {
                $data[] = array(
                    'sid'   => $student['sid'],
                    'name'  => $student['name'],
                    'score' => '-1',
                    'resit' => false
                );
            }
        }
        return $data;
    }

    public function scoreImg($scope, $course)
    {
        $data = $this->scoreAll($scope, $course);

        //宽度
        $imgWidth = $headBoxWidth = $dataBoxWidth = 720;
        //标题高度
        $headBoxHeight = 80;
        //数据行高度
        $dataBoxHeight = 60;
        //整体高度
        $imgHeight = $headBoxHeight + ((count($data) + 2) * $dataBoxHeight);

        //创建图像
        $im = imagecreate($imgWidth, $imgHeight);

        //图片字体设置
        $font  = Config::BASE_PATH . Config::FONT_PATH;

        //定义颜色
        $headBoxColor  = ImageColorAllocate($im, 217, 237, 247);
        $headFontColor = ImageColorAllocate($im,  91, 192, 222);
        $titleBoxColor = ImageColorAllocate($im, 223, 240, 216);
        $oddBoxColor   = ImageColorAllocate($im, 245, 245, 245);
        $evenBoxColor  = ImageColorAllocate($im, 255, 255, 255);
        $failBoxColor  = ImageColorAllocate($im, 242, 222, 222);
        $markBoxColor  = ImageColorAllocate($im, 217, 237, 247);
        $dataFontColor = ImageColorAllocate($im,  51,  51,  51);

        //headBox
        imagefilledrectangle($im, 0, 0, $headBoxWidth, $headBoxHeight, $headBoxColor);

        //head
        $head = (($scope == 'class')? $this->class:$this->major) . '  ';
        if (strlen($course) > (15 * 3)) {
            $head .= substr($course, 0, (14 * 3)) . '...';
        } else {
            $head .= $course;
        }
        $headFontSize = 20;
        $headSize     = ImageTTFBBox($headFontSize, 0, $font, $head);
        $headWidth    = $headSize[2] - $headSize[0];
        $headHeight   = $headSize[1] - $headSize[5];
        $headOffsetX  = - $headSize[0];
        $headOffsetY  = - $headSize[5];
        $headX = (int) ($headBoxWidth  - $headWidth)  / 2 + $headOffsetX;
        $headY = (int) ($headBoxHeight - $headHeight) / 2 + $headOffsetY;
        ImageTTFText($im, $headFontSize, 0, $headX, $headY, $headFontColor, $font, $head);

        //成绩排序
        usort($data, function($a, $b){
            return \Hnust\scoreCompare($a['score'], $b['score']);
        });
        //数据填充
        for ($i = -1; $i <= count($data); $i++) {
            //单双行背景
            if ($i % 2) {
                $dataBoxColor = $oddBoxColor;
            } else {
                $dataBoxColor = $evenBoxColor;
            }

            if ($i == -1) {
                $dataBoxColor = $titleBoxColor;
                $row = array ('学号', '姓名', '分数');
            } else if ($i == count($data)) {
                $dataBoxColor = $markBoxColor;
                $row = array('注：带*的为补考；By:Tick网络工作室');
            } else {
                $score = $data[$i]['score'];
                //不及格红色标记
                if ((is_numeric($score) && ($score < 60)) || ($score == '不及格') || empty($score)) {
                    $dataBoxColor = $failBoxColor;
                }
                //补考加×号
                if ($data[$i]['resit']) {
                    $score .= '*';
                }
                $row = array($data[$i]['sid'], $data[$i]['name'], $score);
            }

            //填充一行背景色
            $dataBoxOffsetHeight = $headBoxHeight + (($i + 1) * $dataBoxHeight);
            imagefilledrectangle($im, 0, $dataBoxOffsetHeight, $dataBoxWidth, ($dataBoxOffsetHeight + $dataBoxHeight), $dataBoxColor);

            //填入一行数据
            for ($j = 0; $j < count($row); $j++) {
                $dataFontSize = 16;
                $dataSize     = ImageTTFBBox($dataFontSize, 0, $font, $row[$j]);
                $dataWidth    = $dataSize[2] - $dataSize[0];
                $dataHeight   = $dataSize[1] - $dataSize[5];
                $dataOffsetX  = - $dataSize[0];
                $dataOffsetY  = $headBoxHeight + (($i + 1) * $dataBoxHeight) - $dataSize[5];
                $dataX = (int) ($dataBoxWidth / count($row) - $dataWidth) / 2 + ($dataBoxWidth / count($row)) * $j + $dataOffsetX;
                $dataY = (int) ($dataBoxHeight - $dataHeight) / 2 + $dataOffsetY;
                ImageTTFText($im, $dataFontSize, 0, $dataX, $dataY, $dataFontColor, $font, $row[$j]);
            }
        }

        //输出下载
        $fileName = (($scope == 'class')? $this->class:$this->major) . "_{$course}.png";
        header('Content-type: image/png');
        header("Content-Disposition: attachment; filename={$fileName}");
        ImagePng($im);
        ImageDestroy($im);
        exit;
    }
}