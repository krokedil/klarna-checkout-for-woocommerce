const { resolve: _resolve } = require("path");
const DependencyExtractionWebpackPlugin = require("@woocommerce/dependency-extraction-webpack-plugin");
const { sync } = require("glob");
const path = require("path");

const isProduction = process.env.NODE_ENV == "production";
// Common configuration settings
const common = {
    mode: isProduction ? "production" : "development",
    module: {
        rules: [
            {
                test: /\.(ts|tsx)$/i,
                use: "ts-loader",
                exclude: ["/node_modules/", "/tests/", "/vendor/"],
            },
            {
                test: /\.s[ac]ss$/i,
                use: ["style-loader", "css-loader", "sass-loader"],
            },
        ],
    },
    resolve: {
        extensions: [".ts", ".tsx", ".scss", ".sass", ".css"],
    },
    plugins: [
        new DependencyExtractionWebpackPlugin({
            injectPolyfill: true,
        }),
    ],
};
// Blocks configuration
const blocksConfig = {
    ...common,
    entry: sync("./blocks/src/**/!(shared)/**/index.tsx", {
            ignore: ["./blocks/src/shared/**/index.tsx"],
        })
        .reduce((entries, file) => {
            const entryName = path.basename(path.dirname(file.toLowerCase()));
            entries[entryName] = `./${file}`;
            return entries;
        }, {}),
    output: {
        path: path.resolve(__dirname, "./blocks/build/"),
        filename: `[name].js`,
    },
};

module.exports = [blocksConfig];
