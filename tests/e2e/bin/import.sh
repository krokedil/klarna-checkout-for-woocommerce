#!/bin/bash
mkdir -p ./wp-content/uploads/2020/12
cp -R ./wp-content/plugins/klarna-checkout-for-woocommerce/tests/e2e/data/imgs/10/* ./wp-content/uploads/2020/12/
ls -la ./wp-content/uploads/2020/12/


wp db import ./wp-content/plugins/klarna-checkout-for-woocommerce/tests/e2e/bin/data.sql

wp import ./wp-content/plugins/klarna-checkout-for-woocommerce/tests/e2e/bin/coupons.xml --authors=create;