/**
 * Ophelia module javascript: polling of the async extraction task status.
 */

/**
 * Start polling /extraction/process/status/{taskId} through document_card.php ajax relay
 * every 3 seconds until status is 'completed' or 'failed'.
 *
 * @param {string} taskId       Task id returned by ophelia-service
 * @param {number} documentId   Ophelia document id
 * @param {string} ajaxUrl      URL of the ajax relay (document_card.php?action=ajax_status)
 * @param {string} redirectUrl  URL to redirect to when processing is completed
 */
function opheliaStartPolling(taskId, documentId, ajaxUrl, redirectUrl) {
	var $bar = jQuery('#ophelia-progress-bar');
	var $msg = jQuery('#ophelia-status-message');

	var poll = function () {
		jQuery.ajax({
			url: ajaxUrl,
			type: 'GET',
			data: { task_id: taskId, document_id: documentId },
			dataType: 'json',
			success: function (data) {
				if (!data) {
					return;
				}

				var progress = data.progress || 0;
				$bar.css('width', progress + '%').text(progress + '%');
				$msg.text(data.message || '');

				if (data.status === 'completed') {
					$msg.text('Traitement termine. Redirection...');
					window.location.href = redirectUrl;
				} else if (data.status === 'failed') {
					$msg.text('Echec du traitement : ' + (data.message || ''));
				} else {
					setTimeout(poll, 3000);
				}
			},
			error: function () {
				$msg.text('Erreur lors de la verification du statut, nouvelle tentative...');
				setTimeout(poll, 5000);
			}
		});
	};

	poll();
}
