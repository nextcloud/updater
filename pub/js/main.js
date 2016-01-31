$(function() {
	var handleResponse = function(response, callback){
		$('#progress').hide();
		if (response.error_code !== 0){
			$('#error').text('Error ' + response.error_code).show();
		} else {
			$('#start-upgrade').show();
			$('#error').hide();
			if (typeof callback === 'function'){
				callback();
			}
		}
		$('#output').html($('#output').html() + response.output).show();
	},
	
	execute  = function(step){
		return $.post('', { command: step.command }, step.onResponse);
	},
	
	operationStack = [
		{ command : 'upgrade:detect', onResponse : handleResponse },
		{ command : 'upgrade:checkSystem', onResponse : handleResponse },
		{ command : 'upgrade:checkpoint --create', onResponse : handleResponse },
		{ command : 'upgrade:disableNotShippedApps', onResponse : handleResponse },
		{ command : 'upgrade:executeCoreUpgradeScripts', onResponse : handleResponse },
		{ command : 'upgrade:upgradeShippedApps', onResponse : handleResponse },
		{ command : 'upgrade:enableNotShippedApps', onResponse : handleResponse },
		{ command : 'upgrade:restartWebServer', onResponse : handleResponse }
	]
	;
	// Setup a global AJAX error handler
	$(document).ajaxError(
		function(event, xhr, options, thrownError){
			$('#error').text('Server error ' 
					+ xhr.status 
					+ ': ' 
					+ xhr.statusText
					+ 'See your webserver logs for details.'
					
			).show();
		}
	);

	execute({ command : 'upgrade:detect --only-check', onResponse : handleResponse  });
	
	//setup handlers
	$(document).on('click', '#create-checkpoint', function (){
		$(this).attr('disabled', true);
		$('#progress').show();
		$.post(
			'',
			{
				command : 'upgrade:checkpoint --create'
			},
			function (response){
				$('#create-checkpoint').attr('disabled', false);
				handleResponse(response);
			}
		);
	});
	
	$(document).on('click', '#start-upgrade', function (){
		$('#output').html('');
		var looper = $.Deferred().resolve();
		$(this).attr('disabled', true);
		$('#progress').show();
		$.each(operationStack, function(i, step) {
			looper = looper.then(
				function() {
					return execute(step);
				}
			);
		});
	});
});
