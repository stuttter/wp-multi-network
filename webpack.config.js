const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const MiniCssExtractPlugin = require( 'mini-css-extract-plugin' );
const RtlCssPlugin = require( 'rtlcss-webpack-plugin' );
const path = require( 'path' );

module.exports = {
	...defaultConfig,
	entry: {
		'wp-multi-network': path.resolve( process.cwd(), 'src/js/index.js' ),
	},
	output: {
		path: path.resolve( process.cwd(), 'wp-multi-network/assets' ),
		filename: 'js/[name].js',
	},
	plugins: [
		...defaultConfig.plugins.filter(
			( plugin ) =>
				! ( plugin instanceof MiniCssExtractPlugin ) &&
				! ( plugin instanceof RtlCssPlugin )
		),
		new MiniCssExtractPlugin( {
			filename: ( pathData ) => {
				// Use the entry name for the CSS filename
				if ( pathData.chunk && pathData.chunk.name ) {
					return 'css/' + pathData.chunk.name + '.css';
				}
				return 'css/[name].css';
			},
		} ),
		new RtlCssPlugin( {
			filename: ( pathData ) => {
				// Use the entry name for the RTL CSS filename
				if ( pathData.chunk && pathData.chunk.name ) {
					return 'css/' + pathData.chunk.name + '-rtl.css';
				}
				return 'css/[name]-rtl.css';
			},
		} ),
	],
};
