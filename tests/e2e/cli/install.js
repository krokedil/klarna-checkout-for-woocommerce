require("dotenv").config();
const waitOn = require("wait-on");
const { execSync } = require("child_process");

const { SITEHOST, PORT, PLUGIN_NAME } = process.env;

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
		url: `http://${SITEHOST}:${PORT}`,
	};
	const installCommand = wpInstallCommand(data);
	executeCommand(installCommand);
};

const installWC = () => {
	executeCommand("wp plugin install woocommerce --activate");
};

const installTheme = (themeName = "storefront") => {
	executeCommand(`wp theme install ${themeName}`);
};

const activateKCO = () => executeCommand(`wp plugin activate ${PLUGIN_NAME}`);

const importDb = () =>
	executeCommand(
		`wp db import ./wp-content/plugins/${PLUGIN_NAME}/tests/e2e/bin/data.sql`
	);

waitOn({
	resources: [`http://${SITEHOST}:${PORT}`],
}).then(() => {
	try {
		// do stuff.
		installWP();
		installWC();
		installTheme();
		activateKCO();
		importDb();
	} catch (error) {
		console.log(error);
	}
});
