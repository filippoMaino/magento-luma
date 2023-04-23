<?php
declare(strict_types=1);


namespace Magento\PromoHandler\Model;


use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;


class PromoHandlerEngine
{
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;


    /**
     * @var LoggerInterface
     */
    private $logger;


    /**
     * GetAvailabilityFropmBorder constructor.
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        LoggerInterface       $logger
    )
    {
        $this->storeManager = $storeManager;
        $this->logger = $logger;
    }


    /**
     * adminHasActivatedPromo
     * @return bool true if promo is activated in BackOffice
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function adminHasActivatedPromo(): bool
    {
        $isFreeTMActive = false;
        $ITA = (int)$this->storeManager->getStore()->getId() === 1;
        if ($ITA === true) {
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            if (is_object($objectManager->get('Magento\Variable\Model\Variable')) === true) {
                $model = $objectManager->get('Magento\Variable\Model\Variable');
            } else {
                $model = $objectManager->create('Magento\Variable\Model\Variable');
            }
            $isFreeTMActive = boolval($model->loadByCode('my_promo_2023')->getPlainValue());
        }
        return $isFreeTMActive;
    }


    /**
     * check if cart is eligible for promo method
     * @param $quote \Magento\Quote\Model\Quote
     * @return bool | float float if promo is valid on that cart items
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function checkCartIsEligible($quote)
    {
        try {
            if ($quote->getCouponCode()!==null and $quote->getCouponCode()!=='' and $this->adminHasActivatedPromo() === true) {
                $totalNotDiscountedAmount = 0;
                $prezzoComplessivoScontati = 0;
                $items = $quote->getItems();
                foreach ($items as $item) {
                    $this->calculateTotalNotDiscounted($item, $totalNotDiscountedAmount);
                    $this->calculateTotalDiscounted($item, $prezzoComplessivoScontati);
                }
                if ($totalNotDiscountedAmount - $prezzoComplessivoScontati >= 0) {
                    return ($prezzoComplessivoScontati);
                } else {
                    throw new \Error("error in price calculation");
                }
            } else {
                return false;
            }
        } catch (\Error $error) {
            $this->logger->log(200, 'error in total cart discounted price determining');
            return false;
        }
    }


    /**
     * calculateTotal NOT Discounted method - to be applied in a foreach cycle on cart items
     * @return bool, true if item is NOT discounted
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function calculateTotalNotDiscounted($item, &$totalNotDiscountedAmount)
    {
        $catalogPrice = floatval($item->getData()['product']->getOrigData()['price']);
        $actualPrice = floatval($item->getPrice());
        $quantita = intval($item->getQty());
        if (intval($actualPrice - $catalogPrice) >= 0) {
            // prodotto a prezzo pieno, lo aggiungo al totale dei soli prodotti NON scontati
            $totalNotDiscountedAmount += ($actualPrice * $quantita);
            return true;
        } else {
            return false;
        }
    }


    /**
     * calculateTotalDiscounted method - to be applied in a foreach cycle on cart items
     * @return bool, true if item is discounted
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function calculateTotalDiscounted($item, &$prezzoComplessivoScontati)
    {


        if ($item->getPrice() < $item->getBaseOriginalPrice()) {
            $qty = $item->getQty();
            $prezzoComplessivoScontati += ($item->getPrice() * $qty);
            return true;
        } else {
            return false;
        }
    }
}
