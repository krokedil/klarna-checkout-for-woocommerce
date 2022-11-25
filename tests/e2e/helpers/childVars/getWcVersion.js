require("dotenv").config();

const { execSync } = require("child_process");

execSync("docker-compose run --rm wordpress-cli wp plugin get klarna-checkout-for-woocommerce --format=yaml", {
// execSync("docker-compose run --rm wordpress-cli wp plugin get woocommerce --format=yaml", {
    stdio: "inherit",
});