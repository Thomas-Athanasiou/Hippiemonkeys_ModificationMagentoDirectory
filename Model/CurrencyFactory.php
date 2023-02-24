<?php
    /**
     * @Thomas-Athanasiou
     *
     * @author Thomas Athanasiou {thomas@hippiemonkeys.com}
     * @link https://hippiemonkeys.com
     * @link https://github.com/Thomas-Athanasiou
     * @copyright Copyright (c) 2023 Hippiemonkeys Web Inteligence EE All Rights Reserved.
     * @license http://www.gnu.org/licenses/ GNU General Public License, version 3
     * @package Hippiemonkeys_Directory
     */

    declare(strict_types=1);

    namespace Hippiemonkeys\ModificationMagentoDirectory\Model;

    use Magento\Framework\ObjectManagerInterface,
        Magento\Directory\Model\CurrencyFactory as ParentCurrencyFactory,
        Hippiemonkeys\Core\Api\Helper\ConfigInterface;

    class CurrencyFactory
    extends ParentCurrencyFactory
    {
        /**
         * Constructor
         *
         * @access public
         *
         * @param \Magento\Framework\ObjectManagerInterface $objectManager
         * @param \Hippiemonkeys\Core\Api\Helper\ConfigInterface $config
         */
        public function __construct(
            ObjectManagerInterface $objectManager,
            ConfigInterface $config
        )
        {
            parent::__construct($objectManager);
            $this->_config = $config;
        }

        /**
         * {@inheritdoc}
         */
        public function create(array $data = [])
        {
            return $this->getIsActive() ? $this->getObjectManager()->create(Currency::class, $data) : parent::create($data);
        }

        /**
         * Gets Object Manager
         *
         * @access protected
         *
         * @return \Magento\Framework\ObjectManagerInterface
         */
        protected function getObjectManager(): ObjectManagerInterface
        {
            return $this->_objectManager;
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
            return $this->getConfig()->getIsActive();
        }

        /**
         * Config property
         *
         * @access private
         *
         * @var \Hippiemonkeys\Core\Api\Helper\ConfigInterface $_config
         */
        private $_config;

        /**
         * Gets Config
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