<?php
declare(strict_types=1);

namespace Graycore\OrderGraphQl\Model;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\GraphQl\Model\Query\ContextInterface;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactoryInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;

/**
 * Orders data resolver
 */
class Orders implements ResolverInterface
{
    /**
     * @var CollectionFactoryInterface
     */
    private $collectionFactory;

    /**
     * @var ProductRepositoryInterface
     */
    protected $productRepository;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @param CollectionFactoryInterface $collectionFactory
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param ProductRepositoryInterface $productRepository
     */
    public function __construct(
        CollectionFactoryInterface $collectionFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        ProductRepositoryInterface $productRepository
    ) {
        $this->collectionFactory = $collectionFactory;
        $this->_storeManager = $storeManager;
        $this->productRepository = $productRepository;
    }

        /**
     * @param \Magento\Sales\Api\Data\OrderItemInterface $item
     * @param \Magento\Sales\Model\Order $order
     */
    function buildOrderItem($item, $order) {
        $productId = $item->getProductId();
        $storeId = $this->_storeManager->getStore()->getId();
        /** @var Product */
        $product = $this->productRepository->getById($productId, false, $storeId);

        return [
            'qty_ordered' => $item->getQtyOrdered(),
            'qty_canceled' => $item->getQtyCanceled(),
            'qty_fulfilled' => $item->getQtyShipped(),
            'order_id' => $order->getId(),
            'image' => $product->getImage(),
            'created_at' => $item->getCreatedAt(),
            'updated_at' => $item->getUpdatedAt(),
            'product_id' => $productId,
            'sku' => $item->getSku(),
            'name' => $item->getName(),
            'weight' => $item->getWeight(),
            'qty' => $item->getQtyInvoiced(),
            'price' => $item->getPrice(),
            'discount_percent' => $item->getDiscountPercent(),
            'discount_amount' => $item->getDiscountAmount(),
            'tax_percent' => $item->getTaxPercent(),
            'tax_amount' => $item->getTaxAmount(),
            'row_total' => $item->getBaseRowTotal(),
            'row_total_with_discount' => $item->getRowTotal(),
            'row_weight' => $item->getRowWeight(),
            'tax_before_discount' => $item->getTaxBeforeDiscount(),
        ];
    }

    /** @param \Magento\Sales\Model\Order $order */
    function getOrderItems($order) {
        /** @param \Magento\Sales\Api\Data\OrderItemInterface $item */
        $buildOrderItems = function ($item) use ($order) {
            return $this->buildOrderItem($item, $order);
        };

        return array_map($buildOrderItems, $order->getItems());
    }

    /** @param \Magento\Sales\Api\Data\OrderAddressInterface $address */
    function getAddress($address, $orderId) {
        return [
            // DaffOrderAddress
            'order_id' => $orderId,
            // DaffPersonalAddress
            'prefix' => $address->getPrefix(),
            'suffix' => $address->getSuffix(),
            'firstname' => $address->getFirstname(),
            'middlename' => $address->getMiddlename(),
            'lastname' => $address->getLastname(),
            'telephone' => $address->getTelephone(),
            'email' => $address->getEmail(),
            // DaffAddress
            'street' => $address->getStreet(),
            'city' => $address->getCity(),
            'region' => $address->getRegion(),
            'region_id' => $address->getRegionId(),
            'country_code' => $address->getCountryId(),
            'postcode' => $address->getPostcode(),
        ];
    }

    /**
     * @param \Magento\Sales\Model\Order $order
     * @param \Magento\Sales\Model\Order\Shipment $shipment
     */
    function getShipmentItems($shipment, $order) {
        /**
         * @param \Magento\Sales\Api\Data\ShipmentItemInterface $item
         * @param \Magento\Sales\Model\Order $order
         */
        $buildShipmentItem = function ($item) use ($order) {
            /** @param \Magento\Sales\Api\Data\OrderItemInterface $i */
            $findItem = function ($i) use ($item) {
                return $i->getItemId() === $item->getOrderItemId();
            };

            return [
                'qty' => $item->getQty(),
                // try to find the original order item
                'item' => $this->buildOrderItem(array_values(array_filter($order->getItems(), $findItem))[0], $order),
            ];
        };

        return array_map($buildShipmentItem, $shipment->getItems());
    }

    /**
     * @param \Magento\Sales\Model\Order $order
     * @param \Magento\Sales\Model\Order\Shipment $shipment
     */
    function getShipmentTracking($shipment, $order) {
        /**
         * @param \Magento\Sales\Model\Order $order
         * @param \Magento\Sales\Api\Data\ShipmentTrackInterface $item
         */
        $buildShipmentTracking = function ($item) use ($order) {
            return [
                'tracking_number' => $item->getTrackNumber(),
                // TODO: implement
                // 'tracking_url' => $shipment->getShipmentTracking(),
                'carrier' => $item->getCarrierCode(),
                // 'carrier_logo' => $item->getQty(),
                'title' => $item->getTitle(),
            ];
        };

        return array_map($buildShipmentTracking, $shipment->getTracks());
    }

    /** @param \Magento\Sales\Model\Order $order */
    function getShipments($order) {
        /** @param \Magento\Sales\Model\Order\Shipment $shipment */
        $buildShipment = function ($shipment) use ($order) {
            return [
                'tracking' => $this->getShipmentTracking($shipment, $order),
                'items' => $this->getShipmentItems($shipment, $order),
            ];
        };

        return array_map($buildShipment, $order->getShipmentsCollection()->getItems());
    }

