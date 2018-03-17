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
			var	checkb = '<tr><td colspan="2" class="input"><div id="ifplcontainer">';
				checkb+= '  <div>';
				checkb+= '    <input type="checkbox" name="_ifpl" id="_ifpl" value="1">';
				checkb+= '    <label for="_ifpl">' + rcmail.gettext('ifpl_rememberme', 'persistent_login') + '</label>';
				checkb+= '  </div>';
				checkb+= '</div></td></tr>';
				
			var hint = rcmail.gettext('ifpl_rememberme_hint', 'persistent_login');
			
			$("table").append(checkb);
			$('.box-inner').append('<div id="plhint"></div>');			
			$('#login-bottomline').html(hint);
			$('head').append('<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0, user-scalable=no">');
			
			$("form").before("<span class='hline'>" + rcmail.gettext('ifpl_sitetitle', 'persistent_login') + "</span>");
			$('.boxtitle').html(rcmail.gettext('ifpl_sitetitle', 'persistent_login'));
			document.getElementById("rcmloginuser").placeholder = "Benutzername";
			document.getElementById("rcmloginpwd").placeholder = "Passwort"; 
			document.body.style.background = "url('" + rcmail.env.bodyBackground + "') no-repeat center center fixed";
			// show hint.
			$('#_ifpl').click(function() {
				var t = $(this);
				if (t.is(':checked')) {
					$('#login-bottomline').show();
					$('#plhint').html(hint);
				}
				else {
					$('#login-bottomline').hide();
					$('#plhint').html('');
				}
			});

		});

	}
	
});
