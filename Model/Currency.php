<?php
    /**
     * @Thomas-Athanasiou
     *
     * @author Thomas Athanasiou {thomas@hippiemonkeys.com}
     * @link https://hippiemonkeys.com
     * @link https://github.com/Thomas-Athanasiou
     * @copyright Copyright (c) 2022 Hippiemonkeys Web Inteligence EE All Rights Reserved.
     * @license http://www.gnu.org/licenses/ GNU General Public License, version 3
     * @package Hippiemonkeys_ModificationMagentoDirectory
     */

    namespace Hippiemonkeys\ModificationMagentoDirectory\Model;

    use Magento\Framework\App\ObjectManager,
        Magento\Framework\Exception\InputException,
        Magento\Directory\Model\Currency\Filter,
        Magento\Framework\Locale\Currency as LocaleCurrency,
        Magento\Framework\Locale\ResolverInterface as LocaleResolverInterface,
        Magento\Framework\NumberFormatterFactory,
        Magento\Framework\Serialize\Serializer\Json as Serializer,
        Magento\Framework\Exception\LocalizedException,
        Magento\Framework\Model\AbstractModel,
        Magento\Directory\Model\Currency as ParentCurrency,
        Magento\Directory\Model\CurrencyConfig,
        Magento\Framework\Model\Context,
        Magento\Framework\Registry,
        Magento\Framework\Locale\FormatInterface,
        Magento\Store\Model\StoreManagerInterface,
        Magento\Directory\Helper\Data as DirectoryHelper,
        Magento\Framework\DataObjectFactory,
        Magento\Framework\Event\ManagerInterface as EventManagerInterface,
        Magento\Framework\Model\ResourceModel\AbstractResource,
        Magento\Framework\Data\Collection\AbstractDb,
        Magento\Directory\Model\Currency\FilterFactory,
        Magento\Framework\Locale\CurrencyInterface,
        Hippiemonkeys\Core\Api\Helper\ConfigInterface;

    /**
     * Currency model
     *
     * @api
     *
     * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
     * @since 100.0.2
     */
    class Currency
    extends ParentCurrency
    {
        protected const CONFIG_PATH_MODIFICATION_STATUS = 'currency_status';

        /**
         * Number Formatter property
         *
         * @access private
         *
         * @var \Magento\Framework\NumberFormatter
         */
        private $numberFormatter;

        /**
         * @var array
         */
        private $numberFormatterCache;

        /**
         * Constructor
         *
         * @access public
         *
         * @param \Magento\Framework\Model\Context $context
         * @param \Magento\Framework\Registry $registry
         * @param \Magento\Framework\Locale\FormatInterface $localeFormat
         * @param \Magento\Store\Model\StoreManagerInterface $storeManager
         * @param \Magento\Directory\Helper\Data $directoryHelper
         * @param \Magento\Directory\Model\Currency\FilterFactory $currencyFilterFactory
         * @param \Magento\Framework\Locale\CurrencyInterface $localeCurrency
         * @param \Hippiemonkeys\Core\Api\Helper\ConfigInterface $config
         * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
         * @param \Magento\Framework\Data\Collection\AbstractDb|null $resourceCollection
         * @param array $data
         * @param \Magento\Directory\Model\CurrencyConfig|null $currencyConfig
         * @param \Magento\Framework\Locale\ResolverInterface|null $localeResolver
         * @param \Magento\Framework\NumberFormatterFactory|null $numberFormatterFactory
         * @param \Magento\Framework\Serialize\Serializer\Json|null $serializer
         * @SuppressWarnings(PHPMD.ExcessiveParameterList)
         */
        public function __construct(
            Context $context,
            Registry $registry,
            FormatInterface $localeFormat,
            StoreManagerInterface $storeManager,
            DirectoryHelper $directoryHelper,
            FilterFactory $currencyFilterFactory,
            CurrencyInterface $localeCurrency,
            DataObjectFactory $dataObjectFactory,
            ConfigInterface $config,
            AbstractResource $resource = null,
            AbstractDb $resourceCollection = null,
            array $data = [],
            CurrencyConfig $currencyConfig = null,
            LocaleResolverInterface $localeResolver = null,
            NumberFormatterFactory $numberFormatterFactory = null,
            Serializer $serializer = null
        )
        {
            parent::__construct(
                $context,
                $registry,
                $localeFormat,
                $storeManager,
                $directoryHelper,
                $currencyFilterFactory,
                $localeCurrency,
                $resource,
                $resourceCollection,
                $data,
                $currencyConfig,
                $localeResolver,
                $numberFormatterFactory,
                $serializer
            );
            $this->_dataObjectFactory = $dataObjectFactory;
            $this->_localeResolver = $localeResolver ?: ObjectManager::getInstance()->get(LocaleResolverInterface::class);
            $this->_numberFormatterFactory = $numberFormatterFactory ?: ObjectManager::getInstance()->get(NumberFormatterFactory::class);
            $this->_serializer = $serializer ?: ObjectManager::getInstance()->get(Serializer::class);
            $this->_config = $config;
        }

        /**
         * @inheritdoc
         */
        public function convert($price, $toCurrency = null)
        {
            if($this->getIsActive())
            {
                if ($toCurrency === null)
                {
                    return $price;
                }
                elseif ($rate = $this->getRate($toCurrency))
                {
                    return (float)$price * (float)$rate;
                }

                throw new LocalizedException(
                    __(
                        'Undefined rate from "%1-%2".',
                        $this->getCode(),
                        $this->getCurrencyCodeFromToCurrency($toCurrency)
                    )
                );
            }
            else
            {
                return parent::convert($price, $toCurrency);
            }
        }

        /**
         * @inheritdoc
         */
        private function getCurrencyCodeFromToCurrency($toCurrency)
        {
            if (is_string($toCurrency))
            {
                $code = $toCurrency;
            }
            elseif ($toCurrency instanceof \Magento\Directory\Model\Currency)
            {
                $code = $toCurrency->getCurrencyCode();
            }
            else
            {
                throw new InputException(__('Please correct the target currency.'));
            }
            return $code;
        }

        /**
         * @inheritdoc
         */
        public function formatTxt($price, $options = [])
        {
            if($this->getIsActive())
            {
                if (!is_numeric($price)) {
                    $price = $this->_localeFormat->getNumber($price);
                }
                /**
                 * Fix problem with 12 000 000, 1 200 000
                 *
                 * %f - the argument is treated as a float, and presented as a floating-point number (locale aware).
                 * %F - the argument is treated as a float, and presented as a floating-point number (non-locale aware).
                 */
                $price = sprintf("%F", $price);

                if ($this->canUseNumberFormatter($options))
                {
                    return $this->formatCurrency($price, $options);
                }

                return $this->getLocaleCurrency()->getCurrency($this->getCode())->toCurrency($price, $options);
            }
            else
            {
                return parent::formatTxt($price, $options);
            }
        }

        /**
         * @inheritdoc
         */
        private function canUseNumberFormatter(array $options): bool
        {
            $allowedOptions = [
                'precision',
                LocaleCurrency::CURRENCY_OPTION_DISPLAY,
                LocaleCurrency::CURRENCY_OPTION_SYMBOL
            ];

            if (!empty(array_diff(array_keys($options), $allowedOptions)))
            {
                return false;
            }

            if (array_key_exists('display', $options)
                && $options['display'] !== \Magento\Framework\Currency::NO_SYMBOL
                && $options['display'] !== \Magento\Framework\Currency::USE_SYMBOL
            )
            {
                return false;
            }

            return true;
        }

        /**
         * @inheritdoc
         */
        private function formatCurrency(string $price, array $options): string
        {
            $customerOptions = $this->getDataObjectFactory()->create();

            $this->getEventManager()->dispatch(
                'currency_display_options_forming',
                ['currency_options' => $customerOptions, 'base_code' => $this->getCode()]
            );

            $options += $customerOptions->toArray();
            $this->numberFormatter = $this->getNumberFormatter($options);
            $formattedCurrency = $this->numberFormatter->formatCurrency(
                $price,
                $this->getCode() ?? $this->numberFormatter->getTextAttribute(\NumberFormatter::CURRENCY_CODE)
            );

            if (array_key_exists(LocaleCurrency::CURRENCY_OPTION_SYMBOL, $options))
            {
                // remove only one non-breaking space from custom currency symbol to allow custom NBSP in currency symbol
                $formattedCurrency = preg_replace('/ /u', '', $formattedCurrency, 1);
            }

            if ((array_key_exists(LocaleCurrency::CURRENCY_OPTION_DISPLAY, $options)
                    && $options[LocaleCurrency::CURRENCY_OPTION_DISPLAY] === \Magento\Framework\Currency::NO_SYMBOL))
            {
                $formattedCurrency = preg_replace(['/[^0-9.,۰٫]+/', '/ /'], '', $formattedCurrency);
            }

            return preg_replace('/^\s+|\s+$/u', '', $formattedCurrency);
        }

        /**
         * @inheritdoc
         */
        private function getNumberFormatter(array $options): \Magento\Framework\NumberFormatter
        {
            $localeResolver = $this->getLocaleResolver();
            $key = 'currency_' . hash('sha256', ($localeResolver->getLocale() . $this->getSerializer()->serialize($options)));

            if (!isset($this->numberFormatterCache[$key]))
            {
                $this->numberFormatter = $this->getNumberFormatterFactory()->create(
                    ['locale' => $localeResolver->getLocale(), 'style' => \NumberFormatter::CURRENCY]
                );

                $this->setOptions($options);
                $this->numberFormatterCache[$key] = $this->numberFormatter;
            }

            return $this->numberFormatterCache[$key];
        }

        /**
         * @inheritdoc
         */
        private function setOptions(array $options): void
        {
            if (array_key_exists(LocaleCurrency::CURRENCY_OPTION_SYMBOL, $options))
            {
                $this->numberFormatter->setSymbol(
                    \NumberFormatter::CURRENCY_SYMBOL,
                    $options[LocaleCurrency::CURRENCY_OPTION_SYMBOL]
                );
            }

            if (array_key_exists(LocaleCurrency::CURRENCY_OPTION_DISPLAY, $options)
                && $options[LocaleCurrency::CURRENCY_OPTION_DISPLAY] === \Magento\Framework\Currency::NO_SYMBOL)
                {
                $this->numberFormatter->setSymbol(\NumberFormatter::CURRENCY_SYMBOL, '');
            }

            if (array_key_exists('precision', $options))
            {
                $this->numberFormatter->setAttribute(\NumberFormatter::FRACTION_DIGITS, $options['precision']);
            }
        }

        /**
         * Gets Event Manager
         *
         * @access protected
         *
         * @return \Magento\Framework\Event\ManagerInterface
         */
        protected function getEventManager(): EventManagerInterface
        {
            return $this->_eventManager;
        }

        /**
         * Gets Locale Currency
         *
         * @access protected
         *
         * @return \Magento\Framework\Locale\CurrencyInterface
         */
        protected function getLocaleCurrency(): CurrencyInterface
        {
            return $this->_localeCurrency;
        }

        /**
         * locale Resolver property
         *
         * @access private
         *
         * @var \Magento\Framework\Locale\ResolverInterface $_localeResolver
         */
        private $_localeResolver;

        /**
         * Gets locale Resolver
         *
         * @access protected
         *
         * @return \Magento\Framework\Locale\ResolverInterface
         */
        protected function getLocaleResolver(): LocaleResolverInterface
        {
            return $this->_localeResolver;
        }

        /**
         * Number Formatter Factory property
         *
         * @access private
         *
         * @var \Magento\Framework\NumberFormatterFactory $_numberFormatterFactory
         */
        private $_numberFormatterFactory;

        /**
         * Gets Number Formatter Factory
         *
         * @access protected
         *
         * @return \Magento\Framework\NumberFormatterFactory
         */
        protected function getNumberFormatterFactory(): NumberFormatterFactory
        {
            return $this->_numberFormatterFactory;
        }

        /**
         * Serializer property
         *
         * @access private
         *
         * @var \Magento\Framework\Serialize\Serializer $_serializer
         */
        private $_serializer;

        /**
         * Gets Serializer
         *
         * @access protected
         *
         * @return \Magento\Framework\Serialize\Serializer
         */
        protected function getSerializer(): Serializer
        {
            return $this->_serializer;
        }

        /**
         * Data Object Factory property
         *
         * @access private
         *
         * @var \Magento\Framework\DataObjectFactory $_dataObjectFactory
         */
        private $_dataObjectFactory;

        /**
         * Gets Data Object Factory
         *
         * @access protected
         *
         * @return \Magento\Framework\DataObjectFactory
         */
        protected function getDataObjectFactory(): DataObjectFactory
        {
            return $this->_dataObjectFactory;
        }

        /**
         * Gets wether the currency modification is active or not.
         *
         * @access protected
         *
         * @return bool
         */
        protected function getIsActive(): bool
        {
            return $this->getConfig()->getModuleStatus() && $this->getModificationStatus();
        }

        /**
         * Gets Modification Status flag
         *
         * @access protected
         *
         * @return bool
         */
        protected function getModificationStatus(): bool
        {
            return $this->getConfig()->getFlag(static::CONFIG_PATH_MODIFICATION_STATUS);
        }

        /**
         * Hippiemonkeys Config property
         *
         * @access private
         *
         * @var \Hippiemonkeys\Core\Api\Helper\ConfigInterface $_config
         */
        private $_config;

        /**
         * Gets Hippiemonkeys Config
         *
         * @access protected
         *
         * @return \Hippiemonkeys\Core\Api\Helper\ConfigInterface
         */
        protected function getConfig(): ConfigInterface
        {
            return $this->_config;
        }
    }
?>