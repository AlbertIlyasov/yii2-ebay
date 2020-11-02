<?php

namespace app\models;

use yii\db\Query;

/**
ALTER TABLE `register` ADD COLUMN `descr` varchar(255) NULL DEFAULT NULL;
INSERT INTO `register` (`name`, `value`, `descr`)
VALUES ('maxQty', 10, 'Максимальное кол-во товара, которое может искать в интернете (применяется для ебей)');
 *
 * @property int id
 * @property int qtyItemsMax Максимальное кол-во товаров, при превышении которого парсинг новых страниц прекращается
 * @property int qtyBasePricesMax При превышении этого кол-ва цен для расчёта средней цены будет используется то кол-во цен, которое указано в qtyAvgPrices
 * @property int qtyAvgPrices При превышении кол-ва цен в qtyBasePricesMax для расчёта средней цены будет использоваться кол-во цен этой переменной
 * @property bool RadwellNoStockAllow Если у Radwell.co.uk товар не в наличии, учитывать ли такой товар для определения цены. Если учитывать, то тогда дополнительно проверяется, чтобы дата последней цены была не старее указанной в поле RadwellLastRetailPriceUpdateMin.
 * @property date RadwellLastRetailPriceUpdateMin Доп.поле для поля RadwellNoStockAllow.
 * @property string EbayAppId ID зарегистрированного приложения в Ebay API
 * @property int localSourceSearchMode Режим поиска по локально загруженным прайсам: 0-equal;1-like;2-regexp 0 or 1 symbol;3-regexp 0 or more symbols
 * @author Albert Ilyasov <kralbert@mail.ru>
 */
class Settings extends \yii\db\ActiveRecord
{
    /** Model class name for work with ebay */
    const SOURCE_EBAY = 'Ebay';
    const SOURCE_RADWELL = 'Radwell';
    const SOURCE_LOCAL = 'Local';
    const SOURCE_TYPE_WEB = 1;
    const SOURCE_TYPE_LOCAL = 2;

    const LOCAL_SOURCE_SEARCH_MODE_EQUAL = 0;
    const LOCAL_SOURCE_SEARCH_MODE_LIKE = 1;
    const LOCAL_SOURCE_SEARCH_MODE_REGEXP_0_OR_1 = 2;

    const QTY_ITEMS_MAX = 5;

    const SOURCE_RATE_TYPE_COST = 1;
    const SOURCE_RATE_TYPE_MARGIN_NEW = 2;
    const SOURCE_RATE_TYPE_MARGIN_USED = 3;
    const SOURCE_RATE_TYPE_MARGIN_ALONE = 4;

    const EBAY_MODE_CENTER_OF_BASE_PRICES = 0;
    const EBAY_MODE_BEGINNING_OF_BASE_PRICES = 1;

    const EBAY_EUROPE_SEARCH_MODE_DE_GB_WO_EUROPE           = 1;
    const EBAY_EUROPE_SEARCH_MODE_DE_W_EUROPE               = 2;
    const EBAY_EUROPE_SEARCH_MODE_EVERY_COUNTRIES_WO_EUROPE = 3;
    const EBAY_EUROPE_SEARCH_MODE_EVERY_COUNTRIES_W_EUROPE  = 4;

    const EBAY_EUROPE_SEARCH_MODE_TITLES  = [
        self::EBAY_EUROPE_SEARCH_MODE_DE_GB_WO_EUROPE
            => 'Германия и Великобритания, без опции поиска по Европе',
        self::EBAY_EUROPE_SEARCH_MODE_DE_W_EUROPE
            => 'Германия, c опцией поиска по Европе',
        self::EBAY_EUROPE_SEARCH_MODE_EVERY_COUNTRIES_WO_EUROPE
            => '10 стран Европы, без опции поиска по Европе',
        self::EBAY_EUROPE_SEARCH_MODE_EVERY_COUNTRIES_W_EUROPE
            => '10 стран Европы, c опцией поиска по Европе',
    ];

