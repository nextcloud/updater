$(function () {
	// Pass the auth token with any request
	$.ajaxSetup({
		headers: { 'Authorization': loginToken }
	});

	// Setup a global AJAX error handler
	$(document).ajaxError(
			function (event, xhr, options, thrownError) {
				$('#error').text('Server error '
						+ xhr.status
						+ ': '
						+ xhr.statusText
						+ "\n"
						+ 'Message: '
						+ thrownError
						+ 'See your webserver logs for details.'

						).show();
			}
	);

	var accordion = {
		setCurrent: function (stepId) {
			$('#progress .step').removeClass('current-step');
			if (typeof stepId !== 'undefined') {
				$(stepId).addClass('current-step');
			}
		},
		setDone: function (stepId) {
			$(stepId).removeClass('current-step');
			$(stepId).addClass('passed-step');
		},
		setContent: function (stepId, content, append) {
			var oldContent;
			if (typeof append !== 'undefined' && append) {
				oldContent = $(stepId).find('.output').html();
			} else {
				oldContent = '';
			}
			$(stepId).find('.output').html(oldContent + content);
		},
		showContent: function (stepId) {
			$(stepId).find('.output').show();
		},
		hideContent: function (stepId) {
			$(stepId).find('.output').hide();
		},
		toggleContent: function (stepId) {
			$(stepId).find('.output').toggle();
		}
	},
	
	handleResponse = function (response, callback, node) {
		if (response.error_code !== 0) {
			$('#error').text('Error ' + response.error_code).show();
		} else {
			$('#start-upgrade').show();
			$('#error').hide();
			if (typeof callback === 'function') {
				callback();
			}
		}
		if (typeof node !== 'undefined') {
			accordion.setContent(node, response.output);
			accordion.showContent(node);
			accordion.setDone(node);
		} else {
			$('#output').html($('#output').html() + response.output).show();
		}
	},
	
	execute = function (step) {
		var node = step.node;
		if (typeof step.node !== 'undefined') {
			accordion.setCurrent(step.node);
		}
		return $.post('', {command: step.command}, function (response) {
			step.onResponse(response, null, node);
		});
	},
	
	operationStack = [
		{command: 'upgrade:checkSystem', onResponse: handleResponse, node: '#step-check'},
		{command: 'upgrade:checkpoint --create', onResponse: handleResponse, node: '#step-checkpoint'},
		{command: 'upgrade:detect', onResponse: handleResponse, node: '#step-download'},
		{command: 'upgrade:disableNotShippedApps', onResponse: handleResponse, node: '#step-coreupgrade'},
		{command: 'upgrade:executeCoreUpgradeScripts', onResponse: handleResponse, node: '#step-coreupgrade'},
		{command: 'upgrade:upgradeShippedApps', onResponse: handleResponse, node: '#step-appupgrade'},
		{command: 'upgrade:enableNotShippedApps', onResponse: handleResponse, node: "#step-finalize"},
		{command: 'upgrade:restartWebServer', onResponse: handleResponse, node: "#step-finalize"},
		{command: 'upgrade:postUpgradeCleanup', onResponse: handleResponse, node: "#step-finalize"}
	],
	
	init = function(){
		execute({
			command: 'upgrade:detect --only-check',
			onResponse: function (response, callback, node) {
				handleResponse(response, null, node);
				accordion.setDone(node);
				accordion.setCurrent();
				if (!response.error_code) {
					accordion.setContent(node, '<button id="start-upgrade" class="side-button">Start</button>', true);
				} else {
					accordion.setContent(node, '<button id="recheck" class="side-button">Recheck</button>', true);
				}
			},
		node: '#step-init'
		});
	};

	//setup handlers
	$(document).on('click', '#create-checkpoint', function () {
		$(this).attr('disabled', true);
		$.post(
				'',
				{
					command: 'upgrade:checkpoint --create --exit-if-none'
				},
				function (response) {
					$('#create-checkpoint').attr('disabled', false);
					handleResponse(response);
				}
		);
	});

	$(document).on('click', '#progress h3', function () {
		if ($(this).parent('li').hasClass('passed-step')) {
			accordion.toggleContent('#' + $(this).parent('li').attr('id'));
		}
	});

	$(document).on('click', '#start-upgrade', function () {
		$('#output').html('');
		var looper = $.Deferred().resolve();
		$(this).attr('disabled', true);
		$.each(operationStack, function (i, step) {
			looper = looper.then(
					function () {
						return execute(step);
					}
			);
		});
	});

	$(document).on('click', '#recheck', init);
	
	init();
});
