/**
 * Custom Webpack Configuration for WP Multi Network
 *
 * This configuration extends @wordpress/scripts default webpack config
 * to customize the output location and naming of built assets.
 *
 * NOTE: Due to how webpack handles CSS extraction from JavaScript imports,
 * the CSS files are generated with auto-generated chunk names that include
 * a 'style-' prefix (e.g., 'style-wp-multi-network.css'). To maintain
 * backward compatibility with the existing plugin structure, we use a
 * post-build rename script (see package.json 'rename-assets') to rename
 * these files to match the expected names ('wp-multi-network.css').
 *
 * @see https://webpack.js.org/configuration/
 */

const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const MiniCssExtractPlugin = require( 'mini-css-extract-plugin' );
const RtlCssPlugin = require( 'rtlcss-webpack-plugin' );
const path = require( 'path' );

/**
 * Helper function to generate CSS filename based on chunk name
 *
 * @param {Object} pathData - Webpack path data object
 * @param {string} suffix   - Optional suffix for the filename (e.g., '-rtl')
 * @return {string} The CSS filename
 */
function getCssFilename( pathData, suffix = '' ) {
	if ( pathData.chunk && pathData.chunk.name ) {
		// The chunk name includes '.min', so we just add the suffix and extension
		return 'css/' + pathData.chunk.name + suffix + '.css';
	}
	return 'css/[name]' + suffix + '.css';
}

module.exports = {
	...defaultConfig,
	entry: {
		'wp-multi-network.min': path.resolve( process.cwd(), 'src/js/index.js' ),
	},
	output: {
		path: path.resolve( process.cwd(), 'wp-multi-network/assets' ),
		filename: 'js/[name].js',
	},
	plugins: [
		// Remove default CSS plugins and replace with custom configuration
		// to place CSS files in the css/ subdirectory with proper naming.
		// @wordpress/scripts generates CSS chunks with auto-generated names
		// (e.g., 'style-wp-multi-network') which we need to customize.
		...defaultConfig.plugins.filter(
			( plugin ) =>
				! ( plugin instanceof MiniCssExtractPlugin ) &&
				! ( plugin instanceof RtlCssPlugin )
		),
		new MiniCssExtractPlugin( {
			filename: ( pathData ) => getCssFilename( pathData ),
		} ),
		new RtlCssPlugin( {
			filename: ( pathData ) => getCssFilename( pathData, '-rtl' ),
		} ),
	],
};
