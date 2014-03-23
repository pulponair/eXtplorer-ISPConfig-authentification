<?php
define('_JEXEC', TRUE );
define('_VALID_MOS', TRUE );
require_once(__DIR__  . '/ispconfig.php');
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
 * Testcase for ext_ispconfig_authentication
 */
class ext_ispconfig_authenticationTest extends PHPUnit_Framework_TestCase {
	/**
	 * fixture
	 *
	 * @var ext_ispconfig_authentication
	 */
	protected $fixture;

	/**
	 * setup
	 *
	 * @return void
	 */
	public function setUp() {
		$this->fixture = $this->getMock('ext_ispconfig_authentication', array(
			'initializeDatabase', 'getISPConfigUser', 'domainBelongsToUser'));
	}

	/**
	 * teardown
	 *
	 * @return void
	 */
	public function tearDown() {
		unset($this->fixture);
	}

	/**
	 * Call protected/private method of a class.
	 *
	 * @param object &$object    Instantiated object that we will run method on.
	 * @param string $methodName Method name to call
	 * @param array  $parameters Array of parameters to pass into method.
	 *
	 * @return mixed Method return.
	 */
	public function invokeMethod(&$object, $methodName, array $parameters = array()) {
		$reflection = new \ReflectionClass(get_class($object));
		$method = $reflection->getMethod($methodName);
		$method->setAccessible(true);

		return $method->invokeArgs($object, $parameters);
	}

	/**
	 * test
	 *
	 * @test
	 * @return void
	 */
	public function onAuthenticatIsCallable() {
		$this->assertTrue(is_callable(array($this->fixture, 'onAuthenticate')));
	}


	/**
	 * test
	 *
	 * @test
	 * @return void
	 */
	public function onShowLoginFormIsCallable() {
		$this->assertTrue(is_callable(array($this->fixture, 'onShowLoginForm')));
	}

	/**
	 * test
	 *
	 * @test
	 * @return void
	 */
	public function onLogoutIsCallable() {
		$this->assertTrue(is_callable(array($this->fixture, 'onLogout')));
	}


	/**
	 * test
	 *
	 * @test
	 * @return void
	 */
	public function onAuthenticateReturnBoolean() {
		$this->assertTrue(is_bool($this->fixture->onAuthenticate(array())));
	}

	/**
	 * test
	 *
	 * @test
	 * @return void
	 */
	public function siteBelongsToUserReturnsBoolean() {
		$this->fixture->expects($this->any())
			->method('domainBelongsToUser')
			->will($this->returnValue(FALSE));

		$this->assertTrue(is_bool($this->invokeMethod($this->fixture, 'siteBelongsToUser', array(array()))));
	}

	/**
	 * test
	 *
	 * @test
	 * @return void
	 */
	public function siteBelongsToUserReturnsTrueForAdmin() {
		$this->assertTrue($this->invokeMethod($this->fixture, 'siteBelongsToUser', array(array('typ' => 'admin'))));
	}

	/**
	 * test
	 *
	 * @test
	 * @return void
	 */
	public function siteBelongsToUserReturnsFalseForNonAdminsThatDoNotOwnTheDomain() {
		$this->fixture->expects($this->any())
			->method('domainBelongsToUser')
			->will($this->returnValue(FALSE));

		$this->assertFalse($this->invokeMethod($this->fixture, 'siteBelongsToUser', array(array('typ' => 'user'))));
	}

	/**
	 * test
	 *
	 * @test
	 * @return void
	 */
	public function siteBelongsToUserTraversesTheHostname() {
		$_SERVER['HTTP_HOST'] = '1.2.3.4';
		$user = array('typ' => 'user');
		$this->fixture->expects($this->at(0))
			->method('domainBelongsToUser')
			->with($user, '1.2.3.4')
			->will($this->returnValue(FALSE));

		$this->fixture->expects($this->at(1))
			->method('domainBelongsToUser')
			->with($user, '2.3.4')
			->will($this->returnValue(FALSE));

		$this->fixture->expects($this->at(2))
			->method('domainBelongsToUser')
			->with($user, '3.4')
			->will($this->returnValue(FALSE));

		$this->fixture->expects($this->exactly(3))
			->method('domainBelongsToUser');

		$this->invokeMethod($this->fixture, 'siteBelongsToUser', array($user));
	}

	/**
	 * test
	 *
	 * @test
	 * @return void
	 */
	public function siteBelongsStopsTraversingIfDomainBelongsToUserReturnsTrueTheHostname() {
		$_SERVER['HTTP_HOST'] = '1.2.3.4';
		$user = array('typ' => 'user');
		$this->fixture->expects($this->once())
			->method('domainBelongsToUser')
			->will($this->returnValue(TRUE));

		$this->invokeMethod($this->fixture, 'siteBelongsToUser', array($user));
	}

}
 