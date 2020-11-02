<?php

namespace app\models;

use yii\helpers\Html;

class Ebay extends EbayClient
{
    const CLASS_NAME = 'Ebay';
    const MAX_CATEGORIES = 3;
    private $qtyBasePricesMin = 5;
    private $qtyAvgPrices = 10;
    private $defaultShippingCost;
    private $isShippingCostEnabled;
    private $defaultShippingCurrency = CurrencyModel::EUR;
    private $qtySaveUnusedItems;

    /** @var array[] */
    private $items;

    /** @var float[] by itemId */
    private $prices;

    /** @var float[] by itemId */
    private $baseQties;

    /** @var float[] */
    private $newBasePrices;

    /** @var float[] */
    private $usedBasePrices;

    /** @var float[] */
    private $basePrices;

    /** @var \app\models\ebayClient */
    private $ebayClient;

    /** @var float */
    private $rateUSDtoEUR;
    private $rateGBPtoEUR;

    private $logger;
    private $searchLog;

    private $ebayByCountries = [];
    /** @var int[] */
    private $categoryIds = [];
    private $minusArtikuls   = [];
    private $isMinusArtikulsEnabled;

    public function init()
    {
        $settings = Settings::getSettings();

        $this->ebayClient = new EbayAPI;
        // $this->ebayClient = new EbayAPITest;
        $this->ebayByCountries = Settings::getEbayByCountries();
        $this->categoryIds = Settings::getEbayCategoryIds();
        $this->qtyBasePricesMin = Settings::getQtyBasePricesMin();
        $this->qtyAvgPrices = Settings::getQtyAvgPrices();
        $currencyModel = new CurrencyModel;
        $this->rateUSDtoEUR = $currencyModel->getExchangeRate(CurrencyModel::USD, CurrencyModel::EUR, $currencyModel->getCurDate());
        $this->rateGBPtoEUR = $currencyModel->getExchangeRate(CurrencyModel::GBP, CurrencyModel::EUR, $currencyModel->getCurDate());
        $this->logger = new \app\models\File;
        $this->searchLog = \app\models\SearchLog::getCurrent();
        $this->defaultShippingCost   = $settings->defaultShippingCost;
        $this->isShippingCostEnabled = $settings->isEbayShippingCostEnabled;
        $this->qtySaveUnusedItems    = $settings->ebayQtySaveUnusedItems;
        $this->isMinusArtikulsEnabled= Settings::isEbayMinusArtikulsEnabled();
        $this->minusArtikuls         = Settings::getEbayMinusArtikulsAsArray();
    }

    public function setSettingsForSearchImg()
    {
        $this->ebayByCountries = SettingsImages::getEbayByCountriesOfMode(
            SettingsImages::getEbayEuropeSearchMode()
        );
        $this->categoryIds = [];
        $this->defaultShippingCost   = 0;
        $this->isShippingCostEnabled = false;
        $this->isMinusArtikulsEnabled = false;
        $this->minusArtikuls = [];
        $this->getEbayClient()
            ->clear()
            ->setSettingsForSearchImg();
    }

    public function clear(): self
    {
        $this->items = null;
        $this->prices = null;
        $this->basePrices = null;
        $this->baseQties = null;
        $this->clearErrors();
        $this->getEbayClient()->clear();
        return $this;
    }

    public function getMostExpensiveItemIdWImg(): ?int
    {
        $prices = array_reverse($this->getPrices() ?: [], true);
        foreach ($prices as $itemId => $price) {
            if ($this->ebayClient->getItemPreviewImg($this->getItems()[$itemId])) {
                return $itemId;
            }
        }
        return null;
    }

    public function getAvgNewPrice(): ?float
    {
        $this->clear();
        $this->getEbayClient()->setNewCondition();
        $this->newBasePrices = $this->getBasePrices();
        return $this->getAvgPrice();
    }

    public function getAvgUsedPrice(): ?float
    {
        $this->clear();
        $this->getEbayClient()->setUsedCondition();
        $this->usedBasePrices = $this->getBasePrices();
        return $this->getAvgPrice();
    }

    /**
     * @return int[]
     */
    private function getItemIdsUsedInCalculation(): array
    {
        return array_keys($this->getBasePrices() ?: []);
    }

    /**
     * @return int[]
     */
    private function getItemIdsNotUsedInCalculation(): array
    {
        $pricesByItemId = $this->getPrices();
        foreach ($this->getItemIdsUsedInCalculation() as $itemId) {
            unset($pricesByItemId[$itemId]);
        }
        $itemIds = array_slice(array_keys($pricesByItemId), 0, $this->qtySaveUnusedItems);
        return $itemIds;
    }

