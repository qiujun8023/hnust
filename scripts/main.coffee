#AngularJS
hnust = angular.module 'hnust', ['ngRoute']

#检查服务器数据
hnust.factory 'checkJsonpData', ($rootScope, $location) ->
    check: (data) ->
        if !angular.isObject(data)
            data = code:-1
        switch data.code
            #跳转到登录
            when -2
                $rootScope.user =
                    name: '游客'
                    rank: -1
                $rootScope.referer = $location.url()
                $location.url '/login'
            #错误提示
            when -1
                $rootScope.error = data.msg || '网络连接超时 OR 服务器错误。'
            #弹出提示框
            when 1
                layer.msg data.msg
            #确认框
            when 2
                layer.open
                    title: data.msg
                    content:data.data
            #跳转到登录前页面
            when 3
                if $rootScope.referer and $rootScope.referer isnt '/login'
                    $location.url $rootScope.referer
                    $rootScope.referer = ''
                else
                    $location.url '/score'
        return if data.code >= 0 then true else false

#加载jsonp获取数据
hnust.factory 'getJsonpData', ($rootScope, $http, $location, checkJsonpData) ->
    query: (params, timeout, callback) ->
        self = this
        #置错误为空
        $rootScope.error = ''

        #jsonp请求参数
        search = angular.copy $location.search()
        search.fun ||= $rootScope.fun
        params = $.extend search, params
        params.callback = 'JSON_CALLBACK'

        #后台加载的数据
        bgjsonp = ['user', 'failRateKey', 'bookInfo']

        #超时时间
        timeout ||= 8000
        #加载中动画
        if params.fun not in bgjsonp
            $rootScope.loading = true
        $http.jsonp $rootScope.url,
            params : params
            timeout: timeout
        .success (res) ->
            if params.fun not in bgjsonp
                $rootScope.loading = false
            res.code = parseInt(res.code)
            if !checkJsonpData.check res
                return
            else if res.code is 4
                params.passwd = prompt res.msg, ''
                if params.passwd
                    self.query params, timeout, callback
                else
                    $rootScope.error = '密码错误！'
            else if callback? then callback res
        .error ->
            if params.fun not in bgjsonp
                $rootScope.loading = false
            checkJsonpData.check 
                code: -1
                msg : '网络异常，请稍后再试。'

hnust.config ($httpProvider, $routeProvider) ->
    #设置路由
    $routeProvider
        .when '/login',
            fun: 'login',
            title: '用户登录',
            controller: 'login',
            templateUrl: 'views/login.html?150723'
        .when '/agreement',
            fun: 'agreement',
            title: '用户使用协议',
            templateUrl: 'views/agreement.html?150723'
        .when '/user',
            fun: 'user',
            title: '用户中心',
            controller: 'user',
            templateUrl: 'views/user.html?150801'
        .when '/score',
            fun: 'score',
            title: '成绩查询',
            controller: 'score',
            templateUrl: 'views/score.html?150806'
        .when '/scoreAll',
            fun: 'scoreAll',
            title: '全班成绩',
            controller: 'scoreAll',
            templateUrl: 'views/scoreAll.html?150801'
        .when '/schedule',
            fun: 'schedule',
            title: '实时课表',
            controller: 'schedule',
            templateUrl: 'views/schedule.html?150723'
        .when '/exam',
            fun: 'exam',
            title: '考试安排',
            controller: 'exam',
            templateUrl: 'views/exam.html?150723'
        .when '/credit', 
            fun: 'credit',
            title: '学分绩点',
            controller: 'credit',
            templateUrl: 'views/credit.html?150723'
        .when '/judge', 
            fun: 'judge',
            title: '教学评价',
            controller: 'judge',
            templateUrl: 'views/judge.html?150723'
        .when '/book', 
            fun: 'book',
            title: '图书续借',
            controller: 'book',
            templateUrl: 'views/book.html?150723'
        .when '/bookList', 
            fun: 'bookList',
            title: '图书检索',
            controller: 'bookList',
            templateUrl: 'views/bookList.html?150803'
        .when '/tuition', 
            fun: 'tuition',
            title: '学年学费',
            controller: 'tuition',
            templateUrl: 'views/tuition.html?150723'
        .when '/card', 
            fun: 'card',
            title: '校园一卡通',
            controller: 'card',
            templateUrl: 'views/card.html?150801'
        .when '/failRate', 
            fun: 'failRate',
            title: '挂科率统计',
            controller: 'failRate',
            templateUrl: 'views/failRate.html?150803'
        .when '/editUser', 
            fun: 'editUser',
            title: '修改权限',
            controller: 'editUser',
            templateUrl: 'views/editUser.html?150723'
        .when '/lastUser', 
            fun: 'lastUser',
            title: '最近使用用户',
            controller: 'lastUser',
            templateUrl: 'views/lastUser.html?150723'
        .otherwise
            redirectTo: '/score'

