<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\ShoppingList\Business\Model;

use Generated\Shared\Transfer\CompanyUserTransfer;
use Generated\Shared\Transfer\CustomerTransfer;
use Generated\Shared\Transfer\PermissionCollectionTransfer;
use Generated\Shared\Transfer\PermissionTransfer;
use Generated\Shared\Transfer\ShoppingListCollectionTransfer;
use Generated\Shared\Transfer\ShoppingListItemCollectionTransfer;
use Generated\Shared\Transfer\ShoppingListOverviewRequestTransfer;
use Generated\Shared\Transfer\ShoppingListOverviewResponseTransfer;
use Generated\Shared\Transfer\ShoppingListPaginationTransfer;
use Generated\Shared\Transfer\ShoppingListPermissionGroupTransfer;
use Generated\Shared\Transfer\ShoppingListTransfer;
use Spryker\Shared\ShoppingList\ShoppingListConfig;
use Spryker\Zed\ShoppingList\Dependency\Facade\ShoppingListToProductFacadeInterface;
use Spryker\Zed\ShoppingList\Persistence\ShoppingListRepositoryInterface;

class Reader implements ReaderInterface
{
    /**
     * @var \Spryker\Zed\ShoppingList\Persistence\ShoppingListRepositoryInterface
     */
    protected $shoppingListRepository;

    /**
     * @var \Spryker\Zed\ShoppingList\Dependency\Plugin\ItemExpanderPluginInterface[]
     */
    protected $itemExpanderPlugins;

    /**
     * @var \Spryker\Zed\ShoppingList\Dependency\Facade\ShoppingListToProductFacadeInterface
     */
    protected $productFacade;

    /**
     * @param \Spryker\Zed\ShoppingList\Persistence\ShoppingListRepositoryInterface $shoppingListRepository
     * @param \Spryker\Zed\ShoppingList\Dependency\Facade\ShoppingListToProductFacadeInterface $productFacade
     * @param \Spryker\Zed\ShoppingList\Dependency\Plugin\ItemExpanderPluginInterface[] $itemExpanderPlugins
     */
    public function __construct(ShoppingListRepositoryInterface $shoppingListRepository, ShoppingListToProductFacadeInterface $productFacade, array $itemExpanderPlugins)
    {
        $this->shoppingListRepository = $shoppingListRepository;
        $this->itemExpanderPlugins = $itemExpanderPlugins;
        $this->productFacade = $productFacade;
    }

    /**
     * @param \Generated\Shared\Transfer\ShoppingListTransfer $shoppingListTransfer
     *
     * @return \Generated\Shared\Transfer\ShoppingListTransfer
     */
    public function getShoppingList(ShoppingListTransfer $shoppingListTransfer): ShoppingListTransfer
    {
        return $this->shoppingListRepository->findCustomerShoppingListByName($shoppingListTransfer);
    }

    /**
     * @api
     *
     * @param \Generated\Shared\Transfer\ShoppingListOverviewRequestTransfer $shoppingListOverviewRequestTransfer
     *
     * @return \Generated\Shared\Transfer\ShoppingListOverviewResponseTransfer
     */
    public function getShoppingListOverview(ShoppingListOverviewRequestTransfer $shoppingListOverviewRequestTransfer): ShoppingListOverviewResponseTransfer
    {
        $shoppingListOverviewRequestTransfer->requireShoppingList();
        $shoppingListOverviewRequestTransfer->getShoppingList()->requireCustomerReference();
        $shoppingListOverviewRequestTransfer->getShoppingList()->requireName();

        $shoppingListPaginationTransfer = $this->buildShoppingListPaginationTransfer($shoppingListOverviewRequestTransfer);

        $shoppingListOverviewResponseTransfer = $this->buildShoppingListOverviewResponseTransfer(
            $shoppingListOverviewRequestTransfer->getShoppingList(),
            $shoppingListPaginationTransfer
        );

        $shoppingListTransfer = $this->getShoppingList($shoppingListOverviewRequestTransfer->getShoppingList());

        if (!$shoppingListTransfer) {
            return $shoppingListOverviewResponseTransfer;
        }

        $shoppingListOverviewRequestTransfer->setShoppingList($shoppingListTransfer);
        $shoppingListOverviewResponseTransfer = $this->shoppingListRepository->findShoppingListPaginatedItems($shoppingListOverviewRequestTransfer);
        $shoppingListOverviewResponseTransfer = $this->expandProducts($shoppingListOverviewResponseTransfer);
        $shoppingListOverviewResponseTransfer->setShoppingList($shoppingListTransfer);
        $shoppingListOverviewResponseTransfer->setShoppingLists($this->getCustomerShoppingListCollectionByReference($shoppingListTransfer->getCustomerReference()));

        return $shoppingListOverviewResponseTransfer;
    }

