<?php

declare(strict_types=1);

namespace Funarbe\DescontoColaborador\Model\Quote;

use Funarbe\Helper\Helper\Data;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Checkout\Model\Session;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Quote\Model\Quote\Address\Total\AbstractTotal;
use Magento\SalesRule\Model\Validator;
use Magento\Store\Model\StoreManagerInterface;

class DescontoColaborador extends AbstractTotal
{
    /**
     * @var \Magento\Framework\Event\ManagerInterface
     */
    private ManagerInterface $eventManager;
    /**
     * @var \Magento\SalesRule\Model\Validator
     */
    private Validator $calculator;
    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;
    /**
     * @var \Magento\Framework\Pricing\PriceCurrencyInterface
     */
    private PriceCurrencyInterface $priceCurrency;
    /**
     * @var \Magento\Checkout\Model\Session
     */
    private Session $checkoutSession;
    /**
     * @var \Funarbe\Helper\Helper\Data
     */
    private Data $helper;

    public function __construct(
        ManagerInterface $eventManager,
        StoreManagerInterface $storeManager,
        Validator $validator,
        PriceCurrencyInterface $priceCurrency,
        ProductRepositoryInterface $productRepository,
        Session $checkoutSession,
        Data $helper
    ) {
        $this->setCode('campodescontocolaborador');
        $this->eventManager = $eventManager;
        $this->calculator = $validator;
        $this->storeManager = $storeManager;
        $this->priceCurrency = $priceCurrency;
        $this->productRepository = $productRepository;
        $this->checkoutSession = $checkoutSession;
        $this->helper = $helper;
    }

    /**
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function collect(
        Quote $quote,
        ShippingAssignmentInterface $shippingAssignment,
        Total $total
    ) {
        parent::collect($quote, $shippingAssignment, $total);
        //$address = $shippingAssignment->getShipping()->getAddress();
        $cpfCliente = $this->checkoutSession->getQuote()->getCustomer()->getTaxvat();
        $funcionario = $this->helper->getIntegratorRmClienteFornecedor($cpfCliente);

        if ($funcionario) {
            $percent = 0.05;
            $label = "Desconto para colaborador $percent%";
            $TotalAmount = $total->getSubtotal();
            $TotalAmount *= number_format($percent, 2, '.', '');

            $discountAmount = "-" . $TotalAmount;
            $appliedCartDiscount = 0;

            if ($total->getDiscountDescription()) {
                // If a discount exists in cart and another discount is applied, the add both discounts.
                $appliedCartDiscount = $total->getDiscountAmount();
                $discountAmount = $total->getDiscountAmount() + $discountAmount;
                $label = $total->getDiscountDescription() . ', ' . $label;
            }

            $total->setDiscountDescription($label);
            $total->setDiscountAmount($discountAmount);
            $total->setBaseDiscountAmount($discountAmount);
            $total->setSubtotalWithDiscount($total->getSubtotal() + $discountAmount);
            $total->setBaseSubtotalWithDiscount($total->getBaseSubtotal() + $discountAmount);

            if (isset($appliedCartDiscount)) {
                $total->addTotalAmount($this->getCode(), $discountAmount - $appliedCartDiscount);
                $total->addBaseTotalAmount($this->getCode(), $discountAmount - $appliedCartDiscount);
            } else {
                $total->addTotalAmount($this->getCode(), $discountAmount);
                $total->addBaseTotalAmount($this->getCode(), $discountAmount);
            }
            return $this;
        }
    }

    /**
     * @param \Magento\Quote\Model\Quote $quote
     * @param \Magento\Quote\Model\Quote\Address\Total $total
     * @return array|null
     */
    public function fetch(Quote $quote, Total $total): ?array
    {
        $result = null;
        $amount = $total->getDiscountAmount();

        if ($amount !== 0) {
            $description = $total->getDiscountDescription();
            $result = [
                'code' => $this->getCode(),
                'title' => $description !== '' ? __('Discount (%1)', $description) : __('Discount'),
                'value' => $amount
            ];
        }
        return $result;
    }
}
