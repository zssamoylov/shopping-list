<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Client\ShoppingList;

use Spryker\Client\Kernel\AbstractDependencyProvider;
use Spryker\Client\Kernel\Container;
use Spryker\Client\ShoppingList\Dependency\Client\ShoppingListToCartClientBridge;
use Spryker\Client\ShoppingList\Dependency\Client\ShoppingListToCustomerClientBridge;
use Spryker\Client\ShoppingList\Dependency\Client\ShoppingListToMessengerClientBridge;
use Spryker\Client\ShoppingList\Dependency\Client\ShoppingListToPriceProductClientBridge;
use Spryker\Client\ShoppingList\Dependency\Client\ShoppingListToProductClientBridge;
use Spryker\Client\ShoppingList\Dependency\Client\ShoppingListToZedRequestClientBridge;

class ShoppingListDependencyProvider extends AbstractDependencyProvider
{
    public const CLIENT_ZED_REQUEST = 'CLIENT_ZED_REQUEST';
    public const CLIENT_CART = 'CLIENT_CART';
    public const CLIENT_PRODUCT = 'CLIENT_PRODUCT';
    public const CLIENT_CUSTOMER = 'CLIENT_CUSTOMER';
    public const CLIENT_PRICE_PRODUCT = 'CLIENT_PRICE_PRODUCT';
    public const CLIENT_MESSENGER = 'CLIENT_MESSENGER';

    public const PLUGINS_SHOPPING_LIST_ITEM_TO_ITEM_MAPPER = 'PLUGINS_SHOPPING_LIST_ITEM_TO_ITEM_MAPPER';
    public const PLUGINS_QUOTE_ITEM_TO_ITEM_MAPPER = 'PLUGINS_QUOTE_ITEM_TO_ITEM_MAPPER';
    public const PLUGINS_ADD_ITEM_SHOPPING_LIST_ITEM_MAPPER = 'PLUGINS_ADD_ITEM_SHOPPING_LIST_ITEM_MAPPER';

    /**
     * @param \Spryker\Client\Kernel\Container $container
     *
     * @return \Spryker\Client\Kernel\Container
     */
    public function provideServiceLayerDependencies(Container $container)
    {
        $container = $this->addZedRequestClient($container);
        $container = $this->addProductClient($container);
        $container = $this->addCartClient($container);
        $container = $this->addCustomerClient($container);
        $container = $this->addPriceProductClient($container);
        $container = $this->addMessengerClient($container);

        $container = $this->addShoppingListItemToItemMapperPlugins($container);
        $container = $this->addQuoteItemToItemMapperPlugins($container);
        $container = $this->addAddItemShoppingListItemMapperPlugins($container);

        return $container;
    }

    /**
     * @param \Spryker\Client\Kernel\Container $container
     *
     * @return \Spryker\Client\Kernel\Container
     */
    protected function addPriceProductClient(Container $container): Container
    {
        $container[static::CLIENT_PRICE_PRODUCT] = function (Container $container) {
            return new ShoppingListToPriceProductClientBridge($container->getLocator()->priceProduct()->client());
        };

        return $container;
    }

    /**
     * @param \Spryker\Client\Kernel\Container $container
     *
     * @return \Spryker\Client\Kernel\Container
     */
    protected function addCustomerClient(Container $container): Container
    {
        $container[static::CLIENT_CUSTOMER] = function (Container $container) {
            return new ShoppingListToCustomerClientBridge($container->getLocator()->customer()->client());
        };

        return $container;
    }

    /**
     * @param \Spryker\Client\Kernel\Container $container
     *
     * @return \Spryker\Client\Kernel\Container
     */
    protected function addCartClient(Container $container): Container
    {
        $container[static::CLIENT_CART] = function (Container $container) {
            return new ShoppingListToCartClientBridge($container->getLocator()->cart()->client());
        };

        return $container;
    }

    /**
     * @param \Spryker\Client\Kernel\Container $container
     *
     * @return \Spryker\Client\Kernel\Container
     */
    protected function addProductClient(Container $container): Container
    {
        $container[static::CLIENT_PRODUCT] = function (Container $container) {
            return new ShoppingListToProductClientBridge($container->getLocator()->product()->client());
        };

        return $container;
    }

    /**
     * @param \Spryker\Client\Kernel\Container $container
     *
     * @return \Spryker\Client\Kernel\Container
     */
    protected function addZedRequestClient(Container $container): Container
    {
        $container[static::CLIENT_ZED_REQUEST] = function (Container $container) {
            return new ShoppingListToZedRequestClientBridge($container->getLocator()->zedRequest()->client());
        };

        return $container;
    }

    /**
     * @param \Spryker\Client\Kernel\Container $container
     *
     * @return \Spryker\Client\Kernel\Container
     */
    protected function addMessengerClient(Container $container): Container
    {
        $container[static::CLIENT_MESSENGER] = function (Container $container) {
            return new ShoppingListToMessengerClientBridge($container->getLocator()->messenger()->client());
        };

        return $container;
    }

    /**
     * @param \Spryker\Client\Kernel\Container $container
     *
     * @return \Spryker\Client\Kernel\Container
     */
    protected function addShoppingListItemToItemMapperPlugins(Container $container): Container
    {
        $container[static::PLUGINS_SHOPPING_LIST_ITEM_TO_ITEM_MAPPER] = function () {
            return $this->getShoppingListItemToItemMapperPlugins();
        };

        return $container;
    }

    /**
     * @param \Spryker\Client\Kernel\Container $container
     *
     * @return \Spryker\Client\Kernel\Container
     */
    protected function addQuoteItemToItemMapperPlugins(Container $container): Container
    {
        $container[static::PLUGINS_QUOTE_ITEM_TO_ITEM_MAPPER] = function () {
            return $this->getQuoteItemToItemMapperPlugins();
        };

        return $container;
    }

    /**
     * @param \Spryker\Client\Kernel\Container $container
     *
     * @return \Spryker\Client\Kernel\Container
     */
    protected function addAddItemShoppingListItemMapperPlugins(Container $container): Container
    {
        $container[static::PLUGINS_ADD_ITEM_SHOPPING_LIST_ITEM_MAPPER] = function () {
            return $this->getAddItemShoppingListItemMapperPlugins();
        };

        return $container;
    }

    /**
     * @return \Spryker\Client\ShoppingListExtension\Dependency\Plugin\ShoppingListItemToItemMapperPluginInterface[]
     */
    protected function getShoppingListItemToItemMapperPlugins(): array
    {
        return [];
    }

    /**
     * @return \Spryker\Client\ShoppingListExtension\Dependency\Plugin\QuoteItemToItemMapperPluginInterface[]
     */
    protected function getQuoteItemToItemMapperPlugins(): array
    {
        return [];
    }

    /**
     * @return \Spryker\Client\ShoppingListExtension\Dependency\Plugin\ShoppingListItemMapperPluginInterface[]
     */
    protected function getAddItemShoppingListItemMapperPlugins(): array
    {
        return [];
    }
}
