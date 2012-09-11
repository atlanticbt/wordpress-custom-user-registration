<?php

//
//class SingletonExample2 extends Singleton {
//	#region ---------- FACTORY --------------
//	
//	/**
//	 * Return singleton instance
//	 * Workaround for PHP < 5.3.0
//	 */
//	static public function instance(){
//		return parent::instance(__CLASS__);
//	}//--	fn	instance
//	
//	#endregion ---------- FACTORY --------------
//	
//	private $foo = 'foo';
//	
//	public function getFoo(){ return $this->foo; }
//	
//	public function init($foo){ $this->foo = $foo; }
//}///---	SingletonExample
//
//$pagination = SingletonExample1::instance();	//prepare for pagination
//echo '<h3>SingletonExample1::printInstances</h3>';
//SingletonExample1::printInstances();
//
//$example = SingletonExample2::instance();
//echo '<h3>SingletonExample2::printInstances</h3>';
//SingletonExample2::printInstances();
//$example->init('bar');
//
//echo '<h3>SingletonExample1::printInstances</h3>';
//SingletonExample1::printInstances();



/**
 * Inheritable class for basic factory methods
 * @author jeremys
 * 
 * @see http://php.net/manual/en/language.oop5.patterns.php
 * @see http://www.php.net/manual/en/language.oop5.patterns.php#93677
 * @see http://www.php.net/manual/en/language.oop5.patterns.php#95196
 *
 */
abstract class Singleton {
	
	#region ---------- restrict access to basic stuff ---------------
	/**
	 * Restrict access to constructor
	 */
	protected function __construct(){ }
	/**
	 * Prevent cloning
	 */
	final private function __clone() {
		trigger_error('Clone is not allowed.', E_USER_ERROR);
	}
	/**
	 * Prevent unserializing
	 */
	final private function __wakeup() {
		trigger_error('Unserializing is not allowed.', E_USER_ERROR);
	}
	#endregion ---------- restrict access to basic stuff ---------------
	
	
	
	
	#region -------------------- Factory / Singleton -------------------

	/**
	 * Instances of child classes
	 * @var unknown_type
	 */
	private static $instances = array();
	
	/**
	 * Create singleton instance
	 * @param $c {optional} a workaround for PHP < 5.3.0
	 * @return an instance of the class
	 */
	/*final*/ static public function instance($c = null) {
		if( ! $c ){
			$c = get_called_class();	// PHP > 5.3.0
		}
		
		if ( ! isset(self::$instances[$c]) ) {
			self::$instances[$c] = new $c;
		}
		return self::$instances[$c];
	}
	
	/**
	 * Debugging
	 */
	static public function printInstances(){
		print_r(self::$instances);
	}
	
	#endregion -------------------- Factory / Singleton -------------------
	
	
	
	/* --------------- begin class methods here --------------- */
	
	/**
	 * Takes the place of regular constructor
	 * ///TODO: declare in implementation
	 */
	#public function init(){
	#}//--	fn	init

}///---	"abstract class"	FactoryObjectAbstract
