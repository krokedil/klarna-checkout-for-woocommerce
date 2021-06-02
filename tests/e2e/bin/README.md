# Klarna Checkout for WooCommerce automated testing documentation

The Klarna Checkout for WooCommerce (KCO) automated testing process consists of running a simulated shopping experience through a WooCommerce Store.

This shopping experience is performed from the testers’ side by inserting the parameters for a different shopping iteration in each separate test file (Test Suite) and commencing a test run via a single command. In turn, this will make a one-by-one run of the iterations and return a list of completed or failed results.

# 1. Basic Elements and terms
***Test Suite*** : A single test file containing automated testing logic and test parameters.

***Test*** : A single test within a test suite, which compares the order lines (amount, address, order ID etc.) in the response received with the ones sent in the order for the current Test Suite.

Each Test Suites test will return as PASSED if the results returned from the KCO order match the ones expected, or FAILED in all other cases.

Each KCO Test Suite contains 20 separate tests.

***Terminal*** : This refers to the terminal screen containing the command console and display the test process and results.

***Console*** : The command console which will receive the input from the tester.

# 2. Basic code structure for testers

- >*PLUGIN_PATH* > tests

  All of the files which require access from the user are within the **e2e** folder.

  This folder’s location is also the suggested path for the console when performing commands and any actions which are to be committed to the repository later on.

- >*PLUGIN_PATH* > tests > e2e > config > config.data.json

  The file named **config.data.json** contains the base parameters that will be used within the plugin’s test environment setup from (customer billing data, credentials, coupon names, product ID’s etc.).

  ( ***! IMPORTANT*** : The file **config.data.json** is not included in the packages cloned from a repository, it has to be included via a simple copy/paste into this path by the testers upon the initial plugin test initialization.

  The file is not to be committed to the repository, as it contains sensitive information, such as access credentials.
  In order to omit such mistakes, the file has been excluded from git detection upon changes )

  For testers, the most important element within this file is the **timeoutTime** key-value pair, which sets the amount of time used in pauses between actions.

  Please see Section 4 for further instructions on the use of the **timeoutTime** variable.

- >*PLUGIN_PATH* > tests > e2e > tests

  The folder containing the Test Suites.

- >*PLUGIN_PATH* > tests > e2e > tests > {test file name}

  A Test Suite.

  Each of the files within the ***PLUGIN_PATH > tests > e2e > tests*** folder represents a single Test Suite.

  Upon the creation of a new Test Suite, the tester must give it a unique name following the naming conventions described in ***Section 6*** and add **.spec.js** to it as an extension.


- >*PLUGIN_PATH* > tests > e2e > bin > data.sql

  The database file which will be used for the formation of the admin data, included plugins, coupons and products within a WooCommerce store.

  ( ***! IMPORTANT*** : The file **data.sql** is not included in the packages cloned from a repository, it has to be included via a simple copy/paste into this path by the testers upon the initial plugin test initialization.

  The file is not to be committed to the repository, as it contains sensitive information, such as access credentials.
  In order to omit such mistakes, the file has been excluded from git detection upon changes )

# 3. Environment setup

**BEFORE ALL** :
- Install the NodeJS and the yarn package manager onto your system.
- Install Docker onto your system.

1. Clone the repository branch containing the E2E Automated Testing.
2. Copy and paste from an external source:

- data.sql

>*PLUGIN_PATH* > tests > e2e > bin > { here }

- config.data.json

>*PLUGIN_PATH* > tests > e2e > config > { here }

- .env

>*PLUGIN_PATH* > tests > e2e > { here }

3. Open your Terminal, and use the Console to navigate to ***PLUGIN_PATH > tests > e2e***
4. Install the required NodeJS packages ***PLUGIN_PATH > tests > e2e***

- yarn install

5. Input the following command:

- yarn docker:up

6. Monitor the loading progress from your Docker Desktop GUI.
7. Input the following command:

- yarn jettison

8. From here on, each time you use the “yarn jettison” command, the automated testing will run all of the created Test Suites one by one.

**AFTER ALL** : Bring the Docker image down with the following command:

- yarn docker:down

# 4. Test setup

- >*PLUGIN_PATH* > tests > e2e > config > config.data.json
  - ***Time Out Time***

  The **timeoutTime** key’s value (set in the ***config.data.json*** file) defines the number of milliseconds the testing run for each Test Suite will pause after completing an action.
  
  Being that the plugin heavily dependent on responses from a server, the loading times for iFrames and different stages of the checkout process may vary independently and inconsistently.

