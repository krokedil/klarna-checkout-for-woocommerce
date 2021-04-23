const waitOn = require("wait-on");
const { execSync } = require("child_process");

const executeCommand = (command) => {
    const dockerRunCLI = "docker-compose run --rm wordpress-cli";
    execSync(`${dockerRunCLI} ${command}`, {
        stdio: "inherit",
    });
};

const wpInstallCommand = (params) => {
    const { title, admin, pass, email, url } = params;
    return `wp core install --title="${title}" --admin_user=${admin} --admin_password=${pass} --admin_email=${email} --skip-email --url=${url}`;
};

const installWP = () => {
    const data = {
        title: "E2E Tests",
        admin: "admin",
        pass: "password",
        email: "info@example.com",
        url: "http://localhost:8000",
    };
    const installCommand = wpInstallCommand(data);
    executeCommand(installCommand);
};

const installWC = () => {
    executeCommand("wp plugin install woocommerce --activate");
};

const installStorefront = (themeName = "storefront") => {
    executeCommand(`wp theme install ${themeName}`);
};

const activateKCO = () => executeCommand(`wp plugin activate klarna-checkout-for-woocommerce`);

const importDb = () => executeCommand("wp db import ./wp-content/plugins/klarna-checkout-for-woocommerce/tests/e2e/bin/data1.sql");

waitOn({ resources: [`http://localhost:8000`] }).then(() => {
    try {
        installWP();
        installWC();
        installStorefront();
        activateKCO();
        importDb();
    } catch (error) {
        console.log(error);
    }
});
