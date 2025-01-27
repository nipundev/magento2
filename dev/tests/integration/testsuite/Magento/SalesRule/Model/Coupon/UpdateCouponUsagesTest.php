<?php
/**
 * Copyright 2023 Adobe
 * All Rights Reserved.
 *
 * NOTICE: All information contained herein is, and remains
 * the property of Adobe and its suppliers, if any. The intellectual
 * and technical concepts contained herein are proprietary to Adobe
 * and its suppliers and are protected by all applicable intellectual
 * property laws, including trade secret and copyright laws.
 * Dissemination of this information or reproduction of this material
 * is strictly forbidden unless prior written permission is obtained
 * from Adobe.
 */
declare(strict_types=1);

namespace Magento\SalesRule\Model\Coupon;

use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Checkout\Test\Fixture\SetBillingAddress as SetBillingAddress;
use Magento\Checkout\Test\Fixture\SetDeliveryMethod;
use Magento\Checkout\Test\Fixture\SetPaymentMethod as SetPaymentMethod;
use Magento\Checkout\Test\Fixture\SetShippingAddress as SetShippingAddress;
use Magento\Customer\Test\Fixture\Customer;
use Magento\Framework\MessageQueue\ConsumerFactory;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\CouponManagementInterface;
use Magento\Quote\Test\Fixture\AddProductToCart;
use Magento\Quote\Test\Fixture\CustomerCart;
use Magento\Quote\Test\Fixture\GuestCart;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\SalesRule\Test\Fixture\Rule as SalesRuleFixture;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorage;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\MessageQueue\ClearQueueProcessor;
use PHPUnit\Framework\TestCase;

class UpdateCouponUsagesTest extends TestCase
{
    /**
     * @var DataFixtureStorage
     */
    private $fixtures;

    /**
     * @var CouponManagementInterface
     */
    private $couponManagement;

    /**
     * @var CartManagementInterface
     */
    private $cartManagement;

    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var OrderManagementInterface
     */
    private $orderManagement;

    /**
     * @var ConsumerFactory
     */
    private $consumerFactory;

    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();

        $clearQueueProcessor = $objectManager->get(ClearQueueProcessor::class);
        $clearQueueProcessor->execute('sales.rule.update.coupon.usage');

        $this->fixtures = $objectManager->get(DataFixtureStorageManager::class)->getStorage();
        $this->couponManagement = $objectManager->get(CouponManagementInterface::class);
        $this->cartManagement = $objectManager->get(CartManagementInterface::class);
        $this->cartRepository = $objectManager->get(CartRepositoryInterface::class);
        $this->orderManagement = $objectManager->get(OrderManagementInterface::class);
        $this->consumerFactory = $objectManager->get(ConsumerFactory::class);
    }

    #[
        DataFixture(ProductFixture::class, as: 'p1'),
        DataFixture(
            SalesRuleFixture::class,
            ['coupon_code' => 'one_per_customer', 'uses_per_customer' => 1, 'discount_amount' => 10]
        ),
        DataFixture(Customer::class, as: 'customer'),

        DataFixture(CustomerCart::class, ['customer_id' => '$customer.id$'], 'cart1'),
        DataFixture(AddProductToCart::class, ['cart_id' => '$cart1.id$', 'product_id' => '$p1.id$']),
        DataFixture(SetBillingAddress::class, ['cart_id' => '$cart1.id$']),
        DataFixture(SetShippingAddress::class, ['cart_id' => '$cart1.id$']),
        DataFixture(SetDeliveryMethod::class, ['cart_id' => '$cart1.id$']),
        DataFixture(SetPaymentMethod::class, ['cart_id' => '$cart1.id$']),

        DataFixture(GuestCart::class, as: 'cart2'),
        DataFixture(AddProductToCart::class, ['cart_id' => '$cart2.id$', 'product_id' => '$p1.id$']),
        DataFixture(SetBillingAddress::class, ['cart_id' => '$cart2.id$']),
        DataFixture(SetShippingAddress::class, ['cart_id' => '$cart2.id$']),
        DataFixture(SetDeliveryMethod::class, ['cart_id' => '$cart2.id$']),
        DataFixture(SetPaymentMethod::class, ['cart_id' => '$cart2.id$']),
    ]
    public function testCancelOrderBeforeUsageConsumerExecution(): void
    {
        $cart = $this->fixtures->get('cart1');
        $this->couponManagement->set($cart->getId(), 'one_per_customer');
        $this->cartManagement->placeOrder($cart->getId());
        $cart = $this->cartRepository->get($cart->getId());
        $this->orderManagement->cancel($cart->getReservedOrderId());
        $consumer = $this->consumerFactory->get('sales.rule.update.coupon.usage');
        $consumer->process(1);

        $cart = $this->fixtures->get('cart2');
        $customer = $this->fixtures->get('customer');
        $this->cartManagement->assignCustomer($cart->getId(), $customer->getId(), 1);
        $cart = $this->cartRepository->get($cart->getId());
        $this->couponManagement->set($cart->getId(), 'one_per_customer');
        $this->cartManagement->placeOrder($cart->getId());
        $consumer->process(1);
    }
}
