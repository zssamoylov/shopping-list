<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\ShoppingList\Communication\Plugin;

use Generated\Shared\Transfer\CustomerTransfer;
use Generated\Shared\Transfer\PermissionCollectionTransfer;
use Spryker\Zed\Customer\Dependency\Plugin\CustomerTransferExpanderPluginInterface;
use Spryker\Zed\Kernel\Communication\AbstractPlugin;

/**
 * @method \Spryker\Zed\ShoppingList\Business\ShoppingListFacadeInterface getFacade()
 * @method \Spryker\Zed\ShoppingList\Communication\ShoppingListCommunicationFactory getFactory()
 */
class ShoppingListPermissionCustomerExpanderPlugin extends AbstractPlugin implements CustomerTransferExpanderPluginInterface
{
    /**
     * @param \Generated\Shared\Transfer\CustomerTransfer $customerTransfer
     *
     * @return \Generated\Shared\Transfer\CustomerTransfer
     */
    public function expandTransfer(CustomerTransfer $customerTransfer): CustomerTransfer
    {
        if ($customerTransfer->getCompanyUserTransfer()) {
            $companyUserPermissionCollection = $this->getFacade()->findCompanyUserPermissions(
                $customerTransfer->getCompanyUserTransfer(),
                $customerTransfer->getCustomerReference()
            );

            $customerTransfer = $this->addPermissionsToCustomerTransfer($customerTransfer, $companyUserPermissionCollection);
        }

        return $customerTransfer;
    }

    /**
     * @param \Generated\Shared\Transfer\CustomerTransfer $customerTransfer
     * @param \Generated\Shared\Transfer\PermissionCollectionTransfer $companyUserPermissionCollection
     *
     * @return \Generated\Shared\Transfer\CustomerTransfer
     */
    protected function addPermissionsToCustomerTransfer(CustomerTransfer $customerTransfer, PermissionCollectionTransfer $companyUserPermissionCollection): CustomerTransfer
    {
        $customerPermissionCollection = $customerTransfer->getPermissions();

        foreach ($companyUserPermissionCollection->getPermissions() as $companyUserPermissionTransfer) {
            $customerPermissionCollection->addPermission($companyUserPermissionTransfer);
        }

        $customerTransfer = $customerTransfer->setPermissions($customerPermissionCollection);

        return $customerTransfer;
    }
}
