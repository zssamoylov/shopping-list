<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\ShoppingList\Persistence;

use Generated\Shared\Transfer\FilterTransfer;
use Generated\Shared\Transfer\ShoppingListCollectionTransfer;
use Generated\Shared\Transfer\ShoppingListCompanyBusinessUnitCollectionTransfer;
use Generated\Shared\Transfer\ShoppingListCompanyBusinessUnitTransfer;
use Generated\Shared\Transfer\ShoppingListCompanyUserCollectionTransfer;
use Generated\Shared\Transfer\ShoppingListCompanyUserTransfer;
use Generated\Shared\Transfer\ShoppingListItemCollectionTransfer;
use Generated\Shared\Transfer\ShoppingListOverviewRequestTransfer;
use Generated\Shared\Transfer\ShoppingListOverviewResponseTransfer;
use Generated\Shared\Transfer\ShoppingListPermissionGroupCollectionTransfer;
use Generated\Shared\Transfer\ShoppingListPermissionGroupTransfer;
use Generated\Shared\Transfer\ShoppingListTransfer;
use Orm\Zed\CompanyBusinessUnit\Persistence\Map\SpyCompanyBusinessUnitTableMap;
use Orm\Zed\CompanyUser\Persistence\Map\SpyCompanyUserTableMap;
use Orm\Zed\Customer\Persistence\Map\SpyCustomerTableMap;
use Orm\Zed\ShoppingList\Persistence\Map\SpyShoppingListCompanyBusinessUnitTableMap;
use Orm\Zed\ShoppingList\Persistence\Map\SpyShoppingListCompanyUserTableMap;
use Orm\Zed\ShoppingList\Persistence\Map\SpyShoppingListItemTableMap;
use Orm\Zed\ShoppingList\Persistence\Map\SpyShoppingListTableMap;
use Propel\Runtime\ActiveQuery\Criteria;
use Spryker\Zed\Kernel\Persistence\AbstractRepository;
use Spryker\Zed\ShoppingList\Persistence\Propel\Mapper\ShoppingListCompanyBusinessUnitMapper;
use Spryker\Zed\ShoppingList\Persistence\Propel\Mapper\ShoppingListCompanyUserMapper;
use Spryker\Zed\ShoppingList\Persistence\Propel\Mapper\ShoppingListMapper;

/**
 * @method \Spryker\Zed\ShoppingList\Persistence\ShoppingListPersistenceFactory getFactory()
 */
class ShoppingListRepository extends AbstractRepository implements ShoppingListRepositoryInterface
{
    /**
     * @param \Generated\Shared\Transfer\ShoppingListTransfer $shoppingListTransfer
     *
     * @return \Generated\Shared\Transfer\ShoppingListTransfer|null
     */
    public function findCustomerShoppingListByName(ShoppingListTransfer $shoppingListTransfer): ?ShoppingListTransfer
    {
        $shoppingListQuery = $this->createCustomerShoppingListQuery($shoppingListTransfer->getCustomerReference())
            ->filterByName($shoppingListTransfer->getName());

        $shoppingListEntityTransferCollection = $this->buildQueryFromCriteria($shoppingListQuery)->find();

        if ($shoppingListEntityTransferCollection) {
            return $this->getFactory()
                ->createShoppingListMapper()
                ->mapShoppingListTransfer($shoppingListEntityTransferCollection[0], new ShoppingListTransfer());
        }

        return null;
    }

    /**
     * @param \Generated\Shared\Transfer\ShoppingListTransfer $shoppingListTransfer
     *
     * @return null|\Generated\Shared\Transfer\ShoppingListTransfer
     */
    public function findCustomerShoppingListById(ShoppingListTransfer $shoppingListTransfer): ?ShoppingListTransfer
    {
        $shoppingListQuery = $this->createCustomerShoppingListQuery($shoppingListTransfer->getCustomerReference())
            ->filterByIdShoppingList($shoppingListTransfer->getIdShoppingList());

        $shoppingListEntityTransferCollection = $this->buildQueryFromCriteria($shoppingListQuery)->find();

        if ($shoppingListEntityTransferCollection) {
            return $this->getFactory()
                ->createShoppingListMapper()
                ->mapShoppingListTransfer($shoppingListEntityTransferCollection[0], $shoppingListTransfer);
        }

        return null;
    }