    const EBAY_BY_COUNTRIES = [
        'EBAY-US' => [
            'ebayId'   => 0,
            'globalId' => 'EBAY-US', //United States
            'currency' => 'USD',
        ],
        'EBAY-ENCA' => [
            'ebayId'   => 2,
            'globalId' => 'EBAY-ENCA', //Canada (English)
            'currency' => 'CAD',
        ],
        'EBAY-GB' => [
            'ebayId'   => 3,
            'globalId' => 'EBAY-GB', //UK
            'currency' => 'GBP',
        ],
        'EBAY-AU' => [
            'ebayId'   => 15,
            'globalId' => 'EBAY-AU', //Australia
            'currency' => 'AUD',
        ],
        'EBAY-AT' => [
            'ebayId'   => 16,
            'globalId' => 'EBAY-AT', //Austria
            'currency' => 'EUR',
        ],
        'EBAY-FRBE' => [
            'ebayId'   => 23,
            'globalId' => 'EBAY-FRBE', //Belgium (French)
            'currency' => 'EUR',
        ],
        'EBAY-FR' => [
            'ebayId'   => 71,
            'globalId' => 'EBAY-FR', //France
            'currency' => 'EUR',
        ],
        'EBAY-DE' => [
            'ebayId'   => 77,
            'globalId' => 'EBAY-DE', //Germany
            'currency' => 'EUR',
        ],
        'EBAY-MOTOR' => [
            'ebayId'   => 100,
            'globalId' => 'EBAY-MOTOR', //Motors
            'currency' => 'EUR',
        ],
        'EBAY-IT' => [
            'ebayId'   => 101,
            'globalId' => 'EBAY-IT', //Italy
            'currency' => 'EUR',
        ],
        'EBAY-NLBE' => [
            'ebayId'   => 123,
            'globalId' => 'EBAY-NLBE', //Belgium (Dutch)
            'currency' => 'EUR',
        ],
        'EBAY-NL' => [
            'ebayId'   => 146,
            'globalId' => 'EBAY-NL', //Netherlands
            'currency' => 'EUR',
        ],
        'EBAY-ES' => [
            'ebayId'   => 186,
            'globalId' => 'EBAY-ES', //Spain
            'currency' => 'EUR',
        ],
        'EBAY-CH' => [
            'ebayId'   => 193,
            'globalId' => 'EBAY-CH', //Switzerland
            'currency' => 'CHF',
        ],
        'EBAY-HK' => [
            'ebayId'   => 201,
            'globalId' => 'EBAY-HK', //Hong Kong
            'currency' => 'EUR',
        ],
        'EBAY-IN' => [
            'ebayId'   => 203,
            'globalId' => 'EBAY-IN', //India
            'currency' => 'EUR',
        ],
        'EBAY-IE' => [
            'ebayId'   => 205,
            'globalId' => 'EBAY-IE', //Ireland
            'currency' => 'EUR',
        ],
        'EBAY-MY' => [
            'ebayId'   => 207,
            'globalId' => 'EBAY-MY', //Malaysia
            'currency' => '',
        ],
        'EBAY-FRCA' => [
            'ebayId'   => 210,
            'globalId' => 'EBAY-FRCA', //Canada (French)
            'currency' => '',
        ],
        'EBAY-PH' => [
            'ebayId'   => 211,
            'globalId' => 'EBAY-PH', //Philippines
            'currency' => '',
        ],
        'EBAY-PL' => [
            'ebayId'   => 212,
            'globalId' => 'EBAY-PL', //Poland
            'currency' => 'PLN',
        ],
        'EBAY-SG' => [
            'ebayId'   => 216,
            'globalId' => 'EBAY-SG', //Singapore
            'currency' => '',
        ],
    ];

    const IS_EBAY_MINUS_ARTIKULS_ENABLED = 'isEbayMinusArtikulsEnabled';
    const EBAY_MINUS_ARTIKULS            = 'ebayMinusArtikuls';
    const MAX_QTY = 'maxQty';

