<?php
namespace Flowpack\Behat\Tests\Functional\Aop;

/*                                                                        *
 * This script belongs to the TYPO3 Flow package "Flowpack.Behat".        *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU Lesser General Public License, either version 3   *
 * of the License, or (at your option) any later version.                 *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Aop\JoinPointInterface;

/**
 * Capture output from command controllers for Behat tests
 *
 * @Flow\Aspect
 * @Flow\Scope("singleton")
 */
class ConsoleLoggingCaptureAspect {

	/**
	 * @var string
	 */
	protected $capturedOutput = '';

	/**
	 * @Flow\Around("method(TYPO3\Flow\Cli\ConsoleOutput->output())")
	 * @param JoinPointInterface $joinPoint
	 */
	public function captureOutput(JoinPointInterface $joinPoint) {
		$text = $joinPoint->getMethodArgument('text');
		$arguments = $joinPoint->getMethodArgument('arguments');
		if ($arguments !== array()) {
			$text = vsprintf($text, $arguments);
		}

		$this->capturedOutput .= $text;
	}

	/**
	 * Reset captured output
	 */
	public function reset() {
		$this->capturedOutput = '';
	}

	/**
	 * @return string
	 */
	public function getCapturedOutput() {
		return $this->capturedOutput;
	}
}