    /**
     * @param \Generated\Shared\Transfer\ShoppingListTransfer $shoppingListTransfer
     *
     * @return null|\Generated\Shared\Transfer\ShoppingListTransfer
     */
    public function findShoppingListById(ShoppingListTransfer $shoppingListTransfer): ?ShoppingListTransfer
    {
        $shoppingListQuery = $this->getFactory()->createShoppingListQuery()
            ->leftJoinWithSpyShoppingListItem()
            ->filterByIdShoppingList($shoppingListTransfer->getIdShoppingList());

        $shoppingListEntityTransferCollection = $this->buildQueryFromCriteria($shoppingListQuery)->find();

        if ($shoppingListEntityTransferCollection) {
            return $this->getFactory()
                ->createShoppingListMapper()
                ->mapShoppingListTransfer($shoppingListEntityTransferCollection[0], $shoppingListTransfer);
        }

        return null;
    }

    /**
     * @param \Generated\Shared\Transfer\ShoppingListOverviewRequestTransfer $shoppingListOverviewRequestTransfer
     *
     * @return \Generated\Shared\Transfer\ShoppingListOverviewResponseTransfer
     */
    public function findShoppingListPaginatedItems(ShoppingListOverviewRequestTransfer $shoppingListOverviewRequestTransfer): ShoppingListOverviewResponseTransfer
    {
        $shoppingListItemQuery = $this->getFactory()
            ->createShoppingListItemQuery()
            ->filterByFkShoppingList($shoppingListOverviewRequestTransfer->getShoppingList()->getIdShoppingList());

        $shoppingListItemEntityTransferCollection = $this->buildQueryFromCriteria(
            $shoppingListItemQuery,
            (new FilterTransfer())
                ->setOrderBy(SpyShoppingListItemTableMap::COL_ID_SHOPPING_LIST_ITEM)
                ->setOrderDirection('ASC')
        )
            ->find();

        return (new ShoppingListOverviewResponseTransfer())
            ->setItemsCollection(
                $this->getFactory()->createShoppingListItemMapper()->mapItemCollectionTransfer($shoppingListItemEntityTransferCollection)
            );
    }

    /**
     * @param string $customerReference
     *
     * @return \Generated\Shared\Transfer\ShoppingListCollectionTransfer
     */
    public function findCustomerShoppingLists(string $customerReference): ShoppingListCollectionTransfer
    {
        $shoppingListsQuery = $this->createCustomerShoppingListQuery($customerReference);
        $shoppingListsEntityTransferCollection = $this->buildQueryFromCriteria($shoppingListsQuery)->find();

        return $this->getFactory()
            ->createShoppingListMapper()
            ->mapCollectionTransfer($shoppingListsEntityTransferCollection);
    }

    /**
     * @param int[] $shoppingListIds
     *
     * @return \Generated\Shared\Transfer\ShoppingListItemCollectionTransfer
     */
    public function findCustomerShoppingListsItemsByIds(array $shoppingListIds): ShoppingListItemCollectionTransfer
    {
        $shoppingListsItemQuery = $this->getFactory()
            ->createShoppingListItemQuery()
            ->useSpyShoppingListQuery()
            ->filterByIdShoppingList_In($shoppingListIds)
            ->endUse();
        $shoppingListsItemEntityTransferCollection = $this->buildQueryFromCriteria($shoppingListsItemQuery)->find();

        return $this->getFactory()
            ->createShoppingListItemMapper()
            ->mapItemCollectionTransfer($shoppingListsItemEntityTransferCollection);
    }

    /**
     * @param array $shoppingListItemIds
     *
     * @return \Generated\Shared\Transfer\ShoppingListItemCollectionTransfer
     */
    public function findShoppingListItemsByIds(array $shoppingListItemIds): ShoppingListItemCollectionTransfer
    {
        $shoppingListsItemQuery = $this->getFactory()
            ->createShoppingListItemQuery()
            ->filterByIdShoppingListItem_In($shoppingListItemIds);
        $shoppingListsItemEntityTransferCollection = $this->buildQueryFromCriteria($shoppingListsItemQuery)->find();

        return $this->getFactory()
            ->createShoppingListItemMapper()
            ->mapItemCollectionTransfer($shoppingListsItemEntityTransferCollection);
    }

