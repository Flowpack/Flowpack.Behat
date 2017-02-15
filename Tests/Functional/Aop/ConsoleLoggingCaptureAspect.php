<?php
namespace Flowpack\Behat\Tests\Functional\Aop;

/*                                                                   *
 * This script belongs to the Flow package "Flowpack.Behat".         *
 *                                                                   */

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Aop\JoinPointInterface;

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
	 * @var boolean
	 */
	protected $sendConsoleOutput = TRUE;

	/**
	 * @Flow\Around("method(Neos\Flow\Cli\ConsoleOutput->output())")
	 * @param JoinPointInterface $joinPoint
	 */
	public function captureOutput(JoinPointInterface $joinPoint) {
		$text = $joinPoint->getMethodArgument('text');
		$arguments = $joinPoint->getMethodArgument('arguments');
		if ($arguments !== array()) {
			$text = vsprintf($text, $arguments);
		}

		$this->capturedOutput .= $text;

		if ($this->sendConsoleOutput === TRUE) {
			return $joinPoint->getAdviceChain()->proceed($joinPoint);
		}
	}

	/**
	 * Enable console output
	 */
	public function enableOutput() {
		$this->sendConsoleOutput = TRUE;
	}

	/**
	 * Disable console output
	 */
	public function disableOutput() {
		$this->sendConsoleOutput = FALSE;
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