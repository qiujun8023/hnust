hnust = angular.module 'hnust', ['ngRoute', 'ngCookies']

hnust.config ($routeProvider) -> 
    $routeProvider
        .when '/login', 
            controller: login,
            templateUrl: 'views/login.html'
        .when '/agreement', 
            controller: agreement,
            templateUrl: 'views/agreement.html'
        .when '/score', 
            controller: score,
            templateUrl: 'views/score.html'
        .when '/schedule', 
            controller: schedule,
            templateUrl: 'views/schedule.html'
        .when '/exam', 
            controller: exam,
            templateUrl: 'views/exam.html'
        .when '/credit', 
            controller: credit,
            templateUrl: 'views/credit.html'
        .when '/tuition', 
            controller: tuition,
            templateUrl: 'views/tuition.html'
        .when '/judge', 
            controller: judge,
            templateUrl: 'views/judge.html'
        .when '/book', 
            controller: book,
            templateUrl: 'views/book.html'
        .when '/card', 
            controller: card,
            templateUrl: 'views/card.html'
        .when '/editUser', 
            controller: editUser,
            templateUrl: 'views/editUser.html'
        .when '/lastUser', 
            controller: lastUser,
            templateUrl: 'views/lastUser.html'
        .otherwise
            redirectTo: '/score'

main = ($scope, $rootScope, $http, $location, $cookies) ->
    #关闭侧栏
    $('#sidebar').sidebar 'attach events', '#menu'

    #网址根路径
    $rootScope.url = 'http://a.hnust.sinaapp.com/index.php'

    #网址监视
    $scope.$watch( -> 
        [$cookies, $location.url()]
    , ->
        $rootScope.hideNav = navigator.userAgent is 'demo'
        $rootScope.params = $location.search()
        $rootScope.rank = $cookies.rank || '-1'
        $rootScope.studentId = $cookies.studentId || '游客'
    , true)

    #检查返回数据
    $rootScope.checkData = (data) ->
        switch data.code
            #错误
            when -1
                $rootScope.error = data.msg || '网络连接超时 OR 服务器错误。'
            #弹窗
            when 1
                layer.msg data.msg
            #返回上一页
            when 2
                layer.msg data.msg, shift:6
                window.history.back()
            #跳至登陆
            when 3
                $cookies.rank = $cookies.studentId = ''
                $cookies.referer = $location.url()
                $location.url '/login'
            #跳回记录页面
            when 4
                if $cookies.referer and $cookies.referer isnt '/login'
                    $location.url $cookies.referer
                    $cookies.referer = ''
                else
                    $location.url '/score'
                return true
            #错误提示
            when 5
                $rootScope.error = data.msg
            #正常
            else 
                return true
        return false

    #请求数据
    $rootScope.jsonp = (params, timeout, callback) ->
        $('#sidebar').sidebar 'hide'
        $rootScope.error = ''

        params ||= {}
        params.callback = 'JSON_CALLBACK'
        timeout ||= 8000

        $rootScope.loading = true
        $http.jsonp $rootScope.url, 
            params  : params
            timeout : timeout
        .success (response) ->
            $rootScope.loading = false
            response.code = parseInt(response?.code)
            if $rootScope.checkData response
                if response.code is 6
                    params.passwd = prompt response.msg, ''
                    if params.passwd
                        $rootScope.jsonp params, timeout, callback
                    else
                        $rootScope.error = '密码错误！'
                else if callback?
                    callback response
        .error ->
            $rootScope.loading = false
            $rootScope.checkData 'code':'-1'

#登录
login = ($scope, $rootScope, $cookies) ->
    $('#sidebar').sidebar 'hide'
    $('.ui.checkbox').checkbox()
    if $cookies?.rank > '-1'
        return $rootScope.checkData code:4
    $rootScope.fun = 'login'
    $rootScope.title = '用户登录'
    $scope.studentId = $scope.passwd = ''

    $('.ui.form').form
        studentId: 
            identifier: 'studentId'
            rules: [
                type  : 'empty'
                prompt: '学号不能为空！'
            ,
                type  : 'length[10]'
                prompt: '学号不能少于10位！'
            ,
                type  : 'maxLength[10]'
                prompt: '学号不能大于10位！'
            ]
        ,
        passwd: 
            identifier: 'passwd'
            rules: [
                type  : 'empty'
                prompt: '密码不能为空！'
            ]
        ,
        agreement: 
            identifier: 'agreement'
            rules: [
                type  : 'checked'
                prompt: '同意用户使用协议方可使用！'
            ]
         
    , 
        inline: true
        on    : 'blur'
        onSuccess: ->
            params = 
                fun : 'login'
                passwd : $scope.passwd
                studentId : $scope.studentId
            $rootScope.jsonp params, 8000, (data) ->
                $cookies.rank = data?.info?.rank || '-1'
                $cookies.studentId = data?.info?.studentId || '游客'

#用户协议
agreement = ($scope, $rootScope) ->
    $rootScope.title = '用户使用协议'
    $rootScope.params.fun = 'agreement'