    public function getAvgPrice(): ?float
    {
        // $time1 = time();
        $prices = $this->getBasePrices();
        if (!$prices) {
            return null;
        }
        $this->saveItems($this->getItemIdsUsedInCalculation(), true);
        $this->saveItems($this->getItemIdsNotUsedInCalculation(), false);
        // return $time1-time();

        $logInfo = [
            'count' => count($prices),
        ];
        if ($this->isAvgPriceBasedOnQty()) {
            $avg = $this->calcAvgPriceBasedOnQty($prices ?? [], $this->getBaseQties());
            $logInfo = array_merge($logInfo, [
                'avgBasedOnQty' => $avg,
                'qties' => $this->getBaseQties(),
            ]);
        } else {
            $avg = $this->calcAvgPrice($prices);
            $logInfo = array_merge($logInfo, [
                'avg' => $avg,
            ]);
        }
        $logInfo = array_merge($logInfo, [
            'prices'=> array_map(function ($price) {return round($price, 2);}, $prices),
            'urls'  => $this->getBaseItemsUrls(),
        ]);

        $log = "\r\n\r\n".json_encode($logInfo);
        $this->logger->save($this->searchLog, Ebay::CLASS_NAME . '__AVG', $log, 'json');

        return $avg;
    }

    public function calcAvgPrice(?array $prices): ?float
    {
        if (!$prices) {
            return null;
        }
        return round(array_sum($prices)/count($prices), 2);
    }

    /**
     * @param float[] $prices by itemId
     * @param int[] $qties by itemId
     * @return float
     */
    public function calcAvgPriceBasedOnQty(array $prices, array $qties): float
    {
        if (!$prices) {
            return 0;
        }
        $totalPrices = [];
        foreach ($prices as $itemId => $price) {
            $totalPrices[] = $price * $qties[$itemId];
        }
        return round(array_sum($totalPrices)/array_sum($qties), 2);
    }

    public function getBaseItemsUrls(): array
    {
        if (!$this->getItems() || !$this->getBasePrices()) {
            return [];
        }
        $urls = [];
        $itemIds = array_keys($this->getBasePrices());
        foreach ($itemIds as $itemId) {
            $urls[$itemId] = $this->ebayClient->getItemUrl($this->getItems()[$itemId]);
        }
        return $urls;
    }

    public function getBasePrices(): ?array
    {
        if (null !== $this->basePrices) {
            return $this->basePrices;
        }
        $prices = $this->getPrices();
        $this->basePrices = $prices;
        if (!$this->basePrices) {
            return $this->basePrices;
        }
        // if need a certain qty of product we will request detailed information
        // about founded products to find out the qty of product in the lot
        if ($this->isAvgPriceBasedOnQty()) {
            return $this->basePrices = $this->getBasePricesByQties();
        }
        $count = count($this->basePrices);
        if ($count <= $this->qtyBasePricesMin) {
            return $this->basePrices;
        }
        if (Settings::isEbayModeOfBasePricesBeginning()) {
            return $this->basePrices = $this->getBasePricesFromBeginning();
        }
        $offset = ceil(($count-$this->qtyAvgPrices)/2);
        $this->basePrices = array_slice($prices, $offset, $this->qtyAvgPrices, true);
        return $this->basePrices;
    }

    public function getBasePricesFromBeginning(): array
    {
        return array_slice($this->getPrices(), 0, $this->qtyAvgPrices, true);
    }

    /**
     * @return float[] by itemId
     */
    private function getBasePricesByQties(): array
    {
        $prices = $this->getPrices();
        $basePrices = [];
        foreach ($this->getBaseQties() as $itemId => $qty) {
            $basePrices[$itemId] = $prices[$itemId];
        }
        return $basePrices;
    }

    /**
     * @return int[] by itemId
     */
    private function getBaseQties(): array
    {
        if (null !== $this->baseQties) {
            return $this->baseQties;
        }

        $this->baseQties = [];
        if (!$this->getRequestedQty()) {
            return [];
        }

        $qtyRemain = $this->getRequestedQty();
        foreach (array_chunk($this->getItemIds(), EbayAPI::DETAILED_INFO_MAX_QTY_OF_PRODUCTS_PER_REQUEST) as $itemIds) {
            foreach (($qties = $this->getEbayClient()->getItemsQty($itemIds)) as $itemId => $qty) {
                $qtyUsed = $qty > $qtyRemain ? $qtyRemain : $qty;
                $this->baseQties[$itemId] = $qtyUsed;
                $qtyRemain -= $qtyUsed;
                if ($qtyRemain <= 0) {
                    break 2;
                }
            }
        }
        return $this->baseQties;
    }

