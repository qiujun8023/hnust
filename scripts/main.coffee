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
            templateUrl: 'views/login.html?150808'
        .when '/agreement',
            fun: 'agreement',
            title: '用户使用协议',
            templateUrl: 'views/agreement.html?150808'
        .when '/user',
            fun: 'user',
            title: '用户中心',
            controller: 'user',
            templateUrl: 'views/user.html?150808'
        .when '/score',
            fun: 'score',
            title: '成绩查询',
            controller: 'score',
            templateUrl: 'views/score.html?150808'
        .when '/scoreAll',
            fun: 'scoreAll',
            title: '全班成绩',
            controller: 'scoreAll',
            templateUrl: 'views/scoreAll.html?150808'
        .when '/schedule',
            fun: 'schedule',
            title: '实时课表',
            controller: 'schedule',
            templateUrl: 'views/schedule.html?150808'
        .when '/exam',
            fun: 'exam',
            title: '考试安排',
            controller: 'exam',
            templateUrl: 'views/exam.html?150808'
        .when '/credit', 
            fun: 'credit',
            title: '学分绩点',
            controller: 'credit',
            templateUrl: 'views/credit.html?150808'
        .when '/classroom', 
            fun: 'classroom',
            title: '空闲教室',
            controller: 'classroom',
            templateUrl: 'views/classroom.html?150810'
        .when '/judge', 
            fun: 'judge',
            title: '教学评价',
            controller: 'judge',
            templateUrl: 'views/judge.html?150808'
        .when '/book', 
            fun: 'book',
            title: '图书续借',
            controller: 'book',
            templateUrl: 'views/book.html?150808'
        .when '/bookList', 
            fun: 'bookList',
            title: '图书检索',
            controller: 'bookList',
            templateUrl: 'views/bookList.html?150808'
        .when '/tuition', 
            fun: 'tuition',
            title: '学年学费',
            controller: 'tuition',
            templateUrl: 'views/tuition.html?150808'
        .when '/card', 
            fun: 'card',
            title: '校园一卡通',
            controller: 'card',
            templateUrl: 'views/card.html?150810'
        .when '/failRate', 
            fun: 'failRate',
            title: '挂科率统计',
            controller: 'failRate',
            templateUrl: 'views/failRate.html?150808'
        .when '/editUser', 
            fun: 'editUser',
            title: '修改权限',
            controller: 'editUser',
            templateUrl: 'views/editUser.html?150808'
        .when '/lastUser', 
            fun: 'lastUser',
            title: '最近使用用户',
            controller: 'lastUser',
            templateUrl: 'views/lastUser.html?150808'
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
    isPhone = document.body.offsetWidth < 1360
    sidebarElement = $('.ui.sidebar')
    #侧栏
    $scope.$watch ->
        $rootScope.user?.rank
    , ->
        sidebarElement.sidebar 'attach events', '#menu'
        if !isPhone
            sidebarElement.sidebar
                closable: false
                dimPage: false
                transition: 'overlay'
                
    #影藏导航栏
    $scope.sidebarHide = ->
        if isPhone then sidebarElement.sidebar 'hide'
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
        , passwd: 
            identifier: 'passwd'
            rules: [
                type  : 'empty'
                prompt: '密码不能为空！'
            ]
        , agreement: 
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
        $scope.terms = (k for k,v of $scope.data).sort (a, b) ->
            a < b

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

