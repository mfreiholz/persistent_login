/**
 * Plugin which provides a persistent login functionality.
 * Also known as "remembery me" or "stay logged in" function.
 *
 * @version @package_version@
 * @author insaneFactory, Manuel Freiholz
 * @website http://manuel.insanefactory.com/
 */
$(document).ready(function() {

	if (window.rcmail) {

		rcmail.addEventListener('init', function() {
		
			// create "stay logged in" checkbox.
			var	text = '<tr><td>&nbsp;</td><td>';
				text+= '    <input type="checkbox" name="_ifpl" id="_ifpl" value="1">';
				text+= '    <label for="_ifpl">' + rcmail.gettext('ifpl_rememberme', 'persistent_login') + '</label>';
				text+= '  <div id="ifplnotice">' + rcmail.gettext('ifpl_rememberme_hint', 'persistent_login') + '</div>';
				text+= '</td></tr>';
			
			var element = $("div.boxcontent > form > table");
			
			if (element && element.length !== 0) {
				element.append(text);
			}
			else {
				$('form').append(text);
			}
			
			// show hint.
			$('#_ifpl').click(function() {
				var t = $(this);
				if (t.is(':checked')) {
					$('#ifplnotice').show();
				}
				else {
					$('#ifplnotice').hide();
				}
			});

		});
	}
	
});