#成绩
score = ($scope, $rootScope) ->
    $rootScope.title = '成绩查询'
    $rootScope.params.fun = 'score'
    $rootScope.jsonp $rootScope.params, 10000, (data) ->
        $scope.data = data.data
        $scope.terms = (k for k,v of $scope.data).reverse()

#课表
schedule = ($scope, $rootScope) ->
    $rootScope.title = '实时课表'
    $rootScope.params.fun = 'schedule'
    $rootScope.jsonp $rootScope.params, 15000, (data) ->
        $scope.data = data.data
        $scope.info = data.info
        $('.menu .item').tab()

#考试
exam = ($scope, $rootScope) ->
    $rootScope.title = '考试安排'
    $rootScope.params.fun = 'exam'
    $rootScope.jsonp $rootScope.params, 15000, (data) ->
        $scope.data = data.data

#学分绩点
credit = ($scope, $rootScope) ->
    $rootScope.title = '学分绩点'
    $rootScope.params.fun = 'credit'
    $rootScope.jsonp $rootScope.params, 15000, (data) ->
        $scope.data = data.data

#学费
tuition = ($scope, $rootScope) ->
    $rootScope.title = '学年学费'
    $rootScope.params.fun = 'tuition'
    $rootScope.jsonp $rootScope.params, 15000, (data) ->
        $scope.total = data.data[0]

#教学评价
judge = ($scope, $rootScope, $location, $anchorScroll) ->
    $rootScope.title = '教学评价'
    $rootScope.params.fun = 'judge'
    $rootScope.jsonp $rootScope.params, 15000, (data) ->
        $scope.data = data.data

    $scope.judge = (item) ->
        $('.ui.checkbox').checkbox()
        $('.ui.form').form 'clear'
        $scope.judging = item

    $scope.submit = ->
        $rootScope.error = ''
        data = params:$scope.judging.params
        flag = true
        for i in [0...10]
            data["a#{i}"] = $("input[name='a#{i}']:checked").val()
            if !data["a#{i}"]
                layer.msg '请确定表单已填写完整。', shift:6
                return false
            if i isnt 0 and data["a#{i}"] isnt data["a#{i-1}"]
                flag = false
        if flag
            layer.msg '不能全部选择相同的选项。', shift:6
            return false
        params =
            fun  : 'judge'
            data : angular.toJson(data)
        $rootScope.jsonp params, 15000, (data) ->
            if data.code is 0
                $scope.judging = false
                $scope.data = data.data

#图书续借
book = ($scope, $rootScope) ->
    $rootScope.title = '图书续借'
    $rootScope.params.fun = 'book'
    $rootScope.jsonp $rootScope.params, 8000, (data) ->
        $scope.data = data.data

    #续借
    $scope.renew = (params) ->
        params.fun = 'book'
        $rootScope.jsonp params, 8000, (data) ->
            $scope.data = data.data

#校园一卡通
card = ($scope, $rootScope) ->
    $rootScope.title = '校园一卡通'
    $rootScope.params.fun = 'card'
    $rootScope.jsonp $rootScope.params, 15000, (data) ->
        $scope.info = data.info
        $scope.data = data.data

#修改权限
editUser = ($scope, $rootScope, $location, $cookies) ->
    $('#sidebar').sidebar 'hide'
    if $cookies?.rank is '-1'
        return $location.url '/login'
    $rootScope.error = ''
    $rootScope.title = '修改权限'
    $rootScope.params.fun = 'editUser'
    $scope.studentId = ''

    $('.ui.dropdown').dropdown()
    $('.ui.form').form
        studentId: 
            identifier: 'studentId'
            rules: [
                type  : 'empty'
                prompt: '学号不能为空！'
            ,
                type  : 'length[10]'
                prompt: '学号不能少于10位！'
            ,
                type  : 'maxLength[10]'
                prompt: '学号不能大于10位！'
            ]
        ,
        rank: 
            identifier: 'rank'
            rules: [
                type  : 'empty'
                prompt: '权限不能为空！'
            ]
        ,
    ,
        inline: true
        on    : 'blur'
        onSuccess: ->
            params =
                fun: 'editUser'
                studentId: $scope.studentId
                rank     : $("select[name='rank']").val()
            $rootScope.jsonp params
            return false

#最近使用用户
lastUser = ($scope, $rootScope) ->
    $rootScope.title = '最近使用用户'
    $rootScope.params.fun = 'lastUser'
    $rootScope.jsonp $rootScope.params, 8000, (data) ->
        $scope.data = data.data

#函数注入
hnust.controller 'main'    , main
hnust.controller 'login'   , login
hnust.controller 'score'   , score
hnust.controller 'schedule', schedule
hnust.controller 'exam'    , exam
hnust.controller 'credit'  , credit
hnust.controller 'tuition' , tuition
hnust.controller 'judge'   , judge
hnust.controller 'book'    , book
hnust.controller 'card'    , card
hnust.controller 'editUser', editUser
hnust.controller 'lastUser', lastUser