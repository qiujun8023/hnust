<?php

namespace Hnust\Crawler;

use Hnust\Config;
use Hnust\Utils\Http;

class Book
{
    protected $sid;
    protected $passwd;
    protected $baseUrl;
    protected $cookies;

    public function __construct($sid = '', $passwd = '')
    {
        $this->sid = $sid;
        $this->passwd = $passwd;
        $this->baseUrl = Config::getConfig('lib_base_url');
    }

    //图书馆登录
    public function login()
    {
        try {
            $http = new Http(array(
                CURLOPT_URL        => $this->baseUrl . 'opac/reader/login.jsp?str_kind=login',
                CURLOPT_POSTFIELDS => 'barcode=&fangshi=1&identification_id=' . $this->sid .'&password=' . $this->passwd,
                CURLOPT_COOKIE     => $this->cookies,
                CURLOPT_TIMEOUT    => 5,
            ));
        } catch (\Exception $e) {
            throw new \Exception('网络异常，登录图书馆失败', Config::RETURN_ERROR);
        }
        $this->cookies = empty($this->cookies)? $http->cookies:$this->cookies;


        if (false !== strpos($http->content, 'infoList.jsp')) {
            return true;
        } else if (false !== strpos($http->content, 'history.back()')) {
            throw new \Exception('密码错误,请输入图书馆密码：', Config::RETURN_NEED_PASSWORD);
        } else {
            throw new \Exception('网络异常，登录图书馆失败', Config::RETURN_ERROR);
        }
    }

    //获取借阅列表
    public function getLoanList()
    {
        $this->login();

        try {
            $http = new Http(array(
                CURLOPT_URL     => $this->baseUrl . 'opac/reader/infoList.jsp',
                CURLOPT_COOKIE  => $this->cookies,
                CURLOPT_TIMEOUT => 5,
            ));
        } catch (\Exception $e) {
            throw new \Exception('网络异常，借阅列表获取失败', Config::RETURN_ERROR);
        }
        $content = str_replace(
            array(' align=center', '&nbsp;', '<font color=#FF0000>', '<font>', '<\/font>'),
            '',
            mb_convert_encoding($http->content, 'UTF-8', 'GBK')
        );
        $pattern = "/<td>(?:\d+)<\/td><td>(.*?)<\/td><td>(.*?)<\/td><td>(.*?)<\/td><td>(.*?)<\/td>"
                 . "(?:.{80,120}Renew\('(?:.*?)','(.*?)','(.*?)'\))?/s";
        preg_match_all($pattern, $content, $temp);

        $result = array();
        for ($i = 0; $i < count($temp[0]); $i++) {
            $time     = strtotime(trim($temp[4][$i]));
            $remain   = ceil(($time - time()) / 86400);
            $result[] = array(
                'title'      => $temp[1][$i],
                'barcode'    => $temp[2][$i],
                'department' => $temp[5][$i],
                'library'    => $temp[6][$i],
                'time'       => $temp[4][$i],
                'remain'     => $remain
            );
        }
        return $result;
    }

    //续借图书
    public function doRenew($barcode, $department, $library)
    {
        if (empty($barcode) || empty($department) || empty($library)) {
            return '续借失败，请及时归还';
        }

        $this->login();

        try {
            $http = new Http(array(
                CURLOPT_URL        => $this->baseUrl . 'opac/reader/infoList.jsp',
                CURLOPT_COOKIE     => $this->cookies,
                CURLOPT_POSTFIELDS => "action=Renew&book_barcode={$barcode}&department_id={$department}&library_id={$library}",
                CURLOPT_TIMEOUT    => 5,
            ));
        } catch (\Exception $e) {
            throw new \Exception('网络异常，续借失败', Config::RETURN_ERROR);
        }
        $content = mb_convert_encoding($http->content, 'UTF-8', 'GBK');
        $pattern = '/<script language="JavaScript">\s*alert\("(.*?)"\);/';
        preg_match($pattern, $content, $temp);
        return $temp[1];
    }

