function indexCtrl($scope) {

}
;

function updateCtrl($scope, $http) {
	$scope.step = 0;
	$scope.backup = '';
	$scope.version = '';
	$scope.url = '';
	
	$scope.fail = function(data){
		var message = t('updater', 'The update was unsuccessful. Please check logs at admin page and report this issue to the <a href="https://github.com/owncloud/apps/issues" target="_blank">ownCloud community</a>.');
		if (data && data.message){
			message = data.message;
		}
		$('<span></span>').addClass('error').append(message).append('<br />').appendTo($('#upd-progress'));
	};
	
	$scope.crash = function(){
		var message = t('updater', 'Server error. Please check webserver log file for details');
		$('<span></span>').addClass('error').append(message).append('<br />').appendTo($('#upd-progress'));
	};
	
	$scope.update = function() {
		if ($scope.step == 0){
			$('#upd-progress').empty().show();
			$('#upd-step-title').text(t('updater', 'Creating backup...')).show();
			$('#updater-start').hide();
			
			$http.get(OC.filePath('updater', 'ajax', 'backup.php'), {headers: {'requesttoken': oc_requesttoken}})
			.success(function(data) {
				if (data && data.status && data.status == 'success'){
					$scope.step = 1;
					$scope.backup = data.backup;
					$scope.version = data.version;
					$scope.url = data.url;
					$scope.update();
				} else {
					$scope.fail(data);
					$('#updater-start').text(t('updater', 'Retry')).show();
				}
			})
			.error($scope.crash);

		} else if ($scope.step == 1) {
			$('#upd-step-title').text(t('updater', 'Downloading package...'));
			$('<span></span>').append(t('updater', 'Here is your backup: ') + $scope.backup).append('<br />').appendTo($('#upd-progress'));
			$http.post(
					OC.filePath('updater', 'ajax', 'download.php'),
					{ 
						url : $scope.url,
						version : $scope.version
					},
					{headers: {'requesttoken': oc_requesttoken}}
			).success(function(data) {
				if (data && data.status && data.status == 'success'){
					$scope.step = 2;
					$scope.update();
				} else {
					$scope.fail(data);
				}
			})
			.error($scope.crash);
		
		} else if ($scope.step == 2) {
			$('#upd-step-title').text(t('updater', 'Moving files...'));
			$http.post(
					OC.filePath('updater', 'ajax', 'update.php'),
					{ 
						url : $scope.url,
						version : $scope.version,
						backupPath : $scope.backup
					},
					{headers: {'requesttoken': oc_requesttoken}}
			).success(function(data) {
				if (data && data.status && data.status == 'success'){
					$scope.step = 3;
					var href = '/',
					title = t('updater', 'Proceed');
					if (OC.webroot!=''){
						href = OC.webroot;
					}
					$('<span></span>').append(t('updater', 'All done. Click to the link below to start database upgrade.')).append('<br />').appendTo($('#upd-progress'));
					$('<span></span>').addClass('bold').append('<br />').append('<a href="' + href + '">' + title + '</a>').appendTo($('#upd-progress'));
				} else {
					$scope.fail(data);
				}
			})
			.error($scope.crash);
		}
	};
}
;

function backupCtrl($scope, $http) {
	$http.get(OC.filePath('updater', 'ajax', 'backup/list.php'), {headers: {'requesttoken': oc_requesttoken}})
			.success(function(data) {
		$scope.entries = data.data;
	});

	$scope.doDelete = function(name) {
		$http.get(OC.filePath('updater', 'ajax', 'backup/delete.php'), {
			headers: {'requesttoken': oc_requesttoken},
			params: {'filename': name}
		}).success(function(data) {
			$http.get(OC.filePath('updater', 'ajax', 'backup/list.php'), {headers: {'requesttoken': oc_requesttoken}})
					.success(function(data) {
				$scope.entries = data.data;
			});
		});
	}
	$scope.doDownload = function(name) {
		window.open(OC.filePath('updater', 'ajax', 'backup/download.php')
				+ '?requesttoken=' + oc_requesttoken
				+ '&filename=' + name
				);
	}
}
;