    /** @var string[]|null */
    private static $availableSources;

    private static $source = [
        [
            'name' => self::SOURCE_EBAY,
            'type' => self::SOURCE_TYPE_WEB,
        ],
    ];

    private static $sourceTypeNames = [
        self::SOURCE_TYPE_WEB   => 'Зарубежный поставщик',
        self::SOURCE_TYPE_LOCAL => 'Российский поставщик',
    ];

    /** @var int unixtimestamp */
    private static $timeStart;

    public static function getEbayByCountries(): array
    {
        return static::getEbayByCountriesOfMode(static::getSettings()->ebayEuropeSearchMode);
    }

    public static function getEbayByCountriesOfMode(int $mode): array
    {
        $ebayByCountries = [
            $alias='EBAY-US' => static::EBAY_BY_COUNTRIES[$alias],
        ];
        switch ($mode) {
            case static::EBAY_EUROPE_SEARCH_MODE_DE_GB_WO_EUROPE:
                return $ebayByCountries + [
                    $alias='EBAY-DE'   => static::EBAY_BY_COUNTRIES[$alias],
                    $alias='EBAY-GB'   => static::EBAY_BY_COUNTRIES[$alias],
                ];
            case static::EBAY_EUROPE_SEARCH_MODE_DE_W_EUROPE:
                return $ebayByCountries + [
                    $alias='EBAY-DE'   => static::EBAY_BY_COUNTRIES[$alias] + ['searchInEurope' => true],
                ];
            case static::EBAY_EUROPE_SEARCH_MODE_EVERY_COUNTRIES_WO_EUROPE:
                return $ebayByCountries + [
                    $alias='EBAY-DE'   => static::EBAY_BY_COUNTRIES[$alias],
                    $alias='EBAY-GB'   => static::EBAY_BY_COUNTRIES[$alias],
                    $alias='EBAY-AT'   => static::EBAY_BY_COUNTRIES[$alias],
                    $alias='EBAY-FRBE' => static::EBAY_BY_COUNTRIES[$alias],
                    $alias='EBAY-FR'   => static::EBAY_BY_COUNTRIES[$alias],
                    $alias='EBAY-IT'   => static::EBAY_BY_COUNTRIES[$alias],
                    $alias='EBAY-NLBE' => static::EBAY_BY_COUNTRIES[$alias],
                    $alias='EBAY-NL'   => static::EBAY_BY_COUNTRIES[$alias],
                    $alias='EBAY-ES'   => static::EBAY_BY_COUNTRIES[$alias],
                    // $alias='EBAY-CH'   => static::EBAY_BY_COUNTRIES[$alias],
                    $alias='EBAY-IE'   => static::EBAY_BY_COUNTRIES[$alias],
                    // $alias='EBAY-PL'   => static::EBAY_BY_COUNTRIES[$alias],
                ];
            case static::EBAY_EUROPE_SEARCH_MODE_EVERY_COUNTRIES_W_EUROPE:
                return $ebayByCountries + [
                    $alias='EBAY-DE'   => static::EBAY_BY_COUNTRIES[$alias] + ['searchInEurope' => true],
                    $alias='EBAY-GB'   => static::EBAY_BY_COUNTRIES[$alias] + ['searchInEurope' => true],
                    $alias='EBAY-AT'   => static::EBAY_BY_COUNTRIES[$alias] + ['searchInEurope' => true],
                    $alias='EBAY-FRBE' => static::EBAY_BY_COUNTRIES[$alias] + ['searchInEurope' => true],
                    $alias='EBAY-FR'   => static::EBAY_BY_COUNTRIES[$alias] + ['searchInEurope' => true],
                    $alias='EBAY-IT'   => static::EBAY_BY_COUNTRIES[$alias] + ['searchInEurope' => true],
                    $alias='EBAY-NLBE' => static::EBAY_BY_COUNTRIES[$alias] + ['searchInEurope' => true],
                    $alias='EBAY-NL'   => static::EBAY_BY_COUNTRIES[$alias] + ['searchInEurope' => true],
                    $alias='EBAY-ES'   => static::EBAY_BY_COUNTRIES[$alias] + ['searchInEurope' => true],
                    // $alias='EBAY-CH'   => static::EBAY_BY_COUNTRIES[$alias] + ['searchInEurope' => true],
                    $alias='EBAY-IE'   => static::EBAY_BY_COUNTRIES[$alias] + ['searchInEurope' => true],
                    // $alias='EBAY-PL'   => static::EBAY_BY_COUNTRIES[$alias] + ['searchInEurope' => true],
                ];
        }
        return $ebayByCountries;
    }

