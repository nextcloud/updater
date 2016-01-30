$(function() {
	$.post(
			'',
			{
				command : 'upgrade:detect --only-check'
			},
			function (response){
				$('#output').html(response.output);
			}
	);
	
	$('#create-checkpoint').click(function (){
		$(this).attr('disabled', true);
		$.post(
			'',
			{
				command : 'upgrade:checkpoint --create'
			},
			function (response){
				$('#output').html(response.output);
				$('#create-checkpoint').attr('disabled', false);
			}
		);
	});
	
});
