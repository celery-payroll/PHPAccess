<?php
/**
 * Class for reading Microsoft Access .MDB files
 * 
 * Uses the command line mdbtools package which should be installed to the system.
 * 
 * Other methods for reading MS Access files is to use the unixODBC library (only works
 * for cataloged connections configured in /etc/odbc.ini) or installing PHP extension 
 * http://pecl.php.net/package/mdbtools. You should use this class only if these methods
 * are not available.
 *
 * @requires PHP >= 5.3 
 * @requires mdbtools
 * 
 * @author Tuomas Angervuori <tuomas.angervuori@gmail.com>
 * @license http://opensource.org/licenses/LGPL-3.0 LGPL v3
 */

namespace PHPAccess;

class PHPAccess {
	
	public $mdbToolsPath; //Path for the mdbtools applications
	
	protected $_mdbFile;
	protected $_escapedMdbFile;
	
	public function __construct($mdbFile) {
		if(!file_exists($mdbFile)) {
			throw new \Exception("File '$mdbFile' not found");
		}
		$this->_mdbFile = $mdbFile;
		$this->_escapedMdbFile = escapeshellarg($mdbFile);
	}
	
	/**
	 * Get version information from the access database file
	 * 
	 * @returns string mdb file format version
	 */
	public function getVersion() {
		return implode("\n",$this->_execute('mdb-ver',$this->_escapedMdbFile));
	}
	
	/**
	 * Get tables from MDB database
	 * 
	 * @returns array List of tables
	 */
	public function getTables() {
		$args = ' -1 ' . $this->_escapedMdbFile;
		return $this->_execute('mdb-tables', $args);
	}
	
	/**
	 * Get table contents as a CSV data
	 * 
	 * @param $table Table to be exported
	 * @param $includeHeaders Include headers in CSV data
	 * @returns string CSV data
	 */
	public function getCSV($table, $includeHeaders = true) {
		return implode("\n",$this->_getCSVArray($table, $includeHeaders));
	}
	
	protected function _getCSVArray($table, $includeHeaders = true) {
		$args = '';
		if(!$includeHeaders) {
			$args .= '-H ';
		}
		$args .= '-D "%F %T" ' . $this->_escapedMdbFile . ' ' . escapeshellarg($table);
		return $this->_execute('mdb-export', $args);
	}
	
	protected function _queryCSVArray($sqlQuery, $includeHeaders = true) {
		$args = '';
		if(!$includeHeaders) {
			$args .= '-H ';
		}
		$args .= '-p -F -d "," ' . $this->_escapedMdbFile;
		return $this->_execute('mdb-sql', $args, 'echo "' . $sqlQuery . '" | ');
	}
	
	/**
	 * Get table contents as SQL insert queries
	 * 
	 * @param $table Table to be exported
	 * @param $format SQL flavour, mysql as default
	 * @returns string SQL insert queries
	 */
	public function getSQL($table, $format = 'mysql') {
		$args = '-I ' . escapeshellarg($format) . ' -D "%F %T" ' . $this->_escapedMdbFile . ' ' . escapeshellarg($table);
		return implode("\n",$this->_execute('mdb-export', $args));
	}
	
	/**
	 * Get contents of a table as an assosiated array
	 * 
	 * @param $table Table to be exported
	 * @param $sqlQuery Optional SQL query to use on the table
	 * @returns array Table contents
	 */
	public function getData($table, $sqlQuery = null) {
		$csvArray = (is_null($sqlQuery)) 
			? $this->_getCSVArray($table, true) 
			: $this->_queryCSVArray($sqlQuery, true);

		$intOffset = (is_null($sqlQuery)) ? 1 : 2;

		$reader = \League\Csv\Reader::createFromString(implode("\n", $csvArray));
		$columns = $this->getColumns($table);
		$reader->setOffset($intOffset);
		$arrReturn = $reader->fetchAssoc($columns);

		//*** Fix empty last row from CSV Reader.
		if (count($arrReturn) > 0) {
		    $arrRow = $arrReturn[count($arrReturn) - 1];
		    if (count($arrRow) > 1) {
                $firstValue = reset($arrRow);
                $lastValue = end($arrRow);

                if (is_null($firstValue) && is_null($lastValue)) {
                    $arrDump = array_pop($arrReturn);
                }
		    }
		}

		return $arrReturn;
	}
	
	/**
	 * Get table columns
	 * @returns array
	 */
	public function getColumns($table) {
		$reader = \League\Csv\Reader::createFromString(implode("\n",$this->_getCSVArray($table, true)));
		return $reader->fetchOne(0);
	}
	
	/**
	 * Return SQL schema for the selected table
	 * @returns string Schema for creating the table into a sql database
	 */
	public function getTableSql($table, $format = 'mysql') {
		$args = '-T ' . escapeshellarg($table) . ' ' . $this->_escapedMdbFile . ' ' . escapeshellarg($format);
		return implode("\n", $this->_execute('mdb-schema', $args));
	}
	
	/**
	 * Return SQL schema from the MDB database
	 * 
	 * @param $format SQL schema format. Default is mysql.
	 * @returns string Schema for creating tables into a sql database
	 */
	public function getDatabaseSql($format = 'mysql') {
		$args = $this->_escapedMdbFile . ' ' . escapeshellarg($format);
		return implode("\n", $this->_execute('mdb-schema', $args));
	}
	
	/**
	 * Execute the command line apps
	 * 
	 * @param $app Name of the application
	 * @param $paramString Attributes for the application
	 * @param $prefix prefix parsed before the application call
	 * @returns array Response lines
	 */
	protected function _execute($app, $paramString = null, $prefix = null) {
		if($this->mdbToolsPath) {
			$app = escapeshellarg($this->mdbToolsPath) . '/' . $app;
		}
		exec($prefix . $app . ' ' . $paramString, $outputArray, $exitValue);
		if($exitValue != 0) {
			throw new \Exception("Could not execute command");
		}
		return $outputArray;
	}
}