    /**
     * @param \Magento\Sales\Model\Order $order
     */
    function getPayment($order) {
        /** @var \Magento\Sales\Api\Data\OrderPaymentInterface $payment */
        $payment = $order->getPayment();

        return [
            'payment_id' => $payment->getQuotePaymentId(),
            'order_id' => $order->getId(),
            // 'created_at' => $payment,
            // 'updated_at' => $payment,
            'method' => $payment->getMethod(),
            'cc_type' => $payment->getCcType(),
            'cc_last4' => $payment->getCcLast4(),
            'cc_owner' => $payment->getCcOwner(),
            'cc_exp_month' => $payment->getCcExpMonth(),
            'cc_exp_year' => $payment->getCcExpYear(),
        ];
    }

    /**
     * @param \Magento\Sales\Model\Order $order
     * @param \Magento\Sales\Model\Order\Invoice $invoice
     */
    function getInvoiceItems($invoice, $order) {
        /**
         * @param \Magento\Sales\Api\Data\InvoiceItemInterface $item
         * @param \Magento\Sales\Model\Order $order
         */
        $buildInvoiceItem = function ($item) use ($order) {
            /** @param \Magento\Sales\Api\Data\OrderItemInterface $i */
            $findItem = function ($i) use ($item) {
                return $i->getItemId() === $item->getOrderItemId();
            };

            return [
                'qty' => $item->getQty(),
                // try to find the original order item
                'item' => $this->buildOrderItem(array_values(array_filter($order->getItems(), $findItem))[0], $order),
            ];
        };

        return array_map($buildInvoiceItem, $invoice->getItems());
    }

    /** @param \Magento\Sales\Model\Order $order */
    function getInvoices($order) {
        /** @param \Magento\Sales\Model\Order\Invoice $invoice */
        $buildInvoice = function ($invoice) use ($order) {
            return [
                'items' => $this->getInvoiceItems($invoice, $order),
                'grand_total' => $invoice->getGrandTotal(),
                'subtotal' => $invoice->getSubtotal(),
                'billing_address' => $this->getAddress($invoice->getBillingAddress(), $order->getId()),
                'shipping_address' => $this->getAddress($invoice->getShippingAddress(), $order->getId()),
                // 'payment' => $invoice->getPayment(),
                // 'shipping_method' => $invoice->getShipmentTracking(),
            ];
        };

        return array_map($buildInvoice, $order->getInvoiceCollection()->getItems());
    }

    /**
     * @param \Magento\Sales\Model\Order $order
     * @param \Magento\Sales\Model\Order\Creditmemo $credit
     */
    function getCreditItems($credit, $order) {
        /**
         * @param \Magento\Sales\Api\Data\CreditmemoItemInterface $item
         * @param \Magento\Sales\Model\Order $order
         */
        $buildCreditItem = function ($item) use ($order) {
            /** @param \Magento\Sales\Api\Data\OrderItemInterface $i */
            $findItem = function ($i) use ($item) {
                return $i->getItemId() === $item->getOrderItemId();
            };

            return [
                'qty' => $item->getQty(),
                // try to find the original order item
                'item' => $this->buildOrderItem(array_values(array_filter($order->getItems(), $findItem))[0], $order),
            ];
        };

        return array_map($buildCreditItem, $credit->getItems());
    }

    /** @param \Magento\Sales\Model\Order $order */
    function getCreditMemos($order) {
        /** @param \Magento\Sales\Model\Order\Creditmemo $credit */
        $buildCredit = function ($credit) use ($order) {
            return [
                'items' => $this->getInvoiceItems($credit, $order),
                'grand_total' => $credit->getGrandTotal(),
                'subtotal' => $credit->getSubtotal(),
                'billing_address' => $this->getAddress($credit->getBillingAddress(), $order->getId()),
                'shipping_address' => $this->getAddress($credit->getShippingAddress(), $order->getId()),
                // 'payment' => $credit->getPayment(),
                // 'shipping_method' => $credit->getShipmentTracking(),
            ];
        };

        return array_map($buildCredit, $order->getCreditmemosCollection()->getItems());
    }

    /**
     * @inheritdoc
     */
    public function resolve(
        Field $field,
        ContextInterface $context,
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

        /** @var \Magento\Sales\Model\Order $order */
        foreach ($orders as $order) {
            $coupon = $order->getCouponCode();
            $items[] = [
                'id' => $order->getId(),
                'order_number' => $order->getIncrementId(),
                'customer_id' => $userId,
                'created_at' => $order->getCreatedAt(),
                'updated_at' => $order->getUpdatedAt(),
                'grand_total' => $order->getGrandTotal(),
                'subtotal' => $order->getSubtotal(),
                'status' => $order->getStatus(),
                'shipments' => $this->getShipments($order),
                'applied_codes' => $coupon ? [$coupon] : [],
                'items' => $this->getOrderItems($order),
                'shipping_address' => $this->getAddress($order->getShippingAddress(), $order->getId()),
                'billing_address' => $this->getAddress($order->getBillingAddress(), $order->getId()),
                'payment' => $this->getPayment($order),
                'invoices' => $this->getInvoices($order),
                'credits' => $this->getCreditMemos($order)
            ];
        }
        return ['items' => $items];
    }
}