    /**
     * @return int[]
     */
    private function getItemIds(): array
    {
        return array_keys($this->getPrices() ?: []);
    }

    /**
     * @return float[]|null
     */
    public function getPrices(): ?array
    {
        if (null !== $this->prices) {
            return $this->prices;
        }
        $items = $this->getItems();
        // echo 'items='; print_r($items);
        if (!$items) {
            return null;
        }
        $prices = [];
        foreach ($items as $itemId => $item) {
            if (!$this->isShippingCostEnabled) {
                $prices[$itemId] = $this->getPrice($item);
                continue;
            }
            $prices[$itemId] = $this->getTotalPrice($item);
        }

        asort($prices);
        $sortedItems = [];
        foreach ($prices as $itemId => $price) {
            $item = $items[$itemId];
            $sortedItems[$itemId] = [
                'itemId' => $this->ebayClient->getItemId($item),
                'title'  => $this->ebayClient->getItemTitle($item),
                'url'    => $this->ebayClient->getItemUrl($item),
                'price'  => round($price, 2),
                'beforeConvert' => [
                    'price' => [
                        $this->ebayClient->getItemPrice($item),
                        $this->ebayClient->getItemCurrency($item),
                    ],
                    'shippingCost' => [
                        $this->ebayClient->getItemShippingCost($item),
                        $this->ebayClient->getItemShippingCurrency($item),
                    ],
                ],
                'categoryId'    => $this->ebayClient->getItemCategoryId($item),
            ];
        }
        $log = "\r\n\r\n".json_encode([
            'count'       => count($sortedItems),
            'sortedItems' => $sortedItems,
        ]);
        $this->logger->save($this->searchLog, Ebay::CLASS_NAME . '__SortedItems', $log, 'json');

        return $this->prices = $prices;
    }

    /**
    * @param string $currency USD or EUR or GBP
    * @return float Rate for convert price to EUR
    * @throws \yii\base\UserException When currency is unknown
    */
    public function getCurrencyRate(string $currency): float
    {
        if (CurrencyModel::USD == $currency) {
            return $this->rateUSDtoEUR;
        } elseif (CurrencyModel::GBP == $currency) {
            return $this->rateGBPtoEUR;
        } elseif (CurrencyModel::EUR == $currency) {
            return 1;
        }
        throw new \yii\base\UserException(sprintf('Unknown currency "%s".', Html::encode($currency)));
    }

    private function saveSettingsToLog(): self
    {
        $clientSettings = $this->getEbayClient()->getSettings();
        $log = "\r\n\r\n".json_encode([
            'ebay' => [
                'date'                    => date('Y-m-d H:i:s', $this->searchLog->getTime()),
                'globalIds'               => $this->ebayByCountries,
                'keywords'                => $this->artikul,
                'categoryIds'             => $this->categoryIds,
                'filters'                 => [
                    'isMinusWordsEnabled'         => $clientSettings['isMinusWordsEnabled'],
                    'minusArtikuls'               => [
                        'enabled'  => $this->isMinusArtikulsEnabled,
                        'artikuls' => $this->minusArtikuls,
                    ],
                    'isKeywordsFilterEnabled'     => $clientSettings['isKeywordsFilterEnabled'],
                    'isConditionFilterEnabled'    => $clientSettings['isConditionFilterEnabled'],
                    'isExcludeCategoryIdsEnabled' => $clientSettings['isExcludeCategoryIdsEnabled'],
                    'minusWords'                  => $clientSettings['minusWords'],
                    'excludeCategoryIds'          => $clientSettings['excludeCategoryIds'],
                ],
                'europeSearchMode'        => Settings::getEbayEuropeSearchModeAsText(),
                'isShippingCostEnabled'   => (bool) $this->isShippingCostEnabled,
                'defaultShippingCost'     => $this->defaultShippingCost,
                'modeOfBasePrices'        => Settings::getEbayModeOfBasePricesAsText(),
                'qtyItemsMax'             => $clientSettings['qtyItemsMax'],
                'qtyBasePricesMin'        => $this->qtyBasePricesMin,
                'qtyAvgPrices'            => $this->qtyAvgPrices,
                'conditionIds'            => [
                    'new'  => $clientSettings['newConditionIds'],
                    'used' => $clientSettings['usedConditionIds'],
                ],
            ],
        ]);
        $this->logger->save($this->searchLog, Ebay::CLASS_NAME . '__Settings', $log, 'json');

        return $this;
    }

