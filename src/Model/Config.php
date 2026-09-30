<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;
use Polkurier\Shipping\Model\Shipment\ParcelWeightEnum;
use Polkurier\Shipping\Model\Shipment\WeightLimitEnum;
use Polkurier\Webservice\Enum\Courier\PointFunctionEnum;
use Polkurier\Webservice\Enum\Http\ApiMethodEnum;
use Polkurier\Webservice\Enum\Order\AdditionalServiceEnum;
use Polkurier\Webservice\Enum\Order\CodReturnTypeEnum;
use Polkurier\Webservice\Enum\Order\CodTypeEnum;
use Polkurier\Webservice\Enum\Order\LabelFileFormatEnum;
use Polkurier\Webservice\Enum\Order\ShipmentTypeEnum;


/**
 * The module's system configuration.
 *
 * The Polkurier account is one for the whole installation: its credentials, environment, logging,
 * available carriers and synchronization are read from the default scope, with no store. The
 * rest, how the module works with Magento's orders and storefront, is read for a given store, and
 * may differ per website or store view. A null store id means the current store, which in the
 * admin is the default scope.
 */
class Config
{
    public const string XML_PATH_ACTIVE = 'carriers/polkurier/active';
    public const string XML_PATH_TITLE = 'carriers/polkurier/title';
    public const string XML_PATH_LOGIN = 'carriers/polkurier/general/customer_id';
    public const string XML_PATH_TOKEN = 'carriers/polkurier/general/api_token';
    public const string XML_PATH_SANDBOX = 'carriers/polkurier/general/sandbox_mode';
    public const string XML_PATH_API_URL = 'carriers/polkurier/general/api_url';
    public const string XML_PATH_MAP_URL = 'carriers/polkurier/map/url';
    public const string XML_PATH_TIMEOUT = 'carriers/polkurier/general/timeout';
    public const string XML_PATH_LOGGING = 'carriers/polkurier/general/logging';
    public const string XML_PATH_LOG_METHODS = 'carriers/polkurier/general/log_methods';
    public const string XML_PATH_AVAILABLE_CARRIERS = 'carriers/polkurier/shipment_defaults/available_carriers';
    public const string XML_PATH_DEFAULT_SHIPMENT_TYPE = 'carriers/polkurier/shipment_defaults/default_shipment_type';
    public const string XML_PATH_USE_STORE_INFORMATION_ADDRESS = 'carriers/polkurier/sender/use_store_information_address';
    public const string XML_PATH_USE_ORIGIN_ADDRESS = 'carriers/polkurier/sender/use_store_origin_address';
    public const string XML_PATH_SENDER_EMAIL = 'carriers/polkurier/sender/default_sender_email';
    public const string XML_PATH_DEFAULT_SERVICES = 'carriers/polkurier/shipment_defaults/default_additional_services';
    public const string XML_PATH_INSURE_BY_DEFAULT = 'carriers/polkurier/shipment_defaults/insure_by_default';
    public const string XML_PATH_PACK_NEW_SHIPMENTS = 'carriers/polkurier/packing/pack_new_shipments';
    public const string XML_PATH_PACKING_MARGIN = 'carriers/polkurier/packing/packing_margin';
    public const string XML_PATH_WEIGHT_LIMIT = 'carriers/polkurier/packing/weight_limit';
    public const string XML_PATH_PARCEL_WEIGHT = 'carriers/polkurier/packing/parcel_weight';
    public const string XML_PATH_DEFAULT_COD_TYPE = 'carriers/polkurier/cod/default_cod_type';
    public const string XML_PATH_DEFAULT_COD_RETURN_TYPE = 'carriers/polkurier/cod/default_cod_refund_type';
    public const string XML_PATH_DESCRIPTION_FORMAT = 'carriers/polkurier/shipment_defaults/description_format';
    public const string XML_PATH_LABEL_FORMAT = 'carriers/polkurier/labels/label_format';
    public const string XML_PATH_NOTIFY_CUSTOMER = 'carriers/polkurier/labels/notify_customer';
    public const string XML_PATH_NATIVE_LABELS = 'carriers/polkurier/labels/native_labels';
    public const string XML_PATH_SHIPMENT_SCREEN = 'carriers/polkurier/labels/shipment_screen';
    public const string XML_PATH_COD_PAYMENT_METHODS = 'carriers/polkurier/cod/cod_payment_methods';
    public const string XML_PATH_MAP_LATITUDE = 'carriers/polkurier/map/default_latitude';
    public const string XML_PATH_MAP_LONGITUDE = 'carriers/polkurier/map/default_longitude';
    public const string XML_PATH_MAP_ZOOM = 'carriers/polkurier/map/default_zoom';
    public const string XML_PATH_MAP_SHOW_LIST = 'carriers/polkurier/map/show_list';
    public const string XML_PATH_MAP_SHOW_SEARCH = 'carriers/polkurier/map/show_search_input';
    public const string XML_PATH_MAP_POINT_FUNCTIONS = 'carriers/polkurier/map/required_point_functions';
    public const string XML_PATH_SYNC_ENABLED = 'carriers/polkurier/sync/enabled';
    public const string XML_PATH_STATUS_SYNC_ENABLED = 'carriers/polkurier/sync/status_enabled';

