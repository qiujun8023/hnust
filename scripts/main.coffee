#Semantic下拉菜单
$('#menu').dropdown 
    action:'hide'
    transition: 'drop'
    forceSelection: true

#API请求地址
apiUrl = 'http://a.hnust.sinaapp.com/index.php'

#AngularJS
hnust = angular.module 'hnust', ['ngRoute']

#加载jsonp获取数据
hnust.factory 'getJsonpData', ($rootScope, $http, $location) ->
    query: (params, timeout, callback) ->
        self = this
        #置错误为空
        $rootScope.error = ''

        #jsonp请求参数
        search = angular.copy($location.search())
        search.fun ||= $rootScope.fun
        params = $.extend search, params
        params.callback = 'JSON_CALLBACK'

        #超时时间
        timeout ||= 8000
        #加载中动画
        if params.fun not in ['userInfo']
            $rootScope.loading = true
        $http.jsonp $rootScope.url,
            params : params
            timeout: timeout
        .success (res) ->
            if params.fun not in ['userInfo']
                $rootScope.loading = false
            if res.code is 6
                params.passwd = prompt res.msg, ''
                if params.passwd
                    self.query params, timeout, callback
                else
                    $rootScope.error = '密码错误！'
            else if callback? then callback res
        .error ->
            if params.fun not in ['userInfo']
                $rootScope.loading = false

#检查服务器数据
hnust.factory 'checkJsonpData', ($rootScope, $location) ->
    check: (data) ->
        switch data.code
            #错误
            when -1
                $rootScope.error = data.msg || '网络连接超时 OR 服务器错误。'
            #弹窗
            when 1
                layer.msg data.msg
                return true
            #返回上一页
            when 2
                layer.msg data.msg, shift:6
                window.history.back()
            #跳至登陆
            when 3
                $rootScope.user =
                    name: '游客'
                    rank: -1
                $rootScope.referer = $location.url()
                $location.url '/login'
            #跳回记录页面
            when 4
                if $rootScope.referer and $rootScope.referer isnt '/login'
                    $location.url $rootScope.referer
                    $rootScope.referer = ''
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

#http拦截器，用户检查jsonp数据
hnust.factory 'httpInterceptor', ($q, checkJsonpData) ->
    response: (res) ->
        if res.config.method isnt 'JSONP'
            return res
        res.data.code = parseInt(res.data?.code)
        if checkJsonpData.check res.data then res else $q.reject('reject')

    responseError: (res) ->
        checkJsonpData.check 
            code: -1
            msg : '网络异常，请稍后再试。'
        $q.reject('reject')

hnust.config ($httpProvider, $routeProvider) ->
    #添加拦截器
    $httpProvider.interceptors.push 'httpInterceptor'
    #设置路由
    $routeProvider
        .when '/login',
            fun: 'login',
            title: '用户登录',
            controller: login,
            templateUrl: 'views/login.html?150722'
        .when '/agreement',
            fun: 'agreement',
            title: '用户使用协议',
            templateUrl: 'views/agreement.html?150722'
        .when '/user',
            fun: 'user',
            title: '用户中心',
            controller: user,
            templateUrl: 'views/user.html?150722'
        .when '/score',
            fun: 'score',
            title: '成绩查询',
            controller: score,
            templateUrl: 'views/score.html?150722'
        .when '/schedule',
            fun: 'schedule',
            title: '实时课表',
            controller: schedule,
            templateUrl: 'views/schedule.html?150722'
        .when '/exam',
            fun: 'exam',
            title: '考试安排',
            controller: exam,
            templateUrl: 'views/exam.html?150722'
        .when '/credit', 
            fun: 'credit',
            title: '学分绩点',
            controller: credit,
            templateUrl: 'views/credit.html?150722'
        .when '/tuition', 
            fun: 'tuition',
            title: '学年学费',
            controller: tuition,
            templateUrl: 'views/tuition.html?150722'
        .when '/judge', 
            fun: 'judge',
            title: '教学评价',
            controller: judge,
            templateUrl: 'views/judge.html?150722'
        .when '/book', 
            fun: 'book',
            title: '图书续借',
            controller: book,
            templateUrl: 'views/book.html?150722'
        .when '/card', 
            fun: 'card',
            title: '校园一卡通',
            controller: card,
            templateUrl: 'views/card.html?150722'
        .when '/editUser', 
            fun: 'editUser',
            title: '修改权限',
            controller: editUser,
            templateUrl: 'views/editUser.html?150722'
        .when '/lastUser', 
            fun: 'lastUser',
            title: '最近使用用户',
            controller: lastUser,
            templateUrl: 'views/lastUser.html?150722'
        .otherwise
            redirectTo: '/score'