    /**
     * @return \Generated\Shared\Transfer\ShoppingListPermissionGroupTransfer
     */
    public function getShoppingListPermissionGroup(): ShoppingListPermissionGroupTransfer
    {
        $shoppingListPermissionGroupQuery = $this->getFactory()->createShoppingListPermissionGroupQuery();
        $permissionGroupEntityTransfer = $this->buildQueryFromCriteria($shoppingListPermissionGroupQuery)->findOne();

        return (new ShoppingListPermissionGroupTransfer())->fromArray($permissionGroupEntityTransfer->modifiedToArray(), true);
    }

    /**
     * @param \Generated\Shared\Transfer\ShoppingListPermissionGroupTransfer $shoppingListPermissionGroupTransfer
     *
     * @return \Generated\Shared\Transfer\ShoppingListPermissionGroupTransfer
     */
    public function findShoppingListPermissionGroupByName(ShoppingListPermissionGroupTransfer $shoppingListPermissionGroupTransfer): ShoppingListPermissionGroupTransfer
    {
        $shoppingListPermissionGroupQuery = $this->getFactory()
            ->createShoppingListPermissionGroupQuery()
            ->filterByName($shoppingListPermissionGroupTransfer->getName());

        $permissionGroupEntityTransfer = $this->buildQueryFromCriteria($shoppingListPermissionGroupQuery)->findOne();

        return (new ShoppingListPermissionGroupTransfer())->fromArray($permissionGroupEntityTransfer->modifiedToArray(), true);
    }

    /**
     * @return \Generated\Shared\Transfer\ShoppingListPermissionGroupCollectionTransfer
     */
    public function getShoppingListPermissionGroups(): ShoppingListPermissionGroupCollectionTransfer
    {
        $shoppingListPermissionGroupQuery = $this->getFactory()->createShoppingListPermissionGroupQuery();
        $permissionGroupEntityTransferCollection = $this->buildQueryFromCriteria($shoppingListPermissionGroupQuery)->find();

        return $this->getFactory()
            ->createShoppingListPermissionGroupMapper()
            ->mapPermissionGroupCollectionTransfer($permissionGroupEntityTransferCollection);
    }

    /**
     * @param int $idShoppingList
     * @param int $idCompanyBusinessUnit
     *
     * @return bool
     */
    public function isShoppingListSharedWithCompanyBusinessUnit(int $idShoppingList, int $idCompanyBusinessUnit): bool
    {
        $shoppingListCompanyBusinessUnitEntityQuery = $this->getFactory()
            ->createShoppingListCompanyBusinessUnitQuery()
            ->filterByFkShoppingList($idShoppingList)
            ->filterByFkCompanyBusinessUnit($idCompanyBusinessUnit);

        return $this->buildQueryFromCriteria($shoppingListCompanyBusinessUnitEntityQuery)->exists();
    }

    /**
     * @param \Generated\Shared\Transfer\ShoppingListCompanyBusinessUnitTransfer $shoppingListCompanyBusinessUnitTransfer
     *
     * @return \Generated\Shared\Transfer\ShoppingListCompanyBusinessUnitTransfer|null
     */
    public function findShoppingListCompanyBusinessUnit(ShoppingListCompanyBusinessUnitTransfer $shoppingListCompanyBusinessUnitTransfer): ?ShoppingListCompanyBusinessUnitTransfer
    {
        $shoppingListCompanyBusinessUnitsQuery = $this->getFactory()
            ->createShoppingListCompanyBusinessUnitQuery()
            ->filterByFkShoppingList($shoppingListCompanyBusinessUnitTransfer->getIdShoppingList())
            ->filterByFkCompanyBusinessUnit($shoppingListCompanyBusinessUnitTransfer->getIdCompanyBusinessUnit());

        $shoppingListCompanyBusinessUnitEntityTransfer = $this->buildQueryFromCriteria($shoppingListCompanyBusinessUnitsQuery)->findOne();

        if (!$shoppingListCompanyBusinessUnitEntityTransfer) {
            return null;
        }

        return $this->getFactory()
            ->createShoppingListCompanyBusinessUnitMapper()
            ->mapCompanyBusinessUnitTransfer($shoppingListCompanyBusinessUnitEntityTransfer, new ShoppingListCompanyBusinessUnitTransfer());
    }

