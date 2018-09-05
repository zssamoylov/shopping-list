<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\ShoppingList\Business\Model;

use Generated\Shared\Transfer\MessageTransfer;
use Generated\Shared\Transfer\ShoppingListAddItemsRequestTransfer;
use Generated\Shared\Transfer\ShoppingListItemResponseTransfer;
use Generated\Shared\Transfer\ShoppingListItemTransfer;
use Generated\Shared\Transfer\ShoppingListPreAddItemCheckResponseTransfer;
use Generated\Shared\Transfer\ShoppingListResponseTransfer;
use Generated\Shared\Transfer\ShoppingListTransfer;
use Spryker\Zed\Kernel\PermissionAwareTrait;
use Spryker\Zed\Kernel\Persistence\EntityManager\TransactionTrait;
use Spryker\Zed\ShoppingList\Dependency\Facade\ShoppingListToMessengerFacadeInterface;
use Spryker\Zed\ShoppingList\Dependency\Facade\ShoppingListToProductFacadeInterface;
use Spryker\Zed\ShoppingList\Persistence\ShoppingListEntityManagerInterface;
use Spryker\Zed\ShoppingList\Persistence\ShoppingListRepositoryInterface;

class ShoppingListItemOperation implements ShoppingListItemOperationInterface
{
    use PermissionAwareTrait;
    use TransactionTrait;

    protected const GLOSSARY_PARAM_SKU = '%sku%';
    protected const GLOSSARY_KEY_CUSTOMER_ACCOUNT_SHOPPING_LIST_ITEM_ADD_SUCCESS = 'customer.account.shopping_list.item.add.success';
    protected const GLOSSARY_KEY_CUSTOMER_ACCOUNT_SHOPPING_LIST_ITEM_ADD_FAILED = 'customer.account.shopping_list.item.add.failed';

    /**
     * @var \Spryker\Zed\ShoppingList\Persistence\ShoppingListEntityManagerInterface
     */
    protected $shoppingListEntityManager;

    /**
     * @var \Spryker\Zed\ShoppingList\Dependency\Facade\ShoppingListToProductFacadeInterface
     */
    protected $productFacade;

    /**
     * @var \Spryker\Zed\ShoppingList\Persistence\ShoppingListRepositoryInterface
     */
    protected $shoppingListRepository;

    /**
     * @var \Spryker\Zed\ShoppingList\Business\Model\ShoppingListResolverInterface
     */
    protected $shoppingListResolver;

    /**
     * @var \Spryker\Zed\ShoppingList\Dependency\Facade\ShoppingListToMessengerFacadeInterface
     */
    protected $messengerFacade;

    /**
     * @var \Spryker\Zed\ShoppingListExtension\Dependency\Plugin\AddItemPreCheckPluginInterface[]
     */
    protected $addItemPreCheckPlugins;

    /**
     * @param \Spryker\Zed\ShoppingList\Persistence\ShoppingListEntityManagerInterface $shoppingListEntityManager
     * @param \Spryker\Zed\ShoppingList\Dependency\Facade\ShoppingListToProductFacadeInterface $productFacade
     * @param \Spryker\Zed\ShoppingList\Persistence\ShoppingListRepositoryInterface $shoppingListRepository
     * @param \Spryker\Zed\ShoppingList\Business\Model\ShoppingListResolverInterface $shoppingListResolver
     * @param \Spryker\Zed\ShoppingList\Dependency\Facade\ShoppingListToMessengerFacadeInterface $messengerFacade
     * @param \Spryker\Zed\ShoppingListExtension\Dependency\Plugin\AddItemPreCheckPluginInterface[] $addItemPreCheckPlugins
     */
    public function __construct(
        ShoppingListEntityManagerInterface $shoppingListEntityManager,
        ShoppingListToProductFacadeInterface $productFacade,
        ShoppingListRepositoryInterface $shoppingListRepository,
        ShoppingListResolverInterface $shoppingListResolver,
        ShoppingListToMessengerFacadeInterface $messengerFacade,
        array $addItemPreCheckPlugins = []
    ) {
        $this->shoppingListEntityManager = $shoppingListEntityManager;
        $this->productFacade = $productFacade;
        $this->shoppingListRepository = $shoppingListRepository;
        $this->shoppingListResolver = $shoppingListResolver;
        $this->messengerFacade = $messengerFacade;
        $this->addItemPreCheckPlugins = $addItemPreCheckPlugins;
    }

