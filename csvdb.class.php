<?php

namespace robgnu;

/**
 * CSV Access Class
 * Use CSV files like a small database.
 * Please note: Use this class not in heavy load environments, there
 * is no database locking or similar features.
 * @package CsvDb
 * @link https://github.com/robgnu/csvdb/
 * @author robgnu, rob@gmx.de
 * @copyright 2013-2017 robgnu
 * @license The MIT License (MIT)
 */

class csvdb {
  protected $errorMessage;    // Contains error messages or is empty
  protected $pathToFile;      // Contains the path to the csv-file
  protected $isFile;          // Is the file present or not?
  protected $isUTF8;          // Are the data unicode (UTF-8) encoded?
  protected $columnKeys;      // An array of the column headers.
  protected $csvValues;       // An array of the CSV data.

  const version = "0.1.1";
  const CsvSeparator = ";";
  const CsvSaveLineEndings = "\r\n"; // Windows

  function __construct($pathToFile, $isUTF8=true) {
    $this->setErrorMessage("");
    $this->isFile = (bool) false;
    $this->setPathToFile($pathToFile);
    $this->setIsUTF8($isUTF8);
    $this->columnKeys = array();
    $this->csvValues = array();
    if (!$this->loadCsvFile()) {
      $this->setErrorMessage("Can't load CSV file!");
    }
  } // function __construct


  //
  // Setter / Getter
  //

  public function isError() {
    return (bool) !empty($this->errorMessage);
  }

  public function getErrorMessage() {
    return (string) $this->errorMessage;
  }

  public function setErrorMessage($setValue) {
    $this->errorMessage = (string) $setValue;
  }

  public function isFile() {
    return (bool) $this->isFile;
  }

  public function getPathToFile() {
    return (string) $this->pathToFile;
  }

  protected function setPathToFile($setValue) {
    $this->pathToFile = (string) $setValue;
    $this->isFile = (bool) @is_file($this->pathToFile);
  }

  public function isUTF8() {
    return (bool) $this->isUTF8;
  }

  public function setIsUTF8($setValue) {
    $this->isUTF8 = (bool) $setValue;
  }

  public function getDataCount() {
    return (int) count($this->csvValues);
  }

  public function getColumnKeys() {
    return $this->columnKeys;
  }

  public function setColumnKeys($setValue) {
    if (is_array($setValue)) {
      $this->columnKeys = $setValue;
    }
  }

  public function getCsvValues() {
    return $this->csvValues;
  }

  public function setCsvValues($setValue) {
    if (is_array($setValue)) {
      $this->csvValues = $setValue;
    }
  }

  public function addCsvValuesRow($setValue) {
    if (is_array($setValue)) {
      $this->csvValues[] = $setValue;
    }
  }



  /**
   * Reads the CSV file into memory.
   * @param  integer $offset The line index where the csv file starts.
   * @return bool            Success or error
   */
  protected function loadCsvFile($offset=0) {
    if ($this->isError()) { return false; }
    if (!$this->isFile()) { return false; }
    $csvFile = file($this->pathToFile); // Read whole file into array. Every line is an array element.
    if (is_array($csvFile) && count($csvFile) <= $offset) { return true; }
    // Get the keys from each column and save the list of entrys into array $this->columnKeys
    if ($this->isUTF8()) {
      $this->setColumnKeys( explode(self::CsvSeparator, $this->removeSpecialChars($csvFile[$offset])) );
    } else {
      $this->setColumnKeys( explode(self::CsvSeparator, $this->removeSpecialChars(utf8_encode($csvFile[$offset]))) );
    }
    // Read the values (offset + 1) and write them into the array.
    for ($i=($offset+1); $i < count($csvFile); $i++) { // For each row...
      $tempRow = explode(self::CsvSeparator, $csvFile[$i]);
      $tempCol = array();
      for ($j=0; $j < count($tempRow); $j++) { // For each column
        if ($this->isUTF8()) {
          $tempCol[$this->columnKeys[$j]] = trim( $this->removeQuotes($tempRow[$j]) );
        } else {
          $tempCol[$this->columnKeys[$j]] = trim( $this->removeQuotes(utf8_encode($tempRow[$j])) );
        }
      }
      $this->addCsvValuesRow($tempCol);
      unset($tempCol);
      unset($tempRow);
    }
    return true;
  } // function loadCsvFile


  /**
   * Saves the CSV file in memory to a file.
   * @return bool            Success or error
   */
  protected function saveCsvFile() {
    if ($this->isError()) { return false; }
    if (!$this->isFile()) { return false; }
    $csvFile = array();
    $iRow = 0;
    $csvFile[$iRow] = implode(self::CsvSeparator, $this->columnKeys);
    for ($i=0; $i <= count($this->csvValues); $i++) { // For each row...
      if (!isset($this->csvValues[$i])) { continue; }
      $iRow++;
      $csvFile[$iRow] = implode(self::CsvSeparator, $this->csvValues[$i]);
    }
    // glue array with a "Windows" line break to a new string and write the new file.
    if (!file_put_contents($this->getPathToFile(), implode(self::CsvSaveLineEndings, $csvFile))) {
      return false;
    }
    return $this->loadCsvFile();
  } // function saveCsvFile


  /**
   * Removes "Quotes" if there are any.
   * This is a "quick and dirty" solution for now.
   * @param  string $str Input String with column value
   * @return string      The same value without quotes.
   */
  private function removeQuotes($str) {
    return str_replace("\"", "", $str);
  }