    /**
     * @param int $idShoppingList
     * @param int $idCompanyUser
     *
     * @return bool
     */
    public function isShoppingListSharedWithCompanyUser(int $idShoppingList, int $idCompanyUser): bool
    {
        $shoppingListCompanyUserEntityQuery = $this->getFactory()
            ->createShoppingListCompanyUserQuery()
            ->filterByFkShoppingList($idShoppingList)
            ->filterByFkCompanyUser($idCompanyUser);

        return $this->buildQueryFromCriteria($shoppingListCompanyUserEntityQuery)->exists();
    }

    /**
     * @param \Generated\Shared\Transfer\ShoppingListCompanyUserTransfer $shoppingListCompanyUserTransfer
     *
     * @return \Generated\Shared\Transfer\ShoppingListCompanyUserTransfer|null
     */
    public function findShoppingListCompanyUser(ShoppingListCompanyUserTransfer $shoppingListCompanyUserTransfer): ?ShoppingListCompanyUserTransfer
    {
        $shoppingListCompanyUsersQuery = $this->getFactory()
            ->createShoppingListCompanyUserQuery()
            ->filterByFkShoppingList($shoppingListCompanyUserTransfer->getIdShoppingList())
            ->filterByFkCompanyUser($shoppingListCompanyUserTransfer->getIdCompanyUser());

        $shoppingListCompanyUserEntityTransfer = $this->buildQueryFromCriteria($shoppingListCompanyUsersQuery)->findOne();

        if (!$shoppingListCompanyUserEntityTransfer) {
            return null;
        }

        return $this->getFactory()
            ->createShoppingListCompanyUserMapper()
            ->mapCompanyUserTransfer($shoppingListCompanyUserEntityTransfer, new ShoppingListCompanyUserTransfer());
    }

    /**
     * @param int $idCompanyBusinessUnit
     *
     * @return \Orm\Zed\ShoppingList\Persistence\SpyShoppingListCompanyBusinessUnit[]
     */
    public function findCompanyBusinessUnitSharedShoppingListsIds(int $idCompanyBusinessUnit): array
    {
        return $this->getFactory()
            ->createShoppingListCompanyBusinessUnitQuery()
            ->filterByFkCompanyBusinessUnit($idCompanyBusinessUnit)
            ->select(SpyShoppingListCompanyBusinessUnitTableMap::COL_FK_SHOPPING_LIST)
            ->find()
            ->toArray();
    }

    /**
     * @param int $idCompanyBusinessUnit
     * @param \Generated\Shared\Transfer\ShoppingListPermissionGroupTransfer $shoppingListPermissionGroupTransfer
     *
     * @return \Orm\Zed\ShoppingList\Persistence\SpyShoppingListCompanyBusinessUnit[]|\Propel\Runtime\Collection\ObjectCollection
     */
    public function findCompanyBusinessUnitSharedShoppingListIdsByPermissionGroup(int $idCompanyBusinessUnit, ShoppingListPermissionGroupTransfer $shoppingListPermissionGroupTransfer)
    {
        return $this->getFactory()
            ->createShoppingListCompanyBusinessUnitQuery()
            ->filterByFkCompanyBusinessUnit($idCompanyBusinessUnit)
            ->select(SpyShoppingListCompanyBusinessUnitTableMap::COL_FK_SHOPPING_LIST)
            ->filterByFkShoppingListPermissionGroup($shoppingListPermissionGroupTransfer->getIdShoppingListPermissionGroup())
            ->find()
            ->toArray();
    }

    /**
     * @param int $idCompanyUser
     *
     * @return \Orm\Zed\ShoppingList\Persistence\SpyShoppingListCompanyUser[]
     */
    public function findCompanyUserSharedShoppingListsIds(int $idCompanyUser): array
    {
        return $this->getFactory()
            ->createShoppingListCompanyUserQuery()
            ->filterByFkCompanyUser($idCompanyUser)
            ->select(SpyShoppingListCompanyUserTableMap::COL_FK_SHOPPING_LIST)
            ->find()
            ->toArray();
    }

