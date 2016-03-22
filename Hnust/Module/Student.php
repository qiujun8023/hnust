<?php

namespace Hnust\Module;

use Hnust\Config;

class Student extends Auth
{
    //获取所有学期
    public function getTerms()
    {
        //获取所有学期编号
        $term  = Config::getConfig('current_term');
        $terms = array();
        $year  = '20' . substr($this->sid, 0, 2);
        do {
            for ($i = 1; $i <= 2; $i++) {
                $tempTerm = $year . '-' . ($year + 1) . '-' . $i;
                $terms[] = $tempTerm;
                if($tempTerm > $term) {
                    break;
                }
            }
            $year++;
        } while ($tempTerm < $term);
        return $terms;
    }

    //成绩
    public function score()
    {
        $score = new \Hnust\Analyse\Score($this->sid);
        $this->data = $score->getScore();
        $this->info = array(
            'sid' => $this->sid
        );
        if ($score->error) {
            $this->msg  = $score->error;
        } elseif (empty($this->data)) {
            $this->msg  = '未查询到相关成绩记录';
            $this->code = Config::RETURN_ERROR;
        }
    }

    //班级/专业成绩
    public function scoreAll()
    {
        $course = \Hnust\input('course');
        $scope  = \Hnust\input('scope', 'class');
        $isDownload = \Hnust\input('download/b', false);
        $scoreAll = new \Hnust\Analyse\ScoreAll($this->sid);
        if ($isDownload) {
            $scoreAll->scoreImg($scope, $course);
        } else {
            $this->data = $scoreAll->scoreAll($scope, $course);
            $this->info = array(
                'sid'       => $this->sid,
                'scoreName' => $scoreAll->scoreName,
                'credit'    => $scoreAll->credit,
                'scope'     => $scope,
                'course'    => $course,
            );
        }
    }

    //课表
    public function schedule()
    {
        $type = \Hnust\input('type');
        $week = \Hnust\input('week');
        $term = \Hnust\input('term');
        $week = strlen($week)? $week:\Hnust\week();
        $term = strlen($term)? $term:Config::getConfig('current_term');
        $isDownload = \Hnust\input('download/b', false);
        $schedule   = new \Hnust\Analyse\Schedule($this->sid);
        if ($isDownload) {
            $path = $schedule->getExcel($this->sid, $term);
            header('Location:' . Config::WEB_PATH . $path);
        } else {
            $this->data = $schedule->getSchdule($this->sid, $term, $type, $week);
            $this->info = array(
                'sid'     => $this->sid,
                'week'    => $week,
                'term'    => $term,
                'terms'   => $this->getTerms(),
                'remarks' => $this->data['remarks']
            );
            $this->msg  = $schedule->error;
            unset($this->data['remarks']);
        }
    }

    //考试
    public function exam()
    {
        $exam = new \Hnust\Analyse\Exam($this->sid, $this->passwd);
        $this->data = $exam->getExam();
        if ($exam->error) {
            $this->msg = $exam->error;
        } elseif (empty($this->data)) {
            $this->code = Config::RETURN_ERROR;
            $this->msg  = '未查询到相关考试安排！';
        }
        $this->info = array(
            'sid'  => $this->sid,
            'type' => $type
        );
    }

    //学分绩点
    public function credit()
    {
        $credit = new \Hnust\Analyse\Credit($this->sid, $this->passwd);
        $this->data = $credit->getCredit();
        if ($credit->error) {
            $this->msg  = $credit->error;
        } elseif (empty($this->data)) {
            $this->code = Config::RETURN_ERROR;
            $this->msg  = '未查询到相关学分绩点记录';
        }
        $this->info = array(
            'sid' => $this->sid
        );
    }

    //空闲教室
    public function classroom()
    {
        $term  = \Hnust\input('term', '');
        $term = empty($term)? Config::getConfig('current_term'):$term;
        $build = \Hnust\input('build', '');
        $week  = \Hnust\input('week', '');
        $day   = \Hnust\input('day', '');
        $beginSession = \Hnust\input('beginSession', '');
        $endSession   = \Hnust\input('endSession', '');
        $classroom = new \Hnust\Analyse\Classroom($this->sid, $this->passwd);
        $this->data = $classroom->getClassroom($term, $build, $week, $day, $beginSession, $endSession);
        if(empty($this->data)) {
            $this->msg  = '未找到相关空闲教室';
            $this->code = Config::RETURN_ERROR;
        }
    }