    public static function getAvailableSources()
    {
        if (null !== self::$availableSources) {
            return self::$availableSources;
        }
        self::$availableSources = Source::find()
            ->where(['active' => 1])
            ->orderBy(['sort' => SORT_ASC])
            ->all();
        return self::$availableSources;
    }

    public static function getSourceTypeName(int $sourceType)
    {
        return $names[$sourceType] ?: null;
    }

    public static function getSourceTypeNameBySourceName(string $sourceName)
    {
        return $names[$sourceType] ?: null;
    }

    public static function getSettings()
    {
        return self::findOne(1);
    }

    public static function getQtyItemsMax()
    {
        return self::getSettings()->qtyItemsMax;
    }

    public static function getQtyBasePricesMin()
    {
        return self::getSettings()->qtyBasePricesMin;
    }

    public static function getQtyAvgPrices()
    {
        return self::getSettings()->qtyAvgPrices;
    }

    public static function getCurrency()
    {
        return self::getSettings()->currency;
    }

    public function getLocalSourceSearchMode()
    {
        return $this->localSourceSearchMode;
    }

    public function isLocalSourceSearchModeEqual()
    {
        return self::LOCAL_SOURCE_SEARCH_MODE_EQUAL == $this->getLocalSourceSearchMode();
    }

    public function isLocalSourceSearchModeLike()
    {
        return self::LOCAL_SOURCE_SEARCH_MODE_LIKE == $this->getLocalSourceSearchMode();
    }

    public function isLocalSourceSearchModeRegexp0or1()
    {
        return self::LOCAL_SOURCE_SEARCH_MODE_REGEXP_0_OR_1 == $this->getLocalSourceSearchMode();
    }

    public static function isKeywordsFilterEnabled(): bool
    {
        return (bool) self::getSettings()->keywordsFilterEnabled;
    }

    public static function isConditionFilterEnabled(): bool
    {
        return (bool) static::getSettings()->conditionFilterEnabled;
    }

    public static function getEbayModeOfBasePrices()
    {
        return self::getSettings()->ebayModeOfBasePrices;
    }

    public static function isEbayModeOfBasePricesBeginning(): bool
    {
        return static::EBAY_MODE_BEGINNING_OF_BASE_PRICES == static::getEbayModeOfBasePrices();
    }

    public static function getEbayModesOfBasePrices(): array
    {
        return [
            static::EBAY_MODE_BEGINNING_OF_BASE_PRICES => 'начало',
            static::EBAY_MODE_CENTER_OF_BASE_PRICES => 'середина',
        ];
    }

    public static function getEbayMinusWords(): ?array
    {
        return static::convertStringToArray(self::getSettings()->EbayMinusWords) ?: null;
    }

    public static function convertStringToArray(?string $str): array
    {
        $str = trim($str);
        if (!$str) {
            return [];
        }
        $words = [];
        foreach (explode("\r\n", $str) as $word) {
            $word = trim($word);
            if (!$word) {
                continue;
            }
            $words[] = $word;
        }
        return array_unique($words);
    }

    public function getBatchSize(): int
    {
        return 1*1000;
    }

    public static function setSettingsToParseFile(): void
    {
        // ignore_user_abort(true);
        set_time_limit(1*3600);
    }

    public static function isPreventTimeoutEnabled(): bool
    {
        // return true;
        return false;
    }

    public static function setSettingsToPreventTimeout(): void
    {
        // thx mandor at mandor - php.net
        ini_set('max_execution_time', 0);
        ini_set('implicit_flush', 1);
        ob_implicit_flush(1);
        // ob_end_flush();
        // flush();

        //nginx conf: fastcgi_buffering off;
        header('X-Accel-Buffering: no');
    }

    public static function sendDataToPreventTimeout(int $repeat = 1): void
    {
        echo 1;
        $repeat--;
        for ($i=0; $i<$repeat; $i++) {
            echo ' ';
        }
        // sleep(1);
        ob_flush();
        flush();
    }

    public static function getEbayCategoryIds(): array
    {
        return static::convertStringToIntArray(self::getSettings()->EbayCategoryId);
    }

    public static function getEbayExcludeCategoryIds(): array
    {
        return static::convertStringToIntArray(self::getSettings()->EbayExcludeCategoryIds);
    }

    /**
     * @param string string Example: "123,456"
     * @param string delimiter
     * @return int[] Example: [123,456]
     */
    public static function convertStringToIntArray(string $string, string $delimiter = ','): array
    {
        if (!$string) {
            return [];
        }

        return array_map('intval', explode($delimiter, $string));
    }

    public static function getEbayModeOfBasePricesAsText(): string
    {
        $mode = static::getSettings()->ebayModeOfBasePrices;
        $modes = [
            static::EBAY_MODE_CENTER_OF_BASE_PRICES    => 'center',
            static::EBAY_MODE_BEGINNING_OF_BASE_PRICES => 'begin',
        ];

        return $modes[$mode] ?? $mode;
    }

    public static function getEbayEuropeSearchModeAsText(): string
    {
        return static::getEbayEuropeSearchModeLabel(static::getSettings()->ebayEuropeSearchMode);
    }

    public static function getEbayEuropeSearchModeLabel(int $mode): string
    {
        return static::EBAY_EUROPE_SEARCH_MODE_TITLES[$mode] ?? $mode;
    }

    public static function isLogExpireEnabled(): bool
    {
        return static::getSettings()->isLogExpireEnabled;
    }

    public static function getLogExpirePeriod(): int
    {
        return 3600*24*static::getSettings()->logExpirePeriod;
    }

    public static function getLogExpireValidatePeriod(): int
    {
        return 3600*24*static::getSettings()->logExpireValidatePeriod;
    }

    public static function isCurrentTimeTimeToDeleteExpiredFiles(): bool
    {
        if (!Register::getLogExpireValidateDate()) {
            return true;
        }
        return time() > strtotime(Register::getLogExpireValidateDate()) + static::getLogExpireValidatePeriod();
    }

    public static function isEbayMinusArtikulsEnabled(): bool
    {
        return (bool) Register::get(static::IS_EBAY_MINUS_ARTIKULS_ENABLED);
    }

    public static function setIsEbayMinusArtikulsEnabled(bool $isEnabled): void
    {
        Register::set(static::IS_EBAY_MINUS_ARTIKULS_ENABLED, $isEnabled);
    }

    public static function getEbayMinusArtikulsAsArray(): array
    {
        return static::convertStringToArray(static::getEbayMinusArtikuls());
    }

    public static function getEbayMinusArtikuls(): string
    {
        return (string) Register::get(static::EBAY_MINUS_ARTIKULS);
    }

    public static function setEbayMinusArtikuls(string $artikuls): void
    {
        Register::set(static::EBAY_MINUS_ARTIKULS, $artikuls);
    }

    public static function getDuration(): float
    {
        return microtime(true) - (static::$timeStart ?? static::$timeStart = microtime(true));
    }

    public static function getDatetime(): string
    {
        return date('Y-m-d H:i:s');
    }

    public static function getDatetimeFromDb(): string
    {
        return (new Query)
            ->select('NOW() as now')
            ->one()['now'];
    }

    public static function getMaxQty(): int
    {
        return Register::get(static::MAX_QTY);
    }
}