    public function getItems(): ?array
    {
        if (null !== $this->items) {
            return $this->items;
        }
        $this->items = $items = [];

        $this->saveSettingsToLog();
        $condition = $this->getEbayClient()->getIsConditionNew();
        $chunksCategoryIds = array_chunk($this->categoryIds, static::MAX_CATEGORIES) ?: [[]];
        foreach ($this->ebayByCountries as $ebayCountry) {
            foreach ($chunksCategoryIds as $categoryIds) {
                $ebayClient = $this
                    ->getEbayClient()
                    ->clear()
                    // ->setNewCondition()
                    // ->setUsedCondition()
                    ->setIsConditionNew($condition)
                    ->setGlobalId($ebayCountry['globalId'])
                    ->setCurrency($ebayCountry['currency'])
                    ->setCategoryIds($categoryIds);
                if ($ebayCountry['searchInEurope'] ?? null) {
                    $ebayClient->setSearchInEurope();
                }
                $itemsOfCountry = $ebayClient->getItemsFoundByKeywords($this->artikul);
                $items = array_merge($items, $itemsOfCountry);
            }
        }

        if (!$items) {
            return $this->items;
        }
        $items = $this->removeDuplicatedItems($items);
        $items = $this->buildItemsByItemId($items);

        $this->items = $items;

        $log = "\r\n\r\n".json_encode([
            'count'         => count($this->items),
            'filteredItems' => $this->items,
        ]);
        $this->logger->save($this->searchLog, Ebay::CLASS_NAME . '__FilteredItems', $log, 'json');
        return $this->items;
    }

    private function removeDuplicatedItems(array $items): array
    {
        $uniqueItems = [];
        $countByItemIds = [];
        $duplicateItems = [];

        foreach ($items as $item) {
            $itemId = $this->ebayClient->getItemId($item);
            if (!isset($countByItemIds[$itemId])) {
                $countByItemIds[$itemId] = 1;
                $uniqueItems[] = $item;
                continue;
            }
            $duplicateItems[] = $item;
            $countByItemIds[$itemId]++;
        }

        if (!$duplicateItems) {
            return $items;
        }

        $countOfDuplicateByItemIds = [];
        foreach ($countByItemIds as $itemId => $count) {
            if ($count < 2) {
                continue;
            }
            $countOfDuplicateByItemIds[$itemId] = $count - 1;
        }

        $log = "\r\n\r\n".json_encode([
            'count'          => count($duplicateItems),
            'countOfDuplicatedItemsByItemIds' => $countOfDuplicateByItemIds,
            'duplicatedItems' => $duplicateItems,
        ]);
        $this->logger->save($this->searchLog, Ebay::CLASS_NAME . '__DuplicatedItems', $log, 'json');

        return $uniqueItems;
    }

    private function buildItemsByItemId(array $items): array
    {
        $itemsByItemId = [];
        foreach ($items as $item) {
            $itemsByItemId[$this->ebayClient->getItemId($item)] = $item;
        }
        return $itemsByItemId;
    }

    public function getItemsUsed(): int
    {
        return count($this->getBasePrices() ?: []);
    }

    public function getItemsFound(): ?int
    {
        return count($this->getItems() ?: []);
    }

    public function getData(): ?array
    {
        return $this->getEbayClient()->getData();
    }

    public function getEbayClient(): EbayClient
    {
        return $this->ebayClient;
    }

    public function getErrors($attribute = NULL)
    {
        return array_merge(parent::getErrors($attribute), $this->getEbayClient()->getErrors());
    }

    // public function setLog(
    // {
    //     $this->artikul = $artikul;
    //     return $this;
    // }

    // public function isQtyItemsExceeded(): bool
    // {
    //     return $this->getEbayClient()->hasErrors(Errors::getNameError(Errors::QTY_ITEMS_EXCEEDED));
    // }

    // public function getResultPage()
    // {
    //     $artikul = '1485AC1';

    //     $domain = 'www.ebay.com';
    //     $path = 'sch/i.html';
    //     $getData = [
    //         '_nkw' => $artikul
    //     ];

    //     $web = new httpClient;
    //     $web->setSecure();
    //     $web->setDomain($domain);
    //     $web->setPath($path);
    //     $web->setGetData($getData);
    //     $web->getBody();
    // }