  /**
   * Removes special Chars from String.
   * Thin function is used to cleanup the CSV headers in some export-files
   * from some companys who use spaces or other special chars in their column-
   * descriptions.
   * @param  string $str Input String
   * @return string      Cleaned String
   */
  private function removeSpecialChars($str) {
    return trim(preg_replace("/[^0-9a-zöäüß\_".self::CsvSeparator."]/i", "", $str));
  }


  /**
   * Alias for getCsvValues()
   */
  public function SelectAll() {
    return $this->getCsvValues();
  } // function SelectAll


  /**
   * Returns an array of the whole CSV line(s) where the given column key
   * contains exact the given value.
   * @param string $colKey   The requested column key (e.g. "id")
   * @param string $colValue The requested value (e.g. the id to get)
   * @return array           An array with every row matching the search.
   */
  public function SearchByKeyValueExact($colKey, $colValue) {
    $csvValues = array();
    for ($i=0; $i < count($this->csvValues); $i++) {
      if ($this->csvValues[$i][$colKey] != $colValue) { continue; }
      $csvValues[] = $this->csvValues[$i];
    }
    return $csvValues;
  } // function SearchByKeyValueExact


  /**
   * Returns an array of the whole CSV line(s) where the given column key
   * contains the given value. (like the LIKE statement in SQL)
   * @param string $colKey   The requested column key (e.g. "name")
   * @param string $colValue The requested value (e.g. the name to search)
   * @return array           An array with every row matching the search.
   */
  public function SearchByKeyValueLike($colKey, $colValue) {
    $csvValues = array();
    for ($i=0; $i < count($this->csvValues); $i++) {
      if (strpos(strtolower($this->csvValues[$i][$colKey]), strtolower($colValue)) === false) { continue; }
      $csvValues[] = $this->csvValues[$i];
    }
    return $csvValues;
  } // function SearchByKeyValueLike


  /**
   * Returns the first found row where the $colName is exact $colValue.
   * Use this function for getting a row with a unique id.
   * @param string $colValue  The content (e.g. the ID) to get.
   * @param string $colName   The column to search - the default value is "id".
   * @return bool|array       Returns an array with the row or a false if there is an error or no data.
   */
  public function SelectByID($colValue, $colName="id") {
    if (empty($colValue) || !is_numeric($colValue)) { return false; }
    for ($i=0; $i < count($this->csvValues); $i++) {
      if ($this->csvValues[$i][$colName] != $colValue) { continue; }
      return $this->csvValues[$i];
    }
    return false;
  } // function SelectByID


  /**
   * Updates the first found row where the $colName is exact $colValue.
   * Use this function for updating a row with a unique id.
   * @param string $colValue  The content (e.g. the ID) to get.
   * @param array  $csvValues A csv array with the complete row of data.
   * @param string $colName   The column to search - the default value is "id".
   * @return bool             Returns true if action is successfull or false if failed.
   */
  public function Update($colValue, $csvValues, $colName="id") {
    if (empty($colValue) || !is_numeric($colValue)) { return false; }
    if (!is_array($csvValues) || count($csvValues) <= 0) { return false; }
    for ($i=0; $i < count($this->csvValues); $i++) {
      if ($this->csvValues[$i][$colName] != $colValue) { continue; }
      $this->csvValues[$i] = $csvValues;
      return $this->saveCsvFile();
    }
    return false;
  } // function Update


  /**
   * The same as "addCsvValuesRow" but saves the CSV file after inserting.
   * @param array  $csvValues A csv array with the complete row of data.
   * @return bool             Returns true if action is successfull or false if failed.
   */
  public function Insert($csvValues) {
    $this->addCsvValuesRow($csvValues);
    return $this->saveCsvFile();
  } // function Insert


  /**
   * Gives a new unique (unused) ID for inserting data.
   * On MySQL this could be done with the auto_increment feature on the
   * primary key., but on CSV files we don't have this luxus.
   * So before inserting new data und probably need this function to
   * get a new ID.
   * @param string $colName Name of the column
   * @return integer        New ID to insert.
   */
  public function GetNewInsertId($colName = 'id') {
    if (!is_array($this->csvValues) || count($this->csvValues) <= 0) {
      return (int) 1; // no data; return 1 as the first value.
    }
    $maxValue = 0;
    for ($i=0; $i < count($this->csvValues); $i++) {
      if (!isset($this->csvValues[$i][$colName]) || !is_numeric($this->csvValues[$i][$colName])) { continue; }
      if ($maxValue >= $this->csvValues[$i][$colName]) { continue; }
      $maxValue = $this->csvValues[$i][$colName];
    }
    return (int) ($maxValue+1);
  } // function GetNewInsertId


  /**
   * Deletes a row by it's ID.
   * @param integer $id         The primary key (unique) of the row. Usually the id.
   * @param string  $colName    Name of the column. Default value is "id".
   * @return bool               Returns true if action is successfull or false if failed.
   */
  public function Delete($id, $colName="id", $deleteOnyleOneRow=true) {
    if (empty($id) || !is_numeric($id)) { return false; }
    for ($i=count($this->csvValues)-1; $i>=0; $i--) { // reverse iterate
      if ($this->csvValues[$i][$colName] != $id) { continue; }
      unset($this->csvValues[$i]);
      if ($deleteOnyleOneRow) { break; }
    }
    return $this->saveCsvFile();
  } // function Delete

} // class csvdb

?>