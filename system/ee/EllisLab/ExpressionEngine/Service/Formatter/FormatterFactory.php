<?php
/**
 * ExpressionEngine (https://expressionengine.com)
 *
 * @link      https://expressionengine.com/
 * @copyright Copyright (c) 2003-2017, EllisLab, Inc. (https://ellislab.com)
 * @license   https://expressionengine.com/license
 */

namespace EllisLab\ExpressionEngine\Service\Formatter;

use EE_Lang;
use EllisLab\ExpressionEngine\Core\Provider;

/**
 * Formatter Factory
 */
class FormatterFactory {

	/**
	 * @var object $lang EE_Lang
	 **/
	private $lang;

	/**
	 * @var int bitwise mask of options
	 */
	protected $options;


	/**
	 * Constructor
	 *
	 * @param object EllisLab\ExpressionEngine\Core\Provider
	 * @param integer bitwise-defined options
	 * @param object EE_Lang
	 */
	public function __construct(EE_Lang $lang, $options)
	{
		$this->lang = $lang;
		$this->options = $options;
	}

	/**
	 * Helper function to create a formatter object
	 *
	 * @param String $formatter_name Formatter
	 * @param mixed $content The content to be formatted
	 * @return Object Formatter
	 */
	public function make($formatter_name, $content)
	{
		$formatter_class = implode('', array_map('ucfirst', explode('_', $formatter_name)));

		$class = __NAMESPACE__."\\Formats\\{$formatter_class}";

		if (class_exists($class))
		{
			return new $class($content, $this->lang, $this->options);
		}

		throw new \Exception("Unknown formatter: `{$formatter_name}`.");
	}
}

// EOF