    /**
     * @param \Generated\Shared\Transfer\ShoppingListItemTransfer $shoppingListItemTransfer
     *
     * @return \Generated\Shared\Transfer\ShoppingListItemTransfer
     */
    public function addItem(ShoppingListItemTransfer $shoppingListItemTransfer): ShoppingListItemTransfer
    {
        if (!$this->assertItem($shoppingListItemTransfer)) {
            return $shoppingListItemTransfer;
        }

        $shoppingListTransfer = $this->createShoppingListTransfer($shoppingListItemTransfer);
        $shoppingListTransfer = $this->resolveShoppingList($shoppingListTransfer);
        $shoppingListItemTransfer->setFkShoppingList($shoppingListTransfer->getIdShoppingList());

        $shoppingListItemTransfer = $this->saveShoppingListItem($shoppingListItemTransfer);
        if ($shoppingListItemTransfer->getIdShoppingListItem()) {
            $this->addItemAddSuccessMessage($shoppingListItemTransfer->getSku());
        }

        return $shoppingListItemTransfer;
    }

    /**
     * @param \Generated\Shared\Transfer\ShoppingListAddItemsRequestTransfer $shoppingListAddItemsRequestTransfer
     *
     * @return \Generated\Shared\Transfer\ShoppingListResponseTransfer
     */
    public function addItems(ShoppingListAddItemsRequestTransfer $shoppingListAddItemsRequestTransfer): ShoppingListResponseTransfer
    {
        $customerTransfer = $shoppingListAddItemsRequestTransfer
            ->requireCustomer()
            ->getCustomer();
        $customerTransfer->requireCompanyUserTransfer()
            ->requireCustomerReference();

        return $this->getTransactionHandler()->handleTransaction(function () use ($shoppingListAddItemsRequestTransfer) {
            return $this->executeAddItemsTransaction($shoppingListAddItemsRequestTransfer);
        });
    }

    /**
     * @param \Generated\Shared\Transfer\ShoppingListAddItemsRequestTransfer $shoppingListAddItemsRequestTransfer
     *
     * @return \Generated\Shared\Transfer\ShoppingListResponseTransfer
     */
    protected function executeAddItemsTransaction(ShoppingListAddItemsRequestTransfer $shoppingListAddItemsRequestTransfer): ShoppingListResponseTransfer
    {
        $customerTransfer = $shoppingListAddItemsRequestTransfer->getCustomer();
        $shoppingListTransfer = (new ShoppingListTransfer())
            ->setIdShoppingList($shoppingListAddItemsRequestTransfer->getShoppingListId())
            ->setIdCompanyUser($customerTransfer->getCompanyUserTransfer()->getIdCompanyUser())
            ->setCustomerReference($customerTransfer->getCustomerReference());
        $shoppingListTransfer = $this->resolveShoppingList($shoppingListTransfer);
        if (!$this->checkWritePermission($shoppingListTransfer)) {
            return (new ShoppingListResponseTransfer())
                ->setIsSuccess(false);
        }

        return $this->createItems($shoppingListTransfer, $shoppingListAddItemsRequestTransfer);
    }

    /**
     * @param \Generated\Shared\Transfer\ShoppingListTransfer $shoppingListTransfer
     * @param \Generated\Shared\Transfer\ShoppingListAddItemsRequestTransfer $shoppingListAddItemsRequestTransfer
     *
     * @return \Generated\Shared\Transfer\ShoppingListResponseTransfer
     */
    protected function createItems(ShoppingListTransfer $shoppingListTransfer, ShoppingListAddItemsRequestTransfer $shoppingListAddItemsRequestTransfer): ShoppingListResponseTransfer
    {
        $shoppingListResponseTransfer = (new ShoppingListResponseTransfer())
            ->setShoppingList($shoppingListTransfer)
            ->setIsSuccess(true);
        foreach ($shoppingListAddItemsRequestTransfer->getItems() as $shoppingListItemTransfer) {
            if (!$this->assertItem($shoppingListItemTransfer)) {
                $shoppingListResponseTransfer->setIsSuccess(false);
                continue;
            }
            $shoppingListItemTransfer->setFkShoppingList($shoppingListTransfer->getIdShoppingList());
            $shoppingListItemTransfer = $this->shoppingListEntityManager->saveShoppingListItem($shoppingListItemTransfer);
            if (!$shoppingListItemTransfer->getIdShoppingListItem()) {
                $shoppingListResponseTransfer->setIsSuccess(false);
                $this->addItemAddFailedMessage($shoppingListItemTransfer->getSku());
                continue;
            }

            $this->addItemAddSuccessMessage($shoppingListItemTransfer->getSku());
        }

        return $shoppingListResponseTransfer;
    }