hnust.run ($location, $rootScope, getJsonpData) ->
    #API网址
    $rootScope.url = 'http://a.hnust.sinaapp.com/index.php'
    #修改title
    $rootScope.$on '$routeChangeSuccess', (event, current, previous) ->
        $rootScope.fun = current.$$route?.fun || ''
        $rootScope.title = current.$$route?.title || ''

    #获取用户信息
    $rootScope.$on 'updateUserInfo', (event, current) ->
        getJsonpData.query fun:'user', 8000, (data) ->
            data.info.id = data.info.studentId || ''
            data.info.name ||= '游客'
            data.info.rank = if data.info.rank? then parseInt(data.info.rank) else -1
            data.info.scoreRemind = !!parseInt(data.info.scoreRemind)
            $rootScope.user = data.info

#导航栏控制器
navbarController = ($scope, $rootScope, getJsonpData) ->
    $scope.isPhone = document.body.offsetWidth < 720
    $scope.sidebar = $('.ui.sidebar')
    #侧栏
    $scope.$watch ->
        $rootScope.user?.rank
    , ->
        if $scope.isPhone
            $scope.sidebar
                .sidebar 'attach events', '#menu'
        else 
            $scope.sidebar
                .sidebar
                    closable: false
                    dimPage: false
                    scrollLock: true
                    transition: 'overlay'
                .sidebar 'attach events', '#menu'
    #影藏导航栏
    $scope.sidebarHide = ->
        if $scope.isPhone then $scope.sidebar.sidebar 'hide'
        return

    #是否隐藏导航栏
    $scope.hideNavbar = navigator.userAgent is 'demo'
    #获取用户信息
    $scope.$emit 'updateUserInfo'
    #注销登录
    $scope.logout = ->
        getJsonpData.query fun:'logout'

#用户中心
userController = ($scope, $rootScope, $location, getJsonpData) ->
    $('.ui.checkbox').checkbox 'check'
    $rootScope.error = ''

    #邮件输入框的显示与不显示
    $scope.scoreRemind = (isCheck) ->
        $scope.user.scoreRemind = if isCheck? then isCheck else !$scope.user?.scoreRemind
        if $scope.user.scoreRemind is true
            $('.ui.checkbox').checkbox 'check'
            $('#mailField').transition 'slide down in'
            $scope.user.mail = $rootScope.user.mail
        else
            $('.ui.checkbox').checkbox 'uncheck'
            $('#mailField').transition 'slide down out'
            $scope.user.mail = ''

    #监视有无获取用户信息
    watch = $scope.$watch ->
        $rootScope.user
    , ->
        if $rootScope.user?.rank? and $rootScope.user.rank isnt -1
            $scope.user = angular.copy $rootScope.user
            $scope.scoreRemind $scope.user.scoreRemind
            watch()
    , true

    #邮件校验
    $('.ui.form').form
        mail: 
            identifier: 'mail'
            optional   : true,
            rules: [
                type  : 'email'
                prompt: '请输入正确的邮件地址。'
            ]
        ,
    ,
        inline: true
        on    : 'blur'
        onSuccess: ->
            params =
                fun: 'userUpdate'
                scoreRemind: if $scope.user.scoreRemind then '1' else '0'
                mail: $scope.user.mail
            getJsonpData.query params, 8000, (data) ->
                $scope.$emit 'updateUserInfo'
            return false