    public function getMaxPrice(): ?float
    {
        $isPriceModeAvg = true;
        if ($isPriceModeAvg) {
            $avgPrices = [
                $this->calcAvgPrice($this->newBasePrices),
                $this->calcAvgPrice($this->usedBasePrices),
            ];
            return max($avgPrices);
        }

        $prices = [];
        if ($this->newBasePrices) {
            $prices = array_merge($prices, $this->newBasePrices);
        }
        if ($this->usedBasePrices) {
            $prices = array_merge($prices, $this->usedBasePrices);
        }
        if (!$prices) {
            return null;
        }
        return max($prices);
    }

    public function hasItems(): bool
    {
        return null !== $this->getMaxPrice();
    }

    /**
     * @param int[] $itemIds
     * @param bool $usedInCalculation
     * @return $this
     */
    private function saveItems(array $itemIds, bool $usedInCalculation): self
    {
        $qties = [];
        $itemIdsWoQty = $itemIds;
        if ($this->isAvgPriceBasedOnQty()) {
            $qties = $this->getBaseQties();
            $itemIdsWoQty = array_filter($itemIds, function ($itemId) use ($qties) {
                return !isset($qties[$itemId]);
            });
        }


        if ($usedInCalculation && $itemIdsWoQty) {
            $qties = $qties + $this->getEbayClient()->getItemsQty($itemIdsWoQty);
        }
        foreach ($itemIds as $itemId) {
            $rawItem = $this->getItems()[$itemId];
            $condition = $this->getEbayClient()->isConditionNew()
                ? EbayItem::CONDITION_NEW
                : ($this->getEbayClient()->isConditionUsed() ? EbayItem::CONDITION_USED : '');
            $qty = $qties[$itemId] ?? null;
            $defaultShippingCostWhenApplied = null;
            if ($this->isShippingCostEnabled && null === $this->getShippingCost($rawItem)) {
                $defaultShippingCostWhenApplied = $this->getDefaultShippingCost();
            }

            $item = new EbayItem;
            $item->searchLogId  = $this->searchLog->id;
            $item->usedInCalculation = $usedInCalculation;
            $item->condition    = $condition;
            $item->itemId       = $itemId;
            $item->title        = $this->getEbayClient()->getItemTitle($rawItem);
            $item->price        = $this->getPrice($rawItem);
            $item->qty          = $qty;
            $item->shippingCost = $this->getShippingCost($rawItem);
            $item->isShippingCostApplied          = $this->isShippingCostEnabled;
            $item->defaultShippingCostWhenApplied = $defaultShippingCostWhenApplied;
            $item->img          = $this->getEbayClient()->getItemPreviewImg($rawItem);
            $item->url          = $this->getEbayClient()->getItemUrl($rawItem);
            $item->save();
        }
        return $this;
    }

    private function convertPrice(float $price, string $currency): float
    {
        return $price * $this->getCurrencyRate($currency);
    }

    private function getPrice(array $item): float
    {
        $price    = $this->getEbayClient()->getItemPrice($item);
        $currency = $this->getEbayClient()->getItemCurrency($item);
        if ($this->getEbayClient()->getItemBuyItNowAvailable($item)) {
            $price    = $this->getEbayClient()->getItemBuyItNowPrice($item);
            $currency = $this->getEbayClient()->getItemBuyItNowCurrency($item);
        }

        return $this->convertPrice($price, $currency);
    }

    private function getDefaultShippingCost(): float
    {
        return $this->convertPrice(
            $this->defaultShippingCost,
            $this->defaultShippingCurrency
        );
    }

    private function getShippingCost(array $item): ?float
    {
        $shippingCost     = $this->getEbayClient()->getItemShippingCost($item);
        $shippingCurrency = $this->getEbayClient()->getItemShippingCurrency($item);

        if (null === $shippingCost || null === $shippingCurrency) {
            return null;
        }

        return $this->convertPrice(
            $shippingCost,
            $shippingCurrency
        );
    }

    private function getTotalPrice(array $item): float
    {
        $shippingCost = $this->getShippingCost($item);
        if (null === $shippingCost) {
            $shippingCost = $this->getDefaultShippingCost();
        }
        return $this->getPrice($item) + $shippingCost;
    }

    public function isAvailable(): bool
    {
        return parent::isAvailable() && $this->isMinusArtikulsValid();
    }

    public function isMinusArtikulsValid(): bool
    {
        return !$this->isMinusArtikulsEnabled || !in_array($this->artikul, $this->minusArtikuls);
    }
}
