<?php
declare(strict_types=1);

namespace Graycore\OrderGraphQl\Model;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\GraphQl\Model\Query\ContextInterface;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactoryInterface;
use Graycore\OrderGraphQl\Model\Orders;

/**
 * Orders data resolver
 */
class CustomerOrders implements ResolverInterface
{
    /**
     * @var \Graycore\OrderGraphQl\Model\Orders
     */
    protected $orders;

    /**
     * @var CollectionFactoryInterface
     */
    private $collectionFactory;

    /**
     * @param CollectionFactoryInterface $collectionFactory
     * @param \Graycore\OrderGraphQl\Model\Orders $order
     */
    public function __construct(
        CollectionFactoryInterface $collectionFactory,
        Orders $orders
    ) {
        $this->orders = $orders;
        $this->collectionFactory = $collectionFactory;
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
        if (false === $context->getExtensionAttributes()->getIsCustomer()) {
            throw new GraphQlAuthorizationException(__('The current customer isn\'t authorized.'));
        }

        $userId = $context->getUserId();
        $items = [];
        $orders = $this->collectionFactory->create($userId);

        if (array_key_exists('orderNumber', $args)) {
            $orders = $orders->addFieldToFilter('increment_id', $args['orderNumber']);
        }

        /** @var \Magento\Sales\Model\Order $order */
        foreach ($orders as $order) {
            $items[] = $this->orders->getOrder($order, $userId);
        }

        return ['orders' => $items];
    }
}
