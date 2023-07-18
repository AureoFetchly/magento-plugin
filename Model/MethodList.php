<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Edebit\Payment\Model;

class MethodList
{
    /**
     * @var array
     */
    private array $methodCodes;

    /**
     * MethodList constructor.
     *
     * @param array $methodCodes
     */
    public function __construct(array $methodCodes = [])
    {
        $this->methodCodes = $methodCodes;
    }

    /**
     * @return array
     */
    public function get(): array
    {
        return $this->methodCodes;
    }
}