    /**
     * @param \Generated\Shared\Transfer\CustomerTransfer $customerTransfer
     *
     * @return \Generated\Shared\Transfer\ShoppingListCollectionTransfer
     */
    public function getCustomerShoppingListCollection(CustomerTransfer $customerTransfer): ShoppingListCollectionTransfer
    {
        $customerReference = $customerTransfer
            ->requireCustomerReference()
            ->getCustomerReference();

        return $this->getCustomerShoppingListCollectionByReference($customerReference);
    }

    /**
     * @param \Generated\Shared\Transfer\ShoppingListCollectionTransfer $shoppingListCollectionTransfer
     *
     * @return \Generated\Shared\Transfer\ShoppingListItemCollectionTransfer
     */
    public function getCustomerShoppingListsItemsCollection(ShoppingListCollectionTransfer $shoppingListCollectionTransfer): ShoppingListItemCollectionTransfer
    {
        return $this->shoppingListRepository->findCustomerShoppingListsItemsByName($shoppingListCollectionTransfer);
    }

    /**
     * @param \Generated\Shared\Transfer\ShoppingListItemCollectionTransfer $shoppingListItemCollectionTransfer
     *
     * @return \Generated\Shared\Transfer\ShoppingListItemCollectionTransfer
     */
    public function getShoppingListItemCollectionTransfer(ShoppingListItemCollectionTransfer $shoppingListItemCollectionTransfer): ShoppingListItemCollectionTransfer
    {
        $shoppingListItemIds = [];

        foreach ($shoppingListItemCollectionTransfer->getItems() as $shoppingListItemTransfer) {
            $shoppingListItemIds[] = $shoppingListItemTransfer->getIdShoppingListItem();
        }

        return $this->shoppingListRepository->findShoppingListItemsByIds($shoppingListItemIds);
    }

    /**
     * @return \Generated\Shared\Transfer\ShoppingListPermissionGroupTransfer
     */
    public function getShoppingListPermissionGroup(): ShoppingListPermissionGroupTransfer
    {
        return $this->shoppingListRepository->getShoppingListPermissionGroup();
    }

    /**
     * @param \Generated\Shared\Transfer\CompanyUserTransfer $companyUserTransfer
     * @param string $customerReference
     *
     * @return \Generated\Shared\Transfer\PermissionCollectionTransfer
     */
    public function findCompanyUserPermissions(CompanyUserTransfer $companyUserTransfer, string $customerReference): PermissionCollectionTransfer
    {
        $companyUserPermissionCollectionTransfer = new PermissionCollectionTransfer();
        $companyUserOwnShoppingLists = $this->shoppingListRepository->findCustomerShoppingLists($customerReference);
        $companyUserOwnShoppingListIds = [];

        foreach ($companyUserOwnShoppingLists->getShoppingLists() as $shoppingList) {
            $companyUserOwnShoppingListIds[] = $shoppingList->getIdShoppingList();
        }

        $companyUserPermissionCollectionTransfer = $this->addReadPermissionToPermissionCollectionTransfer(
            $companyUserPermissionCollectionTransfer,
            array_merge(
                $this->shoppingListRepository->findCompanyUserSharedShoppingLists($companyUserTransfer->getIdCompanyUser()),
                $this->shoppingListRepository->findCompanyBusinessUnitSharedShoppingLists($companyUserTransfer->getFkCompanyBusinessUnit()),
                $companyUserOwnShoppingListIds
            )
        );

        $companyUserPermissionCollectionTransfer = $this->addWritePermissionToPermissionCollectionTransfer($companyUserPermissionCollectionTransfer, $companyUserOwnShoppingListIds);

        return $companyUserPermissionCollectionTransfer;
    }

