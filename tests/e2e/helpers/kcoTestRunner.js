const createWcCustomer = async () => {
	try {
		const customerResponse = await API.getWCCustomers();
		const { data } = customerResponse;
		if (parseInt(data.length, 10) < 1) {
			try {
				await API.createWCCustomer(customerAPIData);
			} catch (error) {
				console.log(error);
			}
		}
	} catch (error) {
		console.log(error);
	}
}

const loginUser = async () => {
	// Check for user logged in
	if (isUserLoggedIn) {
		// Login with User Credentials
		await page.goto(kcoURLS.MY_ACCOUNT);
		await user.login(userCredentials, { page });
	}
}

export default {
	createWcCustomer
}