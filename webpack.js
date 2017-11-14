const path = require('path'),
    webpack = require('webpack'),
    ExtractTextPlugin = require('extract-text-webpack-plugin'),
    CopyWebpackPlugin = require('copy-webpack-plugin'),
    CleanWebpackPlugin = require('clean-webpack-plugin');
const plugins = [
    new webpack.DefinePlugin({
        'process.env': {
            NODE_ENV: JSON.stringify(process.env.NODE_ENV)
        },
    }),
    new webpack.LoaderOptionsPlugin({
        debug: process.env.NODE_ENV === 'development'
    }),
    new webpack.NamedModulesPlugin(),
    new CleanWebpackPlugin([
        path.join(__dirname, 'dist')
    ], {
        exclude: ['index.php']
    }),
    new CopyWebpackPlugin([{
        from: path.join(__dirname, 'assets/bear.svg'),
        to: path.join(__dirname, 'dist')
    }]),
];
const entry = [
    path.join(__dirname, 'assets/main.js'),
];
const rules = [
    {
        enforce: 'pre',
        test: /\.js$/,
        loader: 'eslint-loader',
        include: [
            path.join(__dirname, 'assets'),
        ]
    },
    {
        loader: 'babel-loader',
        test: /\.js$/,
        include: [
            path.join(__dirname, 'assets'),
        ],
    }
];

plugins.push(new webpack.optimize.UglifyJsPlugin({
    sourceMap: true
}));
plugins.push(new ExtractTextPlugin({
    filename: '../dist/main.css'
}));
rules.push({
    test: /\.s?(c|a)ss$/,
    include: [
        path.join(__dirname, 'assets'),
    ],
    use: ExtractTextPlugin.extract({
        fallback: 'style-loader',
        use: [
            {
                loader: 'css-loader',
                options: {
                    minimize: true,
                    sourceMap: true
                }
            },
            'resolve-url-loader',
            'postcss-loader',
            'sass-loader',
        ]
    })
});

module.exports = {
    context: __dirname,
    devtool: process.env.NODE_ENV === 'development' ?
        'inline-eval-cheap-source-map' : 'cheap-module-source-map',
    plugins,
    output: {
        path: path.join(__dirname, 'dist'),
        filename: 'main.js',
        publicPath: '/wp-content/plugins/woocommerce-soundscan/dist/'
    },
    module: {
        rules
    },
    resolve: {
        modules: [
            path.join(__dirname, 'client'),
            path.join(__dirname, 'node_modules')
        ],
        extensions: ['.js', '.jsx', '.scss', '.json', '.css']
    },
    entry,
    externals: {
        jquery: 'jQuery',
        $: 'jQuery'
    }
};