- >*PLUGIN_PATH* > tests > e2e > tests > {Test Suite}

  - ***Logged in user / Guest***

  Toggled by turning the **isUserLoggedIn** variable to *true* (logged in user) or *false* (guest).

  - ***Products to be added to cart***

  Added by inserting the product name variables into the array.

      EXAMPLE:
      Add a Variable product 25% Tax blue, a Simple product 25%, and a Variable product 25% Green to cart.
  
    >const productsToCart = [
      variableProduct25Blue,
      simpleProduct25,
      variableProduct25Green
      ];
  
    To add multiple instances of a single product, just add the product name variable that many times into the array.

    The list of the product names is included in the entry portion of the Test Suite, while the product ID’s are within the **config.data.json** file.

  - ***Select the shipping method***
  
  Toggled by turning the **shippingMethod** variable to *freeShippingMethod* (free shipping) or *flatRateMethod* (flat rate shipping).

  - ***Select the payment method***
  
  Toggled by turning the **selectedPaymentMethod** variable to *invoicePaymentMethod* (invoice) or *creditPaymentMethod* (credit card).

  - ***Applied coupons***
  
  Added by inserting the coupon name variables into the array.
  
      EXAMPLE:
      Apply the percentage total cart discount coupon.

  >const appliedCoupons = [couponPercent];
  
  The list of the coupon names is included in the entry portion of the Test Suite, while the coupon raw name strings are within the **config.data.json** file.

  - ***KCO iFrame shipping method selection***
  
  Toggled by turning the “iframeShipping” variable to “yes” (KCO iframe shipping) or “no” (standard WooCommerce checkout shipping selection).

  ( ***! IMPORTANT*** : Changing any other parameters within the **data.sql** or other installation setup files will require a restart of the Docker image by bringing it down, and then up again (see ***Section 3***))

# 5. Test Suite Creation (Unofficial Tip)

 1. Select All text (CTRL/Command + A)
 2. Copy Text (CTRL/Command + C)
 3. Create new Test Suite file with a unique name
 4. Paste Text (CTRL/Command + V) within the new Test Suite file
 5. Change the test parameters

# 6. Naming convention:

The naming conventions for the Test Suites is as follows, each separated with a dash:

    1. Logged-in User / Guest
    2. Products
    3. Shipping Method
    4. Payment Method
    5. Coupons
    6. Shipping method selection

1. **Logged-in User / Guest**

- LI - Logged In
- G - Guest

2. **Products**
   
   The Products piece begins with the letter **P** and proceeds with the abbreviated product name, then tax amount numeral, and (in case of variable products) abbreviated variation colors, in case of product repeating, end the product line lower case *x* and the number of repetitions:

- S - Simple product
   
- V - Variable product

    - g - green
    - bb - black
    - bl - blue
    - br - brown   
   
- VT - Virtual product

- D - Downloadable product   

      PS25
      - Simple Product 25%

      PV25blx2-PV12bb-PS0x4
      - 2 x Variable Product 25% Blue
      - Variable Product 12% Black
      - 4 x Simple Product 0%

3. **Shipping method**

- FS - Free shipping
- FLT - Flat rate shipping

4. **Payment method**

- INV - Invoice

- CRD - Credit card

5. **Coupons**

   The Coupons piece begins an upper-case Q with the coupon type in capital letters, and percent or fixed discount choice in lower-case letters.

- C - Cart
- P - Product
- pr - Percent
- fx - Fixed
- TT - Total discount
- TTF - Total discount with free shipping

      QCpr50
      - Coupon 50% cart discount

6. **iFrame**
- IFR - KCO Shipping iFrame
- {none} - Standard WooCommerce shipping selection

---
    - EXAMPLE #1 -

    LI-PS25-FLT-CRD-QCpr50-IFR
    * Logged-in User
    * simple product 25
    * Flat rate shipping
    * Credit card
    * Coupon 50% cart
    * iFrame shipping



    - EXAMPLE #2 -

    G-PV12bl-PV25g-FSS-INV-QP12fx
    * Guest
    * Product variable 12% blue
    * Product variable 25% green
    * Free shipping
    * Invoice
    * Coupon 12kr product fixed
    * Standard shipping selector