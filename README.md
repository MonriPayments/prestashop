## About

Monri's online payments enable you to quickly and easily charge debit and credit cards at all online sales points with maximum security.

## Installation

You will first need to register with Monri in order to use this plugin on your site. Additional fees apply.
Please complete the [inquiry form](https://monri.com/contact/), and we will contact you regarding setup and any information you will need.

If you used older Monri plugin, it is best to remove it first before using this new version.
Old settings will be migrated but make sure to recheck them and test new integration.

## Contributing

PrestaShop modules are open source extensions to the [PrestaShop e-commerce platform][prestashop]. Everyone is welcome and even encouraged to contribute with their own improvements!

Just make sure to follow our [contribution guidelines][contribution-guidelines].


## Documentation

You can find additional information regarding Monri payments on Prestashop at
[Monri's official documentation](https://ipg.monri.com/en/documentation/ecomm-plugins-prestashop)

You can find additional information regarding Privacy policy of Monri payments on Prestashop at
[Monri's privacy policy page](https://ipg.monri.com/en/privacy-policy).

## Changelog

= 1.4.2 - 2025-7-23 =
* Improved apply discount logic when there is original_amount in Monri response

= 1.4.1 - 2025-4-23 =
* Fixed issue on Monri WebPay where orders over 1000 currency units would submit wrong value to Monri.
  
= 1.4.0 - 2025-4-2 =
* Added installments option for Monri WebPay
* Added installments option for Monri WSPay
* Added installments option for Monri Components

= 1.3.0 - 2025-4-1 =
* Added Monri Component as new payment gateway service

= 1.2.0 - 2024-12-10 =
* Added Monri WSPay 
* Improvements in success validation
* Added configurable settings for transcation type

= 1.1.0 - 2021-7-21 =
* Enabling use of module for version 1.6+


## License

This module is released under the [Academic Free License 3.0][AFL-3.0] 

[documentation]: https://devdocs.prestashop.com/1.7/modules/
[prestashop]: https://www.prestashop.com/
[contribution-guidelines]: https://devdocs.prestashop.com/1.7/contribute/contribution-guidelines/project-modules/
[AFL-3.0]: https://opensource.org/licenses/AFL-3.0
