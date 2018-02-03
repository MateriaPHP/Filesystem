<?php

namespace Materia\Filesystem;

/**
 * SPL autoloader adhering to PSR-4
 *
 * @package	Materia.Filesystem
 * @author	Filippo Bovo
 * @link	https://lab.alchemica.io/projects/materia/
 **/

class Loader {

	protected $_chroot;

	protected $_files = [];
	protected $_paths = [];

	/**
	 * Constructor
	 *
	 * @param	string	$chroot		base path
	 **/
	public function __construct( string $chroot ) {

		$chroot = realpath( $chroot );

		// Must be a valid directory
		if ( !is_dir( $chroot ) ) {

			throw new \InvalidArgumentException( sprintf( 'Invalid base path %s', $chroot ) );

		}

		// Must be readable
		if ( !is_readable( $chroot ) ) {

			throw new \InvalidArgumentException( sprintf( 'Path %s is not readable', $chroot ) );

		}

		// Normalize path
		$this->_chroot = rtrim( $chroot, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR;

	}

	/**
	 * Registers this autoloader with SPL
	 *
	 * @param	bool	$prepend	TRUE to prepend to the autoload stack
	 * @return	self
	 **/
	public function register( $prepend = FALSE ) : self {

		spl_autoload_register( [ $this, 'loadClass' ], TRUE, ( $prepend ? TRUE : FALSE ) );

		return $this;

	}

	/**
	 * Unregisters this autoloader from SPL
	 **/
	public function unregister() {

		spl_autoload_unregister( [ $this, 'loadClass' ] );

	}

	/**
	 * Adds a base directory for a namespace prefix
	 *
	 * @param	string	$path		base directory for the namespace prefix
	 * @param	string	$prefix		the namespace prefix
	 * @return	self
	 **/
	public function addPath( string $path, string $prefix = NULL ) : self {

		$path = rtrim( realpath( $path ), DIRECTORY_SEPARATOR );

		// Prepend the base path if necessary
		if ( strpos( $path, $this->_chroot ) !== 0 ) {

			$path = $this->_chroot . ltrim( $path, DIRECTORY_SEPARATOR );

		}

		// Normalize the namespace prefix
		$prefix = '\\' . trim( $prefix, '\\' );

		// Explicit class file
		if ( is_file( $path ) && ( $prefix != '\\' ) ) {

			// Append if not present
			if ( !isset( $this->_files[$prefix] ) || !in_array( $path, $this->_files[$prefix] ) ) {

				$this->_files[$prefix][] = $path;

			}

		}
		// File folder
		else if ( is_dir( $path ) ) {

			// Append if not present
			if ( !isset( $this->_paths[$prefix] ) || !in_array( $path, $this->_paths[$prefix] ) ) {

				$this->_paths[$prefix][] = $path;

			}

		}

		return $this;

	}

	/**
	 * Sets multiple paths at the same time
	 *
	 * @param	array	$paths	associative array of namespace prefixes and their base directories
	 * @return	self
	 **/
	public function addPaths( array $paths ) : self {

		// Iterate
		foreach ( $paths as $prefix => $path ) {

			$this->addPath( $path, $prefix );

		}

		return $this;

	}

	/**
	 * Loads the class file for a given class name
	 *
	 * @param	string	$class	fully-qualified class name.
	 * @return	boolean
	 **/
	public function loadClass( string $class ) : bool {

		// Is an explicit class file noted?
		if ( isset( $this->_files[$class] ) ) {

			$file = $this->_files[$class];

			if ( $this->requireFile( $file ) ) {

				$this->_loaded[$class] = $file;

				return TRUE;

			}

		}

		// The current namespace prefix
		$prefix = '\\' . trim( $class, '\\' );

		// Work backwards through the namespace names of the fully-qualified
		// class name to find a mapped file name
		while ( FALSE !== ( $pos = strrpos( $prefix, '\\' ) ) ) {

			$prefix   = rtrim( substr( $prefix, 0, $pos ), '\\' );
			$subclass = ltrim( substr( $class, $pos ), '\\' );

			// Try to load a mapped file for the prefix and relative class
			if ( $file = $this->loadFile( $prefix, $subclass ) ) {

				$this->_loaded[] = $file;

				return TRUE;

			}

			// Remove the trailing namespace separator for the next iteration of strrpos()
			$prefix = rtrim( $prefix, '\\' );

		}

		return FALSE;

	}

	/**
	 * Load the mapped file for a namespace prefix and relative class
	 *
	 * @param	string	$prefix		the namespace prefix
	 * @param	string	$class		the relative class name
	 * @return	mixed               FALSE if no mapped file can be loaded, or the file name that was loaded
	 */
	protected function loadFile( string $prefix, string $class ) {

		// Are there any base directories for this namespace prefix?
		if( !isset( $this->_paths[$prefix] ) ) {

			return NULL;

		}

		// Look through base directories for this namespace prefix
		foreach ( $this->_paths[$prefix] as $dir ) {

			// Replace the namespace prefix with the base directory,
			// replace namespace separators with directory separators
			// in the relative class name, append file extension (.php)
			$file = $dir . DIRECTORY_SEPARATOR . str_replace( '\\', DIRECTORY_SEPARATOR, $class ) . '.php';

			// If the mapped file exists, require it
			if ( $this->requireFile( $file ) ) {

				// Successfully loaded
				return $file;

			}

		}

		// Didn't find it
		return NULL;

	}

	/**
	 * If a file exists, require it from the file system
	 *
	 * @param	string	$file	the file to require
	 * @return	bool			TRUE if the file exists, FALSE otherwise
	 **/
	protected function requireFile( string $file ) : bool {

		if ( file_exists( $file ) ) {

			require_once $file;

			return TRUE;

		}

		return FALSE;

	}

}