#空闲教室
classroomConller = ($scope, $rootScope, $timeout, getJsonpData) ->
    $rootScope.error = ''
    #阿拉伯转汉字
    $scope.nums = 
        '1'  : '一'
        '2'  : '二'
        '3'  : '三'
        '4'  : '四'
        '5'  : '五'
        '6'  : '六'
        '7'  : '七'
        '8'  : '八'
        '9'  : '九'
        '10' : '十'
        '11' : '十一'
        '12' : '十二'
        '13' : '十三'
        '14' : '十四'
        '15' : '十五'
        '16' : '十六'
        '17' : '十七'
        '18' : '十八'
        '19' : '十九'
        '20' : '二十'

    #教学楼代码
    $scope.builds = [
        ['103', '第一教学楼']
        ['102', '第二教学楼']
        ['104', '第三教学楼']
        ['105', '第四教学楼']
        ['107', '第五教学楼']
        ['110', '第八教学楼']
        ['111', '第九教学楼']
        ['212', '第十教学楼']
        ['213', '第十教附一楼']
        ['214', '第十教附二楼']
    ]
    $scope.build = '103'

    #周次代码
    $scope.weeks = []
    for i in [1..20]
        $scope.weeks.push [i, "第#{$scope.nums[i]}周"]

    #星期代码
    $scope.days = []
    for i in [1...7]
        $scope.days.push [i, "星期#{$scope.nums[i]}"]
    $scope.days.push [7, '星期日']

    #开始节数
    $scope.beginSessions = []
    for i in [1..5]
        $scope.beginSessions.push [i, "#{$scope.nums[i * 2 -1]}#{$scope.nums[i * 2]}节"]

    #结束节数
    $scope.endSessions = []
    for i in [1..5]
        $scope.endSessions.push [i, "至#{$scope.nums[i * 2 -1]}#{$scope.nums[i * 2]}节"]

    #获取当前时间日期
    date   = new Date()
    week   = $rootScope.user?.week || 0
    month  = date.getMonth() + 1
    day    = if date.getDay() is 0 then 7 else date.getDay()
    hour   = date.getHours()
    minute = date.getMinutes()
    #判断夏季作息时间表
    isSummer = if month >= 5 and month < 10 then true else false
    if hour < 8 or hour is 8 and isSummer and minute < 30
        $scope.beginSession = 1
        $scope.endSession   = 1
    else if hour < 10 or hour is 10 and isSummer and minute < 30
        $scope.beginSession = 2
        $scope.endSession   = 2
    else if hour < 14 or hour is 14 and isSummer and minute < 30
        $scope.beginSession = 3
        $scope.endSession   = 3
    else if hour < 16 or hour is 16 and isSummer and minute < 30
        $scope.beginSession = 4
        $scope.endSession   = 4
    else if hour < 19 or hour is 19 and isSummer and minute < 30
        $scope.beginSession = 5
        $scope.endSession   = 5
    else
        $scope.beginSession = 1
        $scope.endSession   = 1
        week = if day is 7 then week + 1 else week
        day = if day is 7 then 1 else day + 1

    if !week
        $scope.week = 1
    else if week > 20
        $scope.week = 20
    else 
        $scope.week = week
    $scope.day = day

    $scope.search = ->
        $rootScope.error = ''
        if !$scope.build or !$scope.week or !$scope.day or !$scope.beginSession or !$scope.endSession
            return $rootScope.error = '请填写完整表单'
        params = 
            build: $scope.build
            week : $scope.week
            day  : $scope.day
            beginSession: $scope.beginSession
            endSession  : $scope.endSession
        getJsonpData.query params, 8000, (data) ->
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
        $scope.terms = (k for k,v of $scope.data).sort (a, b) ->
            a < b

    $('.ui.positive.message').popup
        popup : $('.ui.flowing.popup')
        on    : 'hover'

#校园一卡通
cardController = ($scope, getJsonpData) ->
    getJsonpData.query {}, 8000, (data) ->
        $scope.info = data.info
        $scope.data = data.data

    #挂失与解挂
    $scope.card = (fun) ->
        msg = '您确定要' + if fun is 'cardLoss' then '挂失' else '解挂' + '吗？';
        if !confirm(msg)
            return
        params = 
            fun   : fun
            cardId: $scope.info.cardId
        getJsonpData.query params, 8000, (data) ->
            $scope.info = data.info

#挂科率统计
failRateController = ($scope, $rootScope, $timeout, getJsonpData) ->
    $rootScope.error = ''
    $scope.keys = []

    $scope.dropdown = (method) ->
        dropdown = $('.ui.search.dropdown .menu')
        styles   = dropdown.attr('class')
        if method is 'hide'
            if styles.indexOf('hidden') is -1
                dropdown.transition 'slide down out'
        else if styles.indexOf('visible') is -1
            dropdown.transition 'slide down in'
        return

    #检查输入框值的变化
    $scope.check = (key) ->
        $timeout ->
            if key is $scope.key
                $scope.completion key
        , 300

    #自动补全
    $scope.completion = (key) ->
        if !key then return $scope.keys = []
        getJsonpData.query {fun:'failRateKey', key:key}, 8000, (data) ->
            $scope.keys = data.data
            $scope.dropdown 'show'

    #搜索
    $scope.search = (key) ->
        $scope.dropdown 'hide'
        $scope.data = []
        if key then $scope.key = key
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
        , rank: 
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
hnust.controller 'classroom'  , classroomConller
hnust.controller 'judge'      , judgeController
hnust.controller 'book'       , bookController
hnust.controller 'bookList'   , bookListController
hnust.controller 'tuition'    , tuitionController
hnust.controller 'card'       , cardController
hnust.controller 'failRate'   , failRateController
hnust.controller 'editUser'   , editUserController
hnust.controller 'lastUser'   , lastUserController
hnust.filter     'sortBy'     , sortByFilter