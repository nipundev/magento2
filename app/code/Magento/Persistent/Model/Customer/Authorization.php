<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\Persistent\Model\Customer;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\AuthorizationInterface;
use Magento\Persistent\Helper\Session as PersistentSession;

class Authorization implements AuthorizationInterface
{
    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * @var PersistentSession
     */
    private $persistentSession;

    /**
     * @param CustomerSession $customerSession
     * @param PersistentSession $persistentSession
     */
    public function __construct(
        CustomerSession $customerSession,
        PersistentSession $persistentSession
    ) {
        $this->customerSession = $customerSession;
        $this->persistentSession = $persistentSession;
    }

    public function isAllowed(
        $resource,
        $privilege = null
    ) {
        if ($this->persistentSession->isPersistent() && !$this->customerSession->isLoggedIn()) {
            return false;
        }

        return true;
    }
}