    /**
     * @param int $idCompanyUser
     * @param \Generated\Shared\Transfer\ShoppingListPermissionGroupTransfer $shoppingListPermissionGroupTransfer
     *
     * @return mixed|\Orm\Zed\ShoppingList\Persistence\SpyShoppingListCompanyUser[]|\Propel\Runtime\ActiveRecord\ActiveRecordInterface[]|\Propel\Runtime\Collection\ObjectCollection
     */
    public function findCompanyUserSharedShoppingListIdsByPermissionGroup(int $idCompanyUser, ShoppingListPermissionGroupTransfer $shoppingListPermissionGroupTransfer)
    {
        return $this->getFactory()
            ->createShoppingListCompanyUserQuery()
            ->filterByFkCompanyUser($idCompanyUser)
            ->select(SpyShoppingListCompanyUserTableMap::COL_FK_SHOPPING_LIST)
            ->filterByFkShoppingListPermissionGroup($shoppingListPermissionGroupTransfer->getIdShoppingListPermissionGroup())
            ->find()
            ->toArray();
    }

    /**
     * @param int $idCompanyUser
     *
     * @return \Generated\Shared\Transfer\ShoppingListCollectionTransfer
     */
    public function findCompanyUserSharedShoppingLists(int $idCompanyUser): ShoppingListCollectionTransfer
    {
        $shoppingListsQuery = $this->getFactory()
            ->createShoppingListQuery()
            ->addJoin(SpyShoppingListTableMap::COL_CUSTOMER_REFERENCE, SpyCustomerTableMap::COL_CUSTOMER_REFERENCE, Criteria::LEFT_JOIN)
            ->withColumn(SpyCustomerTableMap::COL_FIRST_NAME, ShoppingListMapper::FIELD_FIRST_NAME)
            ->withColumn(SpyCustomerTableMap::COL_LAST_NAME, ShoppingListMapper::FIELD_LAST_NAME)
            ->useSpyShoppingListCompanyUserQuery()
            ->filterByFkCompanyUser($idCompanyUser)
            ->endUse()
            ->leftJoinWithSpyShoppingListItem();

        $shoppingListsEntityTransferCollection = $this->buildQueryFromCriteria($shoppingListsQuery)->find();

        return $this->getFactory()
            ->createShoppingListMapper()
            ->mapCollectionTransfer($shoppingListsEntityTransferCollection);
    }

    /**
     * @param int $idCompanyBusinessUnit
     *
     * @return \Generated\Shared\Transfer\ShoppingListCollectionTransfer
     */
    public function findCompanyBusinessUnitSharedShoppingLists(int $idCompanyBusinessUnit): ShoppingListCollectionTransfer
    {
        $shoppingListQuery = $this->getFactory()
            ->createShoppingListQuery()
            ->addJoin(SpyShoppingListTableMap::COL_CUSTOMER_REFERENCE, SpyCustomerTableMap::COL_CUSTOMER_REFERENCE, Criteria::LEFT_JOIN)
            ->withColumn(SpyCustomerTableMap::COL_FIRST_NAME, ShoppingListMapper::FIELD_FIRST_NAME)
            ->withColumn(SpyCustomerTableMap::COL_LAST_NAME, ShoppingListMapper::FIELD_LAST_NAME)
            ->useSpyShoppingListCompanyBusinessUnitQuery()
            ->filterByFkCompanyBusinessUnit($idCompanyBusinessUnit)
            ->endUse()
            ->leftJoinWithSpyShoppingListItem();

        $shoppingListsEntityTransferCollection = $this->buildQueryFromCriteria($shoppingListQuery)->find();

        return $this->getFactory()
            ->createShoppingListMapper()
            ->mapCollectionTransfer($shoppingListsEntityTransferCollection);
    }