#登录
loginController = ($scope, $rootScope, getJsonpData, checkJsonpData) ->
    $('.ui.checkbox').checkbox()
    if $rootScope.user?.rank? and $rootScope.user.rank isnt -1
        return checkJsonpData.check code:4
    $scope.studentId = $scope.passwd = ''

    #用户名及密码等表单校验
    $('.ui.form').attr 'action', $rootScope.url
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
                $scope.$emit 'updateUserInfo'

#成绩
scoreController = ($scope, getJsonpData) ->
    getJsonpData.query {}, 8000, (data) ->
        $scope.data = data.data
        $scope.terms = (k for k,v of $scope.data).reverse()

#全班成绩
scoreAllController = ($scope, $location, getJsonpData) ->
    if !$location.search().course
        return $location.url '/score'
    getJsonpData.query {}, 8000, (data) ->
        $scope.info = data.info
        $scope.data = data.data

#课表
scheduleController = ($scope, getJsonpData) ->
    getJsonpData.query {}, 8000, (data) ->
        $scope.data = data.data
        $scope.info = data.info
        $('.menu .item').tab()
        $('.ui.inline.dropdown').dropdown()

#考试
examController = ($scope, getJsonpData) ->
    getJsonpData.query {}, 10000, (data) ->
        $scope.data = data.data

#学分绩点
creditController = ($scope, getJsonpData) ->
    getJsonpData.query {}, 10000, (data) ->
        $scope.data = data.data

#教学评价
judgeController = ($scope, $rootScope, $location, $anchorScroll, getJsonpData) ->
    getJsonpData.query {}, 10000, (data) ->
        $scope.data = data.data

    #评教
    $scope.judge = (item) ->
        $('.ui.checkbox').checkbox()
        $('.ui.form').form 'clear'
        $scope.judging = item
        $anchorScroll()

    #提交评价
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
bookController = ($scope, getJsonpData) ->
    #获取图书列表
    getJsonpData.query {}, 8000, (data) ->
        $scope.data = data.data

    #续借
    $scope.renew = (params) ->
        params.fun = 'book'
        getJsonpData.query params, 8000, (data) ->
            $scope.data = data.data

#图书检索
bookListController = ($scope, $rootScope, $timeout, $location, getJsonpData) ->
    #回车键Submit
    $('.ui.form').form {}, 
        onSuccess: ->
            $scope.$apply ->
                $scope.search()
    $rootScope.error = ''

    #搜索书列表
    $scope.search = (key) ->
        if key then $scope.key = key
        if !$scope.key?.length then return
        $location.search
            key : $scope.key
            page: 1
            rand: Math.random()

    #查找详细信息
    $scope.bookInfo = (item) ->
        if item.data or item.loading
            return
        item.loading = true
        getJsonpData.query {fun:'bookInfo', key:item.id}, 8000, (data) ->
            item.loading = false
            item.data = data.data

    #加载数据
    if $location.search().key
        search = $location.search()
        $scope.key = search.key
        getJsonpData.query {key:search.key, page:search.page}, 8000, (data) ->
            $scope.info = data.info
            $scope.data = data.data
            $timeout ->
                $('.ui.accordion').accordion
                    duration: 200
                    exclusive: false

#学费
tuitionController = ($scope, getJsonpData) ->
    getJsonpData.query {}, 8000, (data) ->
        $scope.total = data.data?.total
        delete data.data?.total
        $scope.data = data.data
        $scope.terms = (k for k,v of $scope.data).reverse()

#校园一卡通
cardController = ($scope, getJsonpData) ->
    getJsonpData.query {}, 8000, (data) ->
        $scope.info = data.info
        $scope.data = data.data

