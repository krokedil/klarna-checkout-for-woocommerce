require("dotenv").config();

const { execSync } = require("child_process");

execSync("docker exec e2e_wordpress-dev_1 php --version", {
    stdio: "inherit",
});