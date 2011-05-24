<?php
/*
 * This file is part of the Sonata package.
 *
 * (c) Thomas Rabaix <thomas.rabaix@sonata-project.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sonata\Component\Basket;

use Sonata\Component\Product\Pool;
use Symfony\Component\HttpFoundation\Session;
use Sonata\Component\Customer\AddressManagerInterface;
use Sonata\Component\Delivery\Pool as DeliveryPool;
use Sonata\Component\Payment\Pool as PaymentPool;

class Loader
{
    protected $session;

    protected $productPool;

    protected $basketClass;

    protected $basket;

    protected $addressManager;

    protected $productManager;

    protected $deliveryPool;

    protected $paymentPool;

    public function __construct($class, Session $session, Pool $productPool, AddressManagerInterface $addressManager, DeliveryPool $deliveryPool, PaymentPool $paymentPool)
    {
        $this->basketClass      = $class;
        $this->addressManager   = $addressManager;
        $this->deliveryPool     = $deliveryPool;
        $this->paymentPool      = $paymentPool;
        $this->session          = $session;
        $this->productPool      = $productPool;
    }

    /**
     * @throws \Exception|\RuntimeException
     * @return Sonata\Component\Basket\BasketInterface
     */
    public function getBasket()
    {
        if (!$this->basket) {
            $basket = $this->getSession()->get('sonata/basket');

            if (!$basket) {

                if (!class_exists($this->basketClass)) {
                    throw new \RuntimeException(sprintf('unable to load the class %s', $this->basketClass));
                }

                $basket = new $this->basketClass;
            }

            $basket->setProductPool($this->getProductPool());

            try {
                foreach ($basket->getBasketElements() as $basketElement) {
                    if ($basketElement->getProduct() === null) { // restore information

                        if ($basketElement->getProductCode() == null) {
                            throw new \RuntimeException('the product code is empty');
                        }

                        $repository = $this->getProductPool()->getRepository($basketElement->getProductCode());
                        $basketElement->setProductRepository($repository);
                    }
                }


                // load the delivery address
                $deliveryAddressId = $basket->getDeliveryAddressId();

                if ($deliveryAddressId) {
                    $address = $this->getEntityManager()->find('Application\Sonata\CustomerBundle\Entity\Address', $deliveryAddressId);

                    $basket->setDeliveryAddress($address);
                }

                // load the payment address
                $paymentAddressId = $basket->getPaymentAddressId();
                if ($paymentAddressId) {
                    $address = $this->getEntityManager()->find('Application\Sonata\CustomerBundle\Entity\Address', $paymentAddressId);

                    $basket->setPaymentAddress($address);
                }

                // load the payment method
                $paymentMethodCode = $basket->getPaymentMethodCode();
                if ($paymentMethodCode) {
                    $basket->setPaymentMethod($this->getPaymentPool()->getMethod($paymentMethodCode));
                }

                // customer
                $customerId = $basket->getCustomerId();
                if ($customerId) {
                    $customer = $this->getEntityManager()->find('Application\Sonata\CustomerBundle\Entity\Customer', $customerId);

                    $basket->setCustomer($customer);
                }

            } catch(\Exception $e) {

                // something went wrong while loading the basket
                $basket->reset();
            }


            $this->getSession()->set('sonata/basket', $basket);

            $this->basket = $basket;
        }

        return $this->basket;
    }

    public function getProductPool()
    {
        return $this->productPool;
    }

    public function getBasketClass()
    {
        return $this->basketClass;
    }

    public function getDeliveryPool()
    {
        return $this->deliveryPool;
    }

    public function getPaymentPool()
    {
        return $this->paymentPool;
    }

    public function getAddressManager()
    {
        return $this->addressManager;
    }

    public function getProductManager()
    {
        return $this->productManager;
    }

    public function getSession()
    {
        return $this->session;
    }
}