hnust.run ($location, $rootScope, getJsonpData) ->
    #API网址
    $rootScope.url = apiUrl
    #修改title
    $rootScope.$on '$routeChangeSuccess', (event, current, previous) ->
        $rootScope.fun = current.$$route?.fun || ''
        $rootScope.title = current.$$route?.title || ''

    #获取用户信息
    $rootScope.$on 'userLogin', (event, current) ->
        getJsonpData.query fun:'userInfo', 8000, (data) ->
            data.info.id = data.info.studentId || ''
            data.info.name ||= '游客'
            data.info.rank = if data.info.rank? then parseInt(data.info.rank) else -1
            $rootScope.user = data.info

#导航栏控制器
navbar = ($scope, $rootScope, getJsonpData) ->
    #是否隐藏导航栏
    $scope.hideNavbar = navigator.userAgent is 'demo'
    #获取用户信息
    $scope.$emit 'userLogin'
    #注销登录
    $scope.logout = ->
        getJsonpData.query fun:'logout'

#用户中心
user = ($scope, $rootScope, $location, getJsonpData) ->
    if !$rootScope.user?.rank? or $rootScope.user.rank is -1
        return $location.url '/login'
    $('.ui.checkbox').checkbox()
    $scope.user = $rootScope.user

#登录
login = ($scope, $rootScope, getJsonpData, checkJsonpData) ->
    $('.ui.checkbox').checkbox()
    if $rootScope.user?.rank? and $rootScope.user.rank isnt -1
        return checkJsonpData.check code:4
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
            getJsonpData.query params, 8000, (data) ->
                #发送登录成功事件
                $scope.$emit 'userLogin'

#成绩
score = ($scope, getJsonpData) ->
    getJsonpData.query {}, 8000, (data) ->
        $scope.data = data.data
        $scope.terms = (k for k,v of $scope.data).reverse()

#课表
schedule = ($scope, getJsonpData) ->
    getJsonpData.query {}, 8000, (data) ->
        $scope.data = data.data
        $scope.info = data.info
        $('.menu .item').tab()
        $('#term').dropdown()

#考试
exam = ($scope, getJsonpData) ->
    getJsonpData.query {}, 10000, (data) ->
        $scope.data = data.data

#学分绩点
credit = ($scope, getJsonpData) ->
    getJsonpData.query {}, 10000, (data) ->
        $scope.data = data.data

#学费
tuition = ($scope, getJsonpData) ->
    getJsonpData.query {}, 8000, (data) ->
        $scope.total = data.data?.total
        delete data.data?.total
        $scope.data = data.data
        $scope.terms = (k for k,v of $scope.data).reverse()

#教学评价
judge = ($scope, $rootScope, $location, $anchorScroll, getJsonpData) ->
    getJsonpData.query {}, 10000, (data) ->
        $scope.data = data.data

    $scope.judge = (item) ->
        $('.ui.checkbox').checkbox()
        $('.ui.form').form 'clear'
        $scope.judging = item
        $anchorScroll()

    $scope.submit = ->
        $rootScope.error = ''
        data = params: $scope.judging.params
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
        getJsonpData.query params, 10000, (data) ->
            if data.code is 0
                $scope.judging = false
                $scope.data = data.data

#图书续借
book = ($scope, getJsonpData) ->
    getJsonpData.query {}, 8000, (data) ->
        $scope.data = data.data

    #续借
    $scope.renew = (params) ->
        params.fun = 'book'
        getJsonpData.query params, 8000, (data) ->
            $scope.data = data.data

#校园一卡通
card = ($scope, getJsonpData) ->
    getJsonpData.query {}, 8000, (data) ->
        $scope.info = data.info
        $scope.data = data.data

#修改权限
editUser = ($scope, $rootScope, $location, getJsonpData) ->
    if !$rootScope.user?.rank? or $rootScope.user.rank is -1
        return $location.url '/login'
    $rootScope.error = ''
    $scope.studentId = ''

    $('#rank').dropdown()
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
            getJsonpData.query params
            return false

#最近使用用户
lastUser = ($scope, getJsonpData) ->
    getJsonpData.query {}, 5000, (data) ->
        $scope.data = data.data

#函数注入
hnust.controller 'navbar'  , navbar
hnust.controller 'login'   , login
hnust.controller 'user'    , user
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