#挂科率统计
failRateController = ($scope, $rootScope, $timeout, getJsonpData) ->
    $rootScope.error = ''
    $scope.keys = []

    #自动补全设置
    $('.ui.search.dropdown').dropdown
        onChange: (value)->
            $scope.search(value)

    #抵消失焦时Semantic对值的清空
    $scope.filling = ->
        $("[ng-model='key']")[0].value = $scope.key || ''

    #检查输入框值的变化
    $scope.check = (key) ->
        $timeout ->
            if key is $scope.key and key is ''
                $scope.keys = []
            else if key is $scope.key
                $scope.completion key
        , 300

    #自动补全
    $scope.completion = (key) ->
        getJsonpData.query {fun:'failRateKey', key:key}, 8000, (data) ->
            $scope.keys = data.data
            #显示下拉框（自动补全）
            $timeout ->
                $('.ui.search.dropdown').dropdown 'show'

    #搜索
    $scope.search = (key) ->
        #隐藏下拉框
        $('.ui.search.dropdown').dropdown 'hide'
        #去字体重复
        $('.ui.search.dropdown').dropdown('set text', '')
        if key then $scope.key = key
        if !$scope.key?.length then return
        $("[ng-model='key']")[0].value = $scope.key
        $scope.data = []
        #请求服务器数据
        getJsonpData.query {key:$scope.key}, 8000, (data) ->
            #排序
            $scope.data = _.sortBy data.data, (item) ->
                -parseFloat(item.rate)
            #计算全校挂科率
            if $scope.data.length > 1 and $scope.data[0].name isnt $scope.data[0].course
                total = 
                    'name': '湖南科技大学'
                    'all' : 0
                    'fail': 0
                for item in data.data
                    total.all  += parseInt(item.all)
                    total.fail += parseInt(item.fail)
                total.rate = total.fail / total.all * 100
                $scope.data.unshift(total)
            #进度条显示
            $timeout ->
                $('.progress').progress()

    #进度条颜色
    $scope.progressColor = (rate)->
        if rate <= 2
            ['teal']
        else if rate <= 6
            ['green']
        else if rate <= 12
            ['pink']
        else if rate <= 20
            ['orange']
        else
            ['red']

#修改权限
editUserController = ($scope, $rootScope, $location, getJsonpData) ->
    $rootScope.error = ''
    $scope.studentId = ''

    #权限下拉框
    $('.ui.dropdown').dropdown()
    #表单验证
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
lastUserController = ($scope, getJsonpData) ->
    getJsonpData.query {}, 5000, (data) ->
        $scope.data = data.data

#排序
sortByFilter = ->
    (items, predicate, reverse) ->
        items = _.sortBy items, (item) ->
            #将优中良差转为对应的数值进行比较
            if item[predicate] is '优'
                95.02
            else if item[predicate] is '良'
                84.02
            else if item[predicate] is '中'
                74.02
            else if item[predicate] is '及格'
                60.02
            else if item[predicate] is '不及格'
                0
            else if !isNaN(item[predicate]) && item[predicate]
                parseFloat(item[predicate])
            else
                item[predicate]
        if reverse then items else items.reverse()

#函数注入
hnust.controller 'navbar'     , navbarController
hnust.controller 'login'      , loginController
hnust.controller 'user'       , userController
hnust.controller 'score'      , scoreController
hnust.controller 'scoreAll'   , scoreAllController
hnust.controller 'schedule'   , scheduleController
hnust.controller 'exam'       , examController
hnust.controller 'credit'     , creditController
hnust.controller 'judge'      , judgeController
hnust.controller 'book'       , bookController
hnust.controller 'bookList'   , bookListController
hnust.controller 'tuition'    , tuitionController
hnust.controller 'card'       , cardController
hnust.controller 'failRate'   , failRateController
hnust.controller 'editUser'   , editUserController
hnust.controller 'lastUser'   , lastUserController
hnust.filter     'sortBy'     , sortByFilter