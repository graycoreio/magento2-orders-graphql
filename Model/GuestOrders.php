<?php
declare(strict_types=1);

namespace Graycore\OrderGraphQl\Model;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactoryInterface;

/**
 * Guest orders data resolver
 */
class GuestOrders implements ResolverInterface
{
    /**
     * @var \Graycore\OrderGraphQl\Model\Orders
     */
    protected $orders;

    /**
     * @var MaskedQuoteIdToQuoteIdInterface
     */
    private $maskedQuoteIdToQuoteId;

    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var CollectionFactoryInterface
     */
    private $collectionFactory;

    /**
     * @param CollectionFactoryInterface $collectionFactory
     * @param \Graycore\OrderGraphQl\Model\Orders $order
     * @param MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId
     * @param CartRepositoryInterface $cartRepository
     */
    public function __construct(
        CollectionFactoryInterface $collectionFactory,
        \Graycore\OrderGraphQl\Model\Orders $orders,
        MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId,
        CartRepositoryInterface $cartRepository
    ) {
        $this->orders = $orders;
        $this->maskedQuoteIdToQuoteId = $maskedQuoteIdToQuoteId;
        $this->cartRepository = $cartRepository;
        $this->collectionFactory = $collectionFactory;
    }

    private function getCart(string $cartHash) {
        $cart = null;

        try {
            $cartId = $this->maskedQuoteIdToQuoteId->execute($cartHash);
            $cart = $this->cartRepository->get($cartId);
        } catch (NoSuchEntityException $exception) {
            throw new GraphQlNoSuchEntityException(
                __('Could not find a cart with ID "%masked_cart_id"', ['masked_cart_id' => $cartHash])
            );
        }

        // TODO: throw error if cart is associated with a customer

        return $cart;
    }

    private function getOrderForCart(string $cartHash) {
        $orderId = $this->getCart($cartHash)->getReservedOrderId();

        if (!$orderId) {
            throw new GraphQlNoSuchEntityException(
                __('Could not find an order associated with cart with ID "%masked_cart_id"', ['masked_cart_id' => $cartHash])
            );
        }

        $orders = $this->collectionFactory->create(null)->getItems();

        /** @param \Magento\Sales\Api\Data\OrderInterface $order */
        $isCartOrder = function ($order) use ($orderId) {
            return $order->getIncrementId() === $orderId;
        };

        return array_values(array_filter($orders, $isCartOrder))[0];
    }

    /**
     * @inheritdoc
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        $items = [];

        $items[] = $this->orders->getOrder($this->getOrderForCart($args['cart_id']), null);

        return ['orders' => $items];
    }
}