    /**
     * @param \Generated\Shared\Transfer\ShoppingListTransfer $shoppingListTransfer
     *
     * @return \Generated\Shared\Transfer\ShoppingListCompanyBusinessUnitCollectionTransfer
     */
    public function findShoppingListCompanyBusinessUnitsByShoppingListId(ShoppingListTransfer $shoppingListTransfer): ShoppingListCompanyBusinessUnitCollectionTransfer
    {
        $shoppingListsCompanyBusinessUnitQuery = $this->getFactory()
            ->createShoppingListCompanyBusinessUnitQuery()
            ->filterByFkShoppingList($shoppingListTransfer->getIdShoppingList())
            ->addJoin(SpyShoppingListCompanyBusinessUnitTableMap::COL_FK_COMPANY_BUSINESS_UNIT, SpyCompanyBusinessUnitTableMap::COL_ID_COMPANY_BUSINESS_UNIT, Criteria::LEFT_JOIN)
            ->withColumn(SpyCompanyBusinessUnitTableMap::COL_NAME, ShoppingListCompanyBusinessUnitMapper::FIELD_COMPANY_BUSINESS_UNIT_NAME)
            ->orderBy(ShoppingListCompanyBusinessUnitMapper::FIELD_COMPANY_BUSINESS_UNIT_NAME)
            ->joinWithSpyShoppingListPermissionGroup();

        $shoppingListsCompanyBusinessUnitEntityTransferCollection = $this->buildQueryFromCriteria($shoppingListsCompanyBusinessUnitQuery)->find();

        return $this->getFactory()
            ->createShoppingListCompanyBusinessUnitMapper()
            ->mapCompanyBusinessUnitCollectionTransfer($shoppingListsCompanyBusinessUnitEntityTransferCollection);
    }

    /**
     * @param \Generated\Shared\Transfer\ShoppingListTransfer $shoppingListTransfer
     *
     * @return \Generated\Shared\Transfer\ShoppingListCompanyUserCollectionTransfer
     */
    public function findShoppingListCompanyUsersByShoppingListId(ShoppingListTransfer $shoppingListTransfer): ShoppingListCompanyUserCollectionTransfer
    {
        $shoppingListsCompanyUserQuery = $this->getFactory()
            ->createShoppingListCompanyUserQuery()
            ->filterByFkShoppingList($shoppingListTransfer->getIdShoppingList())
            ->addJoin(SpyShoppingListCompanyUserTableMap::COL_FK_COMPANY_USER, SpyCompanyUserTableMap::COL_ID_COMPANY_USER, Criteria::LEFT_JOIN)
            ->addJoin(SpyCompanyUserTableMap::COL_FK_CUSTOMER, SpyCustomerTableMap::COL_ID_CUSTOMER, Criteria::LEFT_JOIN)
            ->withColumn(SpyCustomerTableMap::COL_FIRST_NAME, ShoppingListCompanyUserMapper::FIELD_FIRST_NAME)
            ->withColumn(SpyCustomerTableMap::COL_LAST_NAME, ShoppingListCompanyUserMapper::FIELD_LAST_NAME)
            ->orderBy(ShoppingListCompanyUserMapper::FIELD_FIRST_NAME)
            ->joinWithSpyShoppingListPermissionGroup();

        $shoppingListsCompanyUserEntityTransferCollection = $this->buildQueryFromCriteria($shoppingListsCompanyUserQuery)->find();

        return $this->getFactory()
            ->createShoppingListCompanyUserMapper()
            ->mapCompanyUserCollectionTransfer($shoppingListsCompanyUserEntityTransferCollection);
    }

    /**
     * @param string $customerReference
     *
     * @return $this|\Orm\Zed\ShoppingList\Persistence\SpyShoppingListQuery
     */
    protected function createCustomerShoppingListQuery(string $customerReference)
    {
        return $this->getFactory()
            ->createShoppingListQuery()
            ->addJoin(SpyShoppingListTableMap::COL_CUSTOMER_REFERENCE, SpyCustomerTableMap::COL_CUSTOMER_REFERENCE, Criteria::LEFT_JOIN)
            ->withColumn(SpyCustomerTableMap::COL_FIRST_NAME, ShoppingListMapper::FIELD_FIRST_NAME)
            ->withColumn(SpyCustomerTableMap::COL_LAST_NAME, ShoppingListMapper::FIELD_LAST_NAME)
            ->filterByCustomerReference($customerReference)
            ->orderByIdShoppingList()
            ->leftJoinWithSpyShoppingListItem();
    }
}
