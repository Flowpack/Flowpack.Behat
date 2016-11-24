<?php
namespace Neos\Behat\Tests\Functional\Aop;

/*
 * This file is part of the Neos.Behat package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Aop\JoinPointInterface;

/**
 * Capture output from command controllers for Behat tests
 *
 * @Flow\Aspect
 * @Flow\Scope("singleton")
 */
class ConsoleLoggingCaptureAspect
{

    /**
     * @var string
     */
    protected $capturedOutput = '';

    /**
     * @var boolean
     */
    protected $sendConsoleOutput = true;

    /**
     * @Flow\Around("method(Neos\Flow\Cli\ConsoleOutput->output())")
     * @param JoinPointInterface $joinPoint
     */
    public function captureOutput(JoinPointInterface $joinPoint)
    {
        $text = $joinPoint->getMethodArgument('text');
        $arguments = $joinPoint->getMethodArgument('arguments');
        if ($arguments !== array()) {
            $text = vsprintf($text, $arguments);
        }

        $this->capturedOutput .= $text;

        if ($this->sendConsoleOutput === true) {
            return $joinPoint->getAdviceChain()->proceed($joinPoint);
        }
    }

    /**
     * Enable console output
     */
    public function enableOutput()
    {
        $this->sendConsoleOutput = true;
    }

    /**
     * Disable console output
     */
    public function disableOutput()
    {
        $this->sendConsoleOutput = true;
    }

    /**
     * Reset captured output
     */
    public function reset()
    {
        $this->capturedOutput = '';
    }

    /**
     * @return string
     */
    public function getCapturedOutput()
    {
        return $this->capturedOutput;
    }
}