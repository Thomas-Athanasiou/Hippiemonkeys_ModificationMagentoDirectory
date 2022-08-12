<?php
    /**
     * @Thomas-Athanasiou
     *
     * @author Thomas Athanasiou {thomas@hippiemonkeys.com}
     * @link https://hippiemonkeys.com
     * @link https://github.com/Thomas-Athanasiou
     * @copyright Copyright (c) 2022 Hippiemonkeys Web Inteligence EE All Rights Reserved.
     * @license http://www.gnu.org/licenses/ GNU General Public License, version 3
     * @package Hippiemonkeys_Directory
     */

    declare(strict_types=1);

    namespace Hippiemonkeys\Directory\Model;

    use Magento\Framework\ObjectManagerInterface,
        Magento\Directory\Model\CurrencyFactory as ParentCurrencyFactory;

    class CurrencyFactory
    extends ParentCurrencyFactory
    {
        /**
         * Creates a Currency
         *
         * @access public
         *
         * @return mixed
         */
        public function create(array $data = [])
        {
            return $this->getObjectManager()->create(Currency::class, $data);
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
    }
?>