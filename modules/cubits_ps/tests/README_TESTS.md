How to run tests
================

## Preparations
1. [Install PhpUnit framework.](https://phpunit.de/getting-started.html)
2. [Install Selenium driver.](https://phpunit.de/manual/current/en/selenium.html)
3. [Install Prestashop.](http://www.prestashop.com/)
4. Add Cubits prestashop extension to prestashop modules.
5. You need to have a handmade admin(created during the installation) and a customer.
6. Rename in the `tests` folder `config_template.php` to `config.php`.
7. Change `config.php` properly to contain the users login data.
8. Run the tests (in `tests` folder `phpunit cubits_prestashop_test.php`).


## Notes
* The test-code doesn't remove created order after the test.
* You need to have a product with `id_product=2` which is per default in Prestashops sample data.
* The tests are tested with Prestashop 1.6.0.9 default theme, a different theme would probably not pass the tests.
* Test-cases: extension installation, extension configuration, order creation, callbacks with diffrent states and uninstall.
