require("dotenv").config();

const { execSync } = require("child_process");

execSync("docker-compose run --rm wordpress-cli wp cli info", {
    stdio: "inherit",
});