<?php

namespace Hnust\Analyse;

Class Card extends \Hnust\Crawler\Card
{
    protected $passwdClass;
    protected $inputPasswd;

    //重载构造函数，用于密码的预处理
    public function __construct($sid, $passwd = '')
    {
        $this->passwdClass = new Passwd($sid);
        $this->inputPasswd = $passwd;

        $passwd = empty($passwd)? $this->passwdClass->ykt():$passwd;
        parent::__construct($sid, $passwd);
    }

    public function login()
    {
        $result = parent::login();
        //保存密码
        if ($result && !empty($this->inputPasswd)) {
            $this->passwdClass->ykt($this->inputPasswd);
        }
        return $result;
    }

    //挂失与解挂
    public function doLoss($loss = true)
    {
        $result = parent::doLoss($loss);
        if (!$loss && '操作成功' === $result) {
            $result = '解挂成功，如卡状态仍未更改，请稍等1分钟再刷新页面';
        }
        return $result;
    }

    //计算账单
    public function getBill($cardId, $startDate = null, $endDate = null)
    {
        $data = $this->getRecord($cardId, $startDate, $endDate);

        usort($data, function($a, $b) {
            return ($a['time'] > $b['time'])? 1:-1;
        });

        //账单
        $bill = array(
            'max_cost'      => 0, //单次消费最高
            'min_cost'      => 0, //单次消费最低
            'total_count'   => 0, //交易流水次数
            'total_cost'    => 0, //总消费金额
            'water_cost'    => 0, //水务消费金额
            'water_count'   => 0, //水务流水次数
            'bus_cost'      => 0, //校车消费金额
            'bus_count'     => 0, //校车流水次数

            'rice_cost'     => 0, //打饭的总金额
            'rice_count'    => 0, //打饭的次数
            'rice_avg'      => 0, //米饭平均价格
            'rice_most'     => 0, //米饭集中价格
            'eat_count'     => 0, //吃饭总次数
            'eat_cost'      => 0, //吃饭总花费
            'eat_avg'       => 0, //吃饭平均价格

            'deposit_count' => 0, //充值次数
            'deposit_total' => 0, //总充值金额
            'deposit_avg'   => 0, //平均充值金额
            'deposit_max'   => 0, //最大充值金额
            'deposit_min'   => 0, //最小充值金额

            'lost_count'    => 0, //一卡通丢失且补办次数
        );
        //上次消费时间
        $lastTime  = 0;
        $lastTotal = 0;
        //米饭价格及次数
        $rice = array(
            '0.20' => 0,
            '0.25' => 0,
            '0.50' => 0,
            '0.60' => 0,
            '0.75' => 0,
            '0.80' => 0,
            '1.00' => 0,
        );
        foreach ($data as $item) {
            $bill['total_count']++;
            if ($item['trade'] < 0) {
                $bill['total_cost'] += -$item['trade'];
                $bill['max_cost'] = max(-$item['trade'], $bill['max_cost']);
                if ($bill['min_cost']) {
                    $bill['min_cost'] = min(-$item['trade'], $bill['min_cost']);
                } else {
                    $bill['min_cost'] = -$item['trade'];
                }
            }
            switch ($item['system']) {
                case '商务子系统':
                    //判断一餐花费
                    $nowTime = strtotime($item['time']);
                    if (abs($nowTime - $lastTime) > 3600) {
                        //判断饭钱
                        $trade = trim($item['trade'], '-');
                        if (isset($rice[$trade])) {
                            $rice[$trade]++;
                            $bill['rice_count']++;
                            $bill['rice_cost'] += -$item['trade'];
                            $bill['rice_avg'] = round($bill['rice_cost'] / $bill['rice_count'], 2);
                        }
                        if ($lastTime) {
                            $bill['eat_count']++;
                            $bill['eat_cost'] += $lastCost;
                            $bill['eat_avg'] = round($bill['eat_cost'] / $bill['eat_count'], 2);
                        }
                        $lastCost = -$item['trade'];
                        $lastTime = $nowTime;
                    } else {
                        $lastCost += -$item['trade'];
                    }
                    break;

                case '水控子系统':
                    $bill['water_count']++;
                    $bill['water_cost'] += -$item['trade'];
                    break;

                case '软网关系统':
                    $bill['bus_count']++;
                    $bill['bus_cost'] += -$item['trade'];
                    break;

                case '综合业务子系统':
                    if ('存款' === $item['type']) {
                        //pass
                    } elseif (('扣款' === $item['type']) && ('-20.00' === $item['trade'])) {
                        $bill['lost_count']++;
                        break;
                    } else {
                        break;
                    }

                case '转帐前置机':
                    $bill['deposit_count']++;
                    $bill['deposit_total'] += $item['trade'];
                    $bill['deposit_avg'] = round($bill['deposit_total'] / $bill['deposit_count'], 2);
                    $bill['deposit_max'] = max(+$item['trade'], $bill['deposit_max']);
                    if ($bill['deposit_min']) {
                        $bill['deposit_min'] = min(+$item['trade'], $bill['deposit_min']);
                    } else {
                        $bill['deposit_min'] = +$item['trade'];
                    }
                    break;
            }
        }
        if ($bill['rice_count']) {
            arsort($rice);
            $bill['rice_most'] = (double) array_keys($rice)[0];
        }

        return $bill;
    }
}