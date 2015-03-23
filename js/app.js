/* global angular, backupCtrl, updateCtrl */
var app = angular.module('updater', [])

app.config(['$routeProvider', function($routeProvider) {
	$routeProvider.
	when('/index', { controller: backupCtrl } ).
	when('/update', { templateUrl: 'templates/partials/update.html', controller: updateCtrl } ).
	otherwise( { redirectTo: '/index' } );
}]);

app.directive('ngConfirmClick', [
  function() {
    return {
      priority: 1,
      restrict: 'A',
      link: function(scope, element, attrs) {
        element.bind('click', function(e) {
          var message = attrs.ngConfirmClick;
          if(message && !confirm(message)) {
            e.stopImmediatePropagation();
            e.preventDefault();
          }
        });
      }
    };
  }
]);