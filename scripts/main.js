(function() {
    //消息弹窗
    Messenger.options = {
        extraClasses: 'messenger-fixed messenger-on-bottom',
        theme: 'flat'
    }
    //AngularJS
    var hnust = angular.module('hnust', ['ngRoute', 'ngCookies']);

    hnust.config(function ($routeProvider, $httpProvider) {
        //设置默认headers
        $httpProvider.defaults.headers.post['Content-Type'] = 'application/x-www-form-urlencoded';

        //路由
        $routeProvider.when('/login', {
            controller: login,
            templateUrl: 'views/login.html'
        }).when('/agreement', {
            controller: agreement,
            templateUrl: 'views/agreement.html'
        }).when('/score', {
            controller: score,
            templateUrl: 'views/score.html'
        }).when('/schedule', {
            controller: schedule,
            templateUrl: 'views/schedule.html'
        }).when('/exam', {
            controller: exam,
            templateUrl: 'views/exam.html'
        }).when('/credit', {
            controller: credit,
            templateUrl: 'views/credit.html'
        }).when('/tuition', {
            controller: tuition,
            templateUrl: 'views/tuition.html'
        }).when('/judge', {
            controller: judge,
            templateUrl: 'views/judge.html'
        }).when('/book', {
            controller: book,
            templateUrl: 'views/book.html'
        }).when('/card', {
            controller: card,
            templateUrl: 'views/card.html'
        }).when('/editUser', {
            controller: editUser,
            templateUrl: 'views/editUser.html'
        }).when('/lastUser', {
            controller: lastUser,
            templateUrl: 'views/lastUser.html'
        }).otherwise({
            redirectTo: '/score'
        });
    });

    //主控
    var main = function($scope, $rootScope, $http, $location, $cookies) {
        $('#sidebar').sidebar('attach events', '#menu');

        //网址根路径
        $rootScope.url = 'http://a.hnust.sinaapp.com/index.php';

        //监视网址变化
        $scope.$watch(function() {
            return [$cookies, $location.url()];
        }, function() {
            $rootScope.hideNav = (navigator.userAgent == 'demo')? true:false;
            $rootScope.params = $location.search();
            $rootScope.rank = $cookies.rank || '-1';
            $rootScope.studentId = $cookies.studentId || '游客';
        }, true);

        //提示信息
        $rootScope.popUp = function(msg) {
            Messenger().post({
                message: msg,
                showCloseButton: true
            });
        }

        //检查状态
        $rootScope.checkStatus = function(data) {
            //弹窗
            if (_.isObject(data) == false) {
                $rootScope.popUp('服务器错误！');
                return false;
            } else if (['1', '2'].indexOf(data.code) != -1) {
                $rootScope.popUp(data.msg);
            }

            if (data.code == '-1') {
                $rootScope.error = data.msg || '网络连接超时，请刷新或稍后再试。'
            //返回上一页
            } else if (data.code == '2') {
                window.history.back();
            //跳至登陆
            } else if (data.code == '3') {
                $cookies.rank = '';
                $cookies.studentId = '';
                $cookies.referer = $location.url();
                $location.url('/login');
            //跳回记录页面
            } else if (data.code == '4') {
                if ($cookies.referer && ($cookies.referer != '/login')) {
                    $location.url($cookies.referer);
                    $cookies.referer = '';
                } else {
                    $location.url('/score');
                }
                return true;
            //错误提示
            } else if (data.code == '5') {
                $rootScope.error = data.msg;
            } else {
                return true
            }
            return false;
        }

        //请求数据
        $rootScope.jsonp = function(params, timeout, callback) {
            $('#sidebar').sidebar('hide');
            $rootScope.error  = '';

            params = params || {};
            params.callback = 'JSON_CALLBACK';
            timeout = timeout || 8000;
            callback = angular.isFunction(callback)? callback:function(response){};

            $rootScope.loading = true;
            $http.jsonp($rootScope.url, {
                params: params,
                timeout: timeout
            }).success(function(response) {
                $rootScope.loading = false;
                if ($rootScope.checkStatus(response)) {
                    if (response.code == '6') {
                        data.passwd = prompt(response.msg, '');
                        if (data.passwd) {
                            $rootScope.jsonp(params, timeout, callback);
                        } else {
                            $rootScope.error = '密码错误！';
                        }
                    } else {
                        callback(response);
                    }
                }
            }).error(function() {
                $rootScope.loading = false;
                $rootScope.checkStatus({'code':'-1'});
            });
        };
    };

    //登录
    var login = function($scope, $rootScope, $cookies) {
        $('#sidebar').sidebar('hide');
        $('.ui.checkbox').checkbox();
        if ($cookies.rank && ($cookies.rank > '-1')) {
            return $rootScope.checkStatus({code:4});
        }
        $rootScope.fun = 'login';
        $rootScope.title = '用户登录';
        $scope.studentId = $scope.passwd = '';

        $('.ui.form').form({
            studentId: {
                identifier: 'studentId',
                rules: [{
                    type  : 'empty',
                    prompt: '学号不能为空！'
                }, {
                    type  : 'length[10]',
                    prompt: '学号不能少于10位！'
                }, {
                    type  : 'maxLength[10]',
                    prompt: '学号不能大于10位！'
                }]
            },
            passwd: {
                identifier: 'passwd',
                rules: [{
                    type  : 'empty',
                    prompt: '密码不能为空！'
                }]
            },
            agreement: {
                identifier: 'agreement',
                rules: [{
                    type  : 'checked',
                    prompt: '同意用户使用协议方可使用！'
                }]
            } 
        }, {
            inline: true,
            on    : 'blur',
            onSuccess: function() {
                var params = {
                    fun : 'login',
                    passwd : $scope.passwd,
                    studentId : $scope.studentId,
                };
                $rootScope.jsonp(params, 8000, function(data) {
                    var info = data.info || {};
                    $cookies.rank = info.rank || '-1';
                    $cookies.studentId = info.studentId || '游客';
                });
                return true;
            }
        });
    };

    var agreement = function($scope, $rootScope) {
        $rootScope.title = '用户使用协议';
        $rootScope.params.fun = 'agreement';
    };

    //成绩
    var score = function($scope, $rootScope) {
        $rootScope.title = '成绩查询';
        $rootScope.params.fun = 'score';
        $rootScope.jsonp($rootScope.params, 10000, function(data) {
            $scope.data = data.data;
            $scope.terms = _.keys(data.data).reverse();
        });
    };

    //课表
    var schedule = function($scope, $rootScope) {
        $rootScope.title = '实时课表';
        $rootScope.params.fun = 'schedule';
        $rootScope.jsonp($rootScope.params, 15000, function(data) {
            $scope.data = data.data;
            $scope.info = data.info;
            $('.menu .item').tab();
        });
    };

    //考试
    var exam = function($scope, $rootScope) {
        $rootScope.title = '考试安排';
        $rootScope.params.fun = 'exam';
        $rootScope.jsonp($rootScope.params, 15000, function(data) {
            $scope.data = data.data;
        });
    };

    //学分绩点
    var credit = function($scope, $rootScope) {
        $rootScope.title = '学分绩点';
        $rootScope.params.fun = 'credit';
        $rootScope.jsonp($rootScope.params, 15000, function(data) {
            $scope.data = data.data;
        });
    };

    //学费
    var tuition = function($scope, $rootScope) {
        $rootScope.title = '学年学费';
        $rootScope.params.fun = 'tuition';
        $rootScope.jsonp($rootScope.params, 15000, function(data) {
            $scope.total = data.data[0];
        });
    };

    //教学评价
    var judge = function($scope, $rootScope) {
        $rootScope.title = '教学评价';
        $rootScope.params.fun = 'judge';
        $rootScope.jsonp($rootScope.params, 15000, function(data) {
            $scope.data = data.data;
        });

        $scope.judge = function(item) {
            $('.ui.checkbox').checkbox();
            $('.ui.form').form('clear');
            $scope.judging = item;
        }
        
        $scope.submit = function() {
            $rootScope.error = '';
            var data = {params:$scope.judging.params}, flag = true;
            for (var i = 0; i < 10; i++) {
                data['a' + i] = $("input[name='a" + i + "']:checked").val();
                if (angular.isUndefined(data['a' + i])) {
                    $rootScope.error = '请确定表单已填写完整。';
                    return false;
                }
                if ((i != 0) && (data['a' + i] != data['a' + (i - 1)])) {
                    flag = false;
                }
            }
            if (flag) {
                $rootScope.error = '不能全部选择相同的选项。';
                return false;
            }
            var params = {
                fun  : 'judge',
                data : angular.toJson(data),
            }
            $rootScope.jsonp(params, 15000, function(data) {
                if (data.code == '0') {
                    $scope.judging = false;
                    $scope.data = data.data;
                }
            });
        }
    };

    //图书续借
    var book = function($scope, $rootScope) {
        $rootScope.title = '图书续借';
        $rootScope.params.fun = 'book';
        $rootScope.jsonp($rootScope.params, 8000, function(data) {
            $scope.data = data.data;
        });

        //续借
        $scope.renew = function(params) {
            params.fun = 'book'
            $rootScope.jsonp(params, 8000, function(data) {
                $scope.data = data.data;
            });
        }
    };

    //校园一卡通
    var card = function($scope, $rootScope) {
        $rootScope.title = '校园一卡通';
        $rootScope.params.fun = 'card';
        $rootScope.jsonp($rootScope.params, 15000, function(data) {
            $scope.info = data.info;
            $scope.data = data.data;
        });
    };

    //修改权限
    var editUser = function($scope, $rootScope, $location, $cookies) {
        $('#sidebar').sidebar('hide');
        if (!$cookies.rank || ($cookies.rank == '-1')) {
            return $location.url('/login');
        }
        $rootScope.error = '';
        $rootScope.title = '修改权限';
        $rootScope.params.fun = 'editUser';
        $scope.studentId = '';

        $('.ui.dropdown').dropdown();
        $('.ui.form').form({
            studentId: {
                identifier: 'studentId',
                rules: [{
                    type  : 'empty',
                    prompt: '学号不能为空！'
                }, {
                    type  : 'length[10]',
                    prompt: '学号不能少于10位！'
                }, {
                    type  : 'maxLength[10]',
                    prompt: '学号不能大于10位！'
                }]
            },
            rank: {
                identifier: 'rank',
                rules: [{
                    type  : 'empty',
                    prompt: '权限不能为空！'
                }]
            },
        }, {
            inline: true,
            on    : 'blur',
            onSuccess: function() {
                var params = {
                    fun: 'editUser',
                    studentId: $scope.studentId,
                    rank     : $("select[name='rank']").val(),
                };
                $rootScope.jsonp(params);
                return false;
            }
        });
    };

    //最近使用用户
    var lastUser = function($scope, $rootScope) {
        $rootScope.title = '最近使用用户';
        $rootScope.params.fun = 'lastUser';
        $rootScope.jsonp($rootScope.params, 8000, function(data) {
            $scope.data = data.data;
        });
    };

    //函数注入
    hnust.controller('main'    , main);
    hnust.controller('login'   , login);
    hnust.controller('score'   , score);
    hnust.controller('schedule', schedule);
    hnust.controller('exam'    , exam);
    hnust.controller('credit'  , credit);
    hnust.controller('tuition' , tuition);
    hnust.controller('judge'   , judge);
    hnust.controller('book'    , book);
    hnust.controller('card'    , card);
    hnust.controller('editUser', editUser);
    hnust.controller('lastUser', lastUser);
})();