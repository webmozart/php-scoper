<?php

/*
 * This file is part of PHPUnit.
 *
 * (c) Sebastian Bergmann <sebastian@phpunit.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace _PhpScoper5b2c11ee6df50\PHPUnit\Framework\MockObject;

/**
 * Interface for classes which can be invoked.
 *
 * The invocation will be taken from a mock object and passed to an object
 * of this class.
 */
interface Invokable extends \_PhpScoper5b2c11ee6df50\PHPUnit\Framework\MockObject\Verifiable
{
    /**
     * Invokes the invocation object $invocation so that it can be checked for
     * expectations or matched against stubs.
     *
     * @param Invocation $invocation The invocation object passed from mock object
     *
     * @return object
     */
    public function invoke(\_PhpScoper5b2c11ee6df50\PHPUnit\Framework\MockObject\Invocation $invocation);
    /**
     * Checks if the invocation matches.
     *
     * @param Invocation $invocation The invocation object passed from mock object
     *
     * @return bool
     */
    public function matches(\_PhpScoper5b2c11ee6df50\PHPUnit\Framework\MockObject\Invocation $invocation);
}
