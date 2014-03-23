<?php
if( !defined( '_JEXEC' ) && !defined( '_VALID_MOS' ) ) die( 'Restricted access' );
/**
 * @package eXtplorer
 * @copyright Nikolas Hagelstein
 * @author Nikolas Hagelstein, <nikolas.hagelstein@gmail.com>
 *
 *
 * @license
 * The contents of this file are subject to the Mozilla Public License
 * Version 1.1 (the "License"); you may not use this file except in
 * compliance with the License. You may obtain a copy of the License at
 * http://www.mozilla.org/MPL/
 *
 * Software distributed under the License is distributed on an "AS IS"
 * basis, WITHOUT WARRANTY OF ANY KIND, either express or implied. See the
 * License for the specific language governing rights and limitations
 * under the License.
 *
 * Alternatively, the contents of this file may be used under the terms
 * of the GNU General Public License Version 2 or later (the "GPL"), in
 * which case the provisions of the GPL are applicable instead of
 * those above. If you wish to allow use of your version of this file only
 * under the terms of the GPL and not to allow others to use
 * your version of this file under the MPL, indicate your decision by
 * deleting  the provisions above and replace  them with the notice and
 * other provisions required by the GPL.  If you do not delete
 * the provisions above, a recipient may use your version of this file
 * under either the MPL or the GPL."
 *
 */

/**
 * This file handles ispconfig authentication
 *
 */
class ext_ispconfig_authentication {

	/**
	 * Initializes the database connection
	 *
	 * @return resource
	 */
	protected function initializeDatabase() {
		$db = mysql_connect(
			$GLOBALS['ext_conf']['ispconfig']['dbHost'],
			$GLOBALS['ext_conf']['ispconfig']['dbUser'],
			$GLOBALS['ext_conf']['ispconfig']['dbPassword']);

		mysql_select_db($GLOBALS['ext_conf']['ispconfig']['dbSchema'], $db);
		return $db;
	}

	/**
	 * Gets the ISPConfig user record
	 *
	 * @param string $username
	 * @return array
	 */
	protected function getISPConfigUser($username) {
		$query = sprintf("SELECT * FROM sys_user where username='%s'",
			mysql_real_escape_string($username));

		return  mysql_fetch_assoc(mysql_query($query));
	}

	/**
	 * Checks if given and stored password are matching. Takes encryption into account
	 *
	 * @param $givenPassword
	 * @param $storedPassword
	 * @return bool
	 */
	protected function givenPasswordMatchesStoredPassword($givenPassword, $storedPassword) {
		$storedPassword = stripslashes($storedPassword);

		if (substr($storedPassword, 0 , 3) == '$1$') {
			$salt = '$1$' . substr($storedPassword, 3, 8) . '$';
			$cryptGivenPassword = crypt(stripslashes($givenPassword), $salt);
		} else {
			$cryptGivenPassword = md5($givenPassword);
		}

		return $cryptGivenPassword === $storedPassword;
	}

	/**
	 * Checks if a given domain belongs to a given user
	 *
	 * @param $user
	 * @param $domain
	 * @return bool
	 */
	protected function domainBelongsToUser($user, $domain) {
		$result = mysql_query('SELECT * FROM web_domain WHERE domain="' . $domain . '"' .
			' AND sys_groupid in (' . $user['groups'] . ')');
		return mysql_num_rows($result) === 1;
	}

	/**
	 * Checks if the current site belongs to the user
	 *
	 * @param $user
	 * @return bool
	 */
	protected function siteBelongsToUser($user) {
		if ($user['typ'] === 'admin') {
			$siteBelongsToUser = TRUE;
		} else {
			$parts = explode('.', $_SERVER['HTTP_HOST']);
			do {
				$siteBelongsToUser = $this->domainBelongsToUser($user, implode('.', $parts));
				array(array_shift($parts));
			} while (count($parts) > 1 && $siteBelongsToUser === FALSE);
		}
		return $siteBelongsToUser;
	}

	/**
	 * The actual authentication method.
	 *
	 * @param $credentials
	 * @param null $options
	 * @return bool
	 * @api
	 */
	public function onAuthenticate($credentials, $options = null ) {
		$siteBelongsToUser = false;

		$this->initializeDatabase();
		$user = $this->getISPConfigUser($credentials['username']);

		$loginSuccessFull = $this->givenPasswordMatchesStoredPassword($credentials['password'], $user['passwort']);
		if ($loginSuccessFull && $siteBelongsToUser = $this->siteBelongsToUser($user)) {
			$_SESSION['credentials_ispconfig']['username'] = $credentials['username'];
			$_SESSION['credentials_ispconfig']['password'] = $credentials['password'];
			$_SESSION['file_mode'] = 'ispconfig';
		}

		return $loginSuccessFull && $siteBelongsToUser;
	}