    /**
     * @param \Generated\Shared\Transfer\ShoppingListTransfer $shoppingListTransfer
     *
     * @return \Generated\Shared\Transfer\ShoppingListResponseTransfer
     */
    public function clearShoppingList(ShoppingListTransfer $shoppingListTransfer): ShoppingListResponseTransfer
    {
        $shoppingListTransfer = $this->shoppingListRepository->findShoppingListById($shoppingListTransfer);
        if (!$shoppingListTransfer || !$this->checkWritePermission($shoppingListTransfer)) {
            return (new ShoppingListResponseTransfer())->setIsSuccess(false);
        }

        $shoppingListItemCollectionTransfer = $this->shoppingListRepository
            ->findShoppingListItemsByIdShoppingList($shoppingListTransfer->getIdShoppingList());

        foreach ($shoppingListItemCollectionTransfer->getItems() as $shoppingListItemTransfer) {
            $this->deleteShoppingListItem($shoppingListItemTransfer);
        }

        return (new ShoppingListResponseTransfer())
            ->setIsSuccess(true);
    }

    /**
     * @param \Generated\Shared\Transfer\ShoppingListItemTransfer $shoppingListItemTransfer
     *
     * @return \Generated\Shared\Transfer\ShoppingListItemResponseTransfer
     */
    public function deleteShoppingListItem(ShoppingListItemTransfer $shoppingListItemTransfer): ShoppingListItemResponseTransfer
    {
        $this->shoppingListEntityManager->deleteShoppingListItem($shoppingListItemTransfer->getIdShoppingListItem());

        return (new ShoppingListItemResponseTransfer())->setIsSuccess(true);
    }

    /**
     * @param \Generated\Shared\Transfer\ShoppingListItemTransfer $shoppingListItemTransfer
     *
     * @return \Generated\Shared\Transfer\ShoppingListItemResponseTransfer
     */
    public function removeItemById(ShoppingListItemTransfer $shoppingListItemTransfer): ShoppingListItemResponseTransfer
    {
        $shoppingListItemTransfer->requireIdShoppingListItem()->requireFkShoppingList();

        $shoppingListTransfer = $this->shoppingListRepository->findShoppingListById(
            (new ShoppingListTransfer())->setIdShoppingList($shoppingListItemTransfer->getFkShoppingList())
        );

        if (!$shoppingListTransfer) {
            return (new ShoppingListItemResponseTransfer())->setIsSuccess(false);
        }

        $shoppingListTransfer->setIdCompanyUser($shoppingListItemTransfer->getIdCompanyUser());

        if (!$this->checkWritePermission($shoppingListTransfer)) {
            return (new ShoppingListItemResponseTransfer())->setIsSuccess(false);
        }

        $this->shoppingListEntityManager->deleteShoppingListItem($shoppingListItemTransfer->getIdShoppingListItem());

        return (new ShoppingListItemResponseTransfer())->setIsSuccess(true);
    }

    /**
     * @param \Generated\Shared\Transfer\ShoppingListItemTransfer $shoppingListItemTransfer
     *
     * @return \Generated\Shared\Transfer\ShoppingListItemTransfer
     */
    public function saveShoppingListItem(ShoppingListItemTransfer $shoppingListItemTransfer): ShoppingListItemTransfer
    {
        $shoppingListTransfer = (new ShoppingListTransfer())
            ->setIdShoppingList($shoppingListItemTransfer->getFkShoppingList())
            ->setIdCompanyUser($shoppingListItemTransfer->getIdCompanyUser());
        if (!$this->checkWritePermission($shoppingListTransfer)) {
            return $shoppingListItemTransfer;
        }

        return $this->shoppingListEntityManager->saveShoppingListItem($shoppingListItemTransfer);
    }

    /**
     * @param \Generated\Shared\Transfer\ShoppingListItemTransfer $shoppingListItemTransfer
     *
     * @return \Generated\Shared\Transfer\ShoppingListItemTransfer
     */
    public function saveShoppingListItemWithoutPermissionsCheck(ShoppingListItemTransfer $shoppingListItemTransfer): ShoppingListItemTransfer
    {
        return return $this->shoppingListEntityManager->saveShoppingListItem($shoppingListItemTransfer);
    }