    //选课平台
    public function elective()
    {
        $key   = \Hnust\input('key', '');
        $title = \Hnust\input('title', '');
        $url   = \Hnust\input('url', '');
        $type  = \Hnust\input('type', '');
        $elective = new \Hnust\Analyse\Elective($this->sid, $this->passwd);
        if ('key' === $type) {
            $this->data = $elective->complet($key);
        } elseif ('search' === $type) {
            $result = $elective->search($key, $this->page);
            $this->data = $result['data'];
            $this->info = $result['info'];
            if (empty($this->data)) {
                $this->code = Config::RETURN_ERROR;
                $this->msg  = '未找到相关课程记录';
            }
        } elseif ('addQueue' === $type) {
            $this->data = $elective->addQueue($title, $url);
            $this->msg  = '已成功加入操作队列。';
        } else {
            $this->data['selected'] = $elective->getSelected();
            $this->data['queue']    = $elective->getQueue();
        }
    }

    //评教
    public function judge()
    {
        $type = \Hnust\input('type', '');
        $judge = new \Hnust\Analyse\Judge($this->sid, $this->passwd);
        if ('submit' === $type) {
            $radio  = \Hnust\input('radio/a', array());
            $params = \Hnust\input('params', '');
            if (count($radio) != 10) {
                $this->code = Config::RETURN_ERROR;
                $this->msg  = '请确定表单已经填写完整';
                return false;
            } elseif (empty($params)) {
                $this->code = Config::RETURN_ERROR;
                $this->msg  = '参数有误。';
                return false;
            }
            $judge->submit($params, $radio);
            $this->msg = '评教成功，如有异常结果请及时联系管理员';
        } else {
            $this->data = $judge->getList();
        }
        $this->info = array(
            'sid' => $this->sid
        );
    }

    //图书借阅
    public function book()
    {
        $type = \Hnust\input('type', '');
        $book = new \Hnust\Analyse\Book($this->sid, $this->passwd);
        if ('renew' === $type) {
            $barcode    = \Hnust\input('barcode', '');
            $department = \Hnust\input('department', '');
            $library    = \Hnust\input('library', '');
            $this->code = Config::RETURN_ALERT;
            $this->data = $this->msg = $book->doRenew($barcode, $department, $library);
        } elseif ('search' === $type) {
            $this->data = $book->getBookList($this->key, $this->page);
        } elseif ('info' === $type) {
            $id = \Hnust\input('id', '');
            $this->data = $book->getBookInfo($id);
        } else {
            $this->data = $book->getLoanList();
            if (0 === count($this->data)) {
                $this->code = Config::RETURN_ERROR;
                $this->msg  = '未找到相关借书记录';
            }
        }
    }

    //学费查询
    public function tuition()
    {
        $tuition = new \Hnust\Analyse\Tuition($this->sid, $this->passwd);
        $this->data = $tuition->getTuition();
        $this->msg  = $tuition->error;
        $this->info = array(
            'sid' => $this->sid
        );
    }

    //一卡通
    public function card()
    {
        $type      = \Hnust\input('type', '');
        $startDate = \Hnust\input('startDate', null);
        $endDate   = \Hnust\input('endDate', null);
        $card      = new \Hnust\Analyse\Card($this->sid, $this->passwd);
        if ('bill' === $type) {
            $this->info  = $card->getInfo();
            $this->data  = $card->getBill($this->info['cardId'], $startDate, $endDate);
            $student     = new \Hnust\Analyse\Student();
            if ($info = $student->info($this->sid)) {
                $this->data['assess'] = $info['assess'];
            } else {
                $this->data['assess'] = '适中';
            }
        } elseif (in_array($type, array('loss', 'reloss'))) {
            $loss = ('loss' === $type);
            $this->msg  = $card->doLoss($loss);
            $this->info = $card->getInfo();
        } else {
            $this->info = $card->getInfo();
            $this->data = $card->getRecord($this->info['cardId'], $startDate, $endDate);
        }
        $this->info['sid'] = $this->sid;
    }

    //排名
    public function rank()
    {
        $by    = \Hnust\input('by', 'term');
        $scope = \Hnust\input('scope', 'class');
        $term  = \Hnust\input('term', '');
        if (empty($term) || !in_array(strlen($term), array(9, 11))) {
            $term = Config::getConfig('current_term');
        } elseif (('term' === $by) && (11 !== strlen($term))) {
            $term = Config::getConfig('current_term');
        }
        $term = ('term' !== $by)? substr($term, 0, 9):$term;
        $isDownload = \Hnust\input('download/b', false);
        $rank = new \Hnust\Analyse\Rank($this->sid);
        if ($isDownload) {
            $path = $rank->getExcel($term, $scope, $by);
            header('Location:' . Config::WEB_PATH . $path);
        } else {
            $this->data = $rank->getRank($term, $scope, $by);
            $this->info = array(
                'sid'   => $this->sid,
                'by'    => $by,
                'scope' => $scope,
                'term'  => $term,
                'class' => $rank->class,
                'major' => $rank->major,
                'terms' => $rank->terms,
                'courses'  => $rank->courses,
                'rankName' => $rank->rankName,
            );
        }
    }
}