    //图书搜索
    public function getBookList($key, $page)
    {
        $key = mb_convert_encoding($key, 'GBK', 'UTF-8');
        try {
            $http = new Http(array(
                CURLOPT_URL        => $this->baseUrl . 'opac/book/queryOut.jsp',
                CURLOPT_POSTFIELDS => "kind=simple&library_id=all&type=title&orderby=pubdate_date&ordersc=desc&recordtype=01&size=15&match=mh&curpage={$page}&word=" . $key,
                CURLOPT_TIMEOUT    => 8,
            ));
        } catch (\Exception $e) {
            throw new \Exception('网络异常，请刷新或稍后再试', Config::RETURN_ERROR);
        }

        $content = str_replace('&nbsp;', '', mb_convert_encoding($http->content, 'UTF-8', 'GBK'));
        $pattern = '/<td><a href="javascript:popup\(\'detailBook.jsp\',\'(.*?)\'\)" class=opac_blue>(.*?)<\/a><\/td>(?:.*?)<td>(.*?)<\/td>\s*<td>(.*?)<\/td>\s*<td>(.*?)<\/td>\s*<td>(.*?)<\/td>(?:.*?)<\/tr>/s';
        preg_match_all($pattern, $content, $temp);

        $result = array();
        for ($i = 0; $i < count($temp[0]); $i++) {
            if (empty(trim($temp[2][$i]))) {
                continue;
            }
            $result[] = array(
                'id'        => trim($temp[1][$i]),
                'title'     => trim($temp[2][$i]),
                'publisher' => trim($temp[4][$i]),
                'ISBN'      => trim($temp[5][$i]),
                'time'      => trim($temp[6][$i]),
            );
        }
        return $result;
    }

    //获取图书详细信息
    public function getBookInfo($id)
    {
        //爬取书籍信息
        try {
            $http = new Http(array(
                CURLOPT_URL     => $this->baseUrl . "opac/book/detailBook.jsp?rec_ctrl_id={$id}",
                CURLOPT_TIMEOUT => 5,
            ));
        } catch (\Exception $e) {
            throw new \Exception('网络异常，请刷新或稍后再试', Config::RETURN_ERROR);
        }

        $content = str_replace('&nbsp;', '', mb_convert_encoding($http->content, 'UTF-8', 'GBK'));

        //正则转换
        $subject = array(
            'ISBN/ISSN:'      => 'ISBN',
            '出版:'           => 'publisher',
            '丛编:'           => 'series',
            '简介:'           => 'intro',
            '题名和责任者说明:' => 'statement',
            '统一题名:'        => 'unified_title',
            '载体形态:'        => 'describe',
            '责任者:'          => 'author',
            '中图分类号:'       => 'CLC',
            '主题:'            => 'subject',
        );
        //正则书籍基本信息
        $pattern = '/width=20% >(.*?)<\/td><td width=80% >(.*?)<\/td><\/tr>/';
        preg_match_all($pattern, $content, $temp);

        $result = array();
        for ($i = 0; $i < count($temp[1]); $i++) {
            if (isset($subject[$temp[1][$i]])) {
                $title = $subject[$temp[1][$i]];
                $result[$title] = trim(strip_tags($temp[2][$i]));
                if ($title == 'ISBN') {
                    $pattern = '/^\:?(.*?)(CNY.*)?$/';
                    preg_match($pattern, $result[$title], $temp_1);
                    $result[$title] = $temp_1[1];
                }
            }
        }
        $result['detail'] = !!count($result);

        //正则可借阅列表
        $pattern = '/<tr(?: bgcolor="#EBF0F2")?>\s*<td align=center>\d*<\/td>\s*<td>(.*?)<\/td>\s*<td>(.*?)<\/td>\s*<td>.*?<\/td>\s*<td>(.*?)<\/td>\s*<td>(.*?)<\/td>\s*<td>(?:.*?)<\/tr>/s';
        preg_match_all($pattern, $content, $temp);
        for ($i = 0; $i < count($temp[0]); $i++) {
            $result['list'][] = array(
                'barcode' => trim($temp[1][$i]),
                'index'   => trim($temp[2][$i]),
                'library' => trim($temp[3][$i]),
                'state'   => trim($temp[4][$i]),
            );
        }
        return $result;
    }
}