	/**
	 * Shows the login form
	 *
	 * @return void
	 * @api
	 */
	public function onShowLoginForm() {

		if (!$this->initializeDatabase()) {
			$statusMessage = 'Connection to ISPConfig database failed. Please check database configuration!';
		} else {
			$statusMessage = '';
		}

		if (!ext_isXHR()) {
			$renderTo = 'renderTo: "adminForm",';
			$cancelButtonText = ext_Lang::msg( 'btnreset', true );
			$cancelButtonHandler = 'simple.getForm().reset();';
		} else {
			$renderTo = '';
			$cancelButtonText = ext_Lang::msg( 'btncancel', true );
			$cancelButtonHandler = 'Ext.getCmp("dialog").destroy(); ';
		}

		$url = basename( $GLOBALS['script_name']);

		$languageOptions = array();
		foreach (get_languages() as $key => $value) {
			$languageOptions[] = '["' . $key  .'", "' . $value . '"]';
		};

		$languageOptions = implode(', ', $languageOptions);

		$currentLanguage = ext_Lang::detect_lang();

		$labelsAndMessages = array(
			ext_Lang::msg('actlogin'),
			ext_Lang::err('error', true),
			ext_Lang::msg('miscusername', true),
			ext_Lang::msg('miscpassword', true),
			ext_Lang::msg('misclang', true),
			ext_Lang::msg('btnlogin', true),
			ext_Lang::err('error', true)
			);

		echo <<<EOT
		{
			xtype: "form",
			$renderTo
			title: "$labelsAndMessages[0]",
			id: "simpleform",
			labelWidth: 125, // label settings here cascade unless overridden
			url: "$url",
			frame: true,
			keys: {
				key: Ext.EventObject.ENTER,
				fn  : function(){
					if (simple.getForm().isValid()) {
						Ext.get( "statusBar").update( "Please wait..." );
						Ext.getCmp("simpleform").getForm().submit({
							reset: false,
							success: function(form, action) { location.reload() },
							failure: function(form, action) {
								if( !action.result ) return;
									Ext.Msg.alert('$labelsAndMessages[1]', action.result.error, function() {
										this.findField( 'password').setValue('');
										this.findField( 'password').focus();
									}, form );
									Ext.get( 'statusBar').update( action.result.error );
							},
							scope: Ext.getCmp("simpleform").getForm(),
							params: {
								option: "com_extplorer",
								action: "login",
								type : "ispconfig"
							}
						});
					} else {
						return false;
					}
				}
			},
			items: [{
					xtype:"textfield",
					fieldLabel: "$labelsAndMessages[2]",
					name: "username",
					width:175,
					allowBlank:false
				},{
					xtype:"textfield",
					fieldLabel: "$labelsAndMessages[3]",
					name: "password",
					inputType: "password",
					width:175,
					allowBlank:false
				}, new Ext.form.ComboBox({
					fieldLabel: "$labelsAndMessages[4]",
					store: new Ext.data.SimpleStore({
						fields: ['language', 'langname'],
						data : [$languageOptions]
					}),
					displayField:"langname",
					valueField: "language",
					value: "$currentLanguage",
					hiddenName: "lang",
					disableKeyFilter: true,
					editable: false,
					triggerAction: "all",
					mode: "local",
					allowBlank: false,
					selectOnFocus:true
				}), {
					xtype: "displayfield",
					id: "statusBar",
					value: "$statusMessage"
				}
			],
			buttons: [{
				text: "$labelsAndMessages[5]",
				type: "submit",
				handler: function() {
					Ext.get( "statusBar").update( "Please wait..." );
					Ext.getCmp("simpleform").getForm().submit({
						reset: false,
						success: function(form, action) { location.reload() },
						failure: function(form, action) {
							if( !action.result ) return;
							Ext.Msg.alert('$labelsAndMessages[6]', action.result.error, function() {
								this.findField( 'password').setValue('');
								this.findField( 'password').focus();
							}, form );
							Ext.get( 'statusBar').update( action.result.error );
						},
						scope: Ext.getCmp("simpleform").getForm(),
						params: {
							option: "com_extplorer",
							action: "login",
							type : "ispconfig"
						}
					});
				}
				}, {
					text: "$cancelButtonText",
					handler: function() { $cancelButtonHandler }
				}
			]
		}
EOT;
	}

	/**
	 * The logout
	 *
	 * @return void
	 * @api
	 */
	public function onLogout() {
		logout();
	}

}
?>