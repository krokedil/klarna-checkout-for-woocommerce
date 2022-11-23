require("dotenv").config();

const { execSync } = require("child_process");

execSync("docker-compose run --rm wordpress-cli wp core version --user=1", {
    stdio: "inherit",
});