    public const string MAP_URL = 'https://maps.polkurier.pl';
    public const string SANDBOX_MAP_URL = 'https://maps-sandbox.polkurier.pl';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly EncryptorInterface $encryptor,
    ) {}

    public function isActive(?int $storeId = null): bool
    {
        return $this->flag(self::XML_PATH_ACTIVE, $storeId);
    }

    /**
     * The carrier's title in Magento's configuration. It names the module in the admin and is
     * never shown to customers.
     */
    public function getTitle(?int $storeId = null): string
    {
        return (string)$this->value(self::XML_PATH_TITLE, $storeId);
    }

    public function getLogin(): string
    {
        return $this->globalDecrypt(self::XML_PATH_LOGIN);
    }

    public function getToken(): string
    {
        return $this->globalDecrypt(self::XML_PATH_TOKEN);
    }

    public function hasCredentials(): bool
    {
        return $this->getLogin() !== '' && $this->getToken() !== '';
    }

    public function isSandbox(): bool
    {
        return $this->globalFlag(self::XML_PATH_SANDBOX);
    }

    /**
     * An API endpoint other than production and sandbox, e.g. a local instance; null lets the SDK
     * choose by the sandbox flag. Not in the admin: set in config.xml or with bin/magento config:set.
     */
    public function getApiUrl(): ?string
    {
        $url = \trim((string)$this->globalValue(self::XML_PATH_API_URL));

        return $url !== '' ? \rtrim($url, '/') : null;
    }

    public function getTimeout(): int
    {
        $timeout = (int)$this->globalValue(self::XML_PATH_TIMEOUT);

        return $timeout > 0 ? $timeout : 30;
    }

    public function isLoggingEnabled(): bool
    {
        return $this->globalFlag(self::XML_PATH_LOGGING);
    }

    /**
     * @return ApiMethodEnum[]
     */
    public function getMethodsToLog(): array
    {
        return \array_values(\array_filter(\array_map(
            static fn(string $method): ?ApiMethodEnum => ApiMethodEnum::tryFrom($method),
            $this->globalList(self::XML_PATH_LOG_METHODS),
        )));
    }

    /**
     * The carriers the merchant works with; the others are left out of every list. Empty: all of
     * the account's carriers.
     *
     * @return string[] carrier codes
     */
    public function getAvailableCarriers(): array
    {
        return \array_values(\array_filter(\array_map('trim', \explode(',', (string)$this->globalValue(self::XML_PATH_AVAILABLE_CARRIERS)))));
    }

    public function getDefaultShipmentType(?int $storeId = null): ShipmentTypeEnum
    {
        return ShipmentTypeEnum::tryFrom((string)$this->value(self::XML_PATH_DEFAULT_SHIPMENT_TYPE, $storeId))
            ?? ShipmentTypeEnum::Box;
    }

    /**
     * Whether a shipment without a sender from the address book is sent from the store's address
     * (General > Store Information).
     */
    public function isUseStoreInformationAddress(?int $storeId = null): bool
    {
        return $this->flag(self::XML_PATH_USE_STORE_INFORMATION_ADDRESS, $storeId);
    }

    public function isUseStoreOriginAddress(?int $storeId = null): bool
    {
        return $this->flag(self::XML_PATH_USE_ORIGIN_ADDRESS, $storeId);
    }

    /**
     * Identity code of the store e-mail used as the sender's address, e.g. "general".
     */
    public function getSenderEmailIdentity(?int $storeId = null): string
    {
        return (string)$this->value(self::XML_PATH_SENDER_EMAIL, $storeId) ?: 'general';
    }

    /**
     * @return AdditionalServiceEnum[]
     */
    public function getDefaultServices(?int $storeId = null): array
    {
        return \array_values(\array_filter(\array_map(
            static fn(string $service): ?AdditionalServiceEnum => AdditionalServiceEnum::tryFrom($service),
            $this->list(self::XML_PATH_DEFAULT_SERVICES, $storeId),
        )));
    }

    public function isInsureByDefault(?int $storeId = null): bool
    {
        return $this->flag(self::XML_PATH_INSURE_BY_DEFAULT, $storeId);
    }

    /**
     * Whether a new shipment starts packed automatically; packing on request is always available.
     */
    public function isPackingNewShipments(?int $storeId = null): bool
    {
        return $this->flag(self::XML_PATH_PACK_NEW_SHIPMENTS, $storeId);
    }

    /**
     * Centimetres added to every dimension of every product when packing into parcels.
     */
    public function getPackingMargin(?int $storeId = null): float
    {
        return \max(0.0, (float)\str_replace(',', '.', (string)$this->value(self::XML_PATH_PACKING_MARGIN, $storeId)));
    }

    public function getWeightLimit(?int $storeId = null): WeightLimitEnum
    {
        return WeightLimitEnum::tryFrom((string)$this->value(self::XML_PATH_WEIGHT_LIMIT, $storeId)) ?? WeightLimitEnum::Warn;
    }

    public function getParcelWeight(?int $storeId = null): ParcelWeightEnum
    {
        return ParcelWeightEnum::tryFrom((string)$this->value(self::XML_PATH_PARCEL_WEIGHT, $storeId)) ?? ParcelWeightEnum::Products;
    }

    public function getDefaultCodType(?int $storeId = null): CodTypeEnum
    {
        return CodTypeEnum::tryFrom((string)$this->value(self::XML_PATH_DEFAULT_COD_TYPE, $storeId))
            ?? CodTypeEnum::Standard;
    }

    public function getDefaultCodReturnType(?int $storeId = null): CodReturnTypeEnum
    {
        return CodReturnTypeEnum::tryFrom((string)$this->value(self::XML_PATH_DEFAULT_COD_RETURN_TYPE, $storeId))
            ?? CodReturnTypeEnum::BankAccount;
    }

    /**
     * sprintf-style format of the shipment description; %s is the order increment id.
     */
    public function getDescriptionFormat(?int $storeId = null): string
    {
        return (string)$this->value(self::XML_PATH_DESCRIPTION_FORMAT, $storeId) ?: '%s';
    }

    /**
     * Null means PDF, which the API returns when no printer language is asked for.
     */
    public function getLabelFormat(?int $storeId = null): ?LabelFileFormatEnum
    {
        return LabelFileFormatEnum::tryFrom((string)$this->value(self::XML_PATH_LABEL_FORMAT, $storeId));
    }

    /**
     * Whether Magento's own shipment page creates shipping labels by placing Polkurier orders.
     */
    public function isNativeLabelsEnabled(?int $storeId = null): bool
    {
        return $this->flag(self::XML_PATH_NATIVE_LABELS, $storeId);
    }

    /**
     * Whether orders get the module's own shipment screen.
     */
    public function isShipmentScreenEnabled(?int $storeId = null): bool
    {
        return $this->flag(self::XML_PATH_SHIPMENT_SCREEN, $storeId);
    }

    public function isNotifyCustomer(?int $storeId = null): bool
    {
        return $this->flag(self::XML_PATH_NOTIFY_CUSTOMER, $storeId);
    }

    /**
     * Payment methods that make an order cash on delivery.
     *
     * @return string[]
     */
    public function getCodPaymentMethods(?int $storeId = null): array
    {
        return $this->list(self::XML_PATH_COD_PAYMENT_METHODS, $storeId);
    }

    public function getMapUrl(): string
    {
        // A map token only works with the map of the environment that issued it.
        $url = \trim((string)$this->globalValue(self::XML_PATH_MAP_URL));
        if ($url !== '') {
            return \rtrim($url, '/');
        }

        return $this->isSandbox() ? self::SANDBOX_MAP_URL : self::MAP_URL;
    }

    public function getMapLatitude(?int $storeId = null): float
    {
        return (float)$this->value(self::XML_PATH_MAP_LATITUDE, $storeId);
    }

    public function getMapLongitude(?int $storeId = null): float
    {
        return (float)$this->value(self::XML_PATH_MAP_LONGITUDE, $storeId);
    }

    public function getMapZoom(?int $storeId = null): int
    {
        return (int)$this->value(self::XML_PATH_MAP_ZOOM, $storeId);
    }

    public function isMapShowList(?int $storeId = null): bool
    {
        return $this->flag(self::XML_PATH_MAP_SHOW_LIST, $storeId);
    }

    public function isMapShowSearch(?int $storeId = null): bool
    {
        return $this->flag(self::XML_PATH_MAP_SHOW_SEARCH, $storeId);
    }

    /**
     * @return PointFunctionEnum[]
     */
    public function getMapPointFunctions(?int $storeId = null): array
    {
        return \array_values(\array_filter(\array_map(
            static fn(string $function): ?PointFunctionEnum => PointFunctionEnum::tryFrom($function),
            $this->list(self::XML_PATH_MAP_POINT_FUNCTIONS, $storeId),
        )));
    }

    public function isSyncEnabled(): bool
    {
        return $this->globalFlag(self::XML_PATH_SYNC_ENABLED);
    }

    public function isStatusSyncEnabled(): bool
    {
        return $this->globalFlag(self::XML_PATH_STATUS_SYNC_ENABLED);
    }

    /**
     * A setting of the Polkurier account, which is one for the whole installation: read from the
     * default scope only, whatever a website or store view may still hold.
     */
    private function globalValue(string $path): mixed
    {
        return $this->scopeConfig->getValue($path, ScopeConfigInterface::SCOPE_TYPE_DEFAULT);
    }

    private function globalFlag(string $path): bool
    {
        return $this->scopeConfig->isSetFlag($path, ScopeConfigInterface::SCOPE_TYPE_DEFAULT);
    }

    /**
     * @return string[]
     */
    private function globalList(string $path): array
    {
        return \array_values(\array_filter(
            \array_map('trim', \explode(',', (string)$this->globalValue($path))),
            static fn(string $item): bool => $item !== '',
        ));
    }

    private function globalDecrypt(string $path): string
    {
        $value = (string)$this->globalValue($path);

        return $value === '' ? '' : \trim($this->encryptor->decrypt($value));
    }

    private function value(string $path, ?int $storeId): mixed
    {
        return $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $storeId);
    }

    private function flag(string $path, ?int $storeId): bool
    {
        return $this->scopeConfig->isSetFlag($path, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @return string[]
     */
    private function list(string $path, ?int $storeId): array
    {
        return \array_values(\array_filter(
            \array_map('trim', \explode(',', (string)$this->value($path, $storeId))),
            static fn(string $item): bool => $item !== '',
        ));
    }

}