    /**
     * @param \Generated\Shared\createOrUpdateShoppingListItemcreateOrUpdateShoppingListItemTransfer\ShoppingListItemTransfer $shoppingListItemTransfer
     *
     * @return bool
     */
    protected function assertItem(ShoppingListItemTransfer $shoppingListItemTransfer): bool
    {
        $shoppingListItemTransfer->requireSku();
        $shoppingListItemTransfer->requireQuantity();

        if (!$this->productFacade->hasProductConcrete($shoppingListItemTransfer->getSku())) {
            $this->addItemAddFailedMessage($shoppingListItemTransfer->getSku());

            return false;
        }

        return $this->preAddItemCheck($shoppingListItemTransfer);
    }

    /**
     * @param \Generated\Shared\Transfer\ShoppingListItemTransfer $shoppingListItemTransfer
     *
     * @return bool
     */
    protected function preAddItemCheck(ShoppingListItemTransfer $shoppingListItemTransfer): bool
    {
        $isValid = true;
        foreach ($this->addItemPreCheckPlugins as $preAddItemCheckPlugin) {
            $shoppingListPreAddItemCheckResponseTransfer = $preAddItemCheckPlugin->check($shoppingListItemTransfer);
            if (!$shoppingListPreAddItemCheckResponseTransfer->getIsSuccess()) {
                $this->processErrorMessages($shoppingListPreAddItemCheckResponseTransfer);
                $isValid = false;
            }
        }

        return $isValid;
    }

    /**
     * @param \Generated\Shared\Transfer\ShoppingListItemTransfer $shoppingListItemTransfer
     *
     * @return \Generated\Shared\Transfer\ShoppingListTransfer
     */
    protected function createShoppingListTransfer(ShoppingListItemTransfer $shoppingListItemTransfer): ShoppingListTransfer
    {
        return (new ShoppingListTransfer())
            ->setIdShoppingList($shoppingListItemTransfer->getFkShoppingList())
            ->setIdCompanyUser($shoppingListItemTransfer->getIdCompanyUser())
            ->setCustomerReference($shoppingListItemTransfer->getCustomerReference());
    }

    /**
     * @param \Generated\Shared\Transfer\ShoppingListTransfer $shoppingListTransfer
     *
     * @return \Generated\Shared\Transfer\ShoppingListTransfer
     */
    protected function resolveShoppingList(ShoppingListTransfer $shoppingListTransfer): ShoppingListTransfer
    {
        if (!$shoppingListTransfer->getIdShoppingList()) {
            return $this->shoppingListResolver
                ->createDefaultShoppingListIfNotExists($shoppingListTransfer->getCustomerReference())
                ->setIdCompanyUser($shoppingListTransfer->getIdCompanyUser());
        }

        return $shoppingListTransfer;
    }

    /**
     * @param \Generated\Shared\Transfer\ShoppingListTransfer $shoppingListTransfer
     *
     * @return bool
     */
    protected function checkWritePermission(ShoppingListTransfer $shoppingListTransfer): bool
    {
        if (!$shoppingListTransfer->getIdShoppingList()) {
            return true;
        }

        if (!$shoppingListTransfer->getIdCompanyUser()) {
            return false;
        }

        return $this->can(
            'WriteShoppingListPermissionPlugin',
            $shoppingListTransfer->getIdCompanyUser(),
            $shoppingListTransfer->getIdShoppingList()
        );
    }

    /**
     * @param \Generated\Shared\Transfer\ShoppingListPreAddItemCheckResponseTransfer $shoppingListPreAddItemCheckResponseTransfer
     *
     * @return void
     */
    protected function processErrorMessages(ShoppingListPreAddItemCheckResponseTransfer $shoppingListPreAddItemCheckResponseTransfer): void
    {
        foreach ($shoppingListPreAddItemCheckResponseTransfer->getMessages() as $messageTransfer) {
            $this->messengerFacade->addErrorMessage($messageTransfer);
        }
    }

    /**
     * @param string $sku
     *
     * @return void
     */
    protected function addItemAddFailedMessage(string $sku): void
    {
        $this->messengerFacade->addErrorMessage(
            (new MessageTransfer())
                ->setValue(static::GLOSSARY_KEY_CUSTOMER_ACCOUNT_SHOPPING_LIST_ITEM_ADD_FAILED)
                ->setParameters([static::GLOSSARY_PARAM_SKU => $sku])
        );
    }

    /**
     * @param string $sku
     *
     * @return void
     */
    protected function addItemAddSuccessMessage(string $sku): void
    {
        $this->messengerFacade->addSuccessMessage(
            (new MessageTransfer())
                ->setValue(static::GLOSSARY_KEY_CUSTOMER_ACCOUNT_SHOPPING_LIST_ITEM_ADD_SUCCESS)
                ->setParameters([static::GLOSSARY_PARAM_SKU => $sku])
        );
    }
}