    /**
     * @param \Generated\Shared\Transfer\PermissionCollectionTransfer $permissionCollectionTransfer
     * @param array $shoppingListIds
     *
     * @return \Generated\Shared\Transfer\PermissionCollectionTransfer
     */
    protected function addReadPermissionToPermissionCollectionTransfer(PermissionCollectionTransfer $permissionCollectionTransfer, array $shoppingListIds): PermissionCollectionTransfer
    {
        $permissionTransfer = (new PermissionTransfer())
            ->setKey(ShoppingListConfig::READ_SHOPPING_LIST_PERMISSION_PLUGIN_KEY)
            ->setConfiguration([
                ShoppingListConfig::PERMISSION_CONFIG_ID_SHOPPING_LIST_COLLECTION => $shoppingListIds,
            ]);

        $permissionCollectionTransfer = $permissionCollectionTransfer->addPermission($permissionTransfer);

        return $permissionCollectionTransfer;
    }

    /**
     * @param \Generated\Shared\Transfer\PermissionCollectionTransfer $permissionCollectionTransfer
     * @param array $shoppingListIds
     *
     * @return \Generated\Shared\Transfer\PermissionCollectionTransfer
     */
    protected function addWritePermissionToPermissionCollectionTransfer(PermissionCollectionTransfer $permissionCollectionTransfer, array $shoppingListIds): PermissionCollectionTransfer
    {
        $permissionTransfer = (new PermissionTransfer())
            ->setKey(ShoppingListConfig::WRITE_SHOPPING_LIST_PERMISSION_PLUGIN_KEY)
            ->setConfiguration([
                ShoppingListConfig::PERMISSION_CONFIG_ID_SHOPPING_LIST_COLLECTION => $shoppingListIds,
            ]);

        $permissionCollectionTransfer = $permissionCollectionTransfer->addPermission($permissionTransfer);

        return $permissionCollectionTransfer;
    }

    /**
     * @param \Generated\Shared\Transfer\ShoppingListOverviewRequestTransfer $shoppingListOverviewRequestTransfer
     *
     * @return \Generated\Shared\Transfer\ShoppingListPaginationTransfer
     */
    protected function buildShoppingListPaginationTransfer(ShoppingListOverviewRequestTransfer $shoppingListOverviewRequestTransfer): ShoppingListPaginationTransfer
    {
        return (new ShoppingListPaginationTransfer())
            ->setPage($shoppingListOverviewRequestTransfer->getPage())
            ->setItemsPerPage($shoppingListOverviewRequestTransfer->getItemsPerPage());
    }

    /**
     * @param \Generated\Shared\Transfer\ShoppingListTransfer $shoppingList
     * @param \Generated\Shared\Transfer\ShoppingListPaginationTransfer $shoppingListPaginationTransfer
     *
     * @return \Generated\Shared\Transfer\ShoppingListOverviewResponseTransfer
     */
    protected function buildShoppingListOverviewResponseTransfer(ShoppingListTransfer $shoppingList, ShoppingListPaginationTransfer $shoppingListPaginationTransfer): ShoppingListOverviewResponseTransfer
    {
        return (new ShoppingListOverviewResponseTransfer())
            ->setShoppingList($shoppingList)
            ->setPagination($shoppingListPaginationTransfer);
    }

    /**
     * @param string $customerReference
     *
     * @return \Generated\Shared\Transfer\ShoppingListCollectionTransfer
     */
    protected function getCustomerShoppingListCollectionByReference(string $customerReference): ShoppingListCollectionTransfer
    {
        return $this->shoppingListRepository->findCustomerShoppingLists($customerReference);
    }

    /**
     * TODO: switch from loop -> query to SKU IN query (create facade function + add to bridge)
     *
     * @param \Generated\Shared\Transfer\ShoppingListOverviewResponseTransfer $shoppingListOverviewResponseTransfer
     *
     * @return \Generated\Shared\Transfer\ShoppingListOverviewResponseTransfer
     */
    protected function expandProducts(ShoppingListOverviewResponseTransfer $shoppingListOverviewResponseTransfer): ShoppingListOverviewResponseTransfer
    {
        foreach ($shoppingListOverviewResponseTransfer->getItemsCollection()->getItems() as $item) {
            $idProduct = $this->productFacade->findProductConcreteIdBySku($item->getSku());
            $item->setIdProduct($idProduct);

            foreach ($this->itemExpanderPlugins as $itemExpanderPlugin) {
                $item = $itemExpanderPlugin->expandItem($item);
            }
        }

        return $shoppingListOverviewResponseTransfer;